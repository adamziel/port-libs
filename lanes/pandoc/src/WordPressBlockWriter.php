<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class WordPressBlockWriter
{
    private const MAX_TABLE_ACCESSIBILITY_SLOTS = 2000;

    /** @var list<string> */
    private const SAFE_GLOBAL_HTML_ATTRIBUTES = [
        'autocapitalize',
        'autocorrect',
        'enterkeyhint',
        'exportparts',
        'inputmode',
        'part',
        'slot',
        'spellcheck',
        'virtualkeyboardpolicy',
        'writingsuggestions',
    ];

    /** @var list<array{number:int, anchor:string, label:string, node:AstNode}> */
    private array $footnotes = [];

    /**
     * @param array{includeMetadata?: bool, preserveListAttributes?: bool, preserveEmptyParagraphs?: bool, taskGlyphsAsCheckboxes?: bool, markEmptyTableCells?: bool, highlightCodeBlocks?: bool, highlightStyle?: string, syntaxHighlighterCodeBlocks?: bool, htmlMathMethod?: string|array<string, mixed>, mathMethod?: string, writerHTMLMathMethod?: string|array<string, mixed>} $options
     */
    public function __construct(private readonly array $options = [])
    {
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
        if ((bool) ($this->options['includeMetadata'] ?? false)) {
            $metadataBlock = $this->renderMetadataReviewBlock($document);
            if ($metadataBlock !== '') {
                $blocks[] = $metadataBlock;
            }
        }
        $children = $document->children;
        for ($index = 0, $count = count($children); $index < $count; $index++) {
            $node = $children[$index];
            if ($node->type !== 'list_item') {
                $this->flushList($pendingList, $blocks);
            }
            if ($this->shouldSkipEmptyParagraphLikeBlock($node)) {
                continue;
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
                $inlineContainer = $this->tryRenderRawHtmlInlineContainerParagraph($children, $index);
                if ($inlineContainer !== null) {
                    $blocks[] = $inlineContainer;
                    continue;
                }
                $blocks[] = $this->renderRawHtmlBlock($node);
            } elseif ($node->type === 'raw_tex') {
                $blocks[] = $this->renderRawTexBlock($node);
            } elseif ($node->type === 'raw_block') {
                $blocks[] = $this->renderRawFormatBlock($node);
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

    private function isEmptyParagraphLikeBlock(AstNode $node): bool
    {
        if (!in_array($node->type, ['paragraph', 'plain'], true)) {
            return false;
        }

        if ($node->children !== []) {
            return false;
        }

        return trim((string) $node->attr('text', '')) === '';
    }

    private function shouldSkipEmptyParagraphLikeBlock(AstNode $node): bool
    {
        return !(bool) ($this->options['preserveEmptyParagraphs'] ?? false)
            && $this->isEmptyParagraphLikeBlock($node);
    }

    private function renderMetadataReviewBlock(AstNode $document): string
    {
        $meta = $document->attr('meta', []);
        if (!is_array($meta) || $meta === []) {
            return '';
        }

        $entries = [];
        foreach ($meta as $key => $value) {
            $key = (string) $key;
            if (in_array($key, ['titleInlines', 'authorInlines', 'dateInlines', 'authors'], true)) {
                continue;
            }

            $entries[] = '<li><strong data-pandoc-meta-key="' . $this->esc($key) . '">' . $this->esc($key) . '</strong>: '
                . $this->renderMetadataValue($value) . '</li>';
        }

        if ($entries === []) {
            return '';
        }

        $inner = '<!-- wp:list -->'
            . "\n" . '<ul>' . implode('', $entries) . '</ul>'
            . "\n" . '<!-- /wp:list -->';
        $group = new AstNode('div', ['htmlAttributes' => ['data-pandoc-source' => 'native-meta']]);

        return $this->renderGroupBlock($group, ['pandoc-document-metadata'], $inner);
    }

    private function renderMetadataValue(mixed $value): string
    {
        if ($value instanceof AstNode) {
            return '<span>' . $this->esc($this->metadataNodeText($value)) . '</span>';
        }

        if (is_array($value) && isset($value['type'])) {
            $payload = $value['value'] ?? null;

            return match ((string) $value['type']) {
                'MetaInlines' => '<span>' . $this->esc($this->metadataInlinesText(is_array($payload) ? $payload : [])) . '</span>',
                'MetaBlocks' => $this->renderMetadataBlocks(is_array($payload) ? $payload : []),
                'MetaList' => $this->renderMetadataList(is_array($payload) ? $payload : []),
                'MetaMap' => $this->renderMetadataMap(is_array($payload) ? $payload : []),
                default => '<span>' . $this->esc((string) $payload) . '</span>',
            };
        }

        if (is_array($value)) {
            if ($this->metadataArrayIsAstNodes($value)) {
                return '<span>' . $this->esc($this->metadataNodesText($value)) . '</span>';
            }

            if ($this->metadataArrayIsSequential($value)) {
                return $this->renderMetadataList($value);
            }

            return $this->renderMetadataMap($value);
        }

        if (is_bool($value)) {
            return '<span>' . ($value ? 'true' : 'false') . '</span>';
        }

        return '<span>' . $this->esc((string) $value) . '</span>';
    }

    /**
     * @param list<mixed> $items
     */
    private function renderMetadataList(array $items): string
    {
        if ($items === []) {
            return '<span>[]</span>';
        }

        $html = '<ul>';
        foreach ($items as $item) {
            $html .= '<li>' . $this->renderMetadataValue($item) . '</li>';
        }

        return $html . '</ul>';
    }

    /**
     * @param array<string, mixed> $items
     */
    private function renderMetadataMap(array $items): string
    {
        if ($items === []) {
            return '<span>{}</span>';
        }

        $html = '<dl>';
        foreach ($items as $key => $item) {
            $html .= '<dt data-pandoc-meta-key="' . $this->esc((string) $key) . '">' . $this->esc((string) $key) . '</dt>'
                . '<dd>' . $this->renderMetadataValue($item) . '</dd>';
        }

        return $html . '</dl>';
    }

    /**
     * @param list<mixed> $blocks
     */
    private function renderMetadataBlocks(array $blocks): string
    {
        if ($blocks === []) {
            return '<span></span>';
        }

        $html = '';
        foreach ($blocks as $block) {
            if (!$block instanceof AstNode) {
                continue;
            }

            $text = $this->metadataNodeText($block);
            $html .= '<p>' . $this->esc($text) . '</p>';
        }

        return $html === '' ? '<span></span>' : $html;
    }

    /**
     * @param list<mixed> $nodes
     */
    private function metadataInlinesText(array $nodes): string
    {
        $text = '';
        foreach ($nodes as $node) {
            if ($node instanceof AstNode) {
                $text .= $this->metadataInlineText($node);
            }
        }

        return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    }

    private function metadataInlineText(AstNode $node): string
    {
        return match ($node->type) {
            'text', 'code' => (string) $node->attr('text', ''),
            'softbreak', 'linebreak' => ' ',
            'math' => (string) $node->attr('text', ''),
            'raw_html_inline' => (string) $node->attr('html', ''),
            'raw_tex', 'raw_tex_inline' => (string) $node->attr('tex', $node->attr('text', '')),
            'raw_inline' => (string) $node->attr('text', ''),
            'image' => (string) $node->attr('alt', $this->metadataInlinesText($node->children)),
            default => $this->metadataInlinesText($node->children),
        };
    }

    private function metadataNodeText(AstNode $node): string
    {
        return match ($node->type) {
            'paragraph', 'plain', 'term', 'line' => $this->metadataInlinesText($node->children),
            'code_block' => (string) $node->attr('text', ''),
            'raw_html' => (string) $node->attr('html', ''),
            'raw_tex' => (string) $node->attr('tex', ''),
            'raw_block' => (string) $node->attr('text', ''),
            'line_block' => implode(' / ', array_map(fn (AstNode $line): string => $this->metadataNodeText($line), $node->children)),
            default => $this->metadataNodesText($node->children) !== ''
                ? $this->metadataNodesText($node->children)
                : (string) $node->attr('text', ''),
        };
    }

    /**
     * @param list<AstNode> $nodes
     */
    private function metadataNodesText(array $nodes): string
    {
        $parts = [];
        foreach ($nodes as $node) {
            $text = $this->metadataNodeText($node);
            if ($text !== '') {
                $parts[] = $text;
            }
        }

        return trim(implode(' ', $parts));
    }

    /**
     * @param array<mixed> $value
     */
    private function metadataArrayIsAstNodes(array $value): bool
    {
        if ($value === []) {
            return false;
        }

        foreach ($value as $item) {
            if (!$item instanceof AstNode) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<mixed> $value
     */
    private function metadataArrayIsSequential(array $value): bool
    {
        return $value === [] || array_keys($value) === range(0, count($value) - 1);
    }

    private function renderParagraphBlock(AstNode $node): string
    {
        $chart = $this->renderablePptxChart($node);
        if ($chart !== null) {
            return '<!-- wp:html -->'
                . "\n" . $this->renderPptxChart($node, $chart)
                . "\n" . '<!-- /wp:html -->';
        }

        if (count($node->children) === 1 && $node->children[0]->type === 'image') {
            return $this->renderParagraphImageBlock($node->children[0]);
        }

        return '<!-- wp:paragraph -->'
            . "\n" . '<p' . $this->renderBlockHtmlAttrs($node) . '>' . $this->renderInlines($node) . '</p>'
            . "\n" . '<!-- /wp:paragraph -->';
    }

    private function renderParagraphImageBlock(AstNode $node): string
    {
        return '<!-- wp:image -->'
            . "\n" . '<figure class="wp-block-image">' . $this->renderImageHtml($node) . '</figure>'
            . "\n" . '<!-- /wp:image -->';
    }

    /**
     * @return array<string, mixed>|null
     */
    private function renderablePptxChart(AstNode $node): ?array
    {
        $chart = $node->attr('pptxChart');
        if (!is_array($chart)) {
            return null;
        }

        if (($chart['issues'] ?? []) !== []) {
            return null;
        }

        $series = $chart['series'] ?? null;
        if (!is_array($series) || $series === []) {
            return null;
        }

        return $chart;
    }

    /**
     * @param array<string, mixed> $chart
     */
    private function renderPptxChart(AstNode $node, array $chart): string
    {
        $title = (string) ($chart['title'] ?? '');
        if ($title === '') {
            $title = 'PPTX chart';
        }
        $type = (string) ($chart['chartType'] ?? 'unknown');
        $partName = (string) ($chart['partName'] ?? '');
        $placeholder = (string) $node->attr('text', '');
        $series = $this->normalizedPptxChartSeries($chart);
        $categories = $this->pptxChartCategories($series);

        $html = '<figure class="pandoc-pptx-chart" data-pandoc-source="pptx-chart"'
            . ($partName === '' ? '' : ' data-pptx-chart-part="' . $this->esc($partName) . '"')
            . ' data-pptx-chart-type="' . $this->esc($type) . '">';
        $html .= '<figcaption><strong>' . $this->esc($title) . '</strong>';
        if ($placeholder !== '') {
            $html .= ' <span class="pandoc-pptx-chart-placeholder">' . $this->esc($placeholder) . '</span>';
        }
        $html .= '</figcaption>';

        $html .= '<table><thead><tr><th>Category</th>';
        foreach ($series as $item) {
            $html .= '<th>' . $this->esc($item['name']) . '</th>';
        }
        $html .= '</tr></thead><tbody>';
        $rowCount = max(1, count($categories));
        for ($row = 0; $row < $rowCount; $row++) {
            $html .= '<tr><td>' . $this->esc((string) ($categories[$row] ?? 'Point ' . ($row + 1))) . '</td>';
            foreach ($series as $item) {
                $html .= '<td>' . $this->esc((string) ($item['rawValues'][$row] ?? '')) . '</td>';
            }
            $html .= '</tr>';
        }
        $html .= '</tbody></table>';
        $html .= $this->renderPptxChartBars($series);
        $html .= '</figure>';

        return $html;
    }

    /**
     * @param array<string, mixed> $chart
     * @return list<array{name:string, categories:list<string>, rawValues:list<string>, values:list<float>}>
     */
    private function normalizedPptxChartSeries(array $chart): array
    {
        $normalized = [];
        foreach (array_values(is_array($chart['series'] ?? null) ? $chart['series'] : []) as $index => $item) {
            if (!is_array($item)) {
                continue;
            }
            $categories = array_values(array_map('strval', is_array($item['categories'] ?? null) ? $item['categories'] : []));
            $rawValues = array_values(array_map('strval', is_array($item['values'] ?? null) ? $item['values'] : []));
            $values = array_map(static fn (string $value): float => is_numeric($value) ? (float) $value : 0.0, $rawValues);
            $normalized[] = [
                'name' => (string) ($item['name'] ?? 'Series ' . ($index + 1)),
                'categories' => $categories,
                'rawValues' => $rawValues,
                'values' => $values,
            ];
        }

        return $normalized;
    }

    /**
     * @param list<array{name:string, categories:list<string>, rawValues:list<string>, values:list<float>}> $series
     * @return list<string>
     */
    private function pptxChartCategories(array $series): array
    {
        foreach ($series as $item) {
            if ($item['categories'] !== []) {
                return $item['categories'];
            }
        }

        return [];
    }

    /**
     * @param list<array{name:string, categories:list<string>, rawValues:list<string>, values:list<float>}> $series
     */
    private function renderPptxChartBars(array $series): string
    {
        $max = 0.0;
        foreach ($series as $item) {
            foreach ($item['values'] as $value) {
                $max = max($max, abs($value));
            }
        }
        if ($max <= 0.0) {
            return '';
        }

        $html = '<div class="pandoc-pptx-chart-bars">';
        foreach ($series as $item) {
            foreach ($item['values'] as $index => $value) {
                $label = $item['name'] . ' / ' . (string) ($item['categories'][$index] ?? 'Point ' . ($index + 1));
                $width = (int) round((abs($value) / $max) * 100);
                $html .= '<div class="pandoc-pptx-chart-bar" style="display:grid;grid-template-columns:minmax(8rem,1fr) 3fr 3rem;gap:.5rem;align-items:center;margin:.25rem 0">'
                    . '<span>' . $this->esc($label) . '</span>'
                    . '<span style="display:block;background:#eef1f5;height:.8rem"><span style="display:block;background:#2563eb;height:.8rem;width:' . $width . '%"></span></span>'
                    . '<span>' . $this->esc((string) ($item['rawValues'][$index] ?? $value)) . '</span>'
                    . '</div>';
            }
        }

        return $html . '</div>';
    }

    /**
     * @param list<AstNode> $nodes
     */
    private function tryRenderRawHtmlInlineContainerParagraph(array $nodes, int &$index): ?string
    {
        $open = $nodes[$index] ?? null;
        $body = $nodes[$index + 1] ?? null;
        $close = $nodes[$index + 2] ?? null;
        if (
            !$open instanceof AstNode
            || !$body instanceof AstNode
            || !$close instanceof AstNode
            || $open->type !== 'raw_html'
            || !in_array($body->type, ['paragraph', 'plain'], true)
            || $close->type !== 'raw_html'
            || $this->shouldSkipEmptyParagraphLikeBlock($body)
        ) {
            return null;
        }

        $openHtml = trim((string) $open->attr('html', ''));
        $closeHtml = trim((string) $close->attr('html', ''));
        if (preg_match('/^<(del|ins|button)(?:\s+(?:"[^"]*"|\'[^\']*\'|[^\'"<>])*)?>$/iu', $openHtml, $match) !== 1) {
            return null;
        }

        $tag = preg_quote((string) $match[1], '/');
        if (preg_match('/^<\/' . $tag . '\s*>$/iu', $closeHtml) !== 1) {
            return null;
        }

        $index += 2;

        return '<!-- wp:paragraph -->'
            . "\n" . '<p>' . $openHtml . $this->renderInlines($body) . $closeHtml . '</p>'
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
            $comment = '<!-- wp:list ' . json_encode($attrs, JSON_THROW_ON_ERROR) . ' -->';
        }
        $tagAttrs = $this->renderListTagAttrs($node, $ordered);
        $blocks = [];
        $items = [];

        foreach ($node->children as $item) {
            if ($item->type !== 'list_item') {
                continue;
            }
            if ($this->listItemIsHeader($item)) {
                $blocks[] = $this->renderListHeaderBlock($item);
                continue;
            }
            $items[] = $this->renderListItem($item);
        }

        if ($items !== [] || $node->children === []) {
            $blocks[] = $comment
                . "\n" . '<' . $tag . $tagAttrs . '>' . implode('', $items) . '</' . $tag . '>'
                . "\n" . '<!-- /wp:list -->';
        }

        return implode("\n\n", $blocks);
    }

    private function renderListHtml(AstNode $node, bool $ordered): string
    {
        $tag = $ordered ? 'ol' : 'ul';
        $tagAttrs = $this->renderListTagAttrs($node, $ordered);
        $blocks = [];
        $items = [];
        foreach ($node->children as $item) {
            if ($item->type !== 'list_item') {
                continue;
            }
            if ($this->listItemIsHeader($item)) {
                $blocks[] = $this->renderListHeaderHtml($item);
                continue;
            }
            $items[] = $this->renderListItem($item);
        }
        if ($items !== [] || $node->children === []) {
            $blocks[] = '<' . $tag . $tagAttrs . '>' . implode('', $items) . '</' . $tag . '>';
        }

        return implode('', $blocks);
    }

    private function listItemIsHeader(AstNode $item): bool
    {
        return $item->attr('listHeader') === true;
    }

    private function renderListHeaderBlock(AstNode $item): string
    {
        return $this->renderGroupBlock(
            new AstNode('div', $item->attrs, $item->children),
            ['pandoc-list-header'],
            $this->renderBlocksAsNativeBlocks($item->children, true)
        );
    }

    private function renderListHeaderHtml(AstNode $item): string
    {
        return '<div' . $this->renderDivAttrs(new AstNode('div', $item->attrs, $item->children)) . '>'
            . $this->renderBlocksAsHtml($item->children)
            . '</div>';
    }

    private function renderListTagAttrs(AstNode $node, bool $ordered): string
    {
        $attrs = $ordered ? $this->renderOrderedListTagAttrs($node) : '';
        $extraClasses = !$ordered && $this->listIsTaskList($node) ? ['task-list'] : [];

        return $attrs . $this->renderBlockHtmlAttrsWithClasses($node, $extraClasses);
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

        if ((bool) ($this->options['preserveListAttributes'] ?? false)) {
            $style = (string) $node->attr('style', 'default');
            $styleIsSourceSpecific = !in_array($style, ['', 'default', 'decimal'], true);
            if ($styleIsSourceSpecific) {
                $attrs .= ' data-pandoc-list-style="' . $this->esc($style) . '"';
            }

            $delimiter = (string) $node->attr('delimiter', 'default');
            if (
                !in_array($delimiter, ['', 'default'], true)
                && ($delimiter !== 'period' || $styleIsSourceSpecific)
            ) {
                $attrs .= ' data-pandoc-list-delimiter="' . $this->esc($delimiter) . '"';
            }
        }

        return $attrs;
    }

    private function renderHeadingAttrs(AstNode $node): string
    {
        return $this->renderBlockHtmlAttrs($node);
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
        $explicitTaskChecked = $item->attr('taskChecked', null);
        $taskChecked = is_bool($explicitTaskChecked) ? $explicitTaskChecked : $this->taskGlyphChecked($item);
        $taskPending = is_bool($taskChecked);
        $stripTaskGlyph = $taskPending && !is_bool($explicitTaskChecked);
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
                if ($child->type === 'plain') {
                    $rendered = $stripTaskGlyph
                        ? $this->renderInlineNodesWithoutLeadingTaskGlyph($child->children)
                        : $this->renderInlines($child);
                    if ($taskPending) {
                        $rendered = $this->renderTaskListLabel($taskChecked, $rendered);
                        $taskPending = false;
                        $stripTaskGlyph = false;
                    }
                    $html .= $rendered;
                    continue;
                }
                $html .= $this->renderBlocksAsHtml([$child]);
                continue;
            }
            if ($child->type === 'paragraph') {
                $rendered = $stripTaskGlyph
                    ? $this->renderInlineNodesWithoutLeadingTaskGlyph($child->children)
                    : $this->renderInlines($child);
                if ($taskPending) {
                    $rendered = $this->renderTaskListLabel($taskChecked, $rendered);
                    $taskPending = false;
                    $stripTaskGlyph = false;
                }
                $html .= $wrapParagraphs ? '<p>' . $rendered . '</p>' : $rendered;
                continue;
            }

            if ($taskPending) {
                $inlineNodes = [];
                while ($index < $count && $this->isInlineNode($children[$index])) {
                    $inlineNodes[] = $children[$index];
                    $index++;
                }
                $index--;
                $inlineHtml = $stripTaskGlyph
                    ? $this->renderInlineNodesWithoutLeadingTaskGlyph($inlineNodes)
                    : $this->renderInlineNodes($inlineNodes);
                $html .= $this->renderTaskListLabel($taskChecked, $inlineHtml);
                $taskPending = false;
                $stripTaskGlyph = false;
                continue;
            }

            $html .= $this->renderInlineNode($child);
        }

        return '<li' . $this->renderBlockHtmlAttrs($item) . '>' . $html . '</li>';
    }

    private function renderBlockHtmlAttrs(AstNode $node): string
    {
        $htmlAttributes = [];
        foreach ($this->inlineHtmlAttributes($node) as $name => $value) {
            $name = strtolower((string) $name);
            if (!$this->isAllowedBlockHtmlAttr($name)) {
                continue;
            }
            $htmlAttributes[$name] = $value;
        }

        $orderedNames = [];
        $priority = array_key_exists('id', $htmlAttributes)
            ? ['id', 'class', 'lang', 'dir', 'role', 'title']
            : ['lang', 'dir', 'role', 'title'];
        foreach ($priority as $name) {
            if (array_key_exists($name, $htmlAttributes)) {
                $orderedNames[] = $name;
            }
        }
        foreach (array_keys($htmlAttributes) as $name) {
            if ($name !== 'class' && !in_array($name, $orderedNames, true)) {
                $orderedNames[] = $name;
            }
        }
        if (!array_key_exists('id', $htmlAttributes) && array_key_exists('class', $htmlAttributes)) {
            $orderedNames[] = 'class';
        }

        $attrs = '';
        foreach ($orderedNames as $name) {
            $value = $htmlAttributes[$name];
            $attrs .= ' ' . $name . '="' . $this->esc((string) $value) . '"';
        }

        return $attrs;
    }

    /**
     * @param list<string> $baseClasses
     * @param list<string> $priorityNames
     * @param list<string> $skipNames
     */
    private function renderBlockHtmlAttrsWithClasses(AstNode $node, array $baseClasses, array $priorityNames = ['id', 'class', 'lang', 'dir', 'role', 'title'], array $skipNames = []): string
    {
        $htmlAttributes = [];
        foreach ($this->inlineHtmlAttributes($node) as $name => $value) {
            $htmlAttributes[strtolower((string) $name)] = $value;
        }
        $classes = $baseClasses;
        $existingClass = trim((string) ($htmlAttributes['class'] ?? ''));
        if ($existingClass !== '') {
            array_push($classes, ...preg_split('/\s+/', $existingClass, -1, PREG_SPLIT_NO_EMPTY));
        }
        $nodeClasses = $node->attr('classes', []);
        if (is_array($nodeClasses)) {
            foreach ($nodeClasses as $class) {
                $class = trim((string) $class);
                if ($class !== '') {
                    $classes[] = $class;
                }
            }
        }
        if ($classes !== []) {
            $htmlAttributes['class'] = implode(' ', array_values(array_unique($classes)));
        }

        $orderedNames = [];
        foreach ($priorityNames as $name) {
            if (array_key_exists($name, $htmlAttributes)) {
                $orderedNames[] = $name;
            }
        }
        foreach (array_keys($htmlAttributes) as $name) {
            $name = strtolower((string) $name);
            if (!in_array($name, $orderedNames, true)) {
                $orderedNames[] = $name;
            }
        }

        $attrs = '';
        foreach ($orderedNames as $name) {
            $value = $htmlAttributes[$name];
            $name = strtolower((string) $name);
            if (in_array($name, $skipNames, true) || !$this->isAllowedBlockHtmlAttr($name)) {
                continue;
            }
            $attrs .= ' ' . $name . '="' . $this->esc((string) $value) . '"';
        }

        return $attrs;
    }

    private function renderTaskListLabel(bool $checked, string $html): string
    {
        $checkbox = '<input type="checkbox"' . ($checked ? ' checked=""' : '') . ' />';

        return '<label>' . $checkbox . $html . '</label>';
    }

    private function listIsTaskList(AstNode $node): bool
    {
        if ($node->attr('taskList') === true) {
            return true;
        }

        if (!$this->taskGlyphsAsCheckboxesEnabled()) {
            return false;
        }

        $hasItems = false;
        foreach ($node->children as $item) {
            if ($item->type !== 'list_item') {
                continue;
            }

            $hasItems = true;
            if ($this->taskGlyphChecked($item) === null) {
                return false;
            }
        }

        return $hasItems;
    }

    private function taskGlyphsAsCheckboxesEnabled(): bool
    {
        return (bool) ($this->options['taskGlyphsAsCheckboxes'] ?? false);
    }

    private function taskGlyphChecked(AstNode $item): ?bool
    {
        if (!$this->taskGlyphsAsCheckboxesEnabled()) {
            return null;
        }

        foreach ($item->children as $child) {
            if (in_array($child->type, ['paragraph', 'plain'], true)) {
                return $this->taskGlyphCheckedFromInlineNodes($child->children);
            }

            if ($this->isInlineNode($child)) {
                return $this->taskGlyphCheckedFromInlineNodes([$child]);
            }

            return null;
        }

        return null;
    }

    /**
     * @param list<AstNode> $nodes
     */
    private function taskGlyphCheckedFromInlineNodes(array $nodes): ?bool
    {
        foreach ($nodes as $node) {
            if ($node->type !== 'text') {
                return null;
            }

            $text = (string) $node->attr('text', '');
            if ($text === '') {
                continue;
            }

            if (preg_match('/^\x{2610}/u', $text) === 1) {
                return false;
            }

            if (preg_match('/^\x{2612}/u', $text) === 1) {
                return true;
            }

            return null;
        }

        return null;
    }

    private function renderDefinitionList(AstNode $node): string
    {
        $blocks = [];
        foreach ($node->children as $item) {
            if ($item->type !== 'definition_item') {
                continue;
            }

            $children = $item->children;
            $term = array_shift($children);
            if (!$term instanceof AstNode || !in_array($term->type, ['term', 'definition_term'], true)) {
                $term = new AstNode('term', ['text' => (string) $item->attr('term', '')]);
            }

            $blocks[] = '<!-- wp:paragraph -->'
                . "\n" . '<p class="pandoc-definition-term"><strong>' . $this->renderInlines($term) . '</strong></p>'
                . "\n" . '<!-- /wp:paragraph -->';

            $items = [];
            $displayParts = $item->attr('cslDisplayParts', []);
            if (is_array($displayParts)) {
                $displayHtml = $this->renderCslDisplayParts($displayParts);
                if ($displayHtml !== '') {
                    $items[] = '<li>' . $displayHtml . '</li>';
                }
            }

            if ($items === []) {
                foreach ($children as $definition) {
                    if ($definition->type === 'definition') {
                        $items[] = '<li>' . $this->renderDefinitionBlocks($definition) . '</li>';
                    }
                }
            }

            if ($items !== []) {
                $blocks[] = '<!-- wp:list -->'
                    . "\n" . '<ul class="pandoc-definition-values">' . implode('', $items) . '</ul>'
                    . "\n" . '<!-- /wp:list -->';
            }
        }

        return $this->renderGroupBlock($node, ['pandoc-definition-list'], implode("\n\n", $blocks));
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

    private function renderRawFormatBlock(AstNode $node): string
    {
        $format = (string) $node->attr('format', 'raw');
        $rawFamily = MarkdownFormatProfile::rawFamily($format);
        if ($rawFamily === 'html') {
            return '<!-- wp:html -->'
                . "\n" . (string) $node->attr('text', '')
                . "\n" . '<!-- /wp:html -->';
        }

        if ($rawFamily === 'tex') {
            return '<!-- wp:code -->'
                . "\n" . $this->renderRawTexBlockHtml($node)
                . "\n" . '<!-- /wp:code -->';
        }

        return '<!-- wp:code -->'
            . "\n" . $this->renderRawFormatBlockHtml($node)
            . "\n" . '<!-- /wp:code -->';
    }

    private function renderDefinitionListHtml(AstNode $node): string
    {
        $html = '<dl' . $this->renderBlockHtmlAttrs($node) . $this->renderCslBibliographyOptionAttrs($node) . '>';
        foreach ($node->children as $item) {
            if ($item->type !== 'definition_item') {
                continue;
            }

            $children = $item->children;
            $term = array_shift($children);
            if (!$term instanceof AstNode || !in_array($term->type, ['term', 'definition_term'], true)) {
                $term = new AstNode('term', ['text' => (string) $item->attr('term', '')]);
            }
            $html .= '<dt>' . $this->renderInlines($term) . '</dt>';

            $displayParts = $item->attr('cslDisplayParts', []);
            if (is_array($displayParts)) {
                $displayHtml = $this->renderCslDisplayParts($displayParts);
                if ($displayHtml !== '') {
                    $html .= '<dd>' . $displayHtml . '</dd>';
                    continue;
                }
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

    private function renderCslBibliographyOptionAttrs(AstNode $node): string
    {
        $classes = $node->attr('classes', []);
        if (!is_array($classes) || !in_array('pandoc-csl-bibliography', $classes, true)) {
            return '';
        }

        $existingAttrs = array_change_key_case($this->inlineHtmlAttributes($node), CASE_LOWER);
        $attrs = '';
        if ($node->attr('hangingIndent') === true && !array_key_exists('data-csl-hanging-indent', $existingAttrs)) {
            $attrs .= ' data-csl-hanging-indent="true"';
        }

        $entrySpacing = $node->attr('entrySpacing');
        if ($entrySpacing !== null && !array_key_exists('data-csl-entry-spacing', $existingAttrs)) {
            $attrs .= ' data-csl-entry-spacing="' . $this->esc((string) $entrySpacing) . '"';
        }

        $lineSpacing = $node->attr('lineSpacing');
        if ($lineSpacing !== null && !array_key_exists('data-csl-line-spacing', $existingAttrs)) {
            $attrs .= ' data-csl-line-spacing="' . $this->esc((string) $lineSpacing) . '"';
        }

        $secondFieldAlign = (string) $node->attr('secondFieldAlign', '');
        if ($secondFieldAlign !== '' && !array_key_exists('data-csl-second-field-align', $existingAttrs)) {
            $attrs .= ' data-csl-second-field-align="' . $this->esc($secondFieldAlign) . '"';
        }

        return $attrs;
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

        $caption = (string) $node->attr('caption', '');
        $captionHtml = $caption !== '' ? $this->renderTableCaptionHtml($node) : '';
        $figcaption = $caption !== ''
            ? '<figcaption' . $this->renderTableFigcaptionAttrs($node) . '>' . $captionHtml . '</figcaption>'
            : '';
        $accessibility = $this->tableAccessibilityByCell($node);
        $cellColumns = $this->tableVisualColumnsByCell($node);
        $html = '<table' . $this->renderTableElementAttrs($node) . '>' . $this->renderTableColgroup($node);
        if ($head instanceof AstNode && $head->children !== []) {
            $html .= '<thead' . $this->renderStoredHtmlAttrs($head, true, []) . '>';
            foreach ($head->children as $row) {
                $html .= $this->renderTableRow($row, $node, true, 0, $accessibility, $cellColumns);
            }
            $html .= '</thead>';
        }

        if ($bodies === []) {
            $bodies[] = new AstNode('table_body');
        }
        foreach ($bodies as $body) {
            $html .= '<tbody' . $this->renderStoredHtmlAttrs($body, true, []) . '>';
            $bodyHeadRows = $body->attr('headRows', []);
            if (is_array($bodyHeadRows)) {
                foreach ($bodyHeadRows as $row) {
                    if ($row instanceof AstNode) {
                        $html .= $this->renderTableRow($row, $node, true, 0, $accessibility, $cellColumns);
                    }
                }
            }
            $rowHeadColumns = max(0, (int) $body->attr('rowHeadColumns', 0));
            foreach ($body->children as $row) {
                $html .= $this->renderTableRow($row, $node, false, $rowHeadColumns, $accessibility, $cellColumns);
            }
            $html .= '</tbody>';
        }
        if ($foot instanceof AstNode && $foot->children !== []) {
            $html .= '<tfoot' . $this->renderStoredHtmlAttrs($foot, true, []) . '>';
            foreach ($foot->children as $row) {
                $html .= $this->renderTableRow($row, $node, false, 0, $accessibility, $cellColumns);
            }
            $html .= '</tfoot>';
        }
        $html .= '</table>';

        return $figcaption !== '' && $this->tableCaptionBeforeTable($node)
            ? $figcaption . $html
            : $html . $figcaption;
    }

    private function tableCaptionBeforeTable(AstNode $node): bool
    {
        $geometry = $node->attr('tableGeometry', []);
        if (is_array($geometry)) {
            $captions = $geometry['captions'] ?? [];
            $long = is_array($captions) && is_array($captions['long'] ?? null) ? $captions['long'] : [];
            if (($long['captionBeforeTable'] ?? false) === true) {
                return true;
            }
        }

        $captionSource = $node->attr('captionSource', []);
        if (!is_array($captionSource)) {
            return false;
        }

        return strtolower(trim((string) ($captionSource['captionSide'] ?? ''))) === 'top';
    }

    private function renderTableFigcaptionAttrs(AstNode $node): string
    {
        $attrs = $this->tableCaptionSourceAttributes($node);
        $classes = ['wp-element-caption'];
        $sourceClass = trim((string) ($attrs['class'] ?? ''));
        if ($sourceClass !== '') {
            array_push($classes, ...preg_split('/\s+/', $sourceClass, -1, PREG_SPLIT_NO_EMPTY));
        }
        $attrs['class'] = implode(' ', array_values(array_unique($classes)));

        return $this->renderTableCaptionAttrMap($attrs);
    }

    /**
     * @return array<string, mixed>
     */
    private function tableCaptionSourceAttributes(AstNode $node): array
    {
        $captionSource = $node->attr('captionSource', []);
        if (!is_array($captionSource)) {
            return [];
        }

        $sourceAttributes = $captionSource['sourceAttributes'] ?? [];
        if (!is_array($sourceAttributes)) {
            return [];
        }

        $attrs = [];
        $htmlAttributes = $sourceAttributes['htmlAttributes'] ?? [];
        if (is_array($htmlAttributes)) {
            foreach ($htmlAttributes as $name => $value) {
                $attrs[strtolower((string) $name)] = $value;
            }
        }

        if (!array_key_exists('id', $attrs)) {
            $id = (string) ($sourceAttributes['id'] ?? '');
            if ($id !== '') {
                $attrs['id'] = $id;
            }
        }

        $classes = [];
        $htmlClass = trim((string) ($attrs['class'] ?? ''));
        if ($htmlClass !== '') {
            $classes = array_merge($classes, preg_split('/\s+/', $htmlClass, -1, PREG_SPLIT_NO_EMPTY));
        }
        $sourceClasses = $sourceAttributes['classes'] ?? [];
        if (is_array($sourceClasses)) {
            foreach ($sourceClasses as $class) {
                $class = trim((string) $class);
                if ($class !== '') {
                    $classes[] = $class;
                }
            }
        }
        if ($classes !== []) {
            $attrs['class'] = implode(' ', array_values(array_unique($classes)));
        }

        $attributes = $sourceAttributes['attributes'] ?? [];
        if (is_array($attributes)) {
            foreach ($attributes as $name => $value) {
                $name = strtolower((string) $name);
                if (!array_key_exists($name, $attrs)) {
                    $attrs[$name] = $value;
                }
            }
        }

        return $attrs;
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
        $attrs = $this->storedTableAttrMap($node, true, []);
        $this->sanitizeTableElementAttrs($attrs);

        return $this->renderHtmlAttrMap($attrs);
    }

    private function renderTableCaptionHtml(AstNode $node): string
    {
        $blocks = $node->attr('captionBlocks', null);
        if (is_array($blocks) && $blocks !== []) {
            foreach ($blocks as $block) {
                if (!$block instanceof AstNode) {
                    return $this->renderCaptionInlines($node);
                }
            }

            if (count($blocks) === 1 && in_array($blocks[0]->type, ['plain', 'paragraph'], true)) {
                if ($this->tableCaptionShouldPreserveBlockWrapper($node)) {
                    return $this->renderBlocksAsHtml($blocks, true);
                }

                return $this->renderInlines($blocks[0]);
            }

            return $this->renderBlocksAsHtml($blocks);
        }

        return $this->renderCaptionInlines($node);
    }

    private function tableCaptionShouldPreserveBlockWrapper(AstNode $node): bool
    {
        $captionSource = $node->attr('captionSource', []);
        if (!is_array($captionSource) || $captionSource === []) {
            return false;
        }

        return is_string($captionSource['source'] ?? null)
            || is_string($captionSource['sourcePosition'] ?? null)
            || is_array($captionSource['sourceAttributes'] ?? null);
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
        $columnCount = TableGeometry::columnCount($node);
        $sourceColumns = $node->attr('columnSources', []);
        if (is_array($sourceColumns) && $sourceColumns !== [] && count($sourceColumns) < $columnCount) {
            return '';
        }

        if (is_array($sourceColumns) && $sourceColumns !== []) {
            $html = '';
            foreach ($this->sourceColumnGroups($sourceColumns) as $group) {
                $source = is_array($group['source'] ?? null) ? $group['source'] : [];
                $groupAttributes = is_array($source['colgroupAttributes'] ?? null) ? $source['colgroupAttributes'] : [];
                $groupHtmlAttributes = is_array($groupAttributes['htmlAttributes'] ?? null) ? $groupAttributes['htmlAttributes'] : [];

                $html .= '<colgroup' . $this->renderHtmlAttrMap($this->sanitizeTableColumnSourceAttrs($groupHtmlAttributes, true)) . '>';

                $columns = is_array($group['columns'] ?? null) ? array_values($group['columns']) : [];
                foreach ($columns as $offset => $column) {
                    $column = (int) $column;
                    $columnSource = $this->sourceForColumn($node, $column);
                    $colAttributes = is_array($columnSource['colAttributes'] ?? null) && is_array($columnSource['colAttributes']['htmlAttributes'] ?? null)
                        ? $columnSource['colAttributes']['htmlAttributes']
                        : [];
                    $attrs = $this->sanitizeTableColumnSourceAttrs($colAttributes, false);

                    $width = $columnSource['width'] ?? null;
                    if (!is_numeric($width) || (float) $width <= 0.0) {
                        $tableWidths = $node->attr('widths', []);
                        $width = is_array($tableWidths) && is_numeric($tableWidths[$column] ?? null)
                            ? (float) $tableWidths[$column]
                            : null;
                    }
                    if (!is_numeric($width) || (float) $width <= 0.0) {
                        continue;
                    }

                    $styles = ['width:' . $this->formatTableWidth((float) $width)];
                    array_push($styles, ...$this->columnSourceStyleDeclarations($columnSource));
                    $attrs['style'] = implode('; ', $styles);
                    $html .= '<col' . $this->renderHtmlAttrMap($attrs) . '/>';
                }

                $html .= '</colgroup>';
            }

            return $html;
        }

        $widths = $node->attr('widths', null);
        if (!is_array($widths) || $widths === []) {
            return '';
        }

        $cols = [];
        foreach ($widths as $width) {
            if (!is_numeric($width) || (float) $width <= 0.0) {
                return '';
            }

            $cols[] = '<col style="width:' . $this->esc($this->formatTableWidth((float) $width)) . '"/>';
        }

        return '<colgroup>' . implode('', $cols) . '</colgroup>';
    }

    private function formatTableWidth(float $width): string
    {
        $formatted = rtrim(rtrim(number_format($width * 100, 4, '.', ''), '0'), '.');

        return ($formatted === '' ? '0' : $formatted) . '%';
    }

    /**
     * @param array<int, array<string, mixed>> $accessibility
     * @param array<int, int> $cellColumns
     */
    private function renderTableRow(
        AstNode $row,
        AstNode $table,
        bool $header,
        int $rowHeadColumns = 0,
        array $accessibility = [],
        array $cellColumns = []
    ): string
    {
        $html = '<tr' . $this->renderStoredHtmlAttrs($row, true, []) . '>';
        $logicalColumn = 0;
        foreach ($row->children as $index => $cell) {
            if ($cell->type !== 'table_cell') {
                continue;
            }
            $colspan = max(1, (int) $cell->attr('colspan', 1));
            $cellId = spl_object_id($cell);
            $visualColumn = $cellColumns[$cellId] ?? $logicalColumn;
            $attrs = $this->renderTableCellAttrs($table, $visualColumn, $cell, $accessibility[$cellId] ?? []);
            $tag = $header || $cell->attr('header') === true || ($visualColumn < $rowHeadColumns && $visualColumn + $colspan <= $rowHeadColumns) ? 'th' : 'td';
            $html .= '<' . $tag . $attrs . '>' . $this->renderTableCellContent($cell) . '</' . $tag . '>';
            $logicalColumn += $colspan;
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
            'space',
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
            'raw_tex_inline',
            'raw_html_inline',
            'raw_inline',
            'code',
            'link',
            'image',
            'note',
            'citation',
        ], true);
    }

    /**
     * @param array<string, mixed> $accessibility
     */
    private function renderTableCellAttrs(AstNode $table, int $column, AstNode $cell, array $accessibility = []): string
    {
        $attrMap = $this->storedTableAttrMap($cell, true, ['style']);
        $this->sanitizeTableCellAttrs($attrMap, $cell, $accessibility);
        if ($this->shouldMarkEmptyTableCell($cell)) {
            $attrMap['data-pandoc-empty-cell'] = 'true';
        }

        $colspan = (int) $cell->attr('colspan', 1);
        if ($colspan > 1) {
            $attrMap['colspan'] = (string) $colspan;
        }

        $rowspan = (int) $cell->attr('renderRowspan', $cell->attr('rowspan', 1));
        if ($rowspan > 1) {
            $attrMap['rowspan'] = (string) $rowspan;
        }

        $alignments = $table->attr('alignments', []);
        $alignment = (string) $cell->attr('align', '');
        if ($alignment === '' && is_array($alignments)) {
            $alignment = (string) ($alignments[$column] ?? 'default');
        }

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

        $verticalAlignment = (string) $cell->attr('valign', '');
        if (
            in_array($verticalAlignment, ['baseline', 'top', 'middle', 'bottom'], true)
            && !($verticalAlignment === 'top' && $table->attr('sourceFormat') === 'docbook')
            && !isset($attrMap['valign'])
            && preg_match('/(?:^|;)\s*vertical-align\s*:/i', $sourceStyle) !== 1
        ) {
            $styles[] = 'vertical-align:' . $verticalAlignment;
        }

        if ($styles !== []) {
            $attrMap['style'] = implode('; ', $styles);
        }

        return $this->renderHtmlAttrMap($attrMap);
    }

    private function shouldMarkEmptyTableCell(AstNode $cell): bool
    {
        return (bool) ($this->options['markEmptyTableCells'] ?? false)
            && $this->isEmptyTableCell($cell);
    }

    private function isEmptyTableCell(AstNode $cell): bool
    {
        if ($cell->children === []) {
            return trim((string) $cell->attr('text', '')) === '';
        }

        foreach ($cell->children as $child) {
            if ($this->nodeHasVisibleTableCellContent($child)) {
                return false;
            }
        }

        return true;
    }

    private function nodeHasVisibleTableCellContent(AstNode $node): bool
    {
        if (trim((string) $node->attr('text', '')) !== '') {
            return true;
        }

        if (in_array($node->type, ['image', 'code_block', 'raw_html', 'raw_tex', 'raw_block', 'table', 'horizontal_rule'], true)) {
            foreach (['url', 'src', 'alt', 'html', 'tex', 'format'] as $attr) {
                if (trim((string) $node->attr($attr, '')) !== '') {
                    return true;
                }
            }
        }

        foreach ($node->children as $child) {
            if ($this->nodeHasVisibleTableCellContent($child)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function tableAccessibilityByCell(AstNode $table): array
    {
        if ($this->tableAccessibilitySlotCount($table) > self::MAX_TABLE_ACCESSIBILITY_SLOTS) {
            return [];
        }

        $accessibility = TableGeometry::accessibilityAttributes($table);
        if ($accessibility === []) {
            return [];
        }

        $byCell = [];
        $coverage = TableGeometry::cellCoverage($table);
        $renderGeneratedHeaders = false;
        foreach ($coverage as $record) {
            $node = $record['node'] ?? null;
            if (!$node instanceof AstNode) {
                continue;
            }
            $htmlAttributes = $node->attr('htmlAttributes', []);
            if (is_array($htmlAttributes) && strtolower(trim((string) ($htmlAttributes['scope'] ?? ''))) === 'auto') {
                $renderGeneratedHeaders = true;
                break;
            }
        }

        foreach ($coverage as $record) {
            $node = $record['node'] ?? null;
            if (!$node instanceof AstNode) {
                continue;
            }

            $key = implode(':', [
                (string) ($record['section'] ?? ''),
                (string) ((int) ($record['row'] ?? 0)),
                (string) ((int) ($record['sourceCell'] ?? 0)),
                (string) ((int) ($record['sourceColumn'] ?? 0)),
            ]);
            if (isset($accessibility[$key]) && is_array($accessibility[$key])) {
                $attrs = $accessibility[$key];
                if ($renderGeneratedHeaders) {
                    $attrs['__renderGeneratedHeaders'] = true;
                }
                $byCell[spl_object_id($node)] = $attrs;
            }
        }

        return $byCell;
    }

    private function tableAccessibilitySlotCount(AstNode $table): int
    {
        $columnCount = TableGeometry::columnCount($table);
        if ($columnCount <= 0) {
            return 0;
        }

        return $columnCount * $this->tableRowCount($table);
    }

    private function tableRowCount(AstNode $node): int
    {
        $count = $node->type === 'table_row' ? 1 : 0;
        foreach ($node->children as $child) {
            $count += $this->tableRowCount($child);
        }

        return $count;
    }

    /**
     * @return array<int, int>
     */
    private function tableVisualColumnsByCell(AstNode $table): array
    {
        $byCell = [];
        foreach (TableGeometry::cellCoverage($table) as $record) {
            $node = $record['node'] ?? null;
            if (!$node instanceof AstNode) {
                continue;
            }

            $byCell[spl_object_id($node)] = max(0, (int) ($record['column'] ?? 0));
        }

        return $byCell;
    }

    /**
     * @param list<string> $skip
     * @return array<string, string>
     */
    private function storedTableAttrMap(AstNode $node, bool $includeIdentity, array $skip): array
    {
        $htmlAttributes = $this->inlineHtmlAttributes($node);
        if ($htmlAttributes === []) {
            return [];
        }

        $attrs = [];
        if ($includeIdentity) {
            $id = (string) ($htmlAttributes['id'] ?? $node->attr('id', ''));
            if ($id !== '') {
                $attrs['id'] = $id;
            }

            $class = (string) ($htmlAttributes['class'] ?? '');
            if ($class === '') {
                $classes = $node->attr('classes', []);
                if (is_array($classes) && $classes !== []) {
                    $class = implode(' ', array_map(static fn (mixed $value): string => (string) $value, $classes));
                }
            }
            if ($class !== '') {
                $attrs['class'] = $class;
            }
        }

        foreach ($htmlAttributes as $name => $value) {
            $name = strtolower((string) $name);
            if (
                $name === 'id'
                || $name === 'class'
                || in_array($name, $skip, true)
                || !$this->isAllowedStoredTableHtmlAttr($name)
            ) {
                continue;
            }

            $attrs[$name] = (string) $value;
        }

        return $attrs;
    }

    /**
     * @param array<string, mixed> $attrs
     */
    private function renderHtmlAttrMap(array $attrs): string
    {
        $html = '';
        foreach ($attrs as $name => $value) {
            $name = strtolower((string) $name);
            if ($name === '' || $value === null || $value === false) {
                continue;
            }

            $html .= ' ' . $name . '="' . $this->esc((string) $value) . '"';
        }

        return $html;
    }

    /**
     * @param array<string, mixed> $attrs
     */
    private function renderTableCaptionAttrMap(array $attrs): string
    {
        $rendered = [];
        foreach (['id', 'class'] as $priorityName) {
            if (!array_key_exists($priorityName, $attrs)) {
                continue;
            }
            $value = $attrs[$priorityName];
            if ($this->isAllowedTableCaptionHtmlAttr($priorityName)) {
                $rendered[$priorityName] = (string) $value;
            }
        }
        foreach ($attrs as $name => $value) {
            $name = strtolower((string) $name);
            if (array_key_exists($name, $rendered)) {
                continue;
            }
            if (
                !$this->isAllowedTableCaptionHtmlAttr($name)
                || ($name === 'translate' && $this->normalizeTranslateAttr((string) $value) === '')
            ) {
                continue;
            }
            if ($name === 'lang' || $name === 'xml:lang') {
                $value = $this->normalizeLanguageAttr((string) $value);
                if ($value === '') {
                    continue;
                }
            }
            $rendered[$name] = (string) $value;
        }

        return $this->renderHtmlAttrMap($rendered);
    }

    /**
     * @param array<string, string> $attrs
     */
    private function sanitizeTableElementAttrs(array &$attrs): void
    {
        $source = $attrs;
        $sanitized = [];
        $xmlLanguage = isset($source['xml:lang']) ? $this->normalizeLanguageAttr((string) $source['xml:lang']) : '';

        foreach ($source as $name => $value) {
            $name = strtolower((string) $name);
            $value = (string) $value;

            if ($name === 'style') {
                $styles = $this->tableStyleDeclarations($value);
                if ($styles !== []) {
                    $sanitized['style'] = implode('; ', $styles);
                }
                continue;
            }

            if ($name === 'lang') {
                $language = $xmlLanguage !== '' ? $xmlLanguage : $this->normalizeLanguageAttr($value);
                if ($language !== '') {
                    $sanitized[$name] = $language;
                }
                continue;
            }

            if ($name === 'xml:lang') {
                if ($xmlLanguage !== '') {
                    $sanitized[$name] = $xmlLanguage;
                }
                continue;
            }

            if ($name === 'translate') {
                $translate = $this->normalizeTranslateAttr($value);
                if ($translate !== '') {
                    $sanitized[$name] = $translate;
                }
                continue;
            }

            if ($name === 'width' || $name === 'height') {
                $dimension = $this->normalizeTableDimensionAttr($value);
                if ($dimension !== '') {
                    $sanitized[$name] = $dimension;
                }
                continue;
            }

            if ($name === 'align') {
                $alignment = $this->normalizePlacementAlignAttr($value);
                if ($alignment !== '') {
                    $sanitized[$name] = $alignment;
                }
                continue;
            }

            if ($name === 'frame') {
                $frame = $this->normalizeTableFrameAttr($value);
                if ($frame !== '') {
                    $sanitized[$name] = $frame;
                }
                continue;
            }

            if ($name === 'rules') {
                $rules = $this->normalizeTableRulesAttr($value);
                if ($rules !== '') {
                    $sanitized[$name] = $rules;
                }
                continue;
            }

            if ($name === 'border') {
                $border = $this->normalizeTableBorderAttr($value);
                if ($border !== '') {
                    $sanitized[$name] = $border;
                }
                continue;
            }

            if ($name === 'cellpadding' || $name === 'cellspacing') {
                $spacing = $this->normalizeTableSpacingAttr($value);
                if ($spacing !== '') {
                    $sanitized[$name] = $spacing;
                }
                continue;
            }

            if ($name === 'bgcolor') {
                $color = $this->normalizeCssColor($value);
                if ($color !== '') {
                    $sanitized[$name] = $color;
                }
                continue;
            }

            if ($name === 'bordercolor') {
                continue;
            }

            if ($this->isAllowedTableElementPassthroughAttr($name)) {
                $sanitized[$name] = $value;
            }
        }

        $attrs = $this->orderTableElementAttrs($sanitized);
    }

    /**
     * @param array<string, string> $attrs
     * @param array<string, mixed> $accessibility
     */
    private function sanitizeTableCellAttrs(array &$attrs, AstNode $cell, array $accessibility): void
    {
        $source = $attrs;
        $sanitized = [];
        $sourceScope = strtolower(trim((string) ($source['scope'] ?? '')));
        if ($sourceScope === 'auto') {
            $scope = strtolower(trim((string) ($accessibility['scope'] ?? '')));
            if (in_array($scope, ['col', 'row', 'colgroup', 'rowgroup'], true)) {
                $sanitized['scope'] = $scope;
            }
        }

        foreach ($source as $name => $value) {
            $name = strtolower((string) $name);
            $value = (string) $value;

            if ($name === 'align') {
                if (strtolower(trim($value)) === 'char') {
                    $sanitized[$name] = 'char';
                }
                continue;
            }

            if ($name === 'scope') {
                $scope = strtolower(trim($value));
                if ($scope !== 'auto' && in_array($scope, ['col', 'row', 'colgroup', 'rowgroup'], true)) {
                    $sanitized[$name] = $scope;
                }
                continue;
            }

            if ($name === 'nowrap') {
                if (!in_array(strtolower(trim($value)), ['0', 'false', 'no'], true)) {
                    $sanitized[$name] = $value === '' ? 'nowrap' : $value;
                }
                continue;
            }

            if ($name === 'valign') {
                $valign = strtolower(trim($value));
                if (in_array($valign, ['baseline', 'top', 'middle', 'bottom'], true)) {
                    $sanitized[$name] = $valign;
                }
                continue;
            }

            if ($name === 'width' || $name === 'height') {
                $dimension = $this->normalizeTableDimensionAttr($value);
                if ($dimension !== '') {
                    $sanitized[$name] = $dimension;
                }
                continue;
            }

            if ($name === 'lang' || $name === 'xml:lang') {
                $language = $this->normalizeLanguageAttr($value);
                if ($language !== '') {
                    $sanitized[$name] = $language;
                }
                continue;
            }

            if ($name === 'translate') {
                $translate = $this->normalizeTranslateAttr($value);
                if ($translate !== '') {
                    $sanitized[$name] = $translate;
                }
                continue;
            }

            if ($name === 'bgcolor') {
                if ($this->normalizeCssColor($value) !== '') {
                    $sanitized[$name] = $value;
                }
                continue;
            }

            if (in_array($name, ['id', 'class', 'abbr', 'axis', 'char', 'charoff', 'dir', 'headers', 'role', 'title'], true)
                || str_starts_with($name, 'data-')
                || str_starts_with($name, 'aria-')
                || $this->isAllowedSafeGlobalHtmlAttr($name)
            ) {
                $sanitized[$name] = $value;
            }
        }

        if (!isset($sanitized['headers']) && ($accessibility['__renderGeneratedHeaders'] ?? false) === true) {
            $headers = $accessibility['headers'] ?? [];
            if (is_array($headers) && $headers !== [] && $cell->attr('header') !== true) {
                $sanitized['headers'] = implode(' ', array_map(static fn (mixed $header): string => (string) $header, $headers));
            }
        }

        $attrs = $sanitized;
    }

    /**
     * @param array<string, mixed> $attrs
     * @return array<string, string>
     */
    private function sanitizeTableColumnSourceAttrs(array $attrs, bool $colgroup): array
    {
        $kept = [];
        foreach ($attrs as $name => $value) {
            $name = strtolower((string) $name);
            $value = (string) $value;
            if (
                in_array($name, ['span', 'style', 'width'], true)
                || !$this->isAllowedTableColumnHtmlAttr($name)
            ) {
                continue;
            }
            if ($name === 'bgcolor' && $this->normalizeCssColor($value) === '') {
                continue;
            }
            if ($name === 'align') {
                if (strtolower(trim($value)) !== 'char') {
                    continue;
                }
                $value = 'char';
            }
            if ($name === 'valign' && !in_array(strtolower(trim($value)), ['baseline', 'top', 'middle', 'bottom'], true)) {
                continue;
            }

            $kept[$name] = $value;
        }

        return $this->orderTableColumnAttrs($kept, $colgroup);
    }

    /**
     * @return array<string, mixed>
     */
    private function sourceForColumn(AstNode $table, int $column): array
    {
        $sources = $table->attr('columnSources', []);
        if (is_array($sources) && isset($sources[$column]) && is_array($sources[$column])) {
            return $sources[$column];
        }

        return [];
    }

    /**
     * @param array<int, mixed> $sources
     * @return list<array{source:array<string, mixed>,columns:list<int>}>
     */
    private function sourceColumnGroups(array $sources): array
    {
        $groups = [];
        $active = null;
        $activeKey = null;
        foreach ($sources as $column => $source) {
            if (!is_array($source)) {
                continue;
            }

            $key = (string) ($source['colgroupIndex'] ?? '');
            if ($active === null || $key !== $activeKey) {
                if ($active !== null) {
                    $groups[] = $active;
                }
                $active = [
                    'source' => $source,
                    'columns' => [(int) $column],
                ];
                $activeKey = $key;
                continue;
            }

            $active['columns'][] = (int) $column;
        }

        if ($active !== null) {
            $groups[] = $active;
        }

        return $groups;
    }

    /**
     * @param array<string, mixed> $source
     * @return list<string>
     */
    private function columnSourceStyleDeclarations(array $source): array
    {
        $colAttributes = is_array($source['colAttributes'] ?? null) && is_array($source['colAttributes']['htmlAttributes'] ?? null)
            ? $source['colAttributes']['htmlAttributes']
            : [];
        $colgroupAttributes = is_array($source['colgroupAttributes'] ?? null) && is_array($source['colgroupAttributes']['htmlAttributes'] ?? null)
            ? $source['colgroupAttributes']['htmlAttributes']
            : [];

        $styles = [];
        $background = $this->columnBackgroundDeclaration($colAttributes);
        if ($background === '') {
            $background = $this->columnBackgroundDeclaration($colgroupAttributes);
        }
        if ($background !== '') {
            $styles[] = $background;
        }

        $borderStyles = $this->columnBorderDeclarations($colAttributes);
        if ($borderStyles === []) {
            $borderStyles = $this->columnBorderDeclarations($colgroupAttributes);
        }
        array_push($styles, ...$borderStyles);

        return $styles;
    }

    /**
     * @param array<string, mixed> $attrs
     */
    private function columnBackgroundDeclaration(array $attrs): string
    {
        $styleColor = $this->styleDeclarationColor((string) ($attrs['style'] ?? ''), 'background-color');
        if ($styleColor !== '') {
            return 'background-color:' . $styleColor;
        }

        $legacyColor = $this->normalizeCssColor((string) ($attrs['bgcolor'] ?? ''));

        return $legacyColor === '' ? '' : 'background-color:' . $legacyColor;
    }

    /**
     * @param array<string, mixed> $attrs
     * @return list<string>
     */
    private function columnBorderDeclarations(array $attrs): array
    {
        $style = (string) ($attrs['style'] ?? '');
        if ($style === '') {
            return [];
        }

        $declarations = [];
        $borderColor = $this->styleDeclarationColor($style, 'border-color');
        if ($borderColor !== '') {
            $declarations[] = 'border-color:' . $borderColor;
        }
        $borderStyle = $this->styleDeclarationLineStyle($style, 'border-style');
        if ($borderStyle !== '') {
            $declarations[] = 'border-style:' . $borderStyle;
        }
        $borderWidth = $this->styleDeclarationBorderWidth($style, 'border-width');
        if ($borderWidth !== '') {
            $declarations[] = 'border-width:' . $borderWidth;
        }
        array_push($declarations, ...$this->styleDeclarationBorderEdges($style));

        return $declarations;
    }

    /**
     * @return list<string>
     */
    private function tableStyleDeclarations(string $style): array
    {
        $declarations = [];
        $backgroundColor = $this->styleDeclarationColor($style, 'background-color');
        if ($backgroundColor !== '') {
            $declarations[] = 'background-color:' . $backgroundColor;
        }

        $tableLayout = strtolower(trim($this->styleDeclarationValue($style, 'table-layout')));
        if (in_array($tableLayout, ['auto', 'fixed'], true)) {
            $declarations[] = 'table-layout:' . $tableLayout;
        }

        $borderCollapse = strtolower(trim($this->styleDeclarationValue($style, 'border-collapse')));
        if (in_array($borderCollapse, ['collapse', 'separate'], true)) {
            $declarations[] = 'border-collapse:' . $borderCollapse;
        }

        $borderColor = $this->styleDeclarationColor($style, 'border-color');
        if ($borderColor !== '') {
            $declarations[] = 'border-color:' . $borderColor;
        }
        $borderStyle = $this->styleDeclarationLineStyle($style, 'border-style');
        if ($borderStyle !== '') {
            $declarations[] = 'border-style:' . $borderStyle;
        }
        $borderWidth = $this->styleDeclarationBorderWidth($style, 'border-width');
        if ($borderWidth !== '') {
            $declarations[] = 'border-width:' . $borderWidth;
        }

        return $declarations;
    }

    private function inlineStyleAttribute(string $style): string
    {
        $declarations = $this->inlineStyleDeclarations($style);

        return $declarations === [] ? '' : implode('; ', $declarations);
    }

    /**
     * @return list<string>
     */
    private function inlineStyleDeclarations(string $style): array
    {
        $declarations = [];

        $color = $this->styleDeclarationColor($style, 'color');
        if ($color !== '') {
            $declarations[] = 'color:' . $color;
        }

        $backgroundColor = $this->styleDeclarationColor($style, 'background-color');
        if ($backgroundColor !== '') {
            $declarations[] = 'background-color:' . $backgroundColor;
        }

        $fontVariant = strtolower(trim($this->styleDeclarationValue($style, 'font-variant')));
        if ($fontVariant === 'small-caps') {
            $declarations[] = 'font-variant:small-caps';
        }

        $textDecoration = $this->normalizeInlineTextDecoration($this->styleDeclarationValue($style, 'text-decoration'));
        if ($textDecoration !== '') {
            $declarations[] = 'text-decoration:' . $textDecoration;
        }

        $textDecorationLine = $this->normalizeInlineTextDecorationLine($this->styleDeclarationValue($style, 'text-decoration-line'));
        if ($textDecorationLine !== '') {
            $declarations[] = 'text-decoration-line:' . $textDecorationLine;
        }

        $textDecorationColor = $this->styleDeclarationColor($style, 'text-decoration-color');
        if ($textDecorationColor !== '') {
            $declarations[] = 'text-decoration-color:' . $textDecorationColor;
        }

        return $declarations;
    }

    private function normalizeInlineTextDecoration(string $value): string
    {
        $tokens = preg_split('/\s+/', strtolower(trim($value)), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if ($tokens === []) {
            return '';
        }

        $lineValues = [];
        $style = '';
        $color = '';
        foreach ($tokens as $token) {
            $line = $this->normalizeInlineTextDecorationLine($token);
            if ($line !== '') {
                $lineValues[] = $line;
                continue;
            }

            if ($style === '' && in_array($token, ['solid', 'double', 'dotted', 'dashed', 'wavy'], true)) {
                $style = $token;
                continue;
            }

            if ($color === '') {
                $candidate = $this->normalizeCssColor($token);
                if ($candidate !== '') {
                    $color = $candidate;
                    continue;
                }
            }

            return '';
        }

        $lineValues = array_values(array_unique($lineValues));
        $parts = array_values(array_filter([implode(' ', $lineValues), $style, $color], static fn (string $part): bool => $part !== ''));

        return $parts === [] ? '' : implode(' ', $parts);
    }

    private function normalizeInlineTextDecorationLine(string $value): string
    {
        $tokens = preg_split('/\s+/', strtolower(trim($value)), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if ($tokens === []) {
            return '';
        }

        $lines = [];
        foreach ($tokens as $token) {
            if (!in_array($token, ['none', 'underline', 'overline', 'line-through'], true)) {
                return '';
            }
            $lines[] = $token;
        }

        if (in_array('none', $lines, true) && count(array_unique($lines)) > 1) {
            return '';
        }

        return implode(' ', array_values(array_unique($lines)));
    }

    private function styleDeclarationValue(string $style, string $property): string
    {
        foreach (explode(';', $style) as $declaration) {
            [$name, $value] = array_pad(explode(':', $declaration, 2), 2, '');
            if (strtolower(trim($name)) === $property) {
                return trim($value);
            }
        }

        return '';
    }

    private function styleDeclarationColor(string $style, string $property): string
    {
        return $this->normalizeCssColor($this->styleDeclarationValue($style, $property));
    }

    private function styleDeclarationLineStyle(string $style, string $property): string
    {
        return $this->normalizeBorderLineStyle($this->styleDeclarationValue($style, $property));
    }

    private function styleDeclarationBorderWidth(string $style, string $property): string
    {
        return $this->normalizeBorderWidth($this->styleDeclarationValue($style, $property));
    }

    /**
     * @return list<string>
     */
    private function styleDeclarationBorderEdges(string $style): array
    {
        $declarations = [];
        foreach (['top', 'right', 'bottom', 'left'] as $edge) {
            $shorthand = $this->normalizeBorderShorthand($this->styleDeclarationValue($style, 'border-' . $edge));
            if ($shorthand !== '') {
                $declarations[] = 'border-' . $edge . ':' . $shorthand;
            }

            $width = $this->styleDeclarationBorderWidth($style, 'border-' . $edge . '-width');
            if ($width !== '') {
                $declarations[] = 'border-' . $edge . '-width:' . $width;
            }
            $lineStyle = $this->styleDeclarationLineStyle($style, 'border-' . $edge . '-style');
            if ($lineStyle !== '') {
                $declarations[] = 'border-' . $edge . '-style:' . $lineStyle;
            }
            $color = $this->styleDeclarationColor($style, 'border-' . $edge . '-color');
            if ($color !== '') {
                $declarations[] = 'border-' . $edge . '-color:' . $color;
            }
        }

        return $declarations;
    }

    private function normalizeBorderShorthand(string $value): string
    {
        $tokens = preg_split('/\s+/', trim($value), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if ($tokens === []) {
            return '';
        }

        $width = '';
        $style = '';
        $color = '';
        foreach ($tokens as $token) {
            if ($width === '') {
                $candidate = $this->normalizeBorderWidth($token);
                if ($candidate !== '') {
                    $width = $candidate;
                    continue;
                }
            }
            if ($style === '') {
                $candidate = $this->normalizeBorderLineStyle($token);
                if ($candidate !== '') {
                    $style = $candidate;
                    continue;
                }
            }
            if ($color === '') {
                $candidate = $this->normalizeCssColor($token);
                if ($candidate !== '') {
                    $color = $candidate;
                    continue;
                }
            }

            return '';
        }

        $parts = array_values(array_filter([$width, $style, $color], static fn (string $part): bool => $part !== ''));

        return count($parts) >= 2 ? implode(' ', $parts) : '';
    }

    private function normalizeBorderLineStyle(string $value): string
    {
        $value = strtolower(trim($value));

        return in_array($value, ['none', 'hidden', 'dotted', 'dashed', 'solid', 'double', 'groove', 'ridge', 'inset', 'outset'], true)
            ? $value
            : '';
    }

    private function normalizeBorderWidth(string $value): string
    {
        $tokens = preg_split('/\s+/', strtolower(trim($value)), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if ($tokens === [] || count($tokens) > 4) {
            return '';
        }

        $widths = [];
        foreach ($tokens as $token) {
            $width = $this->normalizeBorderWidthToken($token);
            if ($width === '') {
                return '';
            }
            $widths[] = $width;
        }

        return implode(' ', $widths);
    }

    private function normalizeBorderWidthToken(string $value): string
    {
        if (in_array($value, ['thin', 'medium', 'thick'], true)) {
            return $value;
        }

        if (preg_match('/^(\d+(?:\.\d+)?)(px|pt|pc|in|cm|mm|em|rem)$/i', $value, $match) !== 1) {
            return '';
        }

        $number = (float) $match[1];
        if ($number < 0.0 || $number > 10000.0) {
            return '';
        }

        $formatted = rtrim(rtrim(number_format($number, 4, '.', ''), '0'), '.');

        return ($formatted === '' ? '0' : $formatted) . strtolower($match[2]);
    }

    private function normalizeCssColor(string $value): string
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
            'aqua', 'black', 'blue', 'fuchsia', 'gray', 'green', 'grey', 'lime', 'maroon', 'navy',
            'olive', 'orange', 'purple', 'red', 'silver', 'teal', 'transparent', 'white', 'yellow',
        ], true) ? $name : '';
    }

    private function normalizeTableDimensionAttr(string $value): string
    {
        $value = trim($value);
        if (preg_match('/^[1-9]\d{0,3}$/', $value) === 1) {
            return (string) (int) $value;
        }
        if (preg_match('/^(\d+(?:\.\d+)?)\s*%$/', $value, $match) !== 1) {
            return '';
        }

        $width = (float) $match[1];
        if ($width <= 0.0 || $width > 100.0) {
            return '';
        }

        $formatted = rtrim(rtrim(number_format($width, 4, '.', ''), '0'), '.');

        return ($formatted === '' ? '0' : $formatted) . '%';
    }

    private function normalizeTableSpacingAttr(string $value): string
    {
        $value = trim($value);

        return preg_match('/^\d{1,3}$/', $value) === 1 ? (string) (int) $value : '';
    }

    private function normalizeTableBorderAttr(string $value): string
    {
        $value = trim($value);
        if ($value === '' || strtolower($value) === 'border') {
            return '1';
        }

        return preg_match('/^\d{1,3}$/', $value) === 1 ? (string) (int) $value : '';
    }

    private function normalizeTableFrameAttr(string $value): string
    {
        $value = strtolower(trim($value));

        return in_array($value, ['void', 'above', 'below', 'hsides', 'lhs', 'rhs', 'vsides', 'box', 'border'], true) ? $value : '';
    }

    private function normalizeTableRulesAttr(string $value): string
    {
        $value = strtolower(trim($value));

        return in_array($value, ['none', 'groups', 'rows', 'cols', 'all'], true) ? $value : '';
    }

    private function normalizePlacementAlignAttr(string $value): string
    {
        $value = strtolower(trim($value));

        return in_array($value, ['left', 'right', 'center'], true) ? $value : '';
    }

    private function normalizeLanguageAttr(string $value): string
    {
        $value = trim($value);
        if (preg_match('/^[A-Za-z]{2,8}(?:-[A-Za-z0-9]{1,8})*$/', $value) !== 1) {
            return '';
        }

        $parts = explode('-', $value);
        $normalized = [strtolower(array_shift($parts))];
        foreach ($parts as $part) {
            $normalized[] = strlen($part) === 2 ? strtoupper($part) : $part;
        }

        return implode('-', $normalized);
    }

    private function normalizeTranslateAttr(string $value): string
    {
        $value = strtolower(trim($value));

        return in_array($value, ['yes', 'no'], true) ? $value : '';
    }

    /**
     * @param array<string, string> $attrs
     * @return array<string, string>
     */
    private function orderTableElementAttrs(array $attrs): array
    {
        if (!isset($attrs['border']) && !isset($attrs['frame']) && !isset($attrs['rules'])) {
            return $attrs;
        }

        $ordered = [];
        $insertedLegacy = false;
        foreach ($attrs as $name => $value) {
            if (in_array($name, ['border', 'frame', 'rules'], true)) {
                if (!$insertedLegacy) {
                    foreach (['border', 'frame', 'rules'] as $legacyName) {
                        if (isset($attrs[$legacyName])) {
                            $ordered[$legacyName] = $attrs[$legacyName];
                        }
                    }
                    $insertedLegacy = true;
                }
                continue;
            }

            $ordered[$name] = $value;
        }

        return $ordered;
    }

    /**
     * @param array<string, string> $attrs
     * @return array<string, string>
     */
    private function orderTableColumnAttrs(array $attrs, bool $colgroup): array
    {
        $ordered = [];
        foreach (['id', 'class'] as $name) {
            if (isset($attrs[$name])) {
                $ordered[$name] = $attrs[$name];
            }
        }
        foreach ($attrs as $name => $value) {
            if (str_starts_with($name, 'aria-')) {
                $ordered[$name] = $value;
            }
        }
        foreach (['align', 'char', 'charoff', 'bgcolor'] as $name) {
            if (isset($attrs[$name])) {
                $ordered[$name] = $attrs[$name];
            }
        }
        foreach ($attrs as $name => $value) {
            if (str_starts_with($name, 'data-')) {
                $ordered[$name] = $value;
            }
        }
        foreach (['title', 'valign', 'dir', 'lang', 'xml:lang', 'translate', 'role'] as $name) {
            if (isset($attrs[$name])) {
                $ordered[$name] = $attrs[$name];
            }
        }
        foreach ($attrs as $name => $value) {
            if (!isset($ordered[$name])) {
                $ordered[$name] = $value;
            }
        }

        return $ordered;
    }

    /**
     * @param list<string> $skip
     */
    private function renderStoredHtmlAttrs(AstNode $node, bool $includeIdentity, array $skip): string
    {
        $htmlAttributes = $this->inlineHtmlAttributes($node);
        if ($htmlAttributes === []) {
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
            || $this->isAllowedSafeGlobalHtmlAttr($name)
            || in_array($name, [
                'abbr',
                'axis',
                'bgcolor',
                'border',
                'cellpadding',
                'cellspacing',
                'char',
                'charoff',
                'class',
                'dir',
                'frame',
                'headers',
                'height',
                'id',
                'lang',
                'nowrap',
                'role',
                'rules',
                'scope',
                'style',
                'summary',
                'title',
                'translate',
                'valign',
                'width',
                'xml:lang',
            ], true);
    }

    private function isAllowedStoredTableHtmlAttr(string $name): bool
    {
        return $this->isAllowedTableHtmlAttr($name)
            || in_array($name, ['align', 'bordercolor'], true);
    }

    private function isAllowedTableElementPassthroughAttr(string $name): bool
    {
        return str_starts_with($name, 'data-')
            || str_starts_with($name, 'aria-')
            || $this->isAllowedSafeGlobalHtmlAttr($name)
            || in_array($name, ['id', 'class', 'dir', 'role', 'summary', 'title'], true);
    }

    private function isAllowedTableCaptionHtmlAttr(string $name): bool
    {
        if (preg_match('/^[a-z][a-z0-9_.:-]*$/', $name) !== 1 || str_starts_with($name, 'on')) {
            return false;
        }

        return str_starts_with($name, 'data-')
            || str_starts_with($name, 'aria-')
            || $this->isAllowedSafeGlobalHtmlAttr($name)
            || in_array($name, ['class', 'dir', 'id', 'lang', 'role', 'style', 'title', 'translate', 'xml:lang'], true);
    }

    private function isAllowedTableColumnHtmlAttr(string $name): bool
    {
        if (preg_match('/^[a-z][a-z0-9_.:-]*$/', $name) !== 1 || str_starts_with($name, 'on')) {
            return false;
        }

        return str_starts_with($name, 'data-')
            || str_starts_with($name, 'aria-')
            || $this->isAllowedSafeGlobalHtmlAttr($name)
            || in_array($name, ['align', 'bgcolor', 'char', 'charoff', 'class', 'dir', 'id', 'lang', 'role', 'title', 'translate', 'valign', 'xml:lang'], true);
    }

    private function renderCodeBlock(AstNode $node): string
    {
        if ((bool) ($this->options['syntaxHighlighterCodeBlocks'] ?? false)) {
            return $this->renderSyntaxHighlighterCodeBlock($node);
        }

        if ((bool) ($this->options['highlightCodeBlocks'] ?? false)) {
            $highlighted = (new SyntaxHighlighter())->highlightCodeBlock(
                $node,
                (string) ($this->options['highlightStyle'] ?? 'pygments')
            );

            return '<!-- wp:html -->'
                . "\n" . '<style data-pandoc-highlight-style="' . $this->esc((string) $highlighted['style']) . '">' . (string) $highlighted['css'] . '</style>'
                . "\n" . (string) $highlighted['html']
                . "\n" . '<!-- /wp:html -->';
        }

        return '<!-- wp:code' . $this->codeBlockCommentAttrs($node) . ' -->'
            . "\n" . $this->renderCodeBlockHtml($node)
            . "\n" . '<!-- /wp:code -->';
    }

    private function renderSyntaxHighlighterCodeBlock(AstNode $node): string
    {
        $language = $this->codeBlockLanguage($node);
        $codeAttrs = $language === '' ? '' : ' class="language-' . $this->esc($language) . '"';
        $preAttrs = $language === '' ? '' : ' data-language="' . $this->esc($language) . '"';
        $preAttrs .= $this->renderCodeBlockPreAttrs($node);

        return '<!-- wp:syntaxhighlighter/code' . $this->codeBlockCommentAttrs($node) . ' -->'
            . "\n" . '<pre class="wp-block-syntaxhighlighter-code"' . $preAttrs . '><code' . $codeAttrs . '>' . $this->esc((string) $node->attr('text', '')) . '</code></pre>'
            . "\n" . '<!-- /wp:syntaxhighlighter/code -->';
    }

    private function renderCodeBlockHtml(AstNode $node): string
    {
        $classes = $node->attr('classes', []);
        $language = $this->codeBlockLanguage($node);
        $codeAttrs = $language === '' ? '' : ' class="language-' . $this->esc($language) . '"';
        $preClasses = $this->codeBlockPreClasses($classes);
        $preAttrs = $this->renderCodeBlockPreAttrs($node);

        return '<pre class="' . $this->esc(implode(' ', $preClasses)) . '"' . $preAttrs . '><code' . $codeAttrs . '>' . $this->esc((string) $node->attr('text', '')) . '</code></pre>';
    }

    private function codeBlockCommentAttrs(AstNode $node): string
    {
        $language = $this->codeBlockLanguage($node);
        if ($language === '') {
            return '';
        }

        return ' ' . json_encode(['language' => $language], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    private function codeBlockLanguage(AstNode $node): string
    {
        $language = SyntaxHighlighter::languageFromCodeBlock($node);
        $normalized = SyntaxHighlighter::normalizeLanguage($language);

        return $this->sanitizeCodeClass($normalized ?? $language);
    }

    /**
     * @return list<string>
     */
    private function codeBlockPreClasses(mixed $classes): array
    {
        $preClasses = ['wp-block-code'];
        if (!is_array($classes)) {
            return $preClasses;
        }

        $sanitized = [];
        foreach ($classes as $class) {
            $sanitized[] = $this->sanitizeCodeClass((string) $class);
        }
        $preserveLanguageClass = array_intersect(['numberLines', 'lineAnchors'], $sanitized) !== [];

        foreach ($sanitized as $index => $class) {
            if ($class === '' || $class === 'literate') {
                continue;
            }
            if ($index === 0 && !$preserveLanguageClass) {
                continue;
            }
            if ($class !== '' && !in_array($class, $preClasses, true)) {
                $preClasses[] = $class;
            }
        }

        return $preClasses;
    }

    private function renderCodeBlockPreAttrs(AstNode $node): string
    {
        $attrs = '';
        $renderedSourceId = false;
        foreach ($this->inlineHtmlAttributes($node) as $name => $value) {
            $name = strtolower((string) $name);
            if ($name === 'class') {
                continue;
            }

            if ($name === 'id') {
                $sourceId = (string) $value;
                $htmlId = $this->htmlFragmentIdNeedsNormalization($sourceId)
                    ? $this->normalizeHtmlFragmentId($sourceId)
                    : $sourceId;
                if ($htmlId !== '') {
                    $attrs .= ' id="' . $this->esc($htmlId) . '"';
                }
                if ($htmlId !== $sourceId) {
                    $attrs .= ' data-pandoc-source-id="' . $this->esc($sourceId) . '"';
                    $renderedSourceId = true;
                }
                continue;
            }

            if ($name === 'custom-style') {
                $attrs .= $this->renderCustomStyleDataAttr((string) $value);
                continue;
            }

            if ($name === 'data-pandoc-source-id' && $renderedSourceId) {
                continue;
            }

            if (!$this->isAllowedBlockHtmlAttr($name)) {
                continue;
            }

            $attrs .= ' ' . $name . '="' . $this->esc((string) $value) . '"';
        }

        return $attrs;
    }

    private function renderRawTexBlockHtml(AstNode $node): string
    {
        return '<pre class="wp-block-code"><code class="language-tex">'
            . $this->esc((string) $node->attr('tex', $node->attr('text', '')))
            . '</code></pre>';
    }

    private function renderRawFormatBlockHtml(AstNode $node): string
    {
        $format = (string) $node->attr('format', 'raw');
        $formatToken = $this->rawFormatToken($format);
        $language = $formatToken === 'openxml' ? 'xml' : $formatToken;

        return '<pre class="wp-block-code pandoc-raw-' . $this->esc($formatToken) . '" data-pandoc-raw-format="' . $this->esc($format) . '"><code class="language-' . $this->esc($language) . '">'
            . $this->esc((string) $node->attr('text', ''))
            . '</code></pre>';
    }

    private function renderRawInlineNode(AstNode $node): string
    {
        $format = (string) $node->attr('format', 'raw');
        $text = (string) $node->attr('text', '');
        $formatToken = $this->rawFormatToken($format);
        $rawFamily = MarkdownFormatProfile::rawFamily($format);

        if ($rawFamily === 'html') {
            return $text;
        }

        if ($rawFamily === 'tex') {
            return '<span class="pandoc-raw-tex">' . $this->esc($text) . '</span>';
        }

        if (strtolower($format) === 'openxml') {
            $bookmark = $this->parseOpenXmlBookmark($text);
            if ($bookmark !== null) {
                $attrs = ' class="pandoc-openxml-bookmark-' . $this->esc($bookmark['kind']) . '"'
                    . ' data-pandoc-raw-format="openxml"';
                if ($bookmark['id'] !== '') {
                    $attrs .= ' data-pandoc-bookmark-id="' . $this->esc($bookmark['id']) . '"';
                }
                if ($bookmark['name'] !== '') {
                    $attrs .= ' data-pandoc-bookmark-name="' . $this->esc($bookmark['name']) . '"';
                }

                return '<span' . $attrs . '></span>';
            }
        }

        return '<span class="pandoc-raw-' . $this->esc($formatToken) . '" data-pandoc-raw-format="' . $this->esc($format) . '">'
            . $this->esc($text)
            . '</span>';
    }

    private function rawFormatToken(string $format): string
    {
        $token = $this->sanitizeCodeClass(strtolower(str_replace(['.', ':'], '-', $format)));

        return $token === '' ? 'raw' : $token;
    }

    /**
     * @return array{kind:string, id:string, name:string}|null
     */
    private function parseOpenXmlBookmark(string $xml): ?array
    {
        if (preg_match('/^<w:bookmark(Start|End)\b([^>]*)\/>$/u', trim($xml), $match) !== 1) {
            return null;
        }

        $attrs = $this->parseOpenXmlAttributes($match[2]);

        return [
            'kind' => strtolower($match[1]),
            'id' => (string) ($attrs['id'] ?? ''),
            'name' => (string) ($attrs['name'] ?? ''),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function parseOpenXmlAttributes(string $source): array
    {
        preg_match_all('/\bw:([A-Za-z0-9_.:-]+)="([^"]*)"/u', $source, $matches, PREG_SET_ORDER);
        $attrs = [];
        foreach ($matches as $match) {
            $attrs[$match[1]] = html_entity_decode($match[2], ENT_QUOTES | ENT_XML1, 'UTF-8');
        }

        return $attrs;
    }

    private function renderFigureBlock(AstNode $node): string
    {
        if (!$this->shouldRenderFigureAsImageBlock($node)) {
            return $this->renderMixedFigureBlock($node);
        }

        return '<!-- wp:image -->'
            . "\n" . $this->renderFigureHtml($node)
            . "\n" . '<!-- /wp:image -->';
    }

    private function renderMixedFigureBlock(AstNode $node): string
    {
        $blocks = $this->renderBlocksAsNativeBlocks($node->children, true);
        $caption = trim((string) $node->attr('caption', ''));
        if ($caption === '') {
            $caption = $this->figureCaptionText($node);
        }
        if ($caption !== '') {
            $blocks .= ($blocks === '' ? '' : "\n\n")
                . '<!-- wp:paragraph -->'
                . "\n" . '<p class="pandoc-figure-caption">' . $this->esc($caption) . '</p>'
                . "\n" . '<!-- /wp:paragraph -->';
        }

        return $this->renderGroupBlock($node, ['pandoc-figure'], $blocks);
    }

    private function shouldRenderFigureAsImageBlock(AstNode $node): bool
    {
        if ($this->isImageOnlyFigure($node)) {
            return true;
        }

        $image = $this->firstFigureImage($node);
        if (!$image instanceof AstNode || !$this->hasFigureCaptionContent($node)) {
            return false;
        }

        if ((string) $image->attr('alt', '') === '' && $this->figureBodyTextWithoutImages($node) !== '') {
            return true;
        }

        $htmlAttributes = $node->attr('htmlAttributes', []);

        return is_array($htmlAttributes) && $htmlAttributes !== [];
    }

    private function isImageOnlyFigure(AstNode $node): bool
    {
        if (count($node->children) !== 1) {
            return false;
        }

        $child = $node->children[0];
        if ($child->type === 'image') {
            return true;
        }

        if (!in_array($child->type, ['plain', 'paragraph'], true) || count($child->children) !== 1) {
            return false;
        }

        return $child->children[0]->type === 'image';
    }

    private function hasFigureCaptionContent(AstNode $node): bool
    {
        if (trim((string) $node->attr('caption', '')) !== '') {
            return true;
        }

        $inlines = $node->attr('captionInlines', null);

        return is_array($inlines) && $inlines !== [];
    }

    private function renderMixedFigureHtml(AstNode $node): string
    {
        $html = '<figure' . $this->renderBlockHtmlAttrs($node) . '>' . $this->renderFigureBlocksAsHtml($node->children);
        $caption = trim((string) $node->attr('caption', ''));
        if ($caption === '') {
            $caption = $this->figureCaptionText($node);
        }
        if ($caption !== '') {
            $html .= '<figcaption>' . $this->esc($caption) . '</figcaption>';
        }

        return $html . '</figure>';
    }

    /**
     * @param list<AstNode> $blocks
     */
    private function renderFigureBlocksAsHtml(array $blocks): string
    {
        $html = '';
        foreach ($blocks as $block) {
            if ($this->shouldSkipEmptyParagraphLikeBlock($block)) {
                continue;
            }
            if ($block->type === 'plain') {
                $html .= '<p' . $this->renderBlockHtmlAttrs($block) . '>' . $this->renderInlines($block) . '</p>';
                continue;
            }
            if ($block->type === 'code_block') {
                $html .= $this->renderFigureCodeBlockHtml($block);
                continue;
            }

            $html .= $this->renderBlocksAsHtml([$block]);
        }

        return $html;
    }

    private function renderFigureCodeBlockHtml(AstNode $node): string
    {
        $language = $this->codeBlockLanguage($node);
        $codeAttrs = $language === '' ? '' : ' class="language-' . $this->esc($language) . '"';

        return '<pre class="wp-block-code"' . $this->renderCodeBlockPreAttrs($node) . '><code' . $codeAttrs . '>'
            . $this->esc((string) $node->attr('text', ''))
            . '</code></pre>';
    }

    private function renderFigureHtml(AstNode $node): string
    {
        $image = $this->firstFigureImage($node);
        if (!$image instanceof AstNode) {
            $image = new AstNode('image', [
                'url' => '',
                'alt' => (string) $node->attr('caption', ''),
            ]);
        } else {
            $image = $this->imageWithFigureAltFallback($image, $node);
        }

        $html = '<figure' . $this->renderImageFigureAttrs($node) . '>' . $this->renderImageHtml($image);
        $caption = $this->renderFigureCaption($node, $image);
        if ($caption !== '') {
            $html .= '<figcaption>' . $caption . '</figcaption>';
        }

        return $html . '</figure>';
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

    private function imageWithFigureAltFallback(AstNode $image, AstNode $figure): AstNode
    {
        if ((string) $image->attr('alt', '') !== '') {
            return $image;
        }

        $alt = $this->figureBodyTextWithoutImages($figure);
        if ($alt !== '') {
            return new AstNode('image', array_replace($image->attrs, ['alt' => $alt]), $image->children);
        }

        $alt = $this->figureCaptionText($figure);
        if ($alt === '') {
            return $image;
        }

        $attrs = array_replace($image->attrs, ['alt' => $alt]);
        $sourceAttrs = $attrs['attributes'] ?? [];
        if (!is_array($sourceAttrs)) {
            $sourceAttrs = [];
        }
        if (!isset($sourceAttrs['data-pandoc-alt-source'])) {
            $sourceAttrs['data-pandoc-alt-source'] = 'figure-caption';
        }
        $attrs['attributes'] = $sourceAttrs;

        return new AstNode('image', $attrs, $image->children);
    }

    private function figureBodyTextWithoutImages(AstNode $figure): string
    {
        $parts = [];
        foreach ($figure->children as $child) {
            if ($this->nodeContainsImage($child)) {
                continue;
            }

            $text = $this->metadataNodeText($child);
            if ($text !== '') {
                $parts[] = $text;
            }
        }

        return trim(implode(' ', $parts));
    }

    private function figureCaptionText(AstNode $figure): string
    {
        $inlines = $figure->attr('captionInlines', null);
        if (is_array($inlines)) {
            $caption = $this->metadataInlinesText($inlines);
            if ($caption !== '') {
                return $caption;
            }
        }

        return trim((string) $figure->attr('caption', ''));
    }

    private function nodeContainsImage(AstNode $node): bool
    {
        if ($node->type === 'image') {
            return true;
        }

        foreach ($node->children as $child) {
            if ($this->nodeContainsImage($child)) {
                return true;
            }
        }

        return false;
    }

    private function renderFigureCaption(AstNode $node, AstNode $image): string
    {
        $inlines = $node->attr('captionInlines', null);
        if (is_array($inlines)) {
            $html = '';
            foreach ($inlines as $inline) {
                if (!$inline instanceof AstNode) {
                    $html = '';
                    break;
                }

                $html .= $this->renderInlineNode($inline);
            }
            if ($html !== '') {
                return $html;
            }
        }

        $caption = (string) $node->attr('caption', $image->attr('alt', ''));

        return $caption === '' ? '' : $this->esc($caption);
    }

    private function renderImageFigureAttrs(AstNode $node): string
    {
        $attrs = $this->renderBlockHtmlAttrsWithClasses($node, ['wp-block-image'], ['class', 'id', 'role', 'title'], $this->imageFigureAttrSkipNames($node));
        $shortCaption = (string) $node->attr('shortCaption', '');
        if ($shortCaption !== '') {
            $attrs .= ' data-pandoc-short-caption="' . $this->esc($shortCaption) . '"';
        }

        $attributes = $node->attr('attributes', []);
        if (
            is_array($attributes)
            && isset($attributes['latex-placement'])
            && !isset($attributes['data-pandoc-latex-placement'])
        ) {
            $attrs .= ' data-pandoc-latex-placement="' . $this->esc((string) $attributes['latex-placement']) . '"';
        }

        return $attrs;
    }

    /**
     * @return list<string>
     */
    private function imageFigureAttrSkipNames(AstNode $node): array
    {
        $htmlAttributes = $node->attr('htmlAttributes', []);
        $attributes = $node->attr('attributes', []);
        if (
            is_array($htmlAttributes)
            && is_array($attributes)
            && array_key_exists('data-review', $htmlAttributes)
            && array_key_exists('review', $attributes)
            && (string) $htmlAttributes['data-review'] === (string) $attributes['review']
        ) {
            return ['data-review'];
        }

        return [];
    }

    private function renderImageHtml(AstNode $node): string
    {
        $attrs = ' src="' . $this->esc((string) $node->attr('url', '')) . '"'
            . ' alt="' . $this->esc((string) $node->attr('alt', '')) . '"';
        $title = (string) $node->attr('title', '');
        if ($title !== '') {
            $attrs .= ' title="' . $this->esc($title) . '"';
        }

        $sourceAttrs = $this->inlineHtmlAttributes($node);
        $dimensionAttrs = $sourceAttrs;
        foreach (['width', 'height'] as $dimension) {
            if (!isset($dimensionAttrs[$dimension])) {
                $dimensionValue = trim((string) $node->attr($dimension, ''));
                if ($dimensionValue !== '') {
                    $dimensionAttrs[$dimension] = $dimensionValue;
                }
            }
        }
        $sourceFormat = $this->unsupportedWordPressImageSourceFormat((string) $node->attr('url', ''));
        $attrs .= $this->renderImageDimensionAttrs($dimensionAttrs);
        if ($sourceFormat !== '' && !isset($sourceAttrs['data-pandoc-source-format'])) {
            $attrs .= ' data-pandoc-source-format="' . $this->esc($sourceFormat) . '"';
        }
        foreach ($sourceAttrs as $name => $value) {
            $name = strtolower((string) $name);
            if (
                in_array($name, ['src', 'href', 'alt', 'title', 'width', 'height', 'style', 'data-pandoc-source-format'], true)
                || !$this->isAllowedImageHtmlAttr($name)
            ) {
                continue;
            }

            $attrs .= ' ' . $name . '="' . $this->esc((string) $value) . '"';
        }

        return '<img' . $attrs . '/>';
    }

    private function unsupportedWordPressImageSourceFormat(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH);
        if (!is_string($path) || $path === '') {
            $path = $url;
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if (!in_array($extension, ['emf', 'wmf', 'tif', 'tiff', 'heic', 'heif'], true)) {
            return '';
        }

        return $extension;
    }

    /**
     * @param array<string, mixed> $sourceAttrs
     */
    private function renderImageDimensionAttrs(array $sourceAttrs): string
    {
        $attrs = '';
        $styles = [];
        foreach (['width', 'height'] as $dimension) {
            $value = trim((string) ($sourceAttrs[$dimension] ?? ''));
            if ($value === '') {
                continue;
            }

            if (preg_match('/^[1-9][0-9]{0,4}$/', $value) === 1) {
                $attrs .= ' ' . $dimension . '="' . $this->esc((string) (int) $value) . '"';
                continue;
            }

            $attrs .= ' data-pandoc-' . $dimension . '="' . $this->esc($value) . '"';
            $cssDimension = $this->safeCssDimension($value);
            if ($cssDimension !== '') {
                $styles[] = $dimension . ':' . $cssDimension;
            }
        }

        if ($styles !== []) {
            $attrs .= ' style="' . $this->esc(implode('; ', $styles)) . '"';
        }

        return $attrs;
    }

    private function safeCssDimension(string $value): string
    {
        $value = trim($value);
        if ($value === '0') {
            return '0';
        }

        if (preg_match('/^(?:\d+(?:\.\d+)?|\.\d+)(?:%|px|em|rem|in|cm|mm|pt|pc|vw|vh|vmin|vmax)$/i', $value) === 1) {
            return $value;
        }

        return '';
    }

    private function renderBlockQuote(AstNode $node): string
    {
        return '<!-- wp:quote -->'
            . "\n" . '<blockquote' . $this->renderBlockHtmlAttrsWithClasses($node, ['wp-block-quote']) . '>' . $this->renderBlocksAsHtml($node->children, true) . '</blockquote>'
            . "\n" . '<!-- /wp:quote -->';
    }

    private function renderLineBlockBlock(AstNode $node): string
    {
        $lines = [];
        foreach ($node->children as $line) {
            if ($line->type === 'line') {
                $lines[] = $this->renderInlines($line);
            }
        }

        return '<!-- wp:verse -->'
            . "\n" . '<pre class="wp-block-verse">' . implode("\n", $lines) . '</pre>'
            . "\n" . '<!-- /wp:verse -->';
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
        if ($this->hasClass($node, 'linegroup')) {
            return $this->renderLineGroupBlock($node);
        }

        return $this->renderGroupBlock(
            $node,
            [],
            $this->renderBlocksAsNativeBlocks($node->children, !$this->divContainsOnlyPlainImage($node))
        );
    }

    private function isLineGroupDiv(AstNode $node): bool
    {
        return $this->hasClass($node, 'linegroup');
    }

    private function hasClass(AstNode $node, string $class): bool
    {
        return in_array($class, $this->nodeClassList($node), true);
    }

    /**
     * @return list<string>
     */
    private function nodeClassList(AstNode $node): array
    {
        $classes = [];
        $nodeClasses = $node->attr('classes', []);
        if (is_array($nodeClasses)) {
            foreach ($nodeClasses as $class) {
                $class = trim((string) $class);
                if ($class !== '') {
                    $classes[] = $class;
                }
            }
        }

        $htmlAttributes = $this->inlineHtmlAttributes($node);
        $htmlClass = trim((string) ($htmlAttributes['class'] ?? ''));
        if ($htmlClass !== '') {
            array_push($classes, ...preg_split('/\s+/', $htmlClass, -1, PREG_SPLIT_NO_EMPTY));
        }

        return array_values(array_unique($classes));
    }

    private function renderLineGroupBlock(AstNode $node): string
    {
        $lineRuns = [];
        $lineRun = [];
        $hasNonLineChildren = false;
        foreach ($node->children as $child) {
            if ($this->isLineGroupLineNode($child)) {
                $lineRun[] = $child;
                continue;
            }

            if ($lineRun !== []) {
                $lineRuns[] = $lineRun;
                $lineRun = [];
            }
            $lineRuns[] = [$child];
            $hasNonLineChildren = true;
        }
        if ($lineRun !== []) {
            $lineRuns[] = $lineRun;
        }

        if (!$hasNonLineChildren) {
            return $this->renderLineGroupParagraphBlock($node->children, $node);
        }

        $innerBlocks = [];
        foreach ($lineRuns as $run) {
            if (count($run) === 1 && !$this->isLineGroupLineNode($run[0])) {
                $innerBlocks[] = $this->renderBlocksAsNativeBlocks($run, true);
            } else {
                $innerBlocks[] = $this->renderLineGroupParagraphBlock($run);
            }
        }

        return $this->renderGroupBlock($node, [], implode("\n\n", array_filter($innerBlocks, static fn (string $block): bool => $block !== '')));
    }

    private function isLineGroupLineNode(AstNode $node): bool
    {
        if (in_array($node->type, ['paragraph', 'plain'], true)) {
            return true;
        }

        return $node->type === 'div'
            && !$this->isLineGroupDiv($node)
            && count($node->children) === 1
            && in_array($node->children[0]->type, ['paragraph', 'plain'], true);
    }

    /**
     * @param list<AstNode> $lines
     */
    private function renderLineGroupParagraphBlock(array $lines, ?AstNode $container = null): string
    {
        $renderedLines = [];
        foreach ($lines as $line) {
            $renderedLines[] = $this->renderLineGroupLine($line);
        }

        return '<!-- wp:paragraph -->'
            . "\n" . '<p' . ($container instanceof AstNode ? $this->renderBlockHtmlAttrs($container) : '') . '>' . implode('<br/>', $renderedLines) . '</p>'
            . "\n" . '<!-- /wp:paragraph -->';
    }

    private function renderLineGroupLine(AstNode $line): string
    {
        $lineBlocks = $line->type === 'div' ? $line->children : [$line];
        if (count($lineBlocks) === 1 && in_array($lineBlocks[0]->type, ['paragraph', 'plain'], true)) {
            $html = $this->renderInlines($lineBlocks[0]);
        } else {
            $html = $this->renderBlocksAsHtml($lineBlocks, true);
        }

        if ($line->type !== 'div') {
            return $html;
        }

        $attrs = $this->renderBlockHtmlAttrs($line);
        return $attrs === '' ? $html : '<span' . $attrs . '>' . $html . '</span>';
    }

    private function divContainsOnlyPlainImage(AstNode $node): bool
    {
        if (count($node->children) !== 1) {
            return false;
        }

        $child = $node->children[0];
        if ($child->type !== 'plain' || count($child->children) !== 1) {
            return false;
        }

        return $child->children[0]->type === 'image';
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
            if (!$this->isInlineNode($child)) {
                $html .= $this->renderBlocksAsHtml([$child]);
                continue;
            }

            $html .= $this->renderInlineNode($child);
        }

        return $html;
    }

    /**
     * @param list<AstNode> $blocks
     */
    private function renderBlocksAsNativeBlocks(array $blocks, bool $wrapPlainBlocks = false): string
    {
        $renderedBlocks = [];
        foreach ($blocks as $block) {
            if ($this->shouldSkipEmptyParagraphLikeBlock($block)) {
                continue;
            }

            if ($block->type === 'paragraph') {
                $renderedBlocks[] = $this->renderParagraphBlock($block);
                continue;
            }
            if ($block->type === 'plain') {
                if (count($block->children) === 1 && $block->children[0]->type === 'image') {
                    $renderedBlocks[] = $this->renderParagraphImageBlock($block->children[0]);
                    continue;
                }
                $renderedBlocks[] = '<!-- wp:paragraph -->'
                    . "\n" . '<p' . $this->renderBlockHtmlAttrs($block) . '>' . $this->renderInlines($block) . '</p>'
                    . "\n" . '<!-- /wp:paragraph -->';
                continue;
            }
            if ($block->type === 'heading') {
                $level = (int) $block->attr('level', 2);
                $renderedBlocks[] = '<!-- wp:heading {"level":' . $level . '} -->'
                    . "\n" . '<h' . $level . $this->renderHeadingAttrs($block) . '>' . $this->renderInlines($block) . '</h' . $level . '>'
                    . "\n" . '<!-- /wp:heading -->';
                continue;
            }
            if ($block->type === 'bullet_list') {
                $renderedBlocks[] = $this->renderList($block, false);
                continue;
            }
            if ($block->type === 'ordered_list') {
                $renderedBlocks[] = $this->renderList($block, true);
                continue;
            }
            if ($block->type === 'definition_list') {
                $renderedBlocks[] = $this->renderDefinitionList($block);
                continue;
            }
            if ($block->type === 'table') {
                $renderedBlocks[] = $this->renderTable($block);
                continue;
            }
            if ($block->type === 'code_block') {
                $renderedBlocks[] = $this->renderCodeBlock($block);
                continue;
            }
            if ($block->type === 'figure') {
                $renderedBlocks[] = $this->renderFigureBlock($block);
                continue;
            }
            if ($block->type === 'image') {
                $renderedBlocks[] = $this->renderParagraphImageBlock($block);
                continue;
            }
            if ($block->type === 'blockquote') {
                $renderedBlocks[] = $this->renderBlockQuote($block);
                continue;
            }
            if ($block->type === 'line_block') {
                $renderedBlocks[] = $this->renderLineBlockBlock($block);
                continue;
            }
            if ($block->type === 'horizontal_rule') {
                $renderedBlocks[] = $this->renderHorizontalRule();
                continue;
            }
            if ($block->type === 'raw_html') {
                $renderedBlocks[] = $this->renderRawHtmlBlock($block);
                continue;
            }
            if ($block->type === 'raw_tex') {
                $renderedBlocks[] = $this->renderRawTexBlock($block);
                continue;
            }
            if ($block->type === 'raw_block') {
                $renderedBlocks[] = $this->renderRawFormatBlock($block);
                continue;
            }
            if ($block->type === 'div') {
                $renderedBlocks[] = $this->renderDivBlock($block);
                continue;
            }
            if ($this->isInlineNode($block)) {
                $renderedBlocks[] = '<!-- wp:paragraph -->'
                    . "\n" . '<p>' . $this->renderInlineNode($block) . '</p>'
                    . "\n" . '<!-- /wp:paragraph -->';
                continue;
            }

            $html = $this->renderBlocksAsHtml([$block], $wrapPlainBlocks);
            if ($html !== '') {
                $renderedBlocks[] = '<!-- wp:html -->' . "\n" . $html . "\n" . '<!-- /wp:html -->';
            }
        }

        return implode("\n\n", $renderedBlocks);
    }

    /**
     * @param list<string> $classes
     */
    private function renderGroupBlock(AstNode $node, array $classes, string $innerBlocks): string
    {
        $attrs = $this->renderBlockHtmlAttrsWithClasses($node, array_merge(['wp-block-group'], $classes));

        return '<!-- wp:group -->'
            . "\n" . '<div' . $attrs . '>'
            . ($innerBlocks === '' ? '' : "\n" . $innerBlocks . "\n")
            . '</div>'
            . "\n" . '<!-- /wp:group -->';
    }

    /**
     * @param list<AstNode> $blocks
     */
    private function renderBlocksAsHtml(array $blocks, bool $wrapPlainBlocks = false): string
    {
        $html = '';
        foreach ($blocks as $block) {
            if ($this->shouldSkipEmptyParagraphLikeBlock($block)) {
                continue;
            }

            if ($block->type === 'paragraph') {
                $html .= '<p' . $this->renderBlockHtmlAttrs($block) . '>' . $this->renderInlines($block) . '</p>';
                continue;
            }
            if ($block->type === 'plain') {
                $rendered = $this->renderInlines($block);
                $html .= $wrapPlainBlocks
                    ? '<p' . $this->renderBlockHtmlAttrs($block) . '>' . $rendered . '</p>'
                    : $rendered;
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
                $html .= $this->isImageOnlyFigure($block)
                    ? $this->renderFigureHtml($block)
                    : $this->renderMixedFigureHtml($block);
                continue;
            }
            if ($block->type === 'image') {
                $html .= $this->renderImageHtml($block);
                continue;
            }
            if ($block->type === 'blockquote') {
                $html .= '<blockquote>' . $this->renderBlocksAsHtml($block->children, $wrapPlainBlocks) . '</blockquote>';
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
            if ($block->type === 'raw_block') {
                $html .= $this->renderRawFormatBlockHtml($block);
                continue;
            }
            if ($block->type === 'div') {
                $html .= '<div' . $this->renderDivAttrs($block) . '>' . $this->renderBlocksAsHtml($block->children, !$this->divContainsOnlyPlainImage($block)) . '</div>';
            }
        }

        return $html;
    }

    private function renderInlines(AstNode $node): string
    {
        if ($node->children === []) {
            return $this->esc((string) $node->attr('text', ''));
        }

        return $this->renderInlineNodes($node->children);
    }

    /**
     * @param list<AstNode> $nodes
     */
    private function renderInlineNodes(array $nodes): string
    {
        $html = '';
        foreach ($nodes as $child) {
            $html .= $this->renderInlineNode($child);
        }

        return $html;
    }

    /**
     * @param list<AstNode> $nodes
     */
    private function renderInlineNodesWithoutLeadingTaskGlyph(array $nodes): string
    {
        $html = '';
        $stripGlyph = true;
        $stripFollowingSpace = false;

        foreach ($nodes as $node) {
            if ($stripGlyph && $node->type === 'text') {
                $text = (string) $node->attr('text', '');
                $stripped = preg_replace('/^(?:\x{2610}|\x{2612})/u', '', $text, 1);
                if ($stripped !== null && $stripped !== $text) {
                    $stripGlyph = false;
                    $stripFollowingSpace = $stripped === '';
                    if ($stripped !== '') {
                        $html .= $this->esc(ltrim($stripped));
                    }
                    continue;
                }
            }

            if ($stripFollowingSpace && $node->type === 'text') {
                $text = ltrim((string) $node->attr('text', ''));
                $stripFollowingSpace = false;
                if ($text === '') {
                    continue;
                }

                $html .= $this->esc($text);
                continue;
            }

            if ($stripFollowingSpace && $node->type === 'space') {
                $stripFollowingSpace = false;
                continue;
            }

            $stripGlyph = false;
            $stripFollowingSpace = false;
            $html .= $this->renderInlineNode($node);
        }

        return $html;
    }

    private function renderInlineNode(AstNode $node): string
    {
        $rendered = $node->attr('rendered', null);
        if (
            ($node->type === 'citation' || $node->type === 'citation_group')
            && is_scalar($rendered)
            && (string) $rendered !== ''
        ) {
            $inlineParts = $node->attr('cslInlineParts', []);
            if (is_array($inlineParts)) {
                $inlineHtml = $this->renderCslInlineParts($inlineParts);
                if ($inlineHtml !== '') {
                    return $inlineHtml;
                }
            }

            return $this->esc((string) $rendered);
        }

        return match ($node->type) {
            'text' => $this->esc((string) $node->attr('text', '')),
            'space' => ' ',
            'emph' => '<em>' . $this->renderInlines($node) . '</em>',
            'strong' => '<strong>' . $this->renderInlines($node) . '</strong>',
            'small_caps' => '<span style="font-variant:small-caps">' . $this->renderInlines($node) . '</span>',
            'underline' => '<u' . $this->renderInlineSpanAttrs($node) . '>' . $this->renderInlines($node) . '</u>',
            'strikeout' => '<del>' . $this->renderInlines($node) . '</del>',
            'superscript' => '<sup>' . $this->renderInlines($node) . '</sup>',
            'subscript' => '<sub>' . $this->renderInlines($node) . '</sub>',
            'softbreak' => "\n",
            'linebreak' => '<br/>',
            'span' => $this->renderSpanInline($node),
            'quoted' => $this->renderQuotedInline($node),
            'math' => $this->renderMathInline($node),
            'raw_tex', 'raw_tex_inline' => '<span class="pandoc-raw-tex">' . $this->esc((string) $node->attr('tex', $node->attr('text', ''))) . '</span>',
            'raw_html_inline' => (string) $node->attr('html', ''),
            'raw_inline' => $this->renderRawInlineNode($node),
            'code' => '<code' . $this->renderInlineCodeAttrs($node) . '>' . $this->esc((string) $node->attr('text', '')) . '</code>',
            'link' => $this->renderLinkInline($node),
            'image' => $this->renderImageHtml($node),
            'note' => $this->renderNoteReference($node),
            'citation', 'citation_group' => $this->renderCitationInline($node),
            default => $this->renderInlines($node),
        };
    }

    private function renderCitationInline(AstNode $node): string
    {
        $citations = $this->citationEntries($node);
        $ids = [];
        foreach ($citations as $citation) {
            $id = (string) ($citation['id'] ?? '');
            if ($id !== '') {
                $ids[] = $id;
            }
        }

        $attrs = ' class="pandoc-citation"';
        if (count($ids) === 1) {
            $attrs .= ' data-pandoc-citation-id="' . $this->esc($ids[0]) . '"';
        }
        $attrs .= ' data-pandoc-citation-count="' . count($citations) . '"';
        if ($ids !== []) {
            $attrs .= ' data-pandoc-citation-ids="' . $this->esc(json_encode($ids, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) . '"';
        }

        $payload = json_encode($citations, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $attrs .= ' data-pandoc-citations="' . $this->esc($payload) . '"';

        $display = $this->renderCitationInlineDisplay($node, $citations, $ids);

        return '<span' . $attrs . '>' . $display . '</span>';
    }

    /**
     * @param list<array{id:string, mode:string, noteNum:int, hash:int, prefix:string, suffix:string}> $citations
     * @param list<string> $ids
     */
    private function renderCitationInlineDisplay(AstNode $node, array $citations, array $ids): string
    {
        if ($node->type === 'citation_group' && $citations !== []) {
            return $this->esc($this->renderCitationMarkdownDisplay($citations));
        }

        $display = $this->renderInlines($node);
        if ($display !== '') {
            return $display;
        }

        $text = (string) $node->attr('text', '');
        if ($text !== '') {
            return $this->esc($text);
        }

        if ($citations !== []) {
            return $this->esc($this->renderCitationMarkdownDisplay($citations));
        }

        return $ids === []
            ? ''
            : $this->esc('[' . implode('; ', array_map(static fn (string $id): string => '@' . $id, $ids)) . ']');
    }

    /**
     * @param list<array<string, mixed>> $parts
     */
    private function renderCslDisplayParts(array $parts): string
    {
        $html = '';
        foreach ($parts as $part) {
            if (!is_array($part)) {
                continue;
            }

            $display = strtolower(trim((string) ($part['display'] ?? '')));
            if (!in_array($display, ['left-margin', 'right-inline', 'indent', 'block'], true)) {
                continue;
            }

            $text = (string) ($part['text'] ?? '');
            if ($text === '') {
                continue;
            }

            $formatting = $part['formatting'] ?? [];
            $html .= '<div'
                . $this->renderCslFormattingAttrs('csl-' . $display, is_array($formatting) ? $formatting : [])
                . '>'
                . $this->esc($text)
                . '</div>';
        }

        return $html === '' ? '' : '<div class="csl-entry">' . $html . '</div>';
    }

    /**
     * @param list<array<string, mixed>> $parts
     */
    private function renderCslInlineParts(array $parts): string
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

            $formatting = $part['formatting'] ?? [];
            if (!is_array($formatting) || $formatting === []) {
                $html .= $this->esc($text);
                continue;
            }

            $html .= '<span' . $this->renderCslFormattingAttrs('', $formatting) . '>' . $this->esc($text) . '</span>';
        }

        return $html;
    }

    /**
     * @param array<string, mixed> $formatting
     */
    private function renderCslFormattingAttrs(string $baseClass, array $formatting): string
    {
        $classes = $baseClass === '' ? [] : [$baseClass];
        $styles = [];
        foreach ($formatting as $name => $value) {
            if (!is_scalar($value)) {
                continue;
            }

            $value = strtolower(trim((string) $value));
            if ($value === '') {
                continue;
            }

            if ($name === 'fontStyle' && in_array($value, ['normal', 'italic', 'oblique'], true)) {
                $classes[] = 'csl-font-style-' . $value;
                $styles[] = 'font-style:' . $value;
                continue;
            }

            if ($name === 'fontVariant' && in_array($value, ['normal', 'small-caps'], true)) {
                $classes[] = 'csl-font-variant-' . $value;
                $styles[] = 'font-variant:' . $value;
                continue;
            }

            if ($name === 'fontWeight' && in_array($value, ['normal', 'bold', 'light'], true)) {
                $classes[] = 'csl-font-weight-' . $value;
                $styles[] = 'font-weight:' . ($value === 'light' ? '300' : $value);
                continue;
            }

            if ($name === 'textDecoration' && in_array($value, ['none', 'underline'], true)) {
                $classes[] = 'csl-text-decoration-' . $value;
                $styles[] = 'text-decoration:' . $value;
                continue;
            }

            if ($name === 'verticalAlign' && in_array($value, ['baseline', 'sup', 'sub'], true)) {
                $classes[] = 'csl-vertical-align-' . $value;
                $styles[] = 'vertical-align:' . ($value === 'sup' ? 'super' : $value);
            }
        }

        $attrs = $classes === [] ? '' : ' class="' . $this->esc(implode(' ', array_values(array_unique($classes)))) . '"';
        if ($styles !== []) {
            $attrs .= ' style="' . $this->esc(implode(';', $styles)) . '"';
        }

        return $attrs;
    }

    private function renderLinkInline(AstNode $node): string
    {
        return '<a' . $this->renderLinkAttrs($node) . '>'
            . $this->renderInlineNodes($this->linksAsSpans($node->children))
            . '</a>';
    }

    /**
     * @param list<AstNode> $nodes
     * @return list<AstNode>
     */
    private function linksAsSpans(array $nodes): array
    {
        $mapped = [];
        foreach ($nodes as $node) {
            $children = $node->children === [] ? [] : $this->linksAsSpans($node->children);
            if ($node->type !== 'link') {
                $mapped[] = $children === $node->children
                    ? $node
                    : new AstNode($node->type, $node->attrs, $children);
                continue;
            }

            $attrs = $node->attrs;
            unset($attrs['url'], $attrs['href'], $attrs['title']);
            $attributes = $attrs['attributes'] ?? [];
            if (is_array($attributes)) {
                unset($attributes['href'], $attributes['title']);
                if ($attributes === []) {
                    unset($attrs['attributes']);
                } else {
                    $attrs['attributes'] = $attributes;
                }
            }

            $mapped[] = new AstNode('span', $attrs, $children);
        }

        return $mapped;
    }

    /**
     * @return list<array{id:string, mode:string, noteNum:int, hash:int, prefix:string, suffix:string}>
     */
    private function citationEntries(AstNode $node): array
    {
        if ($node->type === 'citation_group') {
            $entries = [];
            foreach ($node->children as $child) {
                if ($child->type === 'citation') {
                    $entries[] = $this->citationEntryFromNode($child);
                }
            }

            return $entries;
        }

        $citations = $node->attr('citations', null);
        if (is_array($citations) && $citations !== []) {
            $entries = [];
            foreach ($citations as $citation) {
                if ($citation instanceof AstNode) {
                    $entries[] = $this->citationEntryFromNode($citation);
                } elseif (is_array($citation)) {
                    $entries[] = $this->citationEntryFromArray($citation);
                }
            }

            return $entries;
        }

        $entry = $this->citationEntryFromNode($node);

        return $entry['id'] === '' && (string) $node->attr('text', '') === '' ? [] : [$entry];
    }

    /**
     * @param list<array{id:string, mode:string, noteNum:int, hash:int, prefix:string, suffix:string}> $citations
     */
    private function renderCitationMarkdownDisplay(array $citations): string
    {
        if ($citations === []) {
            return '';
        }

        $first = $citations[0];
        if ($first['mode'] === 'author_in_text') {
            $suffix = $first['suffix'];
            $rest = $this->renderCitationEntries(array_slice($citations, 1));
            $inside = $suffix;
            if ($inside !== '' && $rest !== '') {
                $inside .= ';';
            }
            if ($rest !== '') {
                $inside = $this->joinInlinePartsWithSpace($inside, $rest);
            }

            return '@' . $this->renderCitationKey($first['id'])
                . ($inside === '' ? '' : ' [' . $inside . ']');
        }

        return '[' . $this->renderCitationEntries($citations) . ']';
    }

    /**
     * @param list<array{id:string, mode:string, noteNum:int, hash:int, prefix:string, suffix:string}> $citations
     */
    private function renderCitationEntries(array $citations): string
    {
        $rendered = [];
        foreach ($citations as $citation) {
            $entry = $this->renderCitationEntry($citation);
            if ($entry !== '') {
                $rendered[] = $entry;
            }
        }

        return implode('; ', $rendered);
    }

    /**
     * @param array{id:string, mode:string, noteNum:int, hash:int, prefix:string, suffix:string} $citation
     */
    private function renderCitationEntry(array $citation): string
    {
        $prefix = $citation['prefix'];
        $suffix = $citation['suffix'];
        $key = ($citation['mode'] === 'suppress_author' ? '-' : '')
            . '@'
            . $this->renderCitationKey($citation['id']);

        if ($suffix !== '') {
            $first = mb_substr($suffix, 0, 1, 'UTF-8');
            $key .= ($first === ' ' || in_array($first, [',', ';', ']', '@'], true))
                ? $suffix
                : ' ' . $suffix;
        }

        return $this->joinInlinePartsWithSpace($prefix, $key);
    }

    private function renderCitationKey(string $id): string
    {
        return preg_match('/^[A-Za-z0-9_:.#\/$%&+?<>~|-]*[A-Za-z0-9_#\/$%&+?<>~|-]$/u', $id) === 1
            ? $id
            : '{' . strtr($id, [
                '\\' => '\\\\',
                '[' => '\\[',
                ']' => '\\]',
                '}' => '\\}',
            ]) . '}';
    }

    private function joinInlinePartsWithSpace(string $left, string $right): string
    {
        if ($left === '') {
            return $right;
        }
        if ($right === '') {
            return $left;
        }

        return $left . ' ' . $right;
    }

    /**
     * @return array{id:string, mode:string, noteNum:int, hash:int, prefix:string, suffix:string}
     */
    private function citationEntryFromNode(AstNode $node): array
    {
        return $this->citationEntryFromArray([
            'id' => $node->attr('id', ''),
            'mode' => $node->attr('mode', 'normal'),
            'noteNum' => $node->attr('noteNum', $node->attr('citationNoteNum', 1)),
            'hash' => $node->attr('hash', $node->attr('citationHash', 0)),
            'prefix' => $node->attr('prefix', []),
            'suffix' => $node->attr('suffix', []),
        ]);
    }

    /**
     * @param array<string, mixed> $citation
     * @return array{id:string, mode:string, noteNum:int, hash:int, prefix:string, suffix:string}
     */
    private function citationEntryFromArray(array $citation): array
    {
        return [
            'id' => (string) ($citation['id'] ?? $citation['citationId'] ?? ''),
            'mode' => $this->citationModeName($citation['mode'] ?? $citation['citationMode'] ?? 'normal'),
            'noteNum' => (int) ($citation['noteNum'] ?? $citation['citationNoteNum'] ?? 1),
            'hash' => (int) ($citation['hash'] ?? $citation['citationHash'] ?? 0),
            'prefix' => $this->citationAffixText($citation['prefix'] ?? $citation['citationPrefix'] ?? []),
            'suffix' => $this->citationAffixText($citation['suffix'] ?? $citation['citationSuffix'] ?? []),
        ];
    }

    private function citationModeName(mixed $mode): string
    {
        return match (strtolower(str_replace(['-', '_'], '', (string) $mode))) {
            'authorintext' => 'author_in_text',
            'suppressauthor' => 'suppress_author',
            default => 'normal',
        };
    }

    private function citationAffixText(mixed $value): string
    {
        if ($value instanceof AstNode) {
            return trim($this->metadataInlineText($value));
        }

        if (is_string($value)) {
            return trim($value);
        }

        if (!is_array($value)) {
            return '';
        }

        $nodes = [];
        $text = '';
        foreach ($value as $item) {
            if ($item instanceof AstNode) {
                $nodes[] = $item;
                continue;
            }
            if (is_string($item)) {
                $text .= $item;
            }
        }

        if ($nodes !== []) {
            return $this->metadataInlinesText($nodes);
        }

        return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    }

    private function renderNoteReference(AstNode $node): string
    {
        $entry = $this->registerFootnote($node);
        $number = $entry['number'];
        $anchor = $entry['anchor'];
        $label = $entry['label'];
        $labelAttr = $label === '' ? '' : ' data-pandoc-note-label="' . $this->esc($label) . '"';

        return '<sup id="fnref-' . $this->esc($anchor) . '"' . $labelAttr . '><a href="#fn-' . $this->esc($anchor) . '" role="doc-noteref">'
            . $number
            . '</a></sup>';
    }

    /**
     * @return array{number:int, anchor:string, label:string, node:AstNode}
     */
    private function registerFootnote(AstNode $node): array
    {
        $number = count($this->footnotes) + 1;
        $label = $this->noteSourceLabel($node);
        $anchor = $this->resolvedFootnoteAnchor($label, $number);
        $entry = [
            'number' => $number,
            'anchor' => $anchor,
            'label' => $label,
            'node' => $node,
        ];
        $this->footnotes[] = $entry;

        return $entry;
    }

    private function noteSourceLabel(AstNode $node): string
    {
        // Keep legacy Markdown footnotes numeric; note-style CSL output needs source labels for reviewer handoff.
        if (!$this->containsProcessedCslNoteCitation($node)) {
            return '';
        }

        foreach (['label', 'noteLabel'] as $attribute) {
            $label = $node->attr($attribute);
            if (!is_scalar($label)) {
                continue;
            }

            $label = trim(preg_replace('/\s+/', ' ', (string) $label) ?? (string) $label);
            if ($this->isSafeFootnoteAnchorLabel($label)) {
                return $label;
            }
        }

        return '';
    }

    private function containsProcessedCslNoteCitation(AstNode $node): bool
    {
        if (
            $node->type === 'citation'
            && (string) $node->attr('cslStyleClass', '') === 'note'
        ) {
            return true;
        }

        foreach ($node->children as $child) {
            if ($this->containsProcessedCslNoteCitation($child)) {
                return true;
            }
        }

        return false;
    }

    private function isSafeFootnoteAnchorLabel(string $label): bool
    {
        return $label !== ''
            && strlen($label) <= 999
            && preg_match('/^[A-Za-z0-9_.:-]+$/', $label) === 1;
    }

    private function resolvedFootnoteAnchor(string $label, int $number): string
    {
        $base = $label === '' ? (string) $number : $label;
        $anchor = $base;
        $suffix = 2;
        while ($this->footnoteAnchorExists($anchor)) {
            $anchor = $base . '-' . $suffix;
            $suffix++;
        }

        return $anchor;
    }

    private function footnoteAnchorExists(string $anchor): bool
    {
        $key = strtolower($anchor);
        foreach ($this->footnotes as $footnote) {
            if (strtolower($footnote['anchor']) === $key) {
                return true;
            }
        }

        return false;
    }

    private function renderFootnotesBlock(): string
    {
        $items = [];
        foreach ($this->footnotes as $footnote) {
            $anchor = $footnote['anchor'];
            $label = $footnote['label'];
            $labelAttr = $label === '' ? '' : ' data-pandoc-note-label="' . $this->esc($label) . '"';
            $backlinkAttrs = $label === ''
                ? ' href="#fnref-' . $this->esc($anchor) . '" aria-label="Back to content"'
                : ' href="#fnref-' . $this->esc($anchor) . '" class="footnote-back" role="doc-backlink" aria-label="Back to content"';
            $items[] = '<li id="fn-' . $this->esc($anchor) . '"' . $labelAttr . '>'
                . $this->renderBlocksAsHtml($footnote['node']->children)
                . ' <a' . $backlinkAttrs . '>Back</a>'
                . '</li>';
        }

        $inner = '<!-- wp:list {"ordered":true} -->'
            . "\n" . '<ol>' . implode('', $items) . '</ol>'
            . "\n" . '<!-- /wp:list -->';
        $group = new AstNode('div', ['htmlAttributes' => ['role' => 'doc-endnotes']]);

        return $this->renderGroupBlock($group, ['footnotes'], $inner);
    }

    private function renderLinkAttrs(AstNode $node): string
    {
        $sourceUrl = (string) $node->attr('url', '');
        $url = $this->normalizeInternalFragmentUrl($sourceUrl);
        $attrs = ' href="' . $this->esc($url) . '"';
        if ($url !== $sourceUrl) {
            $attrs .= ' data-pandoc-source-href="' . $this->esc($sourceUrl) . '"';
        }
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
            if ($name === 'style') {
                $style = $this->inlineStyleAttribute((string) $value);
                if ($style !== '') {
                    $attrs .= ' style="' . $this->esc($style) . '"';
                }
                continue;
            }

            if (
                $name === 'href'
                || ($name === 'title' && $title !== '')
                || ($name === 'data-pandoc-source-href' && $url !== $sourceUrl)
                || !$this->isAllowedInlineHtmlAttr($name)
            ) {
                continue;
            }

            $attrs .= ' ' . $name . '="' . $this->esc((string) $value) . '"';
        }

        return $attrs;
    }

    private function renderSpanInline(AstNode $node): string
    {
        $classes = $this->spanClasses($node);
        if (in_array('insertion', $classes, true)) {
            return '<ins' . $this->renderTrackedChangeAttrs($node) . '>' . $this->renderInlines($node) . '</ins>';
        }

        if (in_array('deletion', $classes, true)) {
            return '<del' . $this->renderTrackedChangeAttrs($node) . '>' . $this->renderInlines($node) . '</del>';
        }

        if (in_array('paragraph-insertion', $classes, true)) {
            return '<span' . $this->renderParagraphChangeSpanAttrs($node, 'insertion') . '>' . $this->renderInlines($node) . '</span>';
        }

        if (in_array('paragraph-deletion', $classes, true)) {
            return '<span' . $this->renderParagraphChangeSpanAttrs($node, 'deletion') . '>' . $this->renderInlines($node) . '</span>';
        }

        if (
            in_array('comment-start', $classes, true)
            || in_array('comment-end', $classes, true)
        ) {
            return '<span' . $this->renderCommentSpanAttrs($node) . '>' . $this->renderInlines($node) . '</span>';
        }

        if (in_array('indexref', $classes, true)) {
            return '<span' . $this->renderIndexReferenceSpanAttrs($node) . '>' . $this->renderInlines($node) . '</span>';
        }

        if (in_array('diagram', $classes, true)) {
            return '<span' . $this->renderDiagramSpanAttrs($node) . '>' . $this->renderInlines($node) . '</span>';
        }

        if (in_array('anchor', $classes, true) && $node->children === []) {
            return '<span' . $this->renderEmptyAnchorSpanAttrs($node) . '></span>';
        }

        $spanLike = $this->renderSpanLikeInline($node, $classes);
        if ($spanLike !== null) {
            return $spanLike;
        }

        return '<span' . $this->renderInlineSpanAttrs($node) . '>' . $this->renderInlines($node) . '</span>';
    }

    /**
     * @param list<string> $classes
     */
    private function renderSpanLikeInline(AstNode $node, array $classes): ?string
    {
        $firstSpecial = null;
        foreach ($classes as $index => $class) {
            if ($this->isSpanLikeClass($class)) {
                $firstSpecial = $index;
                break;
            }
        }

        if ($firstSpecial === null) {
            return null;
        }

        $retainedClasses = [];
        foreach (array_slice($classes, $firstSpecial + 1) as $class) {
            if (!$this->isSpanLikeClass($class)) {
                $retainedClasses[] = $class;
            }
        }

        $wrappers = [];
        for ($index = count($classes) - 1; $index >= 0; $index--) {
            if ($classes[$index] === 'smallcaps') {
                $wrappers[] = ['tag' => 'span', 'classes' => ['smallcaps']];
            } elseif ($classes[$index] === 'underline') {
                $wrappers[] = ['tag' => 'u', 'classes' => []];
            }
        }
        foreach ($classes as $class) {
            if ($this->isHtmlSpanLikeElement($class)) {
                $wrappers[] = $class === 'mark'
                    ? ['tag' => 'mark', 'classes' => []]
                    : ['tag' => $class, 'classes' => []];
            }
        }

        if ($wrappers === []) {
            return null;
        }

        $html = $this->renderInlines($node);
        $outerIndex = count($wrappers) - 1;
        foreach ($wrappers as $index => $wrapper) {
            $tag = $wrapper['tag'];
            $wrapperClasses = $wrapper['classes'];
            $attrs = $index === $outerIndex
                ? $this->renderSpanLikeAttrs($node, array_values(array_unique(array_merge($wrapperClasses, $retainedClasses))))
                : $this->renderClassAttr($wrapperClasses);
            $html = '<' . $tag . $attrs . '>' . $html . '</' . $tag . '>';
        }

        return $html;
    }

    private function isSpanLikeClass(string $class): bool
    {
        return $class === 'smallcaps'
            || $class === 'underline'
            || $this->isHtmlSpanLikeElement($class);
    }

    private function isHtmlSpanLikeElement(string $class): bool
    {
        return in_array($class, ['kbd', 'mark', 'dfn', 'abbr'], true);
    }

    /**
     * @return list<string>
     */
    private function spanClasses(AstNode $node): array
    {
        $classes = $node->attr('classes', []);
        if (!is_array($classes)) {
            return [];
        }

        $normalized = [];
        foreach ($classes as $class) {
            $class = (string) $class;
            if ($class !== '') {
                $normalized[] = $class;
            }
        }

        return $normalized;
    }

    private function renderTrackedChangeAttrs(AstNode $node): string
    {
        $attrs = $this->renderClassAttr($this->spanClasses($node));
        $sourceAttrs = $this->inlineHtmlAttributes($node);
        $author = (string) ($sourceAttrs['author'] ?? '');
        if ($author !== '') {
            $attrs .= ' data-pandoc-change-author="' . $this->esc($author) . '"';
        }

        $date = (string) ($sourceAttrs['date'] ?? '');
        if ($date !== '') {
            $attrs .= ' data-pandoc-change-date="' . $this->esc($date) . '"';
            $attrs .= ' datetime="' . $this->esc($date) . '"';
        } elseif ($author !== '') {
            $attrs .= ' data-pandoc-change-date-status="missing"';
        }

        return $attrs;
    }

    private function renderCommentSpanAttrs(AstNode $node): string
    {
        $attrs = $this->renderClassAttr($this->spanClasses($node));
        $sourceAttrs = $this->inlineHtmlAttributes($node);
        $id = (string) ($sourceAttrs['id'] ?? '');
        if ($id !== '') {
            $attrs .= ' data-pandoc-comment-id="' . $this->esc($id) . '"';
        }

        $author = (string) ($sourceAttrs['author'] ?? '');
        if ($author !== '') {
            $attrs .= ' data-pandoc-comment-author="' . $this->esc($author) . '"';
        }

        $date = (string) ($sourceAttrs['date'] ?? '');
        if ($date !== '') {
            $attrs .= ' data-pandoc-comment-date="' . $this->esc($date) . '"';
        } elseif ($author !== '') {
            $attrs .= ' data-pandoc-comment-date-status="missing"';
        }

        return $attrs;
    }

    private function renderParagraphChangeSpanAttrs(AstNode $node, string $kind): string
    {
        $attrs = $this->renderClassAttr($this->spanClasses($node));
        $attrs .= ' data-pandoc-paragraph-change="' . $this->esc($kind) . '"';
        $sourceAttrs = $this->inlineHtmlAttributes($node);
        $author = (string) ($sourceAttrs['author'] ?? '');
        if ($author !== '') {
            $attrs .= ' data-pandoc-change-author="' . $this->esc($author) . '"';
        }

        $date = (string) ($sourceAttrs['date'] ?? '');
        if ($date !== '') {
            $attrs .= ' data-pandoc-change-date="' . $this->esc($date) . '"';
            $attrs .= ' datetime="' . $this->esc($date) . '"';
        } elseif ($author !== '') {
            $attrs .= ' data-pandoc-change-date-status="missing"';
        }

        return $attrs;
    }

    private function renderIndexReferenceSpanAttrs(AstNode $node): string
    {
        $attrs = $this->renderClassAttr($this->spanClasses($node));
        $sourceAttrs = $this->inlineHtmlAttributes($node);
        foreach ($sourceAttrs as $name => $value) {
            $name = strtolower((string) $name);
            if (in_array($name, ['class', 'entry', 'crossref', 'yomi', 'bold', 'italic'], true)) {
                continue;
            }
            if (!str_starts_with($name, 'data-docx-')) {
                continue;
            }
            if (!$this->isAllowedInlineHtmlAttr($name)) {
                continue;
            }
            $attrs .= ' ' . $name . '="' . $this->esc((string) $value) . '"';
        }

        $entry = trim((string) ($sourceAttrs['entry'] ?? ''));
        if ($entry !== '') {
            $attrs .= ' data-pandoc-index-entry="' . $this->esc($entry) . '"';
        }

        return $attrs;
    }

    private function renderDiagramSpanAttrs(AstNode $node): string
    {
        $attrs = $this->renderInlineSpanAttrs($node);
        $hasDiagramAttr = false;
        foreach ($this->inlineHtmlAttributes($node) as $name => $_value) {
            if (strtolower((string) $name) === 'data-pandoc-diagram') {
                $hasDiagramAttr = true;
                break;
            }
        }

        if (!$hasDiagramAttr) {
            $attrs .= ' data-pandoc-diagram="unsupported-docx-diagram"';
        }

        return $attrs;
    }

    private function renderEmptyAnchorSpanAttrs(AstNode $node): string
    {
        $attrs = $this->renderInlineSpanAttrs($node);
        foreach ($this->inlineHtmlAttributes($node) as $name => $_value) {
            if (strtolower((string) $name) === 'data-pandoc-anchor') {
                return $attrs;
            }
        }

        return $attrs . ' data-pandoc-anchor="empty-target"';
    }

    /**
     * @param list<string> $classes
     */
    private function renderClassAttr(array $classes): string
    {
        if ($classes === []) {
            return '';
        }

        return ' class="' . $this->esc(implode(' ', array_values(array_unique($classes)))) . '"';
    }

    private function renderInlineSpanAttrs(AstNode $node): string
    {
        $attrs = '';
        $renderedSourceId = false;
        foreach ($this->inlineHtmlAttributes($node) as $name => $value) {
            $name = strtolower((string) $name);
            if ($name === 'id') {
                $sourceId = (string) $value;
                $htmlId = $this->htmlFragmentIdNeedsNormalization($sourceId)
                    ? $this->normalizeHtmlFragmentId($sourceId)
                    : $sourceId;
                if ($htmlId !== '') {
                    $attrs .= ' id="' . $this->esc($htmlId) . '"';
                }
                if ($htmlId !== $sourceId) {
                    $attrs .= ' data-pandoc-source-id="' . $this->esc($sourceId) . '"';
                    $renderedSourceId = true;
                }
                continue;
            }

            if ($name === 'custom-style') {
                $attrs .= $this->renderCustomStyleDataAttr((string) $value);
                continue;
            }

            if ($name === 'data-pandoc-source-id' && $renderedSourceId) {
                continue;
            }

            if ($name === 'style') {
                $style = $this->inlineStyleAttribute((string) $value);
                if ($style !== '') {
                    $attrs .= ' style="' . $this->esc($style) . '"';
                }
                continue;
            }

            if (!$this->isAllowedInlineHtmlAttr($name)) {
                continue;
            }

            $attrs .= ' ' . $name . '="' . $this->esc((string) $value) . '"';
        }

        return $attrs;
    }

    /**
     * @param list<string> $classes
     */
    private function renderSpanLikeAttrs(AstNode $node, array $classes): string
    {
        $attrs = '';
        $renderedSourceId = false;
        $htmlAttributes = $this->inlineHtmlAttributes($node);
        $id = (string) ($htmlAttributes['id'] ?? $node->attr('id', ''));
        if ($id !== '') {
            $htmlId = $this->htmlFragmentIdNeedsNormalization($id)
                ? $this->normalizeHtmlFragmentId($id)
                : $id;
            if ($htmlId !== '') {
                $attrs .= ' id="' . $this->esc($htmlId) . '"';
            }
            if ($htmlId !== $id) {
                $attrs .= ' data-pandoc-source-id="' . $this->esc($id) . '"';
                $renderedSourceId = true;
            }
        }

        $attrs .= $this->renderClassAttr($classes);

        foreach ($htmlAttributes as $name => $value) {
            $name = strtolower((string) $name);
            if ($name === 'id' || $name === 'class') {
                continue;
            }

            if ($name === 'custom-style') {
                $attrs .= $this->renderCustomStyleDataAttr((string) $value);
                continue;
            }

            if ($name === 'data-pandoc-source-id' && $renderedSourceId) {
                continue;
            }

            if ($name === 'style') {
                $style = $this->inlineStyleAttribute((string) $value);
                if ($style !== '') {
                    $attrs .= ' style="' . $this->esc($style) . '"';
                }
                continue;
            }

            if (!$this->isAllowedInlineHtmlAttr($name)) {
                continue;
            }

            $attrs .= ' ' . $name . '="' . $this->esc((string) $value) . '"';
        }

        return $attrs;
    }

    private function renderDivAttrs(AstNode $node): string
    {
        $attrs = '';
        $renderedSourceId = false;
        foreach ($this->inlineHtmlAttributes($node) as $name => $value) {
            $name = strtolower((string) $name);
            if ($name === 'id') {
                $sourceId = (string) $value;
                $htmlId = $this->htmlFragmentIdNeedsNormalization($sourceId)
                    ? $this->normalizeHtmlFragmentId($sourceId)
                    : $sourceId;
                if ($htmlId !== '') {
                    $attrs .= ' id="' . $this->esc($htmlId) . '"';
                }
                if ($htmlId !== $sourceId) {
                    $attrs .= ' data-pandoc-source-id="' . $this->esc($sourceId) . '"';
                    $renderedSourceId = true;
                }
                continue;
            }

            if ($name === 'custom-style') {
                $attrs .= $this->renderCustomStyleDataAttr((string) $value);
                continue;
            }

            if ($name === 'data-pandoc-source-id' && $renderedSourceId) {
                continue;
            }

            if (!$this->isAllowedBlockHtmlAttr($name)) {
                continue;
            }

            $attrs .= ' ' . $name . '="' . $this->esc((string) $value) . '"';
        }

        return $attrs;
    }

    private function renderCustomStyleDataAttr(string $style): string
    {
        $style = trim($style);
        if ($style === '') {
            return '';
        }

        return ' data-pandoc-custom-style="' . $this->esc($style) . '"';
    }

    private function nodeCustomStyle(AstNode $node): string
    {
        $attributes = $node->attr('attributes', []);
        if (is_array($attributes) && isset($attributes['custom-style'])) {
            return (string) $attributes['custom-style'];
        }

        $htmlAttributes = $node->attr('htmlAttributes', []);
        if (is_array($htmlAttributes) && isset($htmlAttributes['custom-style'])) {
            return (string) $htmlAttributes['custom-style'];
        }

        return '';
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
                if (isset($htmlAttributes['data-' . $name])) {
                    continue;
                }
                if (!isset($htmlAttributes[$name])) {
                    $htmlAttributes[$name] = $value;
                }
            }
        }

        return $htmlAttributes;
    }

    private function normalizeInternalFragmentUrl(string $url): string
    {
        if (!str_starts_with($url, '#')) {
            return $url;
        }

        $fragment = substr($url, 1);
        if (!$this->htmlFragmentIdNeedsNormalization($fragment)) {
            return $url;
        }

        return '#' . $this->normalizeHtmlFragmentId($fragment);
    }

    private function htmlFragmentIdNeedsNormalization(string $id): bool
    {
        return preg_match('/\s/u', $id) === 1;
    }

    private function normalizeHtmlFragmentId(string $id): string
    {
        $normalized = trim($id);
        $normalized = preg_replace('/\s+/u', '-', $normalized) ?? $normalized;
        $normalized = preg_replace('/[^\p{L}\p{N}_.:-]+/u', '-', $normalized) ?? $normalized;
        $normalized = trim($normalized, '-');

        return $normalized === '' ? 'pandoc-anchor-' . substr(sha1($id), 0, 8) : $normalized;
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
            || $this->isAllowedSafeGlobalHtmlAttr($name)
            || in_array($name, ['cite', 'class', 'dir', 'id', 'lang', 'role', 'title', 'translate', 'xml:lang'], true);
    }

    private function isAllowedBlockHtmlAttr(string $name): bool
    {
        if (preg_match('/^[a-z][a-z0-9_.:-]*$/', $name) !== 1 || str_starts_with($name, 'on')) {
            return false;
        }

        return str_starts_with($name, 'data-')
            || str_starts_with($name, 'aria-')
            || $this->isAllowedSafeGlobalHtmlAttr($name)
            || in_array($name, ['class', 'dir', 'id', 'lang', 'role', 'title', 'translate', 'xml:lang'], true);
    }

    private function isAllowedImageHtmlAttr(string $name): bool
    {
        if (preg_match('/^[a-z][a-z0-9_.:-]*$/', $name) !== 1 || str_starts_with($name, 'on')) {
            return false;
        }

        return str_starts_with($name, 'data-')
            || str_starts_with($name, 'aria-')
            || $this->isAllowedSafeGlobalHtmlAttr($name)
            || in_array($name, ['class', 'decoding', 'dir', 'fetchpriority', 'id', 'lang', 'loading', 'sizes', 'srcset', 'xml:lang'], true);
    }

    private function isAllowedSafeGlobalHtmlAttr(string $name): bool
    {
        return in_array($name, self::SAFE_GLOBAL_HTML_ATTRIBUTES, true);
    }

    private function renderMathInline(AstNode $node): string
    {
        $text = (string) $node->attr('text', '');
        $display = $node->attr('display') === true;
        $class = $display ? 'display' : 'inline';

        if ($this->htmlMathMethod() === 'mathml') {
            return $this->renderMathMLInline($node, $text, $display, $class);
        }

        $open = $display ? '\\[' : '\\(';
        $close = $display ? '\\]' : '\\)';

        return '<span class="math ' . $class . '">'
            . $this->esc($open . $text . $close)
            . '</span>';
    }

    private function renderMathMLInline(AstNode $node, string $text, bool $display, string $class): string
    {
        $mathml = $node->attr('mathml', $node->attr('html', ''));
        if (is_scalar($mathml)) {
            $mathml = trim((string) $mathml);
            if ($this->looksLikeMathMLElement($mathml)) {
                return $this->mathMLWithRequiredAttributes($mathml, $display);
            }
        }

        try {
            return (new MathTexConverter())->texToMathMl($text, $display);
        } catch (\InvalidArgumentException) {
            return '<span class="math ' . $class . '">' . $this->esc($text) . '</span>';
        }
    }

    private function htmlMathMethod(): string
    {
        $value = $this->options['writerHTMLMathMethod']
            ?? $this->options['htmlMathMethod']
            ?? $this->options['mathMethod']
            ?? 'mathjax';

        if (is_array($value)) {
            $value = $value['method'] ?? $value['type'] ?? $value['name'] ?? '';
        }

        return strtolower(str_replace(['-', '_', ' '], '', (string) $value)) === 'mathml'
            ? 'mathml'
            : 'mathjax';
    }

    private function looksLikeMathMLElement(string $mathml): bool
    {
        return preg_match('/^<math(?:\s|>|\/)/i', $mathml) === 1
            && !str_contains($mathml, '<?')
            && stripos($mathml, '<script') === false;
    }

    private function mathMLWithRequiredAttributes(string $mathml, bool $display): string
    {
        return preg_replace_callback('/^<math\b([^>]*)>/i', function (array $match) use ($display): string {
            $tail = $match[1];
            $attrs = rtrim($tail);
            if (preg_match('/\sxmlns\s*=/i', $tail) !== 1) {
                $attrs .= ' xmlns="http://www.w3.org/1998/Math/MathML"';
            }
            if ($display && preg_match('/\sdisplay\s*=/i', $tail) !== 1) {
                $attrs .= ' display="block"';
            }

            return '<math' . $attrs . '>';
        }, $mathml, 1) ?? $mathml;
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
