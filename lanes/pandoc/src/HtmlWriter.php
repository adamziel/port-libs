<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class HtmlWriter
{
    /** @var list<array{number:int, node:AstNode}> */
    private array $footnotes = [];

    private int $nextFootnoteNumber = 1;

    private int $flushedFootnoteCount = 0;

    /**
     * @param array{htmlQTags?: bool, htmlMathMethod?: string|array<string, mixed>, mathMethod?: string, writerHTMLMathMethod?: string|array<string, mixed>, referenceLocation?: string, writerReferenceLocation?: string, sectionDivs?: bool, writerSectionDivs?: bool, writerWrapText?: string, wrap?: string, writerSemanticBlockElements?: array<int, string>, semanticBlockElements?: array<int, string>} $options
     */
    public function __construct(private readonly array $options = [])
    {
    }

    public function write(AstNode $document): string
    {
        if ($document->type !== 'document') {
            throw new \InvalidArgumentException('HTML writer expects a document node');
        }

        $previousFootnotes = $this->footnotes;
        $previousNextFootnoteNumber = $this->nextFootnoteNumber;
        $previousFlushedFootnoteCount = $this->flushedFootnoteCount;
        $this->footnotes = [];
        $this->nextFootnoteNumber = 1;
        $this->flushedFootnoteCount = 0;

        try {
            return $this->renderDocument($document);
        } finally {
            $this->footnotes = $previousFootnotes;
            $this->nextFootnoteNumber = $previousNextFootnoteNumber;
            $this->flushedFootnoteCount = $previousFlushedFootnoteCount;
        }
    }

    private function renderDocument(AstNode $document): string
    {
        if ($this->usesSectionDivs()) {
            return $this->renderSectionDivDocument($document);
        }

        $referenceLocation = $this->referenceLocation();
        $blocks = [];
        foreach ($document->children as $node) {
            if ($referenceLocation === 'end_of_section' && $node->type === 'heading' && $blocks !== []) {
                $this->appendRenderedBlock($blocks, $this->renderPendingFootnotes($referenceLocation));
            }

            $html = $this->renderBlock($node);
            if ($html !== '') {
                $blocks[] = $html;
            }

            if ($referenceLocation === 'end_of_block') {
                $this->appendRenderedBlock($blocks, $this->renderPendingFootnotes($referenceLocation));
            }
        }

        $this->appendRenderedBlock($blocks, $this->renderPendingFootnotes($referenceLocation));

        return implode("\n", $blocks);
    }

    private function usesSectionDivs(): bool
    {
        return (bool) ($this->options['writerSectionDivs'] ?? $this->options['sectionDivs'] ?? false);
    }

    private function renderSectionDivDocument(AstNode $document): string
    {
        $index = 0;
        $items = $this->collectSectionItems($document->children, $index, 0);
        $blocks = $this->renderSectionItems($items, true);

        $this->appendRenderedBlock($blocks, $this->renderPendingFootnotes($this->referenceLocation()));

        return implode("\n", $blocks);
    }

    /**
     * @param list<AstNode> $nodes
     * @return list<array{type:string, node?:AstNode, heading?:AstNode, level?:int, children?:array<int, mixed>}>
     */
    private function collectSectionItems(array $nodes, int &$index, int $parentLevel): array
    {
        $items = [];
        $count = count($nodes);
        while ($index < $count) {
            $node = $nodes[$index];
            if ($node->type === 'heading') {
                $level = max(1, min(6, (int) $node->attr('level', 1)));
                if ($level <= $parentLevel) {
                    break;
                }

                $index++;
                $items[] = [
                    'type' => 'section',
                    'heading' => $node,
                    'level' => $level,
                    'children' => $this->collectSectionItems($nodes, $index, $level),
                ];
                continue;
            }

            $items[] = [
                'type' => 'block',
                'node' => $node,
            ];
            $index++;
        }

        return $items;
    }

    /**
     * @param list<array{type:string, node?:AstNode, heading?:AstNode, level?:int, children?:array<int, mixed>}> $items
     * @return list<string>
     */
    private function renderSectionItems(array $items, bool $root = false): array
    {
        $referenceLocation = $this->referenceLocation();
        $blocks = [];
        foreach ($items as $item) {
            if ($item['type'] === 'section') {
                if ($referenceLocation === 'end_of_section') {
                    $this->appendRenderedBlock($blocks, $this->renderPendingFootnotes($referenceLocation));
                }

                $heading = $item['heading'] ?? null;
                $children = $item['children'] ?? [];
                if ($heading instanceof AstNode && is_array($children)) {
                    $blocks[] = $this->renderSectionDiv($heading, (int) ($item['level'] ?? 1), $children);
                }
                continue;
            }

            $node = $item['node'] ?? null;
            if (!$node instanceof AstNode) {
                continue;
            }

            $html = $this->renderBlock($node);
            if ($html !== '') {
                $blocks[] = $html;
            }

            if ($referenceLocation === 'end_of_block') {
                $this->appendRenderedBlock($blocks, $this->renderPendingFootnotes($referenceLocation));
            }
        }

        if ($root && $referenceLocation === 'end_of_section') {
            $this->appendRenderedBlock($blocks, $this->renderPendingFootnotes($referenceLocation));
        }

        return $blocks;
    }

    /**
     * @param list<array{type:string, node?:AstNode, heading?:AstNode, level?:int, children?:array<int, mixed>}> $children
     */
    private function renderSectionDiv(AstNode $heading, int $level, array $children): string
    {
        $referenceLocation = $this->referenceLocation();
        $lines = ['<div' . $this->renderSectionAttributes($heading, $level) . '>'];
        $lines[] = $this->renderSectionHeading($heading);
        foreach ($this->renderSectionItems($children) as $html) {
            $lines[] = $html;
        }
        if ($referenceLocation === 'end_of_section') {
            $this->appendRenderedBlock($lines, $this->renderPendingFootnotes($referenceLocation));
        }
        $lines[] = '</div>';

        return implode("\n", $lines);
    }

    private function renderSectionAttributes(AstNode $heading, int $level): string
    {
        $attrs = $this->attrTuple($heading);
        $attrs['classes'] = array_values(array_unique(array_merge(
            ['section', 'level' . $level],
            $attrs['classes']
        )));

        return $this->renderAttrTuple($attrs);
    }

    private function renderSectionHeading(AstNode $heading): string
    {
        $level = max(1, min(6, (int) $heading->attr('level', 1)));
        $attrs = $this->htmlAttrTuple($heading);
        $attrs['id'] = '';

        return '<h' . $level . $this->renderAttrTuple($attrs, $this->allowedHeadingAttribute(...)) . '>'
            . $this->renderInlines($heading->children)
            . '</h' . $level . '>';
    }

    private function renderBlock(AstNode $node): string
    {
        return match ($node->type) {
            'plain' => $this->renderInlines($node->children),
            'paragraph' => '<p>' . $this->renderInlines($node->children) . '</p>',
            'heading' => $this->renderHeading($node),
            'code_block' => $this->renderCodeBlock($node),
            'horizontal_rule' => $this->renderHorizontalRule(),
            'line_block' => $this->renderLineBlock($node),
            'figure' => $this->renderFigure($node),
            'table' => $this->renderTable($node),
            'div' => $this->renderDiv($node),
            'bullet_list' => $this->renderList($node, false),
            'ordered_list' => $this->renderList($node, true),
            'definition_list' => $this->renderDefinitionList($node),
            'blockquote' => $this->renderBlockQuote($node),
            'raw_html' => (string) $node->attr('html', $node->attr('text', '')),
            'raw_block' => $this->renderRawBlock($node),
            'raw_tex' => '',
            default => $this->renderInlines($node->children),
        };
    }

    private function renderHeading(AstNode $node): string
    {
        $level = max(1, min(6, (int) $node->attr('level', 1)));

        return '<h' . $level . $this->renderHtmlAttributes($node, $this->allowedHeadingAttribute(...)) . '>'
            . $this->renderInlines($node->children)
            . '</h' . $level . '>';
    }

    private function renderCodeBlock(AstNode $node): string
    {
        return '<pre' . $this->renderAttributes($node) . '><code>'
            . $this->esc((string) $node->attr('text', ''))
            . '</code></pre>';
    }

    private function renderHorizontalRule(): string
    {
        return '<hr />';
    }

    private function renderLineBlock(AstNode $node): string
    {
        $lines = [];
        foreach ($node->children as $line) {
            if ($line->type !== 'line') {
                continue;
            }

            $lines[] = $line->children === []
                ? $this->esc((string) $line->attr('text', ''))
                : $this->renderInlines($line->children);
        }

        return '<div class="line-block">' . implode('<br />', $lines) . '</div>';
    }

    private function renderDiv(AstNode $node): string
    {
        if ($this->divIsWrapper($node)) {
            $html = $this->renderBlock($node->children[0]);

            return $html === '' ? '' : $this->addRootAttributes($html, $this->wrapperDivAttributes($node));
        }

        $attrs = $this->htmlAttrTuple($node);
        $isCslBibBody = $attrs['id'] === 'refs' || in_array('csl-bib-body', $attrs['classes'], true);
        $isCslBibEntry = in_array('csl-entry', $attrs['classes'], true);
        if ($isCslBibBody && !array_key_exists('role', $attrs['attributes'])) {
            $attrs['attributes']['role'] = 'list';
        }
        if ($isCslBibEntry && !array_key_exists('role', $attrs['attributes'])) {
            $attrs['attributes']['role'] = 'listitem';
        }

        $formBlockHtml = $this->renderFormBlockElement($node, $attrs);
        if ($formBlockHtml !== null) {
            return $formBlockHtml;
        }

        $detailsBlockHtml = $this->renderDetailsBlockElement($node, $attrs);
        if ($detailsBlockHtml !== null) {
            return $detailsBlockHtml;
        }

        $epubSwitchHtml = $this->renderEpubSwitchBlockElement($node, $attrs);
        if ($epubSwitchHtml !== null) {
            return $epubSwitchHtml;
        }

        $semanticBlockHtml = $this->renderSemanticBlockElement($node, $attrs);
        if ($semanticBlockHtml !== null) {
            return $semanticBlockHtml;
        }

        $tag = 'div';
        if (in_array('section', $attrs['classes'], true)) {
            $tag = 'section';
            $attrs['classes'] = array_values(array_filter(
                $attrs['classes'],
                static fn (string $class): bool => $class !== 'section'
            ));
        }

        if (in_array('column', $attrs['classes'], true) && isset($attrs['attributes']['width'])) {
            $width = $attrs['attributes']['width'];
            unset($attrs['attributes']['width']);
            $existingStyle = rtrim((string) ($attrs['attributes']['style'] ?? ''), ';');
            $attrs['attributes']['style'] = ($existingStyle === '' ? '' : $existingStyle . ';') . 'width:' . $width . ';';
        }

        $lines = ['<' . $tag . $this->renderAttrTuple($attrs) . '>'];
        foreach ($node->children as $child) {
            $html = $this->renderDivChild($child, $isCslBibEntry);
            if ($html !== '') {
                $lines[] = $html;
            }
        }
        $lines[] = '</' . $tag . '>';

        return implode("\n", $lines);
    }

    /**
     * @param array{id:string, classes:list<string>, attributes:array<string, string>} $attrs
     */
    private function renderDetailsBlockElement(AstNode $node, array $attrs): ?string
    {
        foreach ($attrs['classes'] as $index => $class) {
            if (
                $class !== 'details'
                || !$this->divAttrsIndicateDetailsBlockElement($node, $attrs['attributes'])
            ) {
                continue;
            }

            $retainedClasses = [];
            foreach (array_slice($attrs['classes'], $index + 1) as $retainedClass) {
                if ($retainedClass !== 'details') {
                    $retainedClasses[] = $retainedClass;
                }
            }
            $attrs['classes'] = $this->classesWithoutCslNoStyleMarkers($retainedClasses);

            $lines = ['<details' . $this->renderAttrTuple($attrs) . '>'];
            foreach ($node->children as $child) {
                $html = $this->renderDetailsChild($child);
                if ($html !== '') {
                    $lines[] = $html;
                }
            }
            $lines[] = '</details>';

            return implode("\n", $lines);
        }

        return null;
    }

    /**
     * @param array<string, string> $attributes
     */
    private function divAttrsIndicateDetailsBlockElement(AstNode $node, array $attributes): bool
    {
        if (trim($attributes['open'] ?? '') !== '') {
            return true;
        }
        if ($this->divAttrsIndicateSemanticBlockElement('details', $attributes)) {
            return true;
        }

        foreach ($node->children as $child) {
            if ($child->type === 'div' && $this->divHasClass($child, 'summary')) {
                return true;
            }
        }

        return false;
    }

    private function renderDetailsChild(AstNode $node): string
    {
        if ($node->type === 'div') {
            $summaryHtml = $this->renderSummaryBlockElement($node);
            if ($summaryHtml !== null) {
                return $summaryHtml;
            }
        }

        return $this->renderBlock($node);
    }

    private function renderSummaryBlockElement(AstNode $node): ?string
    {
        $attrs = $this->htmlAttrTuple($node);
        foreach ($attrs['classes'] as $index => $class) {
            if ($class !== 'summary') {
                continue;
            }

            $retainedClasses = [];
            foreach (array_slice($attrs['classes'], $index + 1) as $retainedClass) {
                if ($retainedClass !== 'summary') {
                    $retainedClasses[] = $retainedClass;
                }
            }
            $attrs['classes'] = $this->classesWithoutCslNoStyleMarkers($retainedClasses);

            $lines = ['<summary' . $this->renderAttrTuple($attrs) . '>'];
            foreach ($node->children as $child) {
                $html = $this->renderBlock($child);
                if ($html !== '') {
                    $lines[] = $html;
                }
            }
            $lines[] = '</summary>';

            return implode("\n", $lines);
        }

        return null;
    }

    private function divHasClass(AstNode $node, string $class): bool
    {
        $classes = $this->htmlAttrTuple($node)['classes'];

        return in_array($class, $classes, true);
    }

    /**
     * @param array{id:string, classes:list<string>, attributes:array<string, string>} $attrs
     */
    private function renderEpubSwitchBlockElement(AstNode $node, array $attrs): ?string
    {
        foreach ($attrs['classes'] as $index => $class) {
            if ($class !== 'switch' || !$this->divAttrsIndicateEpubSwitchBlockElement($node)) {
                continue;
            }

            $retainedClasses = [];
            foreach (array_slice($attrs['classes'], $index + 1) as $retainedClass) {
                if ($retainedClass !== 'switch') {
                    $retainedClasses[] = $retainedClass;
                }
            }
            $attrs['classes'] = $this->classesWithoutCslNoStyleMarkers($retainedClasses);

            $lines = ['<epub:switch' . $this->renderAttrTuple($attrs) . '>'];
            foreach ($node->children as $child) {
                $html = $this->renderEpubSwitchChild($child);
                if ($html !== '') {
                    $lines[] = $html;
                }
            }
            $lines[] = '</epub:switch>';

            return implode("\n", $lines);
        }

        return null;
    }

    private function divAttrsIndicateEpubSwitchBlockElement(AstNode $node): bool
    {
        foreach ($node->children as $child) {
            if ($child->type === 'div' && $this->divIndicatesEpubSwitchCaseElement($child)) {
                return true;
            }
        }

        return false;
    }

    private function renderEpubSwitchChild(AstNode $node): string
    {
        if ($node->type === 'div') {
            $caseHtml = $this->renderEpubSwitchCaseBlockElement($node);
            if ($caseHtml !== null) {
                return $caseHtml;
            }
        }

        return $this->renderBlock($node);
    }

    private function renderEpubSwitchCaseBlockElement(AstNode $node): ?string
    {
        $attrs = $this->htmlAttrTuple($node);
        foreach ($attrs['classes'] as $index => $class) {
            if (
                !in_array($class, ['case', 'default'], true)
                || ($class === 'case' && !$this->attrsIndicateEpubSwitchCaseElement($attrs['attributes']))
            ) {
                continue;
            }

            $retainedClasses = [];
            foreach (array_slice($attrs['classes'], $index + 1) as $retainedClass) {
                if (!in_array($retainedClass, ['case', 'default'], true)) {
                    $retainedClasses[] = $retainedClass;
                }
            }
            $attrs['classes'] = $this->classesWithoutCslNoStyleMarkers($retainedClasses);
            $attrs = $this->attrsForEpubSwitchCaseBlockElement($class, $attrs);
            $tag = $class === 'case' ? 'epub:case' : 'epub:default';

            $lines = ['<' . $tag . $this->renderAttrTuple($attrs) . '>'];
            foreach ($node->children as $child) {
                $html = $this->renderBlock($child);
                if ($html !== '') {
                    $lines[] = $html;
                }
            }
            $lines[] = '</' . $tag . '>';

            return implode("\n", $lines);
        }

        return null;
    }

    private function divIndicatesEpubSwitchCaseElement(AstNode $node): bool
    {
        $attrs = $this->htmlAttrTuple($node);
        foreach ($attrs['classes'] as $class) {
            if ($class === 'case' && $this->attrsIndicateEpubSwitchCaseElement($attrs['attributes'])) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, string> $attributes
     */
    private function attrsIndicateEpubSwitchCaseElement(array $attributes): bool
    {
        foreach (['required-namespace', 'requiredNamespace', 'required-modules', 'requiredModules'] as $attribute) {
            if (trim($attributes[$attribute] ?? '') !== '') {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array{id:string, classes:list<string>, attributes:array<string, string>} $attrs
     * @return array{id:string, classes:list<string>, attributes:array<string, string>}
     */
    private function attrsForEpubSwitchCaseBlockElement(string $tag, array $attrs): array
    {
        if ($tag !== 'case') {
            return $attrs;
        }

        foreach ([
            'requiredNamespace' => 'required-namespace',
            'requiredModules' => 'required-modules',
        ] as $source => $target) {
            if (!isset($attrs['attributes'][$target]) && isset($attrs['attributes'][$source])) {
                $attrs['attributes'][$target] = $attrs['attributes'][$source];
            }
            unset($attrs['attributes'][$source]);
        }

        return $attrs;
    }

    /**
     * @param array{id:string, classes:list<string>, attributes:array<string, string>} $attrs
     */
    private function renderSemanticBlockElement(AstNode $node, array $attrs): ?string
    {
        foreach ($attrs['classes'] as $index => $class) {
            if (
                !$this->isHtmlSemanticBlockElement($class)
                || !$this->divAttrsIndicateSemanticBlockElement($class, $attrs['attributes'])
            ) {
                continue;
            }

            $retainedClasses = [];
            foreach (array_slice($attrs['classes'], $index + 1) as $retainedClass) {
                if (!$this->isHtmlSemanticBlockElement($retainedClass)) {
                    $retainedClasses[] = $retainedClass;
                }
            }
            $attrs['classes'] = $this->classesWithoutCslNoStyleMarkers($retainedClasses);

            $lines = ['<' . $class . $this->renderAttrTuple($attrs) . '>'];
            foreach ($node->children as $child) {
                $html = $this->renderBlock($child);
                if ($html !== '') {
                    $lines[] = $html;
                }
            }
            $lines[] = '</' . $class . '>';

            return implode("\n", $lines);
        }

        return null;
    }

    /**
     * @param array<string, string> $attributes
     */
    private function divAttrsIndicateSemanticBlockElement(string $tag, array $attributes): bool
    {
        foreach ($attributes as $name => $value) {
            $lowerName = strtolower((string) $name);
            $trimmed = trim((string) $value);
            if ($trimmed === '') {
                continue;
            }
            if ($tag === 'dialog' && $lowerName === 'open') {
                return true;
            }
            if (in_array($lowerName, ['role', 'epub:type', 'lang', 'xml:lang', 'dir', 'title', 'hidden'], true)) {
                return true;
            }
            if (str_starts_with($lowerName, 'aria-')) {
                return true;
            }
            if (str_starts_with($lowerName, 'data-') && $lowerName !== 'data-source') {
                return true;
            }
        }

        return false;
    }

    private function isHtmlSemanticBlockElement(string $tag): bool
    {
        return in_array($tag, $this->htmlSemanticBlockElements(), true);
    }

    /**
     * @return list<string>
     */
    private function htmlSemanticBlockElements(): array
    {
        $elements = ['article', 'aside', 'nav', 'main', 'header', 'footer'];
        $extra = $this->options['writerSemanticBlockElements'] ?? $this->options['semanticBlockElements'] ?? [];
        if (!is_array($extra)) {
            return $elements;
        }

        foreach ($extra as $element) {
            if (!is_scalar($element)) {
                continue;
            }
            $element = strtolower(trim((string) $element));
            if (preg_match('/^[a-z][a-z0-9-]*$/', $element) !== 1) {
                continue;
            }
            $elements[] = $element;
        }

        return array_values(array_unique($elements));
    }

    /**
     * @param array{id:string, classes:list<string>, attributes:array<string, string>} $attrs
     */
    private function renderFormBlockElement(AstNode $node, array $attrs): ?string
    {
        foreach ($attrs['classes'] as $index => $class) {
            if (
                !$this->isHtmlFormBlockElement($class)
                || !$this->divAttrsIndicateFormBlockElement($class, $attrs['attributes'])
            ) {
                continue;
            }

            $retainedClasses = [];
            foreach (array_slice($attrs['classes'], $index + 1) as $retainedClass) {
                if (!$this->isHtmlFormBlockElement($retainedClass)) {
                    $retainedClasses[] = $retainedClass;
                }
            }
            $attrs['classes'] = $this->classesWithoutCslNoStyleMarkers($retainedClasses);

            $lines = ['<' . $class . $this->renderAttrTuple($attrs) . '>'];
            foreach ($node->children as $child) {
                $html = $class === 'fieldset' ? $this->renderFieldsetChild($child) : $this->renderBlock($child);
                if ($html !== '') {
                    $lines[] = $html;
                }
            }
            $lines[] = '</' . $class . '>';

            return implode("\n", $lines);
        }

        return null;
    }

    /**
     * @param array<string, string> $attributes
     */
    private function divAttrsIndicateFormBlockElement(string $tag, array $attributes): bool
    {
        $provingAttributes = match ($tag) {
            'form' => ['action', 'method', 'enctype', 'target', 'name', 'accept-charset', 'autocomplete', 'novalidate'],
            'fieldset' => ['disabled', 'name', 'form'],
            default => [],
        };

        foreach ($provingAttributes as $attribute) {
            if (trim($attributes[$attribute] ?? '') !== '') {
                return true;
            }
        }

        return false;
    }

    private function renderFieldsetChild(AstNode $node): string
    {
        if ($node->type === 'div') {
            $legendHtml = $this->renderLegendBlockElement($node);
            if ($legendHtml !== null) {
                return $legendHtml;
            }
        }

        return $this->renderBlock($node);
    }

    private function renderLegendBlockElement(AstNode $node): ?string
    {
        $attrs = $this->htmlAttrTuple($node);
        foreach ($attrs['classes'] as $index => $class) {
            if ($class !== 'legend') {
                continue;
            }

            $retainedClasses = [];
            foreach (array_slice($attrs['classes'], $index + 1) as $retainedClass) {
                if ($retainedClass !== 'legend') {
                    $retainedClasses[] = $retainedClass;
                }
            }
            $attrs['classes'] = $this->classesWithoutCslNoStyleMarkers($retainedClasses);

            $lines = ['<legend' . $this->renderAttrTuple($attrs) . '>'];
            foreach ($node->children as $child) {
                $html = $this->renderBlock($child);
                if ($html !== '') {
                    $lines[] = $html;
                }
            }
            $lines[] = '</legend>';

            return implode("\n", $lines);
        }

        return null;
    }

    private function isHtmlFormBlockElement(string $tag): bool
    {
        return in_array($tag, ['form', 'fieldset'], true);
    }

    private function divIsWrapper(AstNode $node): bool
    {
        if (count($node->children) !== 1) {
            return false;
        }

        $attrs = $this->attrTuple($node);

        return ($attrs['attributes']['wrapper'] ?? '') === '1';
    }

    /**
     * @return array{id:string, classes:list<string>, attributes:array<string, string>}
     */
    private function wrapperDivAttributes(AstNode $node): array
    {
        $attrs = $this->htmlAttrTuple($node);
        unset($attrs['attributes']['wrapper']);

        return $attrs;
    }

    private function renderDivChild(AstNode $node, bool $cslEntry): string
    {
        if ($cslEntry && $node->type === 'paragraph') {
            return $this->renderInlines($node->children);
        }

        return $this->renderBlock($node);
    }

    /**
     * @param array{id:string, classes:list<string>, attributes:array<string, string>} $attrs
     */
    private function addRootAttributes(string $html, array $attrs): string
    {
        if ($attrs['id'] === '' && $attrs['classes'] === [] && $attrs['attributes'] === []) {
            return $html;
        }

        if (preg_match('/^<([A-Za-z][A-Za-z0-9:-]*)([^>]*)>/', $html, $match) !== 1) {
            return $html;
        }

        $openingTag = $match[0];
        $tag = $match[1];
        $tail = $match[2];
        $selfClosing = str_ends_with(rtrim($tail), '/');
        if ($selfClosing) {
            $tail = rtrim(substr(rtrim($tail), 0, -1));
        }

        $merged = $this->mergeRenderedAttributes($tail, $attrs);
        $replacement = '<' . $tag . $this->renderAttrTuple($merged) . ($selfClosing ? ' /' : '') . '>';

        return $replacement . substr($html, strlen($openingTag));
    }

    /**
     * @param array{id:string, classes:list<string>, attributes:array<string, string>} $attrs
     * @return array{id:string, classes:list<string>, attributes:array<string, string>}
     */
    private function mergeRenderedAttributes(string $tail, array $attrs): array
    {
        $merged = [
            'id' => '',
            'classes' => [],
            'attributes' => [],
        ];

        if (preg_match_all('/\s+([A-Za-z_:][A-Za-z0-9_.:-]*)="([^"]*)"/', $tail, $matches, PREG_SET_ORDER) !== false) {
            foreach ($matches as $match) {
                $name = $match[1];
                $value = html_entity_decode($match[2], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                if ($name === 'id') {
                    $merged['id'] = $value;
                    continue;
                }
                if ($name === 'class') {
                    $classes = preg_split('/\s+/', trim($value)) ?: [];
                    $merged['classes'] = array_values(array_filter($classes, static fn (string $class): bool => $class !== ''));
                    continue;
                }

                $merged['attributes'][$name] = $value;
            }
        }

        if ($merged['id'] === '' && $attrs['id'] !== '') {
            $merged['id'] = $attrs['id'];
        }

        foreach ($attrs['classes'] as $class) {
            if (!in_array($class, $merged['classes'], true)) {
                $merged['classes'][] = $class;
            }
        }

        foreach ($attrs['attributes'] as $name => $value) {
            if (!array_key_exists($name, $merged['attributes'])) {
                $merged['attributes'][$name] = $value;
            }
        }

        return $merged;
    }

    private function renderRawBlock(AstNode $node): string
    {
        $format = strtolower((string) $node->attr('format', ''));
        if (!$this->isRawHtmlFormat($format)) {
            return '';
        }

        return (string) $node->attr('text', $node->attr('html', ''));
    }

    private function isRawHtmlFormat(string $format): bool
    {
        return in_array($format, [
            'html',
            'html4',
            'html5',
            'epub',
            'epub2',
            'epub3',
            's5',
            'slidy',
            'slideous',
            'dzslides',
            'revealjs',
        ], true);
    }

    private function renderFigure(AstNode $node): string
    {
        $lines = ['<figure' . $this->renderHtmlAttributes($node) . '>'];
        foreach ($node->children as $child) {
            $html = $this->renderFigureBodyBlock($child);
            if ($html !== '') {
                $lines[] = $html;
            }
        }

        $caption = $this->renderFigureCaption($node);
        if ($caption !== '') {
            $ariaHidden = $this->figureCaptionMatchesImageAlt($node) ? ' aria-hidden="true"' : '';
            $lines[] = '<figcaption' . $ariaHidden . '>' . $caption . '</figcaption>';
        }
        $lines[] = '</figure>';

        return implode("\n", $lines);
    }

    private function renderFigureBodyBlock(AstNode $node): string
    {
        if ($this->isInlineNode($node)) {
            return $this->renderInline($node);
        }

        return $this->renderBlock($node);
    }

    private function renderFigureCaption(AstNode $node): string
    {
        $captionInlines = $node->attr('captionInlines', null);
        if (is_array($captionInlines)) {
            $html = '';
            foreach ($captionInlines as $inline) {
                if (!$inline instanceof AstNode) {
                    $html = '';
                    break;
                }
                $html .= $this->renderInline($inline);
            }
            if ($html !== '') {
                return $html;
            }
        }

        $caption = (string) $node->attr('caption', '');

        return $caption === '' ? '' : $this->esc($caption);
    }

    private function figureCaptionMatchesImageAlt(AstNode $node): bool
    {
        $caption = $this->plainFigureCaptionText($node);
        if ($caption === '') {
            return false;
        }

        $image = $this->firstFigureImage($node);
        if (!$image instanceof AstNode) {
            return false;
        }

        $alt = (string) $image->attr('alt', '');
        if ($alt === '') {
            $alt = $this->plainInlineText($image->children);
        }

        return $caption === $alt;
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

    private function plainFigureCaptionText(AstNode $node): string
    {
        $captionInlines = $node->attr('captionInlines', null);
        if (is_array($captionInlines)) {
            $allInlines = true;
            foreach ($captionInlines as $inline) {
                if (!$inline instanceof AstNode) {
                    $allInlines = false;
                    break;
                }
            }
            if ($allInlines) {
                return $this->plainInlineText($captionInlines);
            }
        }

        return trim((string) $node->attr('caption', ''));
    }

    private function renderTable(AstNode $node): string
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

        $lines = ['<table' . $this->renderTableAttributes($node) . '>'];
        $this->appendRenderedBlock($lines, $this->renderTableCaption($node));
        $this->appendRenderedBlock($lines, $this->renderTableColgroup($node));

        if ($head instanceof AstNode && $this->tableRows($head) !== []) {
            $lines[] = '<thead' . $this->renderHtmlAttributes($head) . '>';
            $headRowspanOccupancy = [];
            foreach ($this->tableRows($head) as $row) {
                $lines[] = $this->renderTableRow($row, $node, true, 0, $headRowspanOccupancy);
            }
            $lines[] = '</thead>';
        }

        foreach ($bodies as $body) {
            $lines[] = '<tbody' . $this->renderHtmlAttributes($body) . '>';
            $bodyRowspanOccupancy = [];
            $headRows = $body->attr('headRows', []);
            if (is_array($headRows)) {
                foreach ($headRows as $row) {
                    if ($row instanceof AstNode && $row->type === 'table_row') {
                        $lines[] = $this->renderTableRow($row, $node, true, 0, $bodyRowspanOccupancy);
                    }
                }
            }

            $rowHeadColumns = max(0, (int) $body->attr('rowHeadColumns', 0));
            foreach ($this->tableRows($body) as $row) {
                $lines[] = $this->renderTableRow($row, $node, false, $rowHeadColumns, $bodyRowspanOccupancy);
            }
            $lines[] = '</tbody>';
        }

        if ($foot instanceof AstNode && $this->tableRows($foot) !== []) {
            $lines[] = '<tfoot' . $this->renderHtmlAttributes($foot) . '>';
            $footRowspanOccupancy = [];
            foreach ($this->tableRows($foot) as $row) {
                $lines[] = $this->renderTableRow($row, $node, false, 0, $footRowspanOccupancy);
            }
            $lines[] = '</tfoot>';
        }
        $lines[] = '</table>';

        return implode("\n", $lines);
    }

    private function renderTableAttributes(AstNode $node): string
    {
        $attrs = $this->htmlAttrTuple($node);
        $totalWidth = $this->tableTotalExplicitWidth($node);
        if (
            $totalWidth > 0.0
            && $totalWidth < 1.0
            && !array_key_exists('style', $attrs['attributes'])
        ) {
            $attrs['attributes']['style'] = 'width:' . (int) round($totalWidth * 100) . '%;';
        }

        return $this->renderAttrTuple($attrs);
    }

    private function renderTableCaption(AstNode $node): string
    {
        $captionInlines = $node->attr('captionInlines', null);
        if (is_array($captionInlines)) {
            $html = '';
            foreach ($captionInlines as $inline) {
                if (!$inline instanceof AstNode) {
                    $html = '';
                    break;
                }
                $html .= $this->renderInline($inline);
            }
            if ($html !== '') {
                return '<caption>' . $html . '</caption>';
            }
        }

        $caption = (string) $node->attr('caption', '');

        return $caption === '' ? '' : '<caption>' . $this->esc($caption) . '</caption>';
    }

    private function renderTableColgroup(AstNode $node): string
    {
        $widths = $node->attr('widths', null);
        if (!is_array($widths) || $widths === []) {
            return '';
        }

        $hasExplicitWidth = false;
        foreach ($widths as $width) {
            if (is_numeric($width) && (float) $width > 0.0) {
                $hasExplicitWidth = true;
                break;
            }
        }
        if (!$hasExplicitWidth) {
            return '';
        }

        $cols = [];
        foreach ($widths as $width) {
            if (is_numeric($width) && (float) $width > 0.0) {
                $cols[] = '<col style="width: ' . (int) floor((float) $width * 100) . '%" />';
                continue;
            }

            $cols[] = '<col />';
        }

        return '<colgroup>' . implode('', $cols) . '</colgroup>';
    }

    private function tableTotalExplicitWidth(AstNode $node): float
    {
        $widths = $node->attr('widths', null);
        if (!is_array($widths)) {
            return 0.0;
        }

        $total = 0.0;
        foreach ($widths as $width) {
            if (is_numeric($width) && (float) $width > 0.0) {
                $total += (float) $width;
            }
        }

        return $total;
    }

    /**
     * @return list<AstNode>
     */
    private function tableRows(AstNode $section): array
    {
        return array_values(array_filter(
            $section->children,
            static fn (AstNode $child): bool => $child->type === 'table_row'
        ));
    }

    /**
     * @param array<int, int> $rowspanOccupancy
     */
    private function renderTableRow(AstNode $row, AstNode $table, bool $header, int $rowHeadColumns, array &$rowspanOccupancy): string
    {
        $html = '<tr' . $this->renderHtmlAttributes($row) . '>';
        $occupiedColumns = $rowspanOccupancy;
        $nextOccupancy = [];
        foreach ($occupiedColumns as $column => $remainingRows) {
            if ($remainingRows > 1) {
                $nextOccupancy[(int) $column] = $remainingRows - 1;
            }
        }

        $logicalColumn = 0;
        foreach ($row->children as $cell) {
            if ($cell->type !== 'table_cell') {
                continue;
            }

            while (($occupiedColumns[$logicalColumn] ?? 0) > 0) {
                $logicalColumn++;
            }

            $colspan = max(1, (int) $cell->attr('colspan', 1));
            $rowspan = max(1, (int) $cell->attr('rowspan', 1));
            $tag = $header
                || $cell->attr('header') === true
                || ($logicalColumn < $rowHeadColumns && $logicalColumn + $colspan <= $rowHeadColumns)
                ? 'th'
                : 'td';
            $html .= '<' . $tag . $this->renderTableCellAttributes($table, $logicalColumn, $cell) . '>'
                . $this->renderTableCellContents($cell)
                . '</' . $tag . '>';
            if ($rowspan > 1) {
                for ($column = $logicalColumn; $column < $logicalColumn + $colspan; $column++) {
                    $nextOccupancy[$column] = max($nextOccupancy[$column] ?? 0, $rowspan - 1);
                }
            }
            $logicalColumn += $colspan;
        }

        $rowspanOccupancy = $nextOccupancy;

        return $html . '</tr>';
    }

    private function renderTableCellAttributes(AstNode $table, int $logicalColumn, AstNode $cell): string
    {
        $attrs = $this->htmlAttrTuple($cell);
        $sourceStyle = trim((string) ($attrs['attributes']['style'] ?? ''));
        unset($attrs['attributes']['style']);

        $colspan = (int) $cell->attr('colspan', 1);
        if ($colspan > 1) {
            $attrs['attributes']['colspan'] = (string) $colspan;
        }

        $rowspan = (int) $cell->attr('rowspan', 1);
        if ($rowspan > 1) {
            $attrs['attributes']['rowspan'] = (string) $rowspan;
        }

        $styles = [];
        if ($sourceStyle !== '') {
            $styles[] = rtrim($sourceStyle, ';');
        }

        $alignment = (string) $cell->attr('align', '');
        $alignments = $table->attr('alignments', []);
        if ($alignment === '' && is_array($alignments)) {
            $alignment = (string) ($alignments[$logicalColumn] ?? 'default');
        }

        if (
            in_array($alignment, ['left', 'right', 'center'], true)
            && preg_match('/(?:^|;)\s*text-align\s*:/i', $sourceStyle) !== 1
        ) {
            $styles[] = 'text-align:' . $alignment;
        }

        if ($styles !== []) {
            $attrs['attributes']['style'] = implode('; ', $styles);
        }

        return $this->renderAttrTuple($attrs);
    }

    private function renderTableCellContents(AstNode $cell): string
    {
        if ($cell->children === []) {
            return $this->esc((string) $cell->attr('text', ''));
        }

        $html = '';
        foreach ($cell->children as $child) {
            $html .= $this->isInlineNode($child)
                ? $this->renderInline($child)
                : $this->renderBlock($child);
        }

        return $html;
    }

    private function renderList(AstNode $node, bool $ordered): string
    {
        $tag = $ordered ? 'ol' : 'ul';
        $lines = ['<' . $tag . $this->renderListAttributes($node, $ordered) . '>'];
        foreach ($node->children as $item) {
            if ($item->type === 'list_item') {
                $lines[] = $this->renderListItem($item);
            }
        }
        $lines[] = '</' . $tag . '>';

        return implode("\n", $lines);
    }

    private function renderListAttributes(AstNode $node, bool $ordered): string
    {
        $attrs = $this->attrTuple($node);
        if ($ordered) {
            $listAttributes = [];
            $start = (int) $node->attr('start', 1);
            if ($start !== 1) {
                $listAttributes['start'] = (string) $start;
            }

            $type = $this->orderedListHtmlType((string) $node->attr('style', 'default'));
            if ($type !== '') {
                $listAttributes['type'] = $type;
            }

            if ((string) $node->attr('style', '') === 'example' && !in_array('example', $attrs['classes'], true)) {
                $attrs['classes'][] = 'example';
            }

            $attrs['attributes'] = $listAttributes + $attrs['attributes'];

            return $this->renderAttrTuple($attrs);
        }

        if ($this->listIsTaskList($node) && !in_array('task-list', $attrs['classes'], true)) {
            $attrs['classes'][] = 'task-list';
        }

        return $this->renderAttrTuple($attrs);
    }

    private function orderedListHtmlType(string $style): string
    {
        return match ($style) {
            'decimal', 'example' => '1',
            'lower_alpha' => 'a',
            'upper_alpha' => 'A',
            'lower_roman' => 'i',
            'upper_roman' => 'I',
            default => '',
        };
    }

    private function renderListItem(AstNode $item): string
    {
        $parts = [];
        $inlineNodes = [];
        $taskChecked = $item->attr('taskChecked', null);
        $taskPending = is_bool($taskChecked);

        foreach ($item->children as $child) {
            if ($this->isInlineNode($child)) {
                $inlineNodes[] = $child;
                continue;
            }

            $this->appendListItemInlinePart($parts, $inlineNodes, $taskChecked, $taskPending);
            if ($child->type === 'plain') {
                $html = $this->renderInlines($child->children);
                if ($taskPending) {
                    $html = $this->renderTaskListLabel((bool) $taskChecked, $html);
                    $taskPending = false;
                }
                $parts[] = $html;
                continue;
            }

            if ($child->type === 'paragraph') {
                $html = $this->renderInlines($child->children);
                if ($taskPending) {
                    $html = $this->renderTaskListLabel((bool) $taskChecked, $html);
                    $taskPending = false;
                }
                $parts[] = '<p>' . $html . '</p>';
                continue;
            }

            $html = $this->renderBlock($child);
            if ($html !== '') {
                $parts[] = $html;
            }
        }

        $this->appendListItemInlinePart($parts, $inlineNodes, $taskChecked, $taskPending);
        if ($taskPending) {
            $parts[] = $this->renderTaskListLabel((bool) $taskChecked, '');
        }

        if ($parts === []) {
            return '<li></li>';
        }

        return '<li>' . implode("\n", $parts) . '</li>';
    }

    /**
     * @param list<string> $parts
     * @param list<AstNode> $inlineNodes
     */
    private function appendListItemInlinePart(
        array &$parts,
        array &$inlineNodes,
        mixed $taskChecked,
        bool &$taskPending
    ): void {
        if ($inlineNodes === []) {
            return;
        }

        $html = $this->renderInlines($inlineNodes);
        if ($taskPending && is_bool($taskChecked)) {
            $html = $this->renderTaskListLabel($taskChecked, $html);
            $taskPending = false;
        }
        $parts[] = $html;
        $inlineNodes = [];
    }

    private function renderTaskListLabel(bool $checked, string $html): string
    {
        return '<label><input type="checkbox"' . ($checked ? ' checked=""' : '') . ' />' . $html . '</label>';
    }

    private function listIsTaskList(AstNode $node): bool
    {
        if ($node->attr('taskList') === true) {
            return true;
        }

        if ($node->children === []) {
            return false;
        }

        foreach ($node->children as $child) {
            if ($child->type !== 'list_item' || !is_bool($child->attr('taskChecked', null))) {
                return false;
            }
        }

        return true;
    }

    private function renderDefinitionList(AstNode $node): string
    {
        $lines = ['<dl>'];
        foreach ($node->children as $item) {
            if ($item->type !== 'definition_item') {
                continue;
            }

            $children = $item->children;
            $term = array_shift($children);
            if (!$term instanceof AstNode || $term->type !== 'term') {
                $term = new AstNode('term', ['text' => (string) $item->attr('term', '')]);
            }

            $termHtml = $term->children === []
                ? $this->esc((string) $term->attr('text', ''))
                : $this->renderInlines($term->children);
            $lines[] = '<dt>' . $termHtml . '</dt>';

            foreach ($children as $definition) {
                if ($definition->type !== 'definition') {
                    continue;
                }
                $lines[] = '<dd>';
                foreach ($this->renderDefinitionBlocks($definition) as $html) {
                    $lines[] = $html;
                }
                $lines[] = '</dd>';
            }
        }
        $lines[] = '</dl>';

        return implode("\n", $lines);
    }

    private function renderBlockQuote(AstNode $node): string
    {
        $lines = ['<blockquote>'];
        foreach ($node->children as $child) {
            $html = $this->renderBlock($child);
            if ($html !== '') {
                $lines[] = $html;
            }
        }
        $lines[] = '</blockquote>';

        return implode("\n", $lines);
    }

    /**
     * @return list<string>
     */
    private function renderDefinitionBlocks(AstNode $node): array
    {
        $blocks = [];
        foreach ($node->children as $child) {
            $html = $this->renderBlock($child);
            if ($html !== '') {
                $blocks[] = $html;
            }
        }

        return $blocks;
    }

    /**
     * @param list<AstNode> $nodes
     */
    private function renderInlines(array $nodes): string
    {
        $html = '';
        foreach ($nodes as $node) {
            $html .= $this->renderInline($node);
        }

        return $html;
    }

    private function renderInline(AstNode $node): string
    {
        return match ($node->type) {
            'text' => $this->esc((string) $node->attr('text', '')),
            'softbreak' => $this->renderSoftBreak(),
            'linebreak' => $this->renderLineBreak(),
            'code' => $this->renderCode($node),
            'image' => $this->renderImage($node),
            'emph' => '<em>' . $this->renderInlines($node->children) . '</em>',
            'strong' => '<strong>' . $this->renderInlines($node->children) . '</strong>',
            'underline' => '<u>' . $this->renderInlines($node->children) . '</u>',
            'strikeout' => '<del>' . $this->renderInlines($node->children) . '</del>',
            'small_caps' => '<span class="smallcaps">' . $this->renderInlines($node->children) . '</span>',
            'superscript' => '<sup>' . $this->renderInlines($node->children) . '</sup>',
            'subscript' => '<sub>' . $this->renderInlines($node->children) . '</sub>',
            'span' => $this->renderSpan($node),
            'quoted' => $this->renderQuoted($node),
            'link' => $this->renderLink($node),
            'note' => $this->renderNoteReference($node),
            'citation' => $this->renderCitation($node),
            'math' => $this->renderMath($node),
            'raw_html_inline' => (string) $node->attr('html', $node->attr('text', '')),
            'raw_inline' => $this->renderRawInline($node),
            default => $this->renderInlines($node->children),
        };
    }

    private function renderSoftBreak(): string
    {
        return $this->htmlWrapText() === 'preserve' ? "\n" : ' ';
    }

    private function renderLineBreak(): string
    {
        return '<br />' . "\n";
    }

    private function renderRawInline(AstNode $node): string
    {
        $format = strtolower((string) $node->attr('format', ''));
        if (!$this->isRawHtmlFormat($format)) {
            return '';
        }

        return (string) $node->attr('text', $node->attr('html', ''));
    }

    private function htmlWrapText(): string
    {
        $value = (string) ($this->options['writerWrapText'] ?? $this->options['wrap'] ?? 'auto');
        $normalized = strtolower(str_replace(['-', '_', ' '], '', $value));

        return match ($normalized) {
            'preserve', 'wrappreserve' => 'preserve',
            'none', 'wrapnone' => 'none',
            default => 'auto',
        };
    }

    private function renderLink(AstNode $node): string
    {
        $attrs = $this->htmlAttrTuple($node);
        $sourceHref = $attrs['attributes']['href'] ?? '';
        $sourceTitle = $attrs['attributes']['title'] ?? '';
        unset($attrs['attributes']['href'], $attrs['attributes']['title']);

        $url = (string) $node->attr('url', $node->attr('href', $sourceHref));
        $title = (string) $node->attr('title', $sourceTitle);
        $renderedAttrs = ' href="' . $this->esc($url) . '"';
        if ($title !== '') {
            $renderedAttrs .= ' title="' . $this->esc($title) . '"';
        }
        $renderedAttrs .= $this->renderAttrTuple($attrs);

        return '<a' . $renderedAttrs . '>' . $this->renderInlines($this->linksAsSpans($node->children)) . '</a>';
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

    private function renderSpan(AstNode $node): string
    {
        $attrs = $this->spanAttrsWithCslStyle($this->htmlAttrTuple($node));
        $pictureHtml = $this->renderPictureSpanLikeElement($node, $attrs);
        if ($pictureHtml !== null) {
            return $pictureHtml;
        }

        $formControlHtml = $this->renderFormControlSpanLikeElement($node, $attrs);
        if ($formControlHtml !== null) {
            return $formControlHtml;
        }

        $mapHtml = $this->renderMapSpanLikeElement($node, $attrs);
        if ($mapHtml !== null) {
            return $mapHtml;
        }

        $voidHtml = $this->renderVoidSpanLikeElement($attrs);
        if ($voidHtml !== null) {
            return $voidHtml;
        }

        $bdoHtml = $this->renderBdoSpanLikeElement($node, $attrs);
        if ($bdoHtml !== null) {
            return $bdoHtml;
        }

        $progressHtml = $this->renderProgressSpanLikeElement($node, $attrs);
        if ($progressHtml !== null) {
            return $progressHtml;
        }

        $revisionHtml = $this->renderRevisionSpanLikeElement($node, $attrs);
        if ($revisionHtml !== null) {
            return $revisionHtml;
        }

        $spanLike = $this->spanLikeTagsAndClasses($attrs['classes']);
        $html = $this->renderInlines($node->children);

        if ($spanLike === null) {
            $attrs['classes'] = $this->classesWithoutCslNoStyleMarkers($attrs['classes']);

            return '<span' . $this->renderAttrTuple($attrs) . '>' . $html . '</span>';
        }

        $attrs['classes'] = $spanLike['classes'];
        $outerIndex = count($spanLike['tags']) - 1;
        foreach ($spanLike['tags'] as $index => $wrapper) {
            $tag = $wrapper['tag'];
            $wrapperClasses = $wrapper['classes'];
            if ($index === $outerIndex) {
                $outerAttrs = $attrs;
                $outerAttrs['classes'] = array_values(array_unique(array_merge($wrapperClasses, $attrs['classes'])));
                $tagAttrs = $this->renderAttrTuple($outerAttrs);
            } else {
                $tagAttrs = $this->renderClassOnlyAttr($wrapperClasses);
            }
            $html = '<' . $tag . $tagAttrs . '>' . $html . '</' . $tag . '>';
        }

        return $html;
    }

    /**
     * @param array{id:string, classes:list<string>, attributes:array<string, string>} $attrs
     */
    private function renderBdoSpanLikeElement(AstNode $node, array $attrs): ?string
    {
        foreach ($attrs['classes'] as $index => $class) {
            if ($class !== 'bdo' || !$this->spanAttrsIndicateBdoElement($attrs['attributes'])) {
                continue;
            }

            $retainedClasses = [];
            foreach (array_slice($attrs['classes'], $index + 1) as $retainedClass) {
                if (
                    $retainedClass !== 'bdo'
                    && !$this->isSpanLikeClass($retainedClass)
                    && !$this->isHtmlVoidSpanLikeElement($retainedClass)
                    && !$this->isHtmlFormControlSpanLikeElement($retainedClass)
                    && !$this->isHtmlRevisionSpanLikeElement($retainedClass)
                ) {
                    $retainedClasses[] = $retainedClass;
                }
            }
            $attrs['classes'] = $this->classesWithoutCslNoStyleMarkers($retainedClasses);

            return '<bdo' . $this->renderAttrTuple($attrs) . '>' . $this->renderInlines($node->children) . '</bdo>';
        }

        return null;
    }

    /**
     * @param array<string, string> $attributes
     */
    private function spanAttrsIndicateBdoElement(array $attributes): bool
    {
        return in_array(strtolower(trim($attributes['dir'] ?? '')), ['ltr', 'rtl'], true);
    }

    /**
     * @param array{id:string, classes:list<string>, attributes:array<string, string>} $attrs
     */
    private function renderRevisionSpanLikeElement(AstNode $node, array $attrs): ?string
    {
        foreach ($attrs['classes'] as $index => $class) {
            if (!$this->isHtmlRevisionSpanLikeElement($class) || !$this->spanAttrsIndicateRevisionElement($attrs['attributes'])) {
                continue;
            }

            $retainedClasses = [];
            foreach (array_slice($attrs['classes'], $index + 1) as $retainedClass) {
                if (
                    !$this->isHtmlRevisionSpanLikeElement($retainedClass)
                    && !$this->isSpanLikeClass($retainedClass)
                    && !$this->isHtmlVoidSpanLikeElement($retainedClass)
                    && !$this->isHtmlFormControlSpanLikeElement($retainedClass)
                ) {
                    $retainedClasses[] = $retainedClass;
                }
            }
            $attrs['classes'] = $this->classesWithoutCslNoStyleMarkers($retainedClasses);

            return '<' . $class . $this->renderAttrTuple($attrs) . '>' . $this->renderInlines($node->children) . '</' . $class . '>';
        }

        return null;
    }

    private function isHtmlRevisionSpanLikeElement(string $class): bool
    {
        return in_array($class, ['ins', 'del'], true);
    }

    /**
     * @param array<string, string> $attributes
     */
    private function spanAttrsIndicateRevisionElement(array $attributes): bool
    {
        return trim($attributes['cite'] ?? '') !== ''
            || trim($attributes['datetime'] ?? '') !== '';
    }

    /**
     * @param array{id:string, classes:list<string>, attributes:array<string, string>} $attrs
     */
    private function renderProgressSpanLikeElement(AstNode $node, array $attrs): ?string
    {
        foreach ($attrs['classes'] as $index => $class) {
            if ($class !== 'progress' || !$this->spanAttrsIndicateProgressElement($attrs['attributes'])) {
                continue;
            }

            $retainedClasses = [];
            foreach (array_slice($attrs['classes'], $index + 1) as $retainedClass) {
                if (
                    $retainedClass !== 'progress'
                    && !$this->isSpanLikeClass($retainedClass)
                    && !$this->isHtmlVoidSpanLikeElement($retainedClass)
                    && !$this->isHtmlFormControlSpanLikeElement($retainedClass)
                ) {
                    $retainedClasses[] = $retainedClass;
                }
            }
            $attrs['classes'] = $this->classesWithoutCslNoStyleMarkers($retainedClasses);

            return '<progress' . $this->renderAttrTuple($attrs) . '>' . $this->renderInlines($node->children) . '</progress>';
        }

        return null;
    }

    /**
     * @param array<string, string> $attributes
     */
    private function spanAttrsIndicateProgressElement(array $attributes): bool
    {
        return trim($attributes['value'] ?? '') !== ''
            || trim($attributes['max'] ?? '') !== '';
    }

    /**
     * @param array{id:string, classes:list<string>, attributes:array<string, string>} $attrs
     */
    private function renderPictureSpanLikeElement(AstNode $node, array $attrs): ?string
    {
        foreach ($attrs['classes'] as $index => $class) {
            if ($class !== 'picture' || !$this->spanChildrenIndicatePictureElement($node->children)) {
                continue;
            }

            $retainedClasses = [];
            foreach (array_slice($attrs['classes'], $index + 1) as $retainedClass) {
                if (
                    $retainedClass !== 'picture'
                    && !$this->isSpanLikeClass($retainedClass)
                    && !$this->isHtmlVoidSpanLikeElement($retainedClass)
                ) {
                    $retainedClasses[] = $retainedClass;
                }
            }
            $attrs['classes'] = $this->classesWithoutCslNoStyleMarkers($retainedClasses);

            return '<picture' . $this->renderAttrTuple($attrs) . '>' . $this->renderInlines($node->children) . '</picture>';
        }

        return null;
    }

    /**
     * @param list<AstNode> $children
     */
    private function spanChildrenIndicatePictureElement(array $children): bool
    {
        foreach ($children as $child) {
            if ($child->type === 'image') {
                return true;
            }

            if ($child->type !== 'span') {
                continue;
            }

            $attrs = $this->htmlAttrTuple($child);
            foreach ($attrs['classes'] as $class) {
                if ($class === 'source' && $this->spanAttrsIndicateVoidElement($class, $attrs['attributes'])) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param array{id:string, classes:list<string>, attributes:array<string, string>} $attrs
     */
    private function renderMapSpanLikeElement(AstNode $node, array $attrs): ?string
    {
        foreach ($attrs['classes'] as $index => $class) {
            if ($class !== 'map' || !$this->spanAttrsOrChildrenIndicateMapElement($attrs['attributes'], $node->children)) {
                continue;
            }

            $retainedClasses = [];
            foreach (array_slice($attrs['classes'], $index + 1) as $retainedClass) {
                if (
                    $retainedClass !== 'map'
                    && !$this->isSpanLikeClass($retainedClass)
                    && !$this->isHtmlVoidSpanLikeElement($retainedClass)
                    && !$this->isHtmlFormControlSpanLikeElement($retainedClass)
                ) {
                    $retainedClasses[] = $retainedClass;
                }
            }
            $attrs['classes'] = $this->classesWithoutCslNoStyleMarkers($retainedClasses);

            return '<map' . $this->renderAttrTuple($attrs) . '>' . $this->renderInlines($node->children) . '</map>';
        }

        return null;
    }

    /**
     * @param array<string, string> $attributes
     * @param list<AstNode> $children
     */
    private function spanAttrsOrChildrenIndicateMapElement(array $attributes, array $children): bool
    {
        if (trim($attributes['name'] ?? '') !== '') {
            return true;
        }

        foreach ($children as $child) {
            if ($child->type !== 'span') {
                continue;
            }

            $attrs = $this->htmlAttrTuple($child);
            foreach ($attrs['classes'] as $class) {
                if ($class === 'area' && $this->spanAttrsIndicateVoidElement($class, $attrs['attributes'])) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param array{id:string, classes:list<string>, attributes:array<string, string>} $attrs
     */
    private function renderFormControlSpanLikeElement(AstNode $node, array $attrs): ?string
    {
        foreach ($attrs['classes'] as $index => $class) {
            if (
                !$this->isHtmlFormControlSpanLikeElement($class)
                || !$this->spanAttrsIndicateFormControlElement($class, $attrs['attributes'], $node->children)
            ) {
                continue;
            }

            $retainedClasses = [];
            foreach (array_slice($attrs['classes'], $index + 1) as $retainedClass) {
                if (
                    !$this->isSpanLikeClass($retainedClass)
                    && !$this->isHtmlVoidSpanLikeElement($retainedClass)
                    && !$this->isHtmlFormControlSpanLikeElement($retainedClass)
                ) {
                    $retainedClasses[] = $retainedClass;
                }
            }
            $attrs['classes'] = $this->classesWithoutCslNoStyleMarkers($retainedClasses);

            return '<' . $class . $this->renderAttrTuple($attrs) . '>' . $this->renderInlines($node->children) . '</' . $class . '>';
        }

        return null;
    }

    /**
     * @param array<string, string> $attributes
     * @param list<AstNode> $children
     */
    private function spanAttrsIndicateFormControlElement(string $tag, array $attributes, array $children): bool
    {
        $provingAttributes = match ($tag) {
            'select' => ['name', 'form', 'size', 'autocomplete', 'multiple', 'disabled', 'required', 'autofocus'],
            'option' => ['value', 'label', 'selected', 'disabled'],
            'optgroup' => ['label'],
            'textarea' => ['name', 'placeholder', 'rows', 'cols', 'wrap', 'form', 'maxlength', 'minlength', 'dirname', 'disabled', 'readonly', 'required', 'autofocus'],
            default => [],
        };

        foreach ($provingAttributes as $attribute) {
            if (trim($attributes[$attribute] ?? '') !== '') {
                return true;
            }
        }

        if ($tag !== 'select') {
            return false;
        }

        foreach ($children as $child) {
            if ($child->type !== 'span') {
                continue;
            }

            $childAttrs = $this->htmlAttrTuple($child);
            foreach ($childAttrs['classes'] as $class) {
                if (
                    in_array($class, ['option', 'optgroup'], true)
                    && $this->spanAttrsIndicateFormControlElement($class, $childAttrs['attributes'], $child->children)
                ) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param array{id:string, classes:list<string>, attributes:array<string, string>} $attrs
     */
    private function renderVoidSpanLikeElement(array $attrs): ?string
    {
        foreach ($attrs['classes'] as $index => $class) {
            if (
                !$this->isHtmlVoidSpanLikeElement($class)
                || !$this->spanAttrsIndicateVoidElement($class, $attrs['attributes'])
            ) {
                continue;
            }

            $retainedClasses = [];
            foreach (array_slice($attrs['classes'], $index + 1) as $retainedClass) {
                if (
                    !$this->isSpanLikeClass($retainedClass)
                    && !$this->isHtmlVoidSpanLikeElement($retainedClass)
                    && !$this->isHtmlFormControlSpanLikeElement($retainedClass)
                ) {
                    $retainedClasses[] = $retainedClass;
                }
            }
            $attrs['classes'] = $this->classesWithoutCslNoStyleMarkers($retainedClasses);

            $attrs = $this->attrsForVoidSpanLikeElement($class, $attrs);

            return '<' . $this->voidSpanLikeElementTagName($class) . $this->renderAttrTuple($attrs) . ' />';
        }

        return null;
    }

    /**
     * @param array<string, string> $attributes
     */
    private function spanAttrsIndicateVoidElement(string $tag, array $attributes): bool
    {
        if ($tag === 'area') {
            foreach (['href', 'alt', 'coords', 'shape', 'target', 'download', 'rel'] as $attribute) {
                if (trim($attributes[$attribute] ?? '') !== '') {
                    return true;
                }
            }
        }

        if ($tag === 'param') {
            return trim($attributes['name'] ?? '') !== ''
                || trim($attributes['value'] ?? '') !== '';
        }

        if ($tag === 'wbr') {
            foreach ($attributes as $name => $value) {
                $lowerName = strtolower((string) $name);
                if (
                    trim($value) !== ''
                    && (
                        str_starts_with($lowerName, 'data-')
                        || in_array($lowerName, ['title', 'aria-label'], true)
                    )
                ) {
                    return true;
                }
            }
        }

        if ($tag === 'source') {
            return trim($attributes['src'] ?? '') !== ''
                || trim($attributes['srcset'] ?? '') !== '';
        }

        if ($tag === 'track' || $tag === 'embed') {
            return trim($attributes['src'] ?? '') !== '';
        }

        if ($tag === 'input') {
            foreach ([
                'type',
                'name',
                'value',
                'src',
                'alt',
                'placeholder',
                'form',
                'min',
                'max',
                'step',
                'pattern',
                'accept',
                'autocomplete',
                'inputmode',
                'checked',
                'disabled',
                'readonly',
                'required',
                'multiple',
                'autofocus',
            ] as $attribute) {
                if (trim($attributes[$attribute] ?? '') !== '') {
                    return true;
                }
            }
        }

        if ($tag === 'trigger') {
            foreach (['observer', 'ev:observer', 'event', 'ev:event', 'action', 'ref'] as $attribute) {
                if (trim($attributes[$attribute] ?? '') !== '') {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param array{id:string, classes:list<string>, attributes:array<string, string>} $attrs
     * @return array{id:string, classes:list<string>, attributes:array<string, string>}
     */
    private function attrsForVoidSpanLikeElement(string $tag, array $attrs): array
    {
        if ($tag !== 'trigger') {
            return $attrs;
        }

        $attributes = [];
        foreach ($attrs['attributes'] as $name => $value) {
            $attributes[match (strtolower((string) $name)) {
                'observer' => 'ev:observer',
                'event' => 'ev:event',
                default => $name,
            }] = $value;
        }
        $attrs['attributes'] = $attributes;

        return $attrs;
    }

    private function voidSpanLikeElementTagName(string $tag): string
    {
        return $tag === 'trigger' ? 'epub:trigger' : $tag;
    }

    /**
     * @param array{id:string, classes:list<string>, attributes:array<string, string>} $attrs
     * @return array{id:string, classes:list<string>, attributes:array<string, string>}
     */
    private function spanAttrsWithCslStyle(array $attrs): array
    {
        $styles = [];
        if (in_array('csl-no-emph', $attrs['classes'], true)) {
            $styles[] = 'font-style:normal;';
        }
        if (in_array('csl-no-strong', $attrs['classes'], true)) {
            $styles[] = 'font-weight:normal;';
        }
        if (in_array('csl-no-smallcaps', $attrs['classes'], true)) {
            $styles[] = 'font-variant:normal;';
        }

        if ($styles === []) {
            return $attrs;
        }

        $existingStyle = trim($attrs['attributes']['style'] ?? '');
        $attrs['attributes']['style'] = implode('', $styles)
            . ($existingStyle === '' ? '' : rtrim($existingStyle, ';') . ';');

        return $attrs;
    }

    /**
     * @param list<string> $classes
     * @return list<string>
     */
    private function classesWithoutCslNoStyleMarkers(array $classes): array
    {
        return array_values(array_filter(
            $classes,
            static fn (string $class): bool => !in_array($class, [
                'csl-no-emph',
                'csl-no-strong',
                'csl-no-smallcaps',
            ], true)
        ));
    }

    /**
     * @param list<string> $classes
     * @return array{tags:list<array{tag:string, classes:list<string>}>, classes:list<string>}|null
     */
    private function spanLikeTagsAndClasses(array $classes): ?array
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
                array_unshift($retainedClasses, $class);
            }
        }

        $tags = [];
        for ($index = count($classes) - 1; $index >= 0; $index--) {
            if ($classes[$index] === 'smallcaps') {
                $tags[] = ['tag' => 'span', 'classes' => ['smallcaps']];
                continue;
            }
            if ($classes[$index] === 'underline') {
                $tags[] = ['tag' => 'u', 'classes' => []];
            }
        }
        foreach ($classes as $class) {
            if ($this->isHtmlSpanLikeElement($class)) {
                $tags[] = ['tag' => $class, 'classes' => []];
            }
        }

        return $tags === [] ? null : [
            'tags' => $tags,
            'classes' => array_values(array_unique($retainedClasses)),
        ];
    }

    private function isSpanLikeClass(string $class): bool
    {
        return $class === 'smallcaps'
            || $class === 'underline'
            || $this->isHtmlSpanLikeElement($class);
    }

    private function isHtmlSpanLikeElement(string $class): bool
    {
        return in_array($class, ['kbd', 'mark', 'dfn', 'abbr', 'cite', 'small', 'ruby', 'rt', 'rp', 'bdi', 'data', 'meter', 'output', 'time', 'label', 'button', 'canvas', 'iframe', 'audio', 'video', 'object'], true);
    }

    private function isHtmlVoidSpanLikeElement(string $tag): bool
    {
        return in_array($tag, ['area', 'param', 'source', 'track', 'embed', 'input', 'trigger', 'wbr'], true);
    }

    private function isHtmlFormControlSpanLikeElement(string $tag): bool
    {
        return in_array($tag, ['select', 'option', 'optgroup', 'textarea'], true);
    }

    /**
     * @param list<string> $classes
     */
    private function renderClassOnlyAttr(array $classes): string
    {
        return $classes === [] ? '' : ' class="' . $this->esc(implode(' ', $classes)) . '"';
    }

    private function renderCitation(AstNode $node): string
    {
        $citations = $this->citationEntries($node);
        $ids = [];
        foreach ($citations as $citation) {
            $id = (string) ($citation['id'] ?? $citation['citationId'] ?? '');
            if ($id !== '') {
                $ids[] = $id;
            }
        }

        $display = $node->children;
        if ($display === []) {
            $text = (string) $node->attr('text', $ids === [] ? '' : '[' . implode('; ', array_map(
                static fn (string $id): string => '@' . $id,
                $ids
            )) . ']');
            $display = $text === '' ? [] : [new AstNode('text', ['text' => $text])];
        }

        $attrs = [
            'id' => '',
            'classes' => ['citation'],
            'attributes' => [],
        ];
        if ($ids !== []) {
            $attrs['attributes']['data-cites'] = implode(' ', $ids);
        }

        return '<span' . $this->renderAttrTuple($attrs) . '>'
            . $this->renderInlines($this->addBibliorefRoles($display))
            . '</span>';
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function citationEntries(AstNode $node): array
    {
        $citations = $node->attr('citations', null);
        if (is_array($citations) && $citations !== []) {
            $entries = [];
            foreach ($citations as $citation) {
                if ($citation instanceof AstNode) {
                    $entries[] = [
                        'id' => (string) $citation->attr('id', $citation->attr('citationId', '')),
                    ];
                    continue;
                }
                if (is_array($citation)) {
                    $entries[] = $citation;
                }
            }

            return $entries;
        }

        $id = (string) $node->attr('id', $node->attr('citationId', ''));

        return $id === '' ? [] : [['id' => $id]];
    }

    /**
     * @param list<AstNode> $nodes
     * @return list<AstNode>
     */
    private function addBibliorefRoles(array $nodes): array
    {
        $mapped = [];
        foreach ($nodes as $node) {
            $children = $node->children === [] ? [] : $this->addBibliorefRoles($node->children);
            if ($node->type !== 'link') {
                $mapped[] = $children === $node->children
                    ? $node
                    : new AstNode($node->type, $node->attrs, $children);
                continue;
            }

            $linkAttrs = $this->attrTuple($node);
            $url = (string) $node->attr('url', $node->attr('href', $linkAttrs['attributes']['href'] ?? ''));
            if (!str_starts_with($url, '#ref-')) {
                $mapped[] = $children === $node->children
                    ? $node
                    : new AstNode($node->type, $node->attrs, $children);
                continue;
            }

            $attrs = $node->attrs;
            $attributes = $attrs['attributes'] ?? [];
            if (!is_array($attributes)) {
                $attributes = [];
            }
            $attributes['role'] ??= 'doc-biblioref';
            $attrs['attributes'] = $attributes;
            $mapped[] = new AstNode('link', $attrs, $children);
        }

        return $mapped;
    }

    private function renderNoteReference(AstNode $node): string
    {
        $number = $this->nextFootnoteNumber++;
        $this->footnotes[] = [
            'number' => $number,
            'node' => $node,
        ];

        return '<a href="#fn' . $number . '" class="footnote-ref" id="fnref' . $number . '" role="doc-noteref"><sup>'
            . $number
            . '</sup></a>';
    }

    private function renderCode(AstNode $node): string
    {
        $rawText = (string) $node->attr('text', '');
        $text = $this->esc($rawText);
        $attrs = $this->attrTuple($node);
        $highlightLanguage = $this->highlightLanguage($attrs['classes']);

        if ($highlightLanguage !== null) {
            $codeAttrs = $attrs;
            $codeAttrs['classes'] = $this->highlightedCodeClasses($attrs['classes'], $highlightLanguage);
            $code = '<code' . $this->renderAttrTuple($codeAttrs) . '>'
                . $this->highlightCodeText($rawText, $highlightLanguage)
                . '</code>';

            if (in_array('sample', $attrs['classes'], true)) {
                return '<samp>' . $code . '</samp>';
            }

            if (in_array('variable', $attrs['classes'], true)) {
                return '<var>' . $code . '</var>';
            }

            return $code;
        }

        if ($this->isBareCodeRole($attrs, 'sample')) {
            return '<samp>' . $text . '</samp>';
        }

        if ($this->isBareCodeRole($attrs, 'variable')) {
            return '<var>' . $text . '</var>';
        }

        return '<code' . $this->renderAttrTuple($attrs) . '>' . $text . '</code>';
    }

    private function renderMath(AstNode $node): string
    {
        $text = (string) $node->attr('text', '');
        $display = $node->attr('display') === true;
        $class = $display ? 'display' : 'inline';

        return match ($this->htmlMathMethod()) {
            'webtex' => $this->renderWebTeXMath($text, $display, $class),
            'gladtex' => $this->renderGladTeXMath($text, $display),
            'mathml' => $this->renderMathML($node, $text, $display, $class),
            'mathjax' => '<span class="math ' . $class . '">'
                . $this->esc(($display ? '\\[' : '\\(') . $text . ($display ? '\\]' : '\\)'))
                . '</span>',
            'katex' => '<span class="math ' . $class . '">' . $this->esc($text) . '</span>',
            default => '<span class="math ' . $class . '">' . $this->esc($text) . '</span>',
        };
    }

    private function renderMathML(AstNode $node, string $text, bool $display, string $class): string
    {
        $mathml = $node->attr('mathml', $node->attr('html', ''));
        if (is_scalar($mathml)) {
            $mathml = trim((string) $mathml);
            if ($this->looksLikeMathMLElement($mathml)) {
                return $this->mathMLWithRequiredAttributes($mathml, $display);
            }
        }

        $generated = $this->texMathToMathML($text, $display);
        if ($generated !== '') {
            return $generated;
        }

        return '<span class="math ' . $class . '">' . $this->esc($text) . '</span>';
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

    private function texMathToMathML(string $text, bool $display): string
    {
        $source = trim($text);
        if ($source === '') {
            return '';
        }

        $parseSource = $this->preprocessTexMathSource($source);
        if ($parseSource === '') {
            return '';
        }

        $offset = 0;
        $body = $this->parseTexMathRow($parseSource, $offset, '');
        if (trim(substr($parseSource, $offset)) !== '') {
            $body = '<mtext>' . $this->esc($source) . '</mtext>';
        }
        if ($body === '') {
            return '';
        }

        $attributes = ' xmlns="http://www.w3.org/1998/Math/MathML"';
        if ($display) {
            $attributes .= ' display="block"';
        }

        return '<math' . $attributes . '><semantics>'
            . $this->mathMLRow($body)
            . '<annotation encoding="application/x-tex">' . $this->esc($source) . '</annotation>'
            . '</semantics></math>';
    }

    private function preprocessTexMathSource(string $source): string
    {
        $source = $this->stripTexMathIgnorable($source);
        $macros = [];
        $source = $this->extractTexMathOperatorDeclarations($source, $macros);
        $source = $this->extractTexMathMacroDefinitions($source, $macros);

        return $this->expandTexMathMacros($source, $macros);
    }

    private function stripTexMathIgnorable(string $source): string
    {
        $output = '';
        $offset = 0;
        $length = strlen($source);
        while ($offset < $length) {
            $char = $source[$offset];
            if ($char === '%' && !$this->texMathCharIsEscaped($source, $offset)) {
                while ($offset < $length && $source[$offset] !== "\n") {
                    $offset++;
                }
                if ($offset < $length) {
                    $output .= ' ';
                    $offset++;
                }
                continue;
            }

            if ($char !== '\\') {
                $output .= $char;
                $offset++;
                continue;
            }

            $commandStart = $offset;
            $offset++;
            $command = $this->readTexMathCommandName($source, $offset);
            if ($command === 'nonumber' || $command === 'allowbreak') {
                continue;
            }

            if ($command === 'label' || $command === 'tag') {
                $labelOffset = $offset;
                if ($command === 'tag' && ($source[$labelOffset] ?? '') === '*') {
                    $labelOffset++;
                }
                $group = $this->readTexMathRawGroup($source, $labelOffset);
                if ($group !== null) {
                    $offset = $labelOffset;
                    continue;
                }
            }

            $output .= substr($source, $commandStart, $offset - $commandStart);
        }

        return $output;
    }

    private function texMathCharIsEscaped(string $source, int $offset): bool
    {
        $slashes = 0;
        for ($cursor = $offset - 1; $cursor >= 0 && $source[$cursor] === '\\'; $cursor--) {
            $slashes++;
        }

        return $slashes % 2 === 1;
    }

    /**
     * @param array<string,array{args:int,default:?string,body:string}> $macros
     */
    private function extractTexMathOperatorDeclarations(string $source, array &$macros): string
    {
        $output = '';
        $offset = 0;
        $length = strlen($source);
        while ($offset < $length) {
            if ($source[$offset] !== '\\') {
                $output .= $source[$offset];
                $offset++;
                continue;
            }

            $commandStart = $offset;
            $offset++;
            $command = $this->readTexMathCommandName($source, $offset);
            if ($command !== 'DeclareMathOperator') {
                $output .= substr($source, $commandStart, $offset - $commandStart);
                continue;
            }

            $definitionOffset = $offset;
            $star = '';
            $this->skipTexMathWhitespace($source, $definitionOffset);
            if (($source[$definitionOffset] ?? '') === '*') {
                $star = '*';
                $definitionOffset++;
            }

            $name = $this->readTexMathMacroDefinitionName($source, $definitionOffset);
            $body = $this->readTexMathRawGroup($source, $definitionOffset);
            if ($name === null || $body === null) {
                $output .= substr($source, $commandStart, $offset - $commandStart);
                continue;
            }

            $macros[$name] = [
                'args' => 0,
                'default' => null,
                'body' => '\\operatorname' . $star . '{' . $body . '}',
            ];
            $offset = $definitionOffset;
        }

        return $output;
    }

    /**
     * @param array<string,array{args:int,default:?string,body:string}> $macros
     */
    private function extractTexMathMacroDefinitions(string $source, array &$macros): string
    {
        $output = '';
        $offset = 0;
        $length = strlen($source);
        while ($offset < $length) {
            if ($source[$offset] !== '\\') {
                $output .= $source[$offset];
                $offset++;
                continue;
            }

            $commandStart = $offset;
            $offset++;
            $command = $this->readTexMathCommandName($source, $offset);
            if (!in_array($command, ['newcommand', 'renewcommand', 'providecommand'], true)) {
                $output .= substr($source, $commandStart, $offset - $commandStart);
                continue;
            }

            $definitionOffset = $offset;
            $this->skipTexMathWhitespace($source, $definitionOffset);
            if (($source[$definitionOffset] ?? '') === '*') {
                $definitionOffset++;
            }

            $name = $this->readTexMathMacroDefinitionName($source, $definitionOffset);
            if ($name === null) {
                $output .= substr($source, $commandStart, $offset - $commandStart);
                continue;
            }

            $argCountRaw = $this->readTexMathOptionalRawBracket($source, $definitionOffset);
            $argCount = is_string($argCountRaw) && preg_match('/^\s*[0-9]\s*$/', $argCountRaw) === 1
                ? (int) trim($argCountRaw)
                : 0;
            $defaultArgument = $argCount > 0 ? $this->readTexMathOptionalRawBracket($source, $definitionOffset) : null;
            $body = $this->readTexMathRawGroup($source, $definitionOffset);
            if ($body === null) {
                $output .= substr($source, $commandStart, $offset - $commandStart);
                continue;
            }

            if ($command !== 'providecommand' || !array_key_exists($name, $macros)) {
                $macros[$name] = [
                    'args' => $argCount,
                    'default' => $defaultArgument,
                    'body' => $body,
                ];
            }
            $offset = $definitionOffset;
        }

        return $output;
    }

    private function readTexMathMacroDefinitionName(string $source, int &$offset): ?string
    {
        $this->skipTexMathWhitespace($source, $offset);
        if (($source[$offset] ?? '') === '{') {
            $name = $this->readTexMathRawGroup($source, $offset);
            if (!is_string($name) || !str_starts_with($name, '\\')) {
                return null;
            }

            return substr($name, 1);
        }

        if (($source[$offset] ?? '') !== '\\') {
            return null;
        }

        $offset++;
        $name = $this->readTexMathCommandName($source, $offset);

        return $name === '' ? null : $name;
    }

    private function readTexMathOptionalRawBracket(string $source, int &$offset): ?string
    {
        $this->skipTexMathWhitespace($source, $offset);
        if (($source[$offset] ?? '') !== '[') {
            return null;
        }

        $offset++;
        $start = $offset;
        $depth = 1;
        $length = strlen($source);
        while ($offset < $length) {
            $char = $source[$offset];
            if ($char === '\\') {
                $offset += 2;
                continue;
            }
            if ($char === '[') {
                $depth++;
            } elseif ($char === ']') {
                $depth--;
                if ($depth === 0) {
                    $text = substr($source, $start, $offset - $start);
                    $offset++;
                    return $text;
                }
            }
            $offset++;
        }

        return null;
    }

    /**
     * @param array<string,array{args:int,default:?string,body:string}> $macros
     */
    private function expandTexMathMacros(string $source, array $macros): string
    {
        if ($macros === []) {
            return $source;
        }

        for ($iteration = 0; $iteration < 20; $iteration++) {
            $changed = false;
            $output = '';
            $offset = 0;
            $length = strlen($source);
            while ($offset < $length) {
                if ($source[$offset] !== '\\') {
                    $output .= $source[$offset];
                    $offset++;
                    continue;
                }

                $commandStart = $offset;
                $offset++;
                $command = $this->readTexMathCommandName($source, $offset);
                $macro = $macros[$command] ?? null;
                if ($macro === null) {
                    $output .= substr($source, $commandStart, $offset - $commandStart);
                    continue;
                }

                $argumentOffset = $offset;
                $arguments = [];
                $valid = true;
                $firstRequiredArgument = 1;
                if ($macro['default'] !== null && $macro['args'] > 0) {
                    $optional = $this->readTexMathOptionalRawBracket($source, $argumentOffset);
                    $arguments[] = $optional ?? $macro['default'];
                    $firstRequiredArgument = 2;
                }
                for ($argumentIndex = $firstRequiredArgument; $argumentIndex <= $macro['args']; $argumentIndex++) {
                    $argument = $this->readTexMathRawArgument($source, $argumentOffset);
                    if ($argument === null) {
                        $valid = false;
                        break;
                    }
                    $arguments[] = $argument;
                }

                if (!$valid) {
                    $output .= substr($source, $commandStart, $offset - $commandStart);
                    continue;
                }

                $output .= $this->applyTexMathMacroBody($macro['body'], $arguments);
                $offset = $argumentOffset;
                $changed = true;
            }

            $source = $output;
            if (!$changed) {
                break;
            }
        }

        return $source;
    }

    private function readTexMathRawArgument(string $source, int &$offset): ?string
    {
        $this->skipTexMathWhitespace($source, $offset);
        $char = $source[$offset] ?? '';
        if ($char === '') {
            return null;
        }

        if ($char === '{') {
            return $this->readTexMathRawGroup($source, $offset);
        }

        if ($char === '\\') {
            $offset++;
            $command = $this->readTexMathCommandName($source, $offset);
            return '\\' . $command;
        }

        $offset++;
        return $char;
    }

    /**
     * @param list<string> $arguments
     */
    private function applyTexMathMacroBody(string $body, array $arguments): string
    {
        $output = '';
        $length = strlen($body);
        for ($offset = 0; $offset < $length; $offset++) {
            $char = $body[$offset];
            if ($char === '\\' && ($body[$offset + 1] ?? '') === '#') {
                $output .= '\\#';
                $offset++;
                continue;
            }
            if ($char === '#' && ctype_digit($body[$offset + 1] ?? '') && $body[$offset + 1] !== '0') {
                $argumentIndex = (int) $body[$offset + 1] - 1;
                $output .= $arguments[$argumentIndex] ?? '#' . $body[$offset + 1];
                $offset++;
                continue;
            }

            $output .= $char;
        }

        return $output;
    }

    private function parseTexMathRow(string $source, int &$offset, string $terminator): string
    {
        $items = [];
        $length = strlen($source);
        while ($offset < $length) {
            $char = $source[$offset];
            if ($terminator !== '' && $char === $terminator) {
                $offset++;
                break;
            }
            if (ctype_space($char)) {
                $offset++;
                continue;
            }

            $infixOffset = $offset;
            $infixCommand = $this->readTexMathInfixFractionCommand($source, $infixOffset);
            if ($infixCommand !== null && $items !== []) {
                $denominatorOffset = $infixOffset;
                $denominator = $this->parseTexMathRow($source, $denominatorOffset, $terminator);
                if ($denominator !== '') {
                    $offset = $denominatorOffset;
                    return $this->texMathInfixFractionElement($infixCommand, implode('', $items), $denominator);
                }
            }

            $atom = $this->parseTexMathScriptedAtom($source, $offset);
            if ($atom === '') {
                $offset++;
                continue;
            }
            $items[] = $atom;
        }

        return implode('', $items);
    }

    private function readTexMathInfixFractionCommand(string $source, int &$offset): ?string
    {
        $this->skipTexMathWhitespace($source, $offset);
        if (($source[$offset] ?? '') !== '\\') {
            return null;
        }

        $commandOffset = $offset + 1;
        $command = $this->readTexMathCommandName($source, $commandOffset);
        if (!in_array($command, ['over', 'atop', 'choose', 'brack', 'brace'], true)) {
            return null;
        }

        $offset = $commandOffset;

        return $command;
    }

    private function parseTexMathScriptedAtom(string $source, int &$offset): string
    {
        $base = $this->parseTexMathAtom($source, $offset);
        if ($base === '') {
            return '';
        }

        $limitModifier = $this->consumeTexMathLimitModifier($source, $offset);
        $useUnderOverLimits = $limitModifier !== 'nolimits'
            && $limitModifier !== null
            && $this->texMathSupportsUnderOverLimits($base);
        $superscript = '';
        $subscript = '';
        $length = strlen($source);
        while ($offset < $length) {
            $this->skipTexMathWhitespace($source, $offset);
            $marker = $source[$offset] ?? '';
            if ($marker === "'") {
                $argument = $this->consumeTexMathPrimeSuffix($source, $offset);
                if ($argument !== '') {
                    $superscript .= $argument;
                }
                continue;
            }
            if ($marker !== '^' && $marker !== '_') {
                break;
            }
            $offset++;
            $argument = $this->parseTexMathArgument($source, $offset);
            if ($argument === '') {
                continue;
            }
            if ($marker === '^' && $superscript === '') {
                $superscript = $argument;
            } elseif ($marker === '_' && $subscript === '') {
                $subscript = $argument;
            }
        }

        if ($useUnderOverLimits) {
            if ($superscript !== '' && $subscript !== '') {
                return '<munderover>' . $base . $this->mathMLRow($subscript) . $this->mathMLRow($superscript) . '</munderover>';
            }
            if ($superscript !== '') {
                return '<mover>' . $base . $this->mathMLRow($superscript) . '</mover>';
            }
            if ($subscript !== '') {
                return '<munder>' . $base . $this->mathMLRow($subscript) . '</munder>';
            }
        }

        if ($superscript !== '' && $subscript !== '') {
            return '<msubsup>' . $base . $this->mathMLRow($subscript) . $this->mathMLRow($superscript) . '</msubsup>';
        }
        if ($superscript !== '') {
            return '<msup>' . $base . $this->mathMLRow($superscript) . '</msup>';
        }
        if ($subscript !== '') {
            return '<msub>' . $base . $this->mathMLRow($subscript) . '</msub>';
        }

        return $base;
    }

    private function consumeTexMathLimitModifier(string $source, int &$offset): ?string
    {
        $this->skipTexMathWhitespace($source, $offset);
        if (($source[$offset] ?? '') !== '\\') {
            return null;
        }

        $commandOffset = $offset + 1;
        $command = $this->readTexMathCommandName($source, $commandOffset);
        if (!in_array($command, ['limits', 'nolimits', 'displaylimits'], true)) {
            return null;
        }

        $offset = $commandOffset;

        return $command;
    }

    private function texMathSupportsUnderOverLimits(string $base): bool
    {
        if (preg_match('/^<mi mathvariant="normal">[^<]+<\/mi>$/', $base) === 1) {
            return true;
        }

        return in_array($base, [
            '<mo>∫</mo>',
            '<mo>∮</mo>',
            '<mo>∬</mo>',
            '<mo>∭</mo>',
            '<mo>⨌</mo>',
            '<mo>∯</mo>',
            '<mo>∰</mo>',
            '<mo>∫⋯∫</mo>',
            '<mo>∑</mo>',
            '<mo>⅀</mo>',
            '<mo>∏</mo>',
            '<mo>∐</mo>',
            '<mo>⋃</mo>',
            '<mo>⋂</mo>',
            '<mo>⨆</mo>',
            '<mo>⋁</mo>',
            '<mo>⋀</mo>',
            '<mo>⨁</mo>',
            '<mo>⨂</mo>',
            '<mo>⨀</mo>',
            '<mo>⨄</mo>',
            '<mi>lim</mi>',
            '<mi>inf</mi>',
            '<mi>sup</mi>',
        ], true);
    }

    private function parseTexMathAtom(string $source, int &$offset): string
    {
        $this->skipTexMathWhitespace($source, $offset);
        $char = $source[$offset] ?? '';
        if ($char === '') {
            return '';
        }

        if ($char === '{') {
            $offset++;
            return $this->mathMLRow($this->parseTexMathRow($source, $offset, '}'));
        }
        if ($char === '}') {
            return '';
        }
        if ($char === '\\') {
            return $this->parseTexMathCommand($source, $offset);
        }
        if (ctype_digit($char)) {
            return '<mn>' . $this->esc($this->readTexMathNumber($source, $offset)) . '</mn>';
        }
        $identifier = $this->readTexMathIdentifier($source, $offset);
        if ($identifier !== '') {
            return '<mi>' . $this->esc($identifier) . '</mi>';
        }

        return '<mo>' . $this->esc($this->readTexMathCharacter($source, $offset)) . '</mo>';
    }

    private function consumeTexMathPrimeSuffix(string $source, int &$offset): string
    {
        $count = 0;
        $length = strlen($source);
        while ($offset < $length && $source[$offset] === "'") {
            $count++;
            $offset++;
        }

        if ($count === 0) {
            return '';
        }

        $prime = [
            1 => '′',
            2 => '″',
            3 => '‴',
            4 => '⁗',
        ][$count] ?? str_repeat('′', $count);

        return '<mo>' . $this->esc($prime) . '</mo>';
    }

    private function parseTexMathCommand(string $source, int &$offset): string
    {
        $commandStart = $offset;
        $offset++;
        $command = $this->readTexMathCommandName($source, $offset);
        if ($command === '') {
            return '<mo>\\</mo>';
        }

        if (in_array($command, ['hline', 'hdashline'], true)) {
            return '';
        }

        if (in_array($command, ['cline', 'hhline'], true)) {
            $this->readTexMathRawGroup($source, $offset);
            return '';
        }

        if ($command === 'left') {
            $afterCommand = $offset;
            $leftDelimiter = $this->readTexMathDelimiter($source, $offset);
            $bodyOffset = $offset;
            $fenced = $this->parseTexMathRowUntilRight($source, $bodyOffset);
            if ($fenced !== null) {
                $offset = $bodyOffset;
                return $this->texMathFencedRow($fenced[0], $leftDelimiter, $fenced[1]);
            }
            $offset = $afterCommand;
        }

        if ($command === 'middle') {
            $delimiter = $this->readTexMathDelimiter($source, $offset);
            return $delimiter === null ? '' : '<mo stretchy="true">' . $this->esc($delimiter) . '</mo>';
        }

        if ($command === 'not') {
            $negated = $this->readTexMathNegatedRelation($source, $offset);
            return $negated ?? '<mo>¬</mo>';
        }

        if ($command === 'begin') {
            $environmentOffset = $offset;
            $rawEnvironment = $this->readTexMathRawGroup($source, $environmentOffset);
            $environment = is_string($rawEnvironment) ? $this->texMathNormalizedEnvironmentName($rawEnvironment) : null;
            if ($environment === 'array' || $environment === 'subarray') {
                $arrayOffset = $environmentOffset;
                $columnSpec = $this->readTexMathRawGroup($source, $arrayOffset);
                if ($columnSpec !== null) {
                    $bodyOffset = $arrayOffset;
                    $body = $this->readTexMathEnvironmentBody($source, $bodyOffset, $rawEnvironment);
                    if ($body !== null) {
                        $offset = $bodyOffset;
                        return $this->texMathMatrixToMathML(
                            $body,
                            null,
                            null,
                            $this->texMathArrayColumnAlign($columnSpec)
                        );
                    }
                }

                return $this->texMathMalformedEnvironmentFallback($source, $offset, $commandStart);
            }

            if ($environment === 'equation') {
                $bodyOffset = $environmentOffset;
                $body = $this->readTexMathEnvironmentBody($source, $bodyOffset, $rawEnvironment);
                if ($body !== null) {
                    $bodyParseOffset = 0;
                    $bodyContent = $this->parseTexMathRow($body, $bodyParseOffset, '');
                    if ($bodyContent !== '' && trim(substr($body, $bodyParseOffset)) === '') {
                        $offset = $bodyOffset;
                        return $this->mathMLRow($bodyContent);
                    }
                }

                return $this->texMathMalformedEnvironmentFallback($source, $offset, $commandStart);
            }

            $fences = is_string($environment) ? $this->texMathMatrixEnvironmentFences($environment) : null;
            if ($fences !== null) {
                $bodyOffset = $environmentOffset;
                if ($this->texMathEnvironmentConsumesLeadingGroup($environment)) {
                    $pairCount = $this->readTexMathRawGroup($source, $bodyOffset);
                    if ($pairCount === null) {
                        return $this->texMathMalformedEnvironmentFallback($source, $offset, $commandStart);
                    }
                }

                $body = $this->readTexMathEnvironmentBody($source, $bodyOffset, $rawEnvironment);
                if ($body !== null) {
                    $offset = $bodyOffset;
                    return $this->texMathMatrixToMathML(
                        $body,
                        $fences[0],
                        $fences[1],
                        $this->texMathMatrixEnvironmentColumnAlign($environment)
                    );
                }

                return $this->texMathMalformedEnvironmentFallback($source, $offset, $commandStart);
            }
        }

        if (in_array($command, ['frac', 'dfrac', 'tfrac'], true)) {
            $numerator = $this->parseTexMathArgument($source, $offset);
            $denominator = $this->parseTexMathArgument($source, $offset);
            if ($numerator !== '' && $denominator !== '') {
                return $this->texMathFractionElement($numerator, $denominator, $this->texMathFractionDisplayStyle($command));
            }
        }

        if (in_array($command, ['binom', 'dbinom', 'tbinom'], true)) {
            $numerator = $this->parseTexMathArgument($source, $offset);
            $denominator = $this->parseTexMathArgument($source, $offset);
            if ($numerator !== '' && $denominator !== '') {
                $fraction = $this->texMathFractionElement($numerator, $denominator, null, false);
                $binomial = $this->texMathFencedRow($fraction, '(', ')');

                return $this->texMathApplyDisplayStyle($binomial, $this->texMathFractionDisplayStyle($command));
            }
        }

        if ($command === 'genfrac') {
            $leftFence = $this->readTexMathRawGroup($source, $offset);
            $rightFence = $this->readTexMathRawGroup($source, $offset);
            $lineThickness = $this->readTexMathRawGroup($source, $offset);
            $style = $this->readTexMathRawGroup($source, $offset);
            $numerator = $this->parseTexMathArgument($source, $offset);
            $denominator = $this->parseTexMathArgument($source, $offset);
            if (
                $leftFence !== null
                && $rightFence !== null
                && $lineThickness !== null
                && $style !== null
                && $numerator !== ''
                && $denominator !== ''
            ) {
                return $this->texMathGeneralFractionElement(
                    $leftFence,
                    $rightFence,
                    $lineThickness,
                    $style,
                    $numerator,
                    $denominator
                );
            }
        }

        if ($command === 'sqrt') {
            $rootIndex = $this->parseTexMathOptionalBracketArgument($source, $offset);
            $radicand = $this->parseTexMathArgument($source, $offset);
            if ($radicand !== '') {
                if ($rootIndex !== '') {
                    return '<mroot>' . $this->mathMLRow($radicand) . $this->mathMLRow($rootIndex) . '</mroot>';
                }

                return '<msqrt>' . $this->mathMLRow($radicand) . '</msqrt>';
            }
        }

        $overscript = [
            'acute' => '´',
            'breve' => '˘',
            'check' => 'ˇ',
            'ddot' => '¨',
            'dot' => '˙',
            'grave' => 'ˋ',
            'mathring' => '˚',
            'overline' => '¯',
            'bar' => '¯',
            'overbar' => '¯',
            'wideoverbar' => '¯',
            'hat' => '^',
            'widehat' => '^',
            'tilde' => '~',
            'widetilde' => '~',
            'widebreve' => '˘',
            'ocirc' => '˚',
            'widecheck' => 'ˇ',
            'vec' => '→',
            'overrightarrow' => '→',
            'overleftarrow' => '←',
            'overleftrightarrow' => '↔',
            'leftharpoonaccent' => '↼',
            'overleftharpoon' => '↼',
            'rightharpoonaccent' => '⇀',
            'overrightharpoon' => '⇀',
            'dddot' => '⃛',
            'ddddot' => '⃜',
            'asteraccent' => '*',
            'ovhook' => '̉',
            'candra' => '̐',
            'oturnedcomma' => '̒',
            'ocommatopright' => '̕',
            'droang' => '̚',
            'vertoverlay' => '⃒',
            'annuity' => '⃧',
            'widebridgeabove' => '⃩',
        ];
        if (isset($overscript[$command])) {
            $base = $this->parseTexMathArgument($source, $offset);
            if ($base !== '') {
                return '<mover accent="true">' . $this->mathMLRow($base) . '<mo stretchy="true">' . $this->esc($overscript[$command]) . '</mo></mover>';
            }
        }

        $underscript = [
            'underleftarrow' => '←',
            'underrightarrow' => '→',
            'underleftrightarrow' => '↔',
            'wideutilde' => '~',
            'mathunderbar' => '_',
            'threeunderdot' => '⃨',
            'underrightharpoondown' => '⇁',
            'underleftharpoondown' => '↽',
        ];
        if (isset($underscript[$command])) {
            $base = $this->parseTexMathArgument($source, $offset);
            if ($base !== '') {
                return '<munder accentunder="true">' . $this->mathMLRow($base) . '<mo stretchy="true">' . $this->esc($underscript[$command]) . '</mo></munder>';
            }
        }

        if ($command === 'notaccent') {
            $base = $this->parseTexMathArgument($source, $offset);
            if ($base !== '') {
                return '<menclose notation="updiagonalstrike">' . $this->mathMLRow($base) . '</menclose>';
            }
        }

        if ($command === 'underline') {
            $base = $this->parseTexMathArgument($source, $offset);
            if ($base !== '') {
                return '<munder accentunder="true">' . $this->mathMLRow($base) . '<mo stretchy="true">_</mo></munder>';
            }
        }

        $overUnderOperators = [
            'overbracket' => ['mover', '⎴'],
            'underbracket' => ['munder', '⎵'],
            'overparen' => ['mover', '⏜'],
            'underparen' => ['munder', '⏝'],
            'overbrace' => ['mover', '⏞'],
            'underbrace' => ['munder', '⏟'],
        ];
        if (isset($overUnderOperators[$command])) {
            $base = $this->parseTexMathArgument($source, $offset);
            if ($base !== '') {
                [$wrapper, $operator] = $overUnderOperators[$command];

                return '<' . $wrapper . '>' . $this->mathMLRow($base) . '<mo stretchy="true">' . $this->esc($operator) . '</mo></' . $wrapper . '>';
            }
        }

        if ($command === 'overset' || $command === 'stackrel') {
            $above = $this->parseTexMathArgument($source, $offset);
            $base = $this->parseTexMathArgument($source, $offset);
            if ($above !== '' && $base !== '') {
                return '<mover>' . $this->mathMLRow($base) . $this->mathMLRow($above) . '</mover>';
            }
        }

        if ($command === 'underset') {
            $below = $this->parseTexMathArgument($source, $offset);
            $base = $this->parseTexMathArgument($source, $offset);
            if ($below !== '' && $base !== '') {
                return '<munder>' . $this->mathMLRow($base) . $this->mathMLRow($below) . '</munder>';
            }
        }

        if ($command === 'substack') {
            $body = $this->readTexMathRawGroup($source, $offset);
            if (is_string($body)) {
                return $this->texMathMatrixToMathML($body, null, null, 'center');
            }
        }

        $styleDeclarationAttributes = $this->texMathStyleDeclarationAttributes($command);
        if ($styleDeclarationAttributes !== null) {
            $base = $this->parseTexMathArgument($source, $offset);
            if ($base !== '') {
                return '<mstyle ' . $styleDeclarationAttributes . '>' . $this->mathMLRow($base) . '</mstyle>';
            }
        }

        $enclosureNotation = $this->texMathEnclosureNotation($command);
        if ($enclosureNotation !== null) {
            $base = $this->parseTexMathArgument($source, $offset);
            if ($base !== '') {
                return '<menclose notation="' . $this->esc($enclosureNotation) . '">' . $this->mathMLRow($base) . '</menclose>';
            }
        }

        if (in_array($command, ['mod', 'pmod', 'pod'], true)) {
            $argument = $this->parseTexMathArgument($source, $offset);
            if ($argument !== '') {
                return $this->texMathModuloElement($command, $argument);
            }
        }

        if (in_array($command, ['color', 'textcolor'], true)) {
            $color = $this->readTexMathRawGroup($source, $offset);
            $base = $this->parseTexMathArgument($source, $offset);
            if ($color !== null && trim($color) !== '' && $base !== '') {
                return $this->texMathMStyleElement('mathcolor', $color, $base);
            }
        }

        if ($command === 'colorbox') {
            $color = $this->readTexMathRawGroup($source, $offset);
            $base = $this->parseTexMathArgument($source, $offset);
            if ($color !== null && trim($color) !== '' && $base !== '') {
                return $this->texMathMStyleElement('mathbackground', $color, $base);
            }
        }

        if (in_array($command, ['phantom', 'hphantom', 'vphantom'], true)) {
            $base = $this->parseTexMathArgument($source, $offset);
            if ($base !== '') {
                return '<mphantom>' . $this->mathMLRow($base) . '</mphantom>';
            }
        }

        if ($command === 'smash') {
            $base = $this->parseTexMathArgument($source, $offset);
            if ($base !== '') {
                return '<mpadded height="0" depth="0">' . $this->mathMLRow($base) . '</mpadded>';
            }
        }

        if ($command === 'operatorname') {
            if (($source[$offset] ?? '') === '*') {
                $offset++;
            }
            $name = $this->readTexMathRawGroup($source, $offset);
            if (is_string($name) && trim($name) !== '') {
                return '<mi mathvariant="normal">' . $this->esc($this->texMathOperatorNameText($name)) . '</mi>';
            }
        }

        $namedOperator = $this->texMathNamedOperatorElement($command);
        if ($namedOperator !== null) {
            return $namedOperator;
        }

        $mathVariant = $this->texMathStyleCommandVariant($command);
        if ($mathVariant !== null) {
            if (str_starts_with($command, 'text')) {
                $text = $this->readTexMathRawGroup($source, $offset);
                if ($text !== null) {
                    return '<mstyle mathvariant="' . $this->esc($mathVariant) . '"><mtext>' . $this->esc($text) . '</mtext></mstyle>';
                }
            } else {
                $base = $this->parseTexMathArgument($source, $offset);
                if ($base !== '') {
                    return '<mstyle mathvariant="' . $this->esc($mathVariant) . '">' . $this->mathMLRow($base) . '</mstyle>';
                }
            }
        }

        if ($command === 'text') {
            $text = $this->readTexMathBalancedText($source, $offset);
            return $text === '' ? '' : '<mtext>' . $this->esc($text) . '</mtext>';
        }

        if (in_array($command, ['mbox', 'hbox'], true)) {
            $text = $this->readTexMathBalancedText($source, $offset);
            return $text === '' ? '' : '<mtext>' . $this->esc($text) . '</mtext>';
        }

        $mapped = $this->texMathCommandElement($command);
        if ($mapped !== null) {
            return $mapped;
        }

        return '<mi>' . $this->esc($command) . '</mi>';
    }

    private function parseTexMathArgument(string $source, int &$offset): string
    {
        $this->skipTexMathWhitespace($source, $offset);
        if (($source[$offset] ?? '') === '{') {
            $offset++;
            return $this->parseTexMathRow($source, $offset, '}');
        }

        return $this->parseTexMathAtom($source, $offset);
    }

    private function texMathOperatorNameText(string $name): string
    {
        $text = trim($name);
        $text = preg_replace('/\\\\(?:,|:|;|!|thinspace|medspace|thickspace|negthinspace|negmedspace|negthickspace|enspace|quad|qquad)\s*/', ' ', $text) ?? $text;
        $text = preg_replace('/\\\\([A-Za-z]+)/', '$1', $text) ?? $text;
        $text = str_replace(['\\{', '\\}'], ['{', '}'], $text);

        return preg_replace('/\s+/', ' ', $text) ?? $text;
    }

    private function texMathFractionDisplayStyle(string $command): ?bool
    {
        return [
            'dfrac' => true,
            'dbinom' => true,
            'tfrac' => false,
            'tbinom' => false,
        ][$command] ?? null;
    }

    private function texMathFractionElement(string $numerator, string $denominator, ?bool $displayStyle, bool $withLine = true): string
    {
        $fraction = '<mfrac' . ($withLine ? '' : ' linethickness="0"') . '>'
            . $this->mathMLRow($numerator)
            . $this->mathMLRow($denominator)
            . '</mfrac>';

        return $this->texMathApplyDisplayStyle($fraction, $displayStyle);
    }

    private function texMathGeneralFractionElement(
        string $leftFence,
        string $rightFence,
        string $lineThickness,
        string $style,
        string $numerator,
        string $denominator
    ): string {
        $fraction = '<mfrac' . $this->texMathLineThicknessAttribute($lineThickness) . '>'
            . $this->mathMLRow($numerator)
            . $this->mathMLRow($denominator)
            . '</mfrac>';
        $fenced = $this->texMathFencedRow(
            $fraction,
            $this->texMathDelimiterFromRaw($leftFence),
            $this->texMathDelimiterFromRaw($rightFence)
        );

        return $this->texMathApplyStyleNumber($fenced, $style);
    }

    private function texMathLineThicknessAttribute(string $lineThickness): string
    {
        $thickness = trim($lineThickness);
        if ($thickness === '') {
            return '';
        }

        if (preg_match('/^0(?:\.0+)?(?:pt|em|ex|px|in|cm|mm|pc|%)?$/i', $thickness) === 1) {
            return ' linethickness="0"';
        }

        return ' linethickness="' . $this->esc($thickness) . '"';
    }

    private function texMathApplyStyleNumber(string $element, string $style): string
    {
        return match (trim($style)) {
            '0' => '<mstyle displaystyle="true">' . $element . '</mstyle>',
            '1' => '<mstyle displaystyle="false">' . $element . '</mstyle>',
            '2' => '<mstyle displaystyle="false" scriptlevel="1">' . $element . '</mstyle>',
            '3' => '<mstyle displaystyle="false" scriptlevel="2">' . $element . '</mstyle>',
            default => $element,
        };
    }

    private function texMathInfixFractionElement(string $command, string $numerator, string $denominator): string
    {
        $fraction = $this->texMathFractionElement($numerator, $denominator, null, $command === 'over');

        return match ($command) {
            'choose' => $this->texMathFencedRow($fraction, '(', ')'),
            'brack' => $this->texMathFencedRow($fraction, '[', ']'),
            'brace' => $this->texMathFencedRow($fraction, '{', '}'),
            default => $fraction,
        };
    }

    private function texMathApplyDisplayStyle(string $element, ?bool $displayStyle): string
    {
        if ($displayStyle === null) {
            return $element;
        }

        return '<mstyle displaystyle="' . ($displayStyle ? 'true' : 'false') . '">' . $element . '</mstyle>';
    }

    private function texMathStyleDeclarationAttributes(string $command): ?string
    {
        return [
            'displaystyle' => 'displaystyle="true" scriptlevel="0"',
            'textstyle' => 'displaystyle="false" scriptlevel="0"',
            'scriptstyle' => 'displaystyle="false" scriptlevel="1"',
            'scriptscriptstyle' => 'displaystyle="false" scriptlevel="2"',
        ][$command] ?? null;
    }

    private function texMathMStyleElement(string $attribute, string $value, string $body): string
    {
        return '<mstyle ' . $attribute . '="' . $this->esc(trim($value)) . '">' . $this->mathMLRow($body) . '</mstyle>';
    }

    private function texMathEnclosureNotation(string $command): ?string
    {
        return [
            'boxed' => 'box',
            'fbox' => 'box',
            'cancel' => 'updiagonalstrike',
            'bcancel' => 'downdiagonalstrike',
            'xcancel' => 'updiagonalstrike downdiagonalstrike',
        ][$command] ?? null;
    }

    private function texMathModuloElement(string $command, string $argument): string
    {
        if ($command === 'pod') {
            return $this->texMathFencedRow($argument, '(', ')');
        }

        $content = '<mi mathvariant="normal">mod</mi>' . $this->mathMLRow($argument);
        if ($command === 'pmod') {
            return $this->texMathFencedRow($content, '(', ')');
        }

        return $this->mathMLRow($content);
    }

    private function texMathNamedOperatorElement(string $command): ?string
    {
        static $operators = [
            'arccos',
            'arcsin',
            'arctan',
            'arg',
            'cos',
            'cosh',
            'cot',
            'coth',
            'csc',
            'deg',
            'det',
            'dim',
            'exp',
            'gcd',
            'hom',
            'inf',
            'ker',
            'lg',
            'lim',
            'liminf',
            'limsup',
            'ln',
            'log',
            'max',
            'min',
            'Pr',
            'sec',
            'sin',
            'sinh',
            'sup',
            'tan',
            'tanh',
        ];

        if (!in_array($command, $operators, true)) {
            return null;
        }

        return '<mi mathvariant="normal">' . $this->esc($command) . '</mi>';
    }

    /**
     * @return array{0:string,1:?string}|null
     */
    private function parseTexMathRowUntilRight(string $source, int &$offset): ?array
    {
        $items = [];
        $length = strlen($source);
        while ($offset < $length) {
            $this->skipTexMathWhitespace($source, $offset);
            if ($offset >= $length) {
                break;
            }

            $commandOffset = $offset + 1;
            if (($source[$offset] ?? '') === '\\' && $this->readTexMathCommandName($source, $commandOffset) === 'right') {
                $offset = $commandOffset;
                return [implode('', $items), $this->readTexMathDelimiter($source, $offset)];
            }

            $infixOffset = $offset;
            $infixCommand = $this->readTexMathInfixFractionCommand($source, $infixOffset);
            if ($infixCommand !== null && $items !== []) {
                $denominatorOffset = $infixOffset;
                $fenced = $this->parseTexMathRowUntilRight($source, $denominatorOffset);
                if ($fenced !== null && $fenced[0] !== '') {
                    $offset = $denominatorOffset;
                    return [$this->texMathInfixFractionElement($infixCommand, implode('', $items), $fenced[0]), $fenced[1]];
                }
            }

            $atom = $this->parseTexMathScriptedAtom($source, $offset);
            if ($atom === '') {
                $offset++;
                continue;
            }
            $items[] = $atom;
        }

        return null;
    }

    private function parseTexMathOptionalBracketArgument(string $source, int &$offset): string
    {
        $this->skipTexMathWhitespace($source, $offset);
        if (($source[$offset] ?? '') !== '[') {
            return '';
        }

        $length = strlen($source);
        $start = $offset + 1;
        $cursor = $start;
        $depth = 1;
        while ($cursor < $length) {
            $char = $source[$cursor];
            if ($char === '\\') {
                $cursor++;
                while ($cursor < $length && ctype_alpha($source[$cursor])) {
                    $cursor++;
                }
                if ($cursor < $length && !ctype_alpha($source[$cursor])) {
                    $cursor++;
                }
                continue;
            }
            if ($char === '[') {
                $depth++;
            } elseif ($char === ']') {
                $depth--;
                if ($depth === 0) {
                    $inner = substr($source, $start, $cursor - $start);
                    $innerOffset = 0;
                    $content = $this->parseTexMathRow($inner, $innerOffset, '');
                    if (trim(substr($inner, $innerOffset)) !== '') {
                        return '';
                    }

                    $offset = $cursor + 1;
                    return $content;
                }
            }
            $cursor++;
        }

        return '';
    }

    private function readTexMathRawGroup(string $source, int &$offset): ?string
    {
        $this->skipTexMathWhitespace($source, $offset);
        if (($source[$offset] ?? '') !== '{') {
            return null;
        }

        $offset++;
        $start = $offset;
        $depth = 1;
        $length = strlen($source);
        while ($offset < $length && $depth > 0) {
            $char = $source[$offset];
            if ($char === '\\') {
                $offset += 2;
                continue;
            }
            if ($char === '{') {
                $depth++;
            } elseif ($char === '}') {
                $depth--;
                if ($depth === 0) {
                    $text = substr($source, $start, $offset - $start);
                    $offset++;
                    return $text;
                }
            }
            $offset++;
        }

        return null;
    }

    private function readTexMathDelimiter(string $source, int &$offset): ?string
    {
        $this->skipTexMathWhitespace($source, $offset);
        $char = $source[$offset] ?? '';
        if ($char === '') {
            return null;
        }

        if ($char !== '\\') {
            $offset++;
            return $char === '.' ? null : $char;
        }

        $offset++;
        $command = $this->readTexMathCommandName($source, $offset);
        return match ($command) {
            '.', '' => null,
            '{', 'lbrace' => '{',
            '}', 'rbrace' => '}',
            'lparen' => '(',
            'rparen' => ')',
            'lbrack' => '[',
            'rbrack' => ']',
            'langle' => '⟨',
            'rangle' => '⟩',
            'lfloor' => '⌊',
            'rfloor' => '⌋',
            'lceil' => '⌈',
            'rceil' => '⌉',
            'vert', 'lvert', 'rvert' => '|',
            '|', 'Vert', 'lVert', 'rVert' => '‖',
            'backslash' => '\\',
            default => $command,
        };
    }

    private function texMathDelimiterFromRaw(string $raw): ?string
    {
        $trimmed = trim($raw);
        if ($trimmed === '' || $trimmed === '.') {
            return null;
        }

        $offset = 0;
        $delimiter = $this->readTexMathDelimiter($trimmed, $offset);
        if ($delimiter === null) {
            return null;
        }
        if (trim(substr($trimmed, $offset)) !== '') {
            return $trimmed;
        }

        return $delimiter;
    }

    private function texMathArrayColumnAlign(string $columnSpec): string
    {
        $alignments = [];
        $length = strlen($columnSpec);
        for ($index = 0; $index < $length;) {
            $char = $columnSpec[$index];

            if ($char === '@' || $char === '!') {
                $index++;
                $this->readTexMathRawGroup($columnSpec, $index);
                continue;
            }

            if ($char === '*') {
                $index++;
                $repeatRaw = $this->readTexMathRawGroup($columnSpec, $index);
                $specRaw = $this->readTexMathRawGroup($columnSpec, $index);
                if ($repeatRaw !== null && $specRaw !== null && preg_match('/^\d+$/', trim($repeatRaw)) === 1) {
                    $repeated = $this->texMathArrayColumnAlign($specRaw);
                    if ($repeated !== '') {
                        for ($repeat = 0; $repeat < (int) trim($repeatRaw); $repeat++) {
                            foreach (explode(' ', $repeated) as $alignment) {
                                if ($alignment !== '') {
                                    $alignments[] = $alignment;
                                }
                            }
                        }
                    }
                }
                continue;
            }

            $alignment = match ($char) {
                'l' => 'left',
                'c' => 'center',
                'r' => 'right',
                default => null,
            };
            if ($alignment !== null) {
                $alignments[] = $alignment;
            }
            $index++;
        }

        return implode(' ', $alignments);
    }

    private function texMathStyleCommandVariant(string $command): ?string
    {
        return [
            'mathrm' => 'normal',
            'mathup' => 'normal',
            'textrm' => 'normal',
            'mathbf' => 'bold',
            'boldsymbol' => 'bold',
            'bm' => 'bold',
            'symbf' => 'bold',
            'mathbold' => 'bold',
            'pmb' => 'bold',
            'mathbfup' => 'bold',
            'textbf' => 'bold',
            'mathit' => 'italic',
            'textit' => 'italic',
            'mathsf' => 'sans-serif',
            'mathsfup' => 'sans-serif',
            'textsf' => 'sans-serif',
            'mathtt' => 'monospace',
            'texttt' => 'monospace',
            'mathbb' => 'double-struck',
            'mathds' => 'double-struck',
            'mathcal' => 'script',
            'mathscr' => 'script',
            'mathfrak' => 'fraktur',
            'mathbfit' => 'bold-italic',
            'mathbfsfup' => 'bold-sans-serif',
            'mathbfsfit' => 'sans-serif-bold-italic',
            'mathbfscr' => 'bold-script',
            'mathbffrak' => 'bold-fraktur',
            'mathbfcal' => 'bold-script',
            'mathsfit' => 'sans-serif-italic',
        ][$command] ?? null;
    }

    /**
     * @return array{0:?string,1:?string}|null
     */
    private function texMathMatrixEnvironmentFences(string $environment): ?array
    {
        $environment = $this->texMathNormalizedEnvironmentName($environment);
        $fences = [
            'align' => [null, null],
            'alignat' => [null, null],
            'aligned' => [null, null],
            'alignedat' => [null, null],
            'eqnarray' => [null, null],
            'flalign' => [null, null],
            'flaligned' => [null, null],
            'gather' => [null, null],
            'gathered' => [null, null],
            'multline' => [null, null],
            'multlined' => [null, null],
            'split' => [null, null],
            'cases' => ['{', null],
            'dcases' => ['{', null],
            'rcases' => [null, '}'],
            'matrix' => [null, null],
            'smallmatrix' => [null, null],
            'pmatrix' => ['(', ')'],
            'bmatrix' => ['[', ']'],
            'Bmatrix' => ['{', '}'],
            'vmatrix' => ['|', '|'],
            'Vmatrix' => ['‖', '‖'],
        ];

        return $fences[$environment] ?? null;
    }

    private function texMathNormalizedEnvironmentName(string $environment): string
    {
        return str_ends_with($environment, '*') ? substr($environment, 0, -1) : $environment;
    }

    private function texMathEnvironmentConsumesLeadingGroup(string $environment): bool
    {
        return in_array($this->texMathNormalizedEnvironmentName($environment), ['alignat', 'alignedat'], true);
    }

    private function texMathMatrixEnvironmentColumnAlign(string $environment): string
    {
        return match ($this->texMathNormalizedEnvironmentName($environment)) {
            'align', 'aligned', 'alignat', 'alignedat', 'split' => 'right left',
            'flalign', 'flaligned' => 'left right',
            'eqnarray' => 'right center left',
            'gather', 'gathered', 'multline', 'multlined' => 'center',
            default => '',
        };
    }

    private function readTexMathEnvironmentBody(string $source, int &$offset, string $environment): ?string
    {
        $start = $offset;
        $cursor = $offset;
        $depth = 1;
        $length = strlen($source);
        while ($cursor < $length) {
            if ($source[$cursor] !== '\\') {
                $cursor++;
                continue;
            }

            $commandStart = $cursor;
            $cursor++;
            $command = $this->readTexMathCommandName($source, $cursor);
            if ($command !== 'begin' && $command !== 'end') {
                continue;
            }

            $environmentOffset = $cursor;
            $name = $this->readTexMathRawGroup($source, $environmentOffset);
            if ($name !== $environment) {
                continue;
            }

            if ($command === 'begin') {
                $depth++;
                $cursor = $environmentOffset;
                continue;
            }

            $depth--;
            if ($depth === 0) {
                $body = substr($source, $start, $commandStart - $start);
                $offset = $environmentOffset;
                return $body;
            }

            $cursor = $environmentOffset;
        }

        return null;
    }

    private function texMathMatrixToMathML(string $body, ?string $leftFence, ?string $rightFence, string $columnAlign = ''): string
    {
        $rows = [];
        $columnCount = 0;
        foreach ($this->splitTexMathMatrixRows($body) as $row) {
            $cells = $this->splitTexMathMatrixCells($row);
            if (count($cells) === 1 && trim($cells[0]) === '') {
                continue;
            }

            $columnCount = max($columnCount, count($cells));
            $cellXml = '';
            foreach ($cells as $cell) {
                $cellXml .= '<mtd>' . $this->texMathMatrixCellToMathML($cell) . '</mtd>';
            }
            $rows[] = '<mtr>' . $cellXml . '</mtr>';
        }
        if ($rows === []) {
            return '';
        }

        $columnAlign = $this->texMathColumnAlignForTable($columnAlign, $columnCount);
        $attrs = $columnAlign === '' ? '' : ' columnalign="' . $this->esc($columnAlign) . '"';
        $table = '<mtable' . $attrs . '>' . implode('', $rows) . '</mtable>';
        return $this->texMathFencedRow($table, $leftFence, $rightFence);
    }

    private function texMathColumnAlignForTable(string $columnAlign, int $columnCount): string
    {
        $alignments = array_values(array_filter(
            preg_split('/\s+/', trim($columnAlign)) ?: [],
            static fn (string $alignment): bool => $alignment !== ''
        ));
        if ($alignments === [] || $columnCount <= 0) {
            return '';
        }

        $cyclePattern = in_array($alignments, [
            ['center'],
            ['right', 'left'],
            ['left', 'right'],
            ['right', 'center', 'left'],
        ], true);
        if ($cyclePattern) {
            $pattern = $alignments;
            while (count($alignments) < $columnCount) {
                $alignments[] = $pattern[count($alignments) % count($pattern)];
            }
        } elseif (count($alignments) < $columnCount) {
            while (count($alignments) < $columnCount) {
                $alignments[] = 'center';
            }
        }

        return implode(' ', $alignments);
    }

    private function texMathFencedRow(string $body, ?string $leftFence, ?string $rightFence): string
    {
        if ($leftFence === null && $rightFence === null) {
            return $body;
        }

        $content = '<mrow>';
        if ($leftFence !== null) {
            $content .= '<mo stretchy="true">' . $this->esc($leftFence) . '</mo>';
        }
        $content .= $this->mathMLRow($body);
        if ($rightFence !== null) {
            $content .= '<mo stretchy="true">' . $this->esc($rightFence) . '</mo>';
        }

        return $content . '</mrow>';
    }

    /**
     * @return list<string>
     */
    private function splitTexMathMatrixRows(string $body): array
    {
        return $this->splitTexMathMatrixParts($body, true);
    }

    /**
     * @return list<string>
     */
    private function splitTexMathMatrixCells(string $row): array
    {
        return $this->splitTexMathMatrixParts($row, false);
    }

    /**
     * @return list<string>
     */
    private function splitTexMathMatrixParts(string $source, bool $splitRows): array
    {
        $parts = [];
        $start = 0;
        $braceDepth = 0;
        $bracketDepth = 0;
        $environmentDepth = 0;
        $length = strlen($source);

        for ($cursor = 0; $cursor < $length; $cursor++) {
            $char = $source[$cursor];

            if ($char === '\\') {
                if (
                    $splitRows
                    && $braceDepth === 0
                    && $bracketDepth === 0
                    && $environmentDepth === 0
                    && ($source[$cursor + 1] ?? '') === '\\'
                ) {
                    $parts[] = substr($source, $start, $cursor - $start);
                    $afterBreak = $this->consumeTexMathMatrixLineBreakSuffix($source, $cursor + 2);
                    $cursor = $afterBreak - 1;
                    $start = $afterBreak;
                    continue;
                }

                $commandOffset = $cursor + 1;
                $command = $this->readTexMathCommandName($source, $commandOffset);
                if ($command === 'begin' || $command === 'end') {
                    $environmentOffset = $commandOffset;
                    $environment = $this->readTexMathRawGroup($source, $environmentOffset);
                    if (is_string($environment) && $this->texMathTableEnvironmentHasBody($environment)) {
                        if ($command === 'begin') {
                            $environmentDepth++;
                        } elseif ($environmentDepth > 0) {
                            $environmentDepth--;
                        }
                        $cursor = $environmentOffset - 1;
                        continue;
                    }
                }

                $cursor = max($cursor, $commandOffset - 1);
                continue;
            }

            if ($char === '{') {
                $braceDepth++;
                continue;
            }
            if ($char === '}') {
                $braceDepth = max(0, $braceDepth - 1);
                continue;
            }
            if ($char === '[') {
                $bracketDepth++;
                continue;
            }
            if ($char === ']') {
                $bracketDepth = max(0, $bracketDepth - 1);
                continue;
            }
            if (
                !$splitRows
                && $char === '&'
                && $braceDepth === 0
                && $bracketDepth === 0
                && $environmentDepth === 0
            ) {
                $parts[] = substr($source, $start, $cursor - $start);
                $start = $cursor + 1;
            }
        }

        $parts[] = substr($source, $start);
        return $parts;
    }

    private function texMathTableEnvironmentHasBody(string $environment): bool
    {
        $environment = $this->texMathNormalizedEnvironmentName($environment);
        return $environment === 'array'
            || $environment === 'subarray'
            || $environment === 'equation'
            || $this->texMathMatrixEnvironmentFences($environment) !== null;
    }

    private function consumeTexMathMatrixLineBreakSuffix(string $source, int $offset): int
    {
        $cursor = $offset;
        $length = strlen($source);
        while ($cursor < $length && ctype_space($source[$cursor])) {
            $cursor++;
        }

        if (($source[$cursor] ?? '') !== '[') {
            return $offset;
        }

        $cursor++;
        $depth = 1;
        while ($cursor < $length) {
            $char = $source[$cursor];
            if ($char === '\\') {
                $cursor += 2;
                continue;
            }
            if ($char === '[') {
                $depth++;
            } elseif ($char === ']') {
                $depth--;
                if ($depth === 0) {
                    return $cursor + 1;
                }
            }
            $cursor++;
        }

        return $offset;
    }

    private function texMathMalformedEnvironmentFallback(string $source, int &$offset, int $start): string
    {
        $offset = strlen($source);

        return '<mtext>' . $this->esc(substr($source, $start)) . '</mtext>';
    }

    private function texMathMatrixCellToMathML(string $cell): string
    {
        $trimmed = trim($cell);
        $offset = 0;
        $content = $this->parseTexMathRow($trimmed, $offset, '');
        if (trim(substr($trimmed, $offset)) !== '') {
            return '<mtext>' . $this->esc($trimmed) . '</mtext>';
        }
        if ($content === '') {
            return '<mrow></mrow>';
        }

        return $this->mathMLRow($content);
    }

    private function skipTexMathWhitespace(string $source, int &$offset): void
    {
        $length = strlen($source);
        while ($offset < $length && ctype_space($source[$offset])) {
            $offset++;
        }
    }

    private function readTexMathCommandName(string $source, int &$offset): string
    {
        $length = strlen($source);
        $start = $offset;
        while ($offset < $length && ctype_alpha($source[$offset])) {
            $offset++;
        }
        if ($offset > $start) {
            return substr($source, $start, $offset - $start);
        }

        $command = $source[$offset] ?? '';
        if ($command !== '') {
            $offset++;
        }

        return $command;
    }

    private function readTexMathNegatedRelation(string $source, int &$offset): ?string
    {
        $this->skipTexMathWhitespace($source, $offset);
        $char = $source[$offset] ?? '';
        if ($char === '') {
            return null;
        }

        if ($char === '\\') {
            $commandOffset = $offset + 1;
            $command = $this->readTexMathCommandName($source, $commandOffset);
            $negated = $this->texMathNegatedRelationSymbol($command);
            if ($negated === null) {
                return null;
            }
            $offset = $commandOffset;

            return '<mo>' . $this->esc($negated) . '</mo>';
        }

        $negated = $this->texMathNegatedRelationSymbol($char);
        if ($negated === null) {
            return null;
        }
        $offset++;

        return '<mo>' . $this->esc($negated) . '</mo>';
    }

    private function texMathNegatedRelationSymbol(string $relation): ?string
    {
        return [
            '=' => '≠',
            '<' => '≮',
            '>' => '≯',
            'in' => '∉',
            'ni' => '∌',
            'owns' => '∌',
            'le' => '≰',
            'leq' => '≰',
            'leqq' => '≰',
            'ge' => '≱',
            'geq' => '≱',
            'geqq' => '≱',
            'lt' => '≮',
            'gt' => '≯',
            'equiv' => '≢',
            'approx' => '≉',
            'sim' => '≁',
            'simeq' => '≄',
            'cong' => '≇',
            'lesssim' => '≴',
            'gtrsim' => '≵',
            'prec' => '⊀',
            'succ' => '⊁',
            'preceq' => '⋠',
            'succeq' => '⋡',
            'subset' => '⊄',
            'subseteq' => '⊈',
            'supset' => '⊅',
            'supseteq' => '⊉',
            'sqsubseteq' => '⋢',
            'sqsupseteq' => '⋣',
            'asymp' => '≭',
            'parallel' => '∦',
            'mid' => '∤',
        ][$relation] ?? null;
    }

    private function readTexMathNumber(string $source, int &$offset): string
    {
        $start = $offset;
        $length = strlen($source);
        while ($offset < $length && (ctype_digit($source[$offset]) || $source[$offset] === '.')) {
            $offset++;
        }

        return substr($source, $start, $offset - $start);
    }

    private function readTexMathIdentifier(string $source, int &$offset): string
    {
        $remaining = substr($source, $offset);
        if (preg_match('/^\p{L}[\p{L}\p{M}\p{N}]*/u', $remaining, $match) !== 1) {
            return '';
        }

        $offset += strlen($match[0]);

        return $match[0];
    }

    private function readTexMathCharacter(string $source, int &$offset): string
    {
        $remaining = substr($source, $offset);
        if (preg_match('/^./us', $remaining, $match) === 1) {
            $offset += strlen($match[0]);

            return $match[0];
        }

        $char = $source[$offset] ?? '';
        if ($char !== '') {
            $offset++;
        }

        return $char;
    }

    private function readTexMathBalancedText(string $source, int &$offset): string
    {
        $this->skipTexMathWhitespace($source, $offset);
        if (($source[$offset] ?? '') !== '{') {
            return '';
        }

        $offset++;
        $start = $offset;
        $depth = 1;
        $length = strlen($source);
        while ($offset < $length && $depth > 0) {
            $char = $source[$offset];
            if ($char === '\\') {
                $offset += 2;
                continue;
            }
            if ($char === '{') {
                $depth++;
            } elseif ($char === '}') {
                $depth--;
                if ($depth === 0) {
                    $text = substr($source, $start, $offset - $start);
                    $offset++;
                    return $text;
                }
            }
            $offset++;
        }

        return substr($source, $start);
    }

    private function texMathCommandElement(string $command): ?string
    {
        $greek = [
            'alpha' => 'α',
            'beta' => 'β',
            'gamma' => 'γ',
            'delta' => 'δ',
            'epsilon' => 'ϵ',
            'varepsilon' => 'ε',
            'zeta' => 'ζ',
            'eta' => 'η',
            'theta' => 'θ',
            'vartheta' => 'ϑ',
            'iota' => 'ι',
            'kappa' => 'κ',
            'lambda' => 'λ',
            'mu' => 'μ',
            'nu' => 'ν',
            'xi' => 'ξ',
            'pi' => 'π',
            'varpi' => 'ϖ',
            'rho' => 'ρ',
            'varrho' => 'ϱ',
            'sigma' => 'σ',
            'varsigma' => 'ς',
            'tau' => 'τ',
            'upsilon' => 'υ',
            'phi' => 'ϕ',
            'varphi' => 'φ',
            'chi' => 'χ',
            'psi' => 'ψ',
            'omega' => 'ω',
            'digamma' => 'ϝ',
            'varkappa' => 'ϰ',
            'Gamma' => 'Γ',
            'Delta' => 'Δ',
            'Theta' => 'Θ',
            'Lambda' => 'Λ',
            'Xi' => 'Ξ',
            'Pi' => 'Π',
            'Sigma' => 'Σ',
            'Upsilon' => 'Υ',
            'Phi' => 'Φ',
            'Psi' => 'Ψ',
            'Omega' => 'Ω',
            'mupAlpha' => 'Α',
            'mupBeta' => 'Β',
            'mupGamma' => 'Γ',
            'mupDelta' => 'Δ',
            'mupEpsilon' => 'Ε',
            'mupZeta' => 'Ζ',
            'mupEta' => 'Η',
            'mupTheta' => 'Θ',
            'mupIota' => 'Ι',
            'mupKappa' => 'Κ',
            'mupLambda' => 'Λ',
            'mupMu' => 'Μ',
            'mupNu' => 'Ν',
            'mupXi' => 'Ξ',
            'mupOmicron' => 'Ο',
            'mupPi' => 'Π',
            'mupRho' => 'Ρ',
            'mupSigma' => 'Σ',
            'mupTau' => 'Τ',
            'mupUpsilon' => 'Υ',
            'mupPhi' => 'Φ',
            'mupChi' => 'Χ',
            'mupPsi' => 'Ψ',
            'mupOmega' => 'Ω',
            'mupalpha' => 'α',
            'mupbeta' => 'β',
            'mupgamma' => 'γ',
            'mupdelta' => 'δ',
            'mupvarepsilon' => 'ε',
            'mupzeta' => 'ζ',
            'mupeta' => 'η',
            'muptheta' => 'θ',
            'mupiota' => 'ι',
            'mupkappa' => 'κ',
            'muplambda' => 'λ',
            'mupmu' => 'μ',
            'mupnu' => 'ν',
            'mupxi' => 'ξ',
            'mupomicron' => 'ο',
            'muppi' => 'π',
            'muprho' => 'ρ',
            'mupvarsigma' => 'ς',
            'mupsigma' => 'σ',
            'muptau' => 'τ',
            'mupupsilon' => 'υ',
            'mupvarphi' => 'φ',
            'mupchi' => 'χ',
            'muppsi' => 'ψ',
            'mupomega' => 'ω',
            'mupvartheta' => 'ϑ',
            'mupphi' => 'ϕ',
            'mupvarpi' => 'ϖ',
            'upDigamma' => 'Ϝ',
            'updigamma' => 'ϝ',
            'mupvarkappa' => 'ϰ',
            'mupvarrho' => 'ϱ',
            'mupvarTheta' => 'ϴ',
            'mupepsilon' => 'ϵ',
            'mbfAlpha' => '𝚨',
            'mbfBeta' => '𝚩',
            'mbfGamma' => '𝚪',
            'mbfDelta' => '𝚫',
            'mbfEpsilon' => '𝚬',
            'mbfZeta' => '𝚭',
            'mbfEta' => '𝚮',
            'mbfTheta' => '𝚯',
            'mbfIota' => '𝚰',
            'mbfKappa' => '𝚱',
            'mbfLambda' => '𝚲',
            'mbfMu' => '𝚳',
            'mbfNu' => '𝚴',
            'mbfXi' => '𝚵',
            'mbfOmicron' => '𝚶',
            'mbfPi' => '𝚷',
            'mbfRho' => '𝚸',
            'mbfvarTheta' => '𝚹',
            'mbfSigma' => '𝚺',
            'mbfTau' => '𝚻',
            'mbfUpsilon' => '𝚼',
            'mbfPhi' => '𝚽',
            'mbfChi' => '𝚾',
            'mbfPsi' => '𝚿',
            'mbfOmega' => '𝛀',
            'mbfnabla' => '𝛁',
            'mbfalpha' => '𝛂',
            'mbfbeta' => '𝛃',
            'mbfgamma' => '𝛄',
            'mbfdelta' => '𝛅',
            'mbfvarepsilon' => '𝛆',
            'mbfzeta' => '𝛇',
            'mbfeta' => '𝛈',
            'mbftheta' => '𝛉',
            'mbfiota' => '𝛊',
            'mbfkappa' => '𝛋',
            'mbflambda' => '𝛌',
            'mbfmu' => '𝛍',
            'mbfnu' => '𝛎',
            'mbfxi' => '𝛏',
            'mbfomicron' => '𝛐',
            'mbfpi' => '𝛑',
            'mbfrho' => '𝛒',
            'mbfvarsigma' => '𝛓',
            'mbfsigma' => '𝛔',
            'mbftau' => '𝛕',
            'mbfupsilon' => '𝛖',
            'mbfvarphi' => '𝛗',
            'mbfchi' => '𝛘',
            'mbfpsi' => '𝛙',
            'mbfomega' => '𝛚',
            'mbfpartial' => '𝛛',
            'mbfepsilon' => '𝛜',
            'mbfvartheta' => '𝛝',
            'mbfvarkappa' => '𝛞',
            'mbfphi' => '𝛟',
            'mbfvarrho' => '𝛠',
            'mbfvarpi' => '𝛡',
            'mitAlpha' => '𝛢',
            'mitBeta' => '𝛣',
            'mitGamma' => '𝛤',
            'mitDelta' => '𝛥',
            'mitEpsilon' => '𝛦',
            'mitZeta' => '𝛧',
            'mitEta' => '𝛨',
            'mitTheta' => '𝛩',
            'mitIota' => '𝛪',
            'mitKappa' => '𝛫',
            'mitLambda' => '𝛬',
            'mitMu' => '𝛭',
            'mitNu' => '𝛮',
            'mitXi' => '𝛯',
            'mitOmicron' => '𝛰',
            'mitPi' => '𝛱',
            'mitRho' => '𝛲',
            'mitvarTheta' => '𝛳',
            'mitSigma' => '𝛴',
            'mitTau' => '𝛵',
            'mitUpsilon' => '𝛶',
            'mitPhi' => '𝛷',
            'mitChi' => '𝛸',
            'mitPsi' => '𝛹',
            'mitOmega' => '𝛺',
            'mitnabla' => '𝛻',
            'mitalpha' => '𝛼',
            'mitbeta' => '𝛽',
            'mitgamma' => '𝛾',
            'mitdelta' => '𝛿',
            'mitvarepsilon' => '𝜀',
            'mitzeta' => '𝜁',
            'miteta' => '𝜂',
            'mittheta' => '𝜃',
            'mitiota' => '𝜄',
            'mitkappa' => '𝜅',
            'mitlambda' => '𝜆',
            'mitmu' => '𝜇',
            'mitnu' => '𝜈',
            'mitxi' => '𝜉',
            'mitomicron' => '𝜊',
            'mitpi' => '𝜋',
            'mitrho' => '𝜌',
            'mitvarsigma' => '𝜍',
            'mitsigma' => '𝜎',
            'mittau' => '𝜏',
            'mitupsilon' => '𝜐',
            'mitvarphi' => '𝜑',
            'mitchi' => '𝜒',
            'mitpsi' => '𝜓',
            'mitomega' => '𝜔',
            'mitpartial' => '𝜕',
            'mitepsilon' => '𝜖',
            'mitvartheta' => '𝜗',
            'mitvarkappa' => '𝜘',
            'mitphi' => '𝜙',
            'mitvarrho' => '𝜚',
            'mitvarpi' => '𝜛',
            'mbfitAlpha' => '𝜜',
            'mbfitBeta' => '𝜝',
            'mbfitGamma' => '𝜞',
            'mbfitDelta' => '𝜟',
            'mbfitEpsilon' => '𝜠',
            'mbfitZeta' => '𝜡',
            'mbfitEta' => '𝜢',
            'mbfitTheta' => '𝜣',
            'mbfitIota' => '𝜤',
            'mbfitKappa' => '𝜥',
            'mbfitLambda' => '𝜦',
            'mbfitMu' => '𝜧',
            'mbfitNu' => '𝜨',
            'mbfitXi' => '𝜩',
            'mbfitOmicron' => '𝜪',
            'mbfitPi' => '𝜫',
            'mbfitRho' => '𝜬',
            'mbfitvarTheta' => '𝜭',
            'mbfitSigma' => '𝜮',
            'mbfitTau' => '𝜯',
            'mbfitUpsilon' => '𝜰',
            'mbfitPhi' => '𝜱',
            'mbfitChi' => '𝜲',
            'mbfitPsi' => '𝜳',
            'mbfitOmega' => '𝜴',
            'mbfitnabla' => '𝜵',
            'mbfitalpha' => '𝜶',
            'mbfitbeta' => '𝜷',
            'mbfitgamma' => '𝜸',
            'mbfitdelta' => '𝜹',
            'mbfitvarepsilon' => '𝜺',
            'mbfitzeta' => '𝜻',
            'mbfiteta' => '𝜼',
            'mbfittheta' => '𝜽',
            'mbfitiota' => '𝜾',
            'mbfitkappa' => '𝜿',
            'mbfitlambda' => '𝝀',
            'mbfitmu' => '𝝁',
            'mbfitnu' => '𝝂',
            'mbfitxi' => '𝝃',
            'mbfitomicron' => '𝝄',
            'mbfitpi' => '𝝅',
            'mbfitrho' => '𝝆',
            'mbfitvarsigma' => '𝝇',
            'mbfitsigma' => '𝝈',
            'mbfittau' => '𝝉',
            'mbfitupsilon' => '𝝊',
            'mbfitvarphi' => '𝝋',
            'mbfitchi' => '𝝌',
            'mbfitpsi' => '𝝍',
            'mbfitomega' => '𝝎',
            'mbfitpartial' => '𝝏',
            'mbfitepsilon' => '𝝐',
            'mbfitvartheta' => '𝝑',
            'mbfitvarkappa' => '𝝒',
            'mbfitphi' => '𝝓',
            'mbfitvarrho' => '𝝔',
            'mbfitvarpi' => '𝝕',
            'mbfsansAlpha' => '𝝖',
            'mbfsansBeta' => '𝝗',
            'mbfsansGamma' => '𝝘',
            'mbfsansDelta' => '𝝙',
            'mbfsansEpsilon' => '𝝚',
            'mbfsansZeta' => '𝝛',
            'mbfsansEta' => '𝝜',
            'mbfsansTheta' => '𝝝',
            'mbfsansIota' => '𝝞',
            'mbfsansKappa' => '𝝟',
            'mbfsansLambda' => '𝝠',
            'mbfsansMu' => '𝝡',
            'mbfsansNu' => '𝝢',
            'mbfsansXi' => '𝝣',
            'mbfsansOmicron' => '𝝤',
            'mbfsansPi' => '𝝥',
            'mbfsansRho' => '𝝦',
            'mbfsansvarTheta' => '𝝧',
            'mbfsansSigma' => '𝝨',
            'mbfsansTau' => '𝝩',
            'mbfsansUpsilon' => '𝝪',
            'mbfsansPhi' => '𝝫',
            'mbfsansChi' => '𝝬',
            'mbfsansPsi' => '𝝭',
            'mbfsansOmega' => '𝝮',
            'mbfsansnabla' => '𝝯',
            'mbfsansalpha' => '𝝰',
            'mbfsansbeta' => '𝝱',
            'mbfsansgamma' => '𝝲',
            'mbfsansdelta' => '𝝳',
            'mbfsansvarepsilon' => '𝝴',
            'mbfsanszeta' => '𝝵',
            'mbfsanseta' => '𝝶',
            'mbfsanstheta' => '𝝷',
            'mbfsansiota' => '𝝸',
            'mbfsanskappa' => '𝝹',
            'mbfsanslambda' => '𝝺',
            'mbfsansmu' => '𝝻',
            'mbfsansnu' => '𝝼',
            'mbfsansxi' => '𝝽',
            'mbfsansomicron' => '𝝾',
            'mbfsanspi' => '𝝿',
            'mbfsansrho' => '𝞀',
            'mbfsansvarsigma' => '𝞁',
            'mbfsanssigma' => '𝞂',
            'mbfsanstau' => '𝞃',
            'mbfsansupsilon' => '𝞄',
            'mbfsansvarphi' => '𝞅',
            'mbfsanschi' => '𝞆',
            'mbfsanspsi' => '𝞇',
            'mbfsansomega' => '𝞈',
            'mbfsanspartial' => '𝞉',
            'mbfsansepsilon' => '𝞊',
            'mbfsansvartheta' => '𝞋',
            'mbfsansvarkappa' => '𝞌',
            'mbfsansphi' => '𝞍',
            'mbfsansvarrho' => '𝞎',
            'mbfsansvarpi' => '𝞏',
            'mbfitsansAlpha' => '𝞐',
            'mbfitsansBeta' => '𝞑',
            'mbfitsansGamma' => '𝞒',
            'mbfitsansDelta' => '𝞓',
            'mbfitsansEpsilon' => '𝞔',
            'mbfitsansZeta' => '𝞕',
            'mbfitsansEta' => '𝞖',
            'mbfitsansTheta' => '𝞗',
            'mbfitsansIota' => '𝞘',
            'mbfitsansKappa' => '𝞙',
            'mbfitsansLambda' => '𝞚',
            'mbfitsansMu' => '𝞛',
            'mbfitsansNu' => '𝞜',
            'mbfitsansXi' => '𝞝',
            'mbfitsansOmicron' => '𝞞',
            'mbfitsansPi' => '𝞟',
            'mbfitsansRho' => '𝞠',
            'mbfitsansvarTheta' => '𝞡',
            'mbfitsansSigma' => '𝞢',
            'mbfitsansTau' => '𝞣',
            'mbfitsansUpsilon' => '𝞤',
            'mbfitsansPhi' => '𝞥',
            'mbfitsansChi' => '𝞦',
            'mbfitsansPsi' => '𝞧',
            'mbfitsansOmega' => '𝞨',
            'mbfitsansnabla' => '𝞩',
            'mbfitsansalpha' => '𝞪',
            'mbfitsansbeta' => '𝞫',
            'mbfitsansgamma' => '𝞬',
            'mbfitsansdelta' => '𝞭',
            'mbfitsansvarepsilon' => '𝞮',
            'mbfitsanszeta' => '𝞯',
            'mbfitsanseta' => '𝞰',
            'mbfitsanstheta' => '𝞱',
            'mbfitsansiota' => '𝞲',
            'mbfitsanskappa' => '𝞳',
            'mbfitsanslambda' => '𝞴',
            'mbfitsansmu' => '𝞵',
            'mbfitsansnu' => '𝞶',
            'mbfitsansxi' => '𝞷',
            'mbfitsansomicron' => '𝞸',
            'mbfitsanspi' => '𝞹',
            'mbfitsansrho' => '𝞺',
            'mbfitsansvarsigma' => '𝞻',
            'mbfitsanssigma' => '𝞼',
            'mbfitsanstau' => '𝞽',
            'mbfitsansupsilon' => '𝞾',
            'mbfitsansvarphi' => '𝞿',
            'mbfitsanschi' => '𝟀',
            'mbfitsanspsi' => '𝟁',
            'mbfitsansomega' => '𝟂',
            'mbfitsanspartial' => '𝟃',
            'mbfitsansepsilon' => '𝟄',
            'mbfitsansvartheta' => '𝟅',
            'mbfitsansvarkappa' => '𝟆',
            'mbfitsansphi' => '𝟇',
            'mbfitsansvarrho' => '𝟈',
            'mbfitsansvarpi' => '𝟉',
            'mbfDigamma' => '𝟊',
            'mbfdigamma' => '𝟋',
        ];
        if (isset($greek[$command])) {
            return '<mi>' . $this->esc($greek[$command]) . '</mi>';
        }

        $numberSymbols = [
            'mbfzero' => '𝟎',
            'mbfone' => '𝟏',
            'mbftwo' => '𝟐',
            'mbfthree' => '𝟑',
            'mbffour' => '𝟒',
            'mbffive' => '𝟓',
            'mbfsix' => '𝟔',
            'mbfseven' => '𝟕',
            'mbfeight' => '𝟖',
            'mbfnine' => '𝟗',
            'Bbbzero' => '𝟘',
            'Bbbone' => '𝟙',
            'Bbbtwo' => '𝟚',
            'Bbbthree' => '𝟛',
            'Bbbfour' => '𝟜',
            'Bbbfive' => '𝟝',
            'Bbbsix' => '𝟞',
            'Bbbseven' => '𝟟',
            'Bbbeight' => '𝟠',
            'Bbbnine' => '𝟡',
            'msanszero' => '𝟢',
            'msansone' => '𝟣',
            'msanstwo' => '𝟤',
            'msansthree' => '𝟥',
            'msansfour' => '𝟦',
            'msansfive' => '𝟧',
            'msanssix' => '𝟨',
            'msansseven' => '𝟩',
            'msanseight' => '𝟪',
            'msansnine' => '𝟫',
            'mbfsanszero' => '𝟬',
            'mbfsansone' => '𝟭',
            'mbfsanstwo' => '𝟮',
            'mbfsansthree' => '𝟯',
            'mbfsansfour' => '𝟰',
            'mbfsansfive' => '𝟱',
            'mbfsanssix' => '𝟲',
            'mbfsansseven' => '𝟳',
            'mbfsanseight' => '𝟴',
            'mbfsansnine' => '𝟵',
            'mttzero' => '𝟶',
            'mttone' => '𝟷',
            'mtttwo' => '𝟸',
            'mttthree' => '𝟹',
            'mttfour' => '𝟺',
            'mttfive' => '𝟻',
            'mttsix' => '𝟼',
            'mttseven' => '𝟽',
            'mtteight' => '𝟾',
            'mttnine' => '𝟿',
        ];
        if (isset($numberSymbols[$command])) {
            return '<mn>' . $this->esc($numberSymbols[$command]) . '</mn>';
        }

        $letterSymbols = [
            'aleph' => 'ℵ',
            'beth' => 'ℶ',
            'gimel' => 'ℷ',
            'daleth' => 'ℸ',
            'ell' => 'ℓ',
            'Eulerconst' => 'ℇ',
            'Planckconst' => 'ℎ',
            'hbar' => 'ℏ',
            'hslash' => 'ℏ',
            'imath' => 'ı',
            'jmath' => 'ȷ',
            'wp' => '℘',
            'mho' => '℧',
            'Finv' => 'Ⅎ',
            'Game' => '⅁',
            'eth' => 'ð',
            'matheth' => 'ð',
            'mathhyphen' => '‐',
            'Zbar' => 'Ƶ',
            'mscrg' => 'ℊ',
            'mscrH' => 'ℋ',
            'mscrI' => 'ℐ',
            'mscrL' => 'ℒ',
            'mscrR' => 'ℛ',
            'turnediota' => '℩',
            'Angstrom' => 'Å',
            'mscrB' => 'ℬ',
            'mscre' => 'ℯ',
            'mscrE' => 'ℰ',
            'mscrF' => 'ℱ',
            'mscrM' => 'ℳ',
            'mscro' => 'ℴ',
            'mscrA' => '𝒜',
            'mscrC' => '𝒞',
            'mscrD' => '𝒟',
            'mscrG' => '𝒢',
            'mscrJ' => '𝒥',
            'mscrK' => '𝒦',
            'mscrN' => '𝒩',
            'mscrO' => '𝒪',
            'mscrP' => '𝒫',
            'mscrQ' => '𝒬',
            'mscrS' => '𝒮',
            'mscrT' => '𝒯',
            'mscrU' => '𝒰',
            'mscrV' => '𝒱',
            'mscrW' => '𝒲',
            'mscrX' => '𝒳',
            'mscrY' => '𝒴',
            'mscrZ' => '𝒵',
            'mscra' => '𝒶',
            'mscrb' => '𝒷',
            'mscrc' => '𝒸',
            'mscrd' => '𝒹',
            'mscrf' => '𝒻',
            'mscrh' => '𝒽',
            'mscri' => '𝒾',
            'mscrj' => '𝒿',
            'mscrk' => '𝓀',
            'mscrl' => '𝓁',
            'mscrm' => '𝓂',
            'mscrn' => '𝓃',
            'mscrp' => '𝓅',
            'mscrq' => '𝓆',
            'mscrr' => '𝓇',
            'mscrs' => '𝓈',
            'mscrt' => '𝓉',
            'mscru' => '𝓊',
            'mscrv' => '𝓋',
            'mscrw' => '𝓌',
            'mscrx' => '𝓍',
            'mscry' => '𝓎',
            'mscrz' => '𝓏',
            'Bbbk' => '𝕜',
            'Bbbpi' => 'ℼ',
            'Bbbgamma' => 'ℽ',
            'BbbGamma' => 'ℾ',
            'BbbPi' => 'ℿ',
            'sansLturned' => '⅂',
            'sansLmirrored' => '⅃',
            'Yup' => '⅄',
            'mitBbbD' => 'ⅅ',
            'mitBbbd' => 'ⅆ',
            'mitBbbe' => 'ⅇ',
            'mitBbbi' => 'ⅈ',
            'mitBbbj' => 'ⅉ',
            'PropertyLine' => '⅊',
            'Re' => 'ℜ',
            'Im' => 'ℑ',
            'mbfA' => '𝐀',
            'mbfB' => '𝐁',
            'mbfC' => '𝐂',
            'mbfD' => '𝐃',
            'mbfE' => '𝐄',
            'mbfF' => '𝐅',
            'mbfG' => '𝐆',
            'mbfH' => '𝐇',
            'mbfI' => '𝐈',
            'mbfJ' => '𝐉',
            'mbfK' => '𝐊',
            'mbfL' => '𝐋',
            'mbfM' => '𝐌',
            'mbfN' => '𝐍',
            'mbfO' => '𝐎',
            'mbfP' => '𝐏',
            'mbfQ' => '𝐐',
            'mbfR' => '𝐑',
            'mbfS' => '𝐒',
            'mbfT' => '𝐓',
            'mbfU' => '𝐔',
            'mbfV' => '𝐕',
            'mbfW' => '𝐖',
            'mbfX' => '𝐗',
            'mbfY' => '𝐘',
            'mbfZ' => '𝐙',
            'mbfa' => '𝐚',
            'mbfb' => '𝐛',
            'mbfc' => '𝐜',
            'mbfd' => '𝐝',
            'mbfe' => '𝐞',
            'mbff' => '𝐟',
            'mbfg' => '𝐠',
            'mbfh' => '𝐡',
            'mbfi' => '𝐢',
            'mbfj' => '𝐣',
            'mbfk' => '𝐤',
            'mbfl' => '𝐥',
            'mbfm' => '𝐦',
            'mbfn' => '𝐧',
            'mbfo' => '𝐨',
            'mbfp' => '𝐩',
            'mbfq' => '𝐪',
            'mbfr' => '𝐫',
            'mbfs' => '𝐬',
            'mbft' => '𝐭',
            'mbfu' => '𝐮',
            'mbfv' => '𝐯',
            'mbfw' => '𝐰',
            'mbfx' => '𝐱',
            'mbfy' => '𝐲',
            'mbfz' => '𝐳',
            'mitA' => '𝐴',
            'mitB' => '𝐵',
            'mitC' => '𝐶',
            'mitD' => '𝐷',
            'mitE' => '𝐸',
            'mitF' => '𝐹',
            'mitG' => '𝐺',
            'mitH' => '𝐻',
            'mitI' => '𝐼',
            'mitJ' => '𝐽',
            'mitK' => '𝐾',
            'mitL' => '𝐿',
            'mitM' => '𝑀',
            'mitN' => '𝑁',
            'mitO' => '𝑂',
            'mitP' => '𝑃',
            'mitQ' => '𝑄',
            'mitR' => '𝑅',
            'mitS' => '𝑆',
            'mitT' => '𝑇',
            'mitU' => '𝑈',
            'mitV' => '𝑉',
            'mitW' => '𝑊',
            'mitX' => '𝑋',
            'mitY' => '𝑌',
            'mitZ' => '𝑍',
            'mita' => '𝑎',
            'mitb' => '𝑏',
            'mitc' => '𝑐',
            'mitd' => '𝑑',
            'mite' => '𝑒',
            'mitf' => '𝑓',
            'mitg' => '𝑔',
            'miti' => '𝑖',
            'mitj' => '𝑗',
            'mitk' => '𝑘',
            'mitl' => '𝑙',
            'mitm' => '𝑚',
            'mitn' => '𝑛',
            'mito' => '𝑜',
            'mitp' => '𝑝',
            'mitq' => '𝑞',
            'mitr' => '𝑟',
            'mits' => '𝑠',
            'mitt' => '𝑡',
            'mitu' => '𝑢',
            'mitv' => '𝑣',
            'mitw' => '𝑤',
            'mitx' => '𝑥',
            'mity' => '𝑦',
            'mitz' => '𝑧',
            'mbfitA' => '𝑨',
            'mbfitB' => '𝑩',
            'mbfitC' => '𝑪',
            'mbfitD' => '𝑫',
            'mbfitE' => '𝑬',
            'mbfitF' => '𝑭',
            'mbfitG' => '𝑮',
            'mbfitH' => '𝑯',
            'mbfitI' => '𝑰',
            'mbfitJ' => '𝑱',
            'mbfitK' => '𝑲',
            'mbfitL' => '𝑳',
            'mbfitM' => '𝑴',
            'mbfitN' => '𝑵',
            'mbfitO' => '𝑶',
            'mbfitP' => '𝑷',
            'mbfitQ' => '𝑸',
            'mbfitR' => '𝑹',
            'mbfitS' => '𝑺',
            'mbfitT' => '𝑻',
            'mbfitU' => '𝑼',
            'mbfitV' => '𝑽',
            'mbfitW' => '𝑾',
            'mbfitX' => '𝑿',
            'mbfitY' => '𝒀',
            'mbfitZ' => '𝒁',
            'mbfita' => '𝒂',
            'mbfitb' => '𝒃',
            'mbfitc' => '𝒄',
            'mbfitd' => '𝒅',
            'mbfite' => '𝒆',
            'mbfitf' => '𝒇',
            'mbfitg' => '𝒈',
            'mbfith' => '𝒉',
            'mbfiti' => '𝒊',
            'mbfitj' => '𝒋',
            'mbfitk' => '𝒌',
            'mbfitl' => '𝒍',
            'mbfitm' => '𝒎',
            'mbfitn' => '𝒏',
            'mbfito' => '𝒐',
            'mbfitp' => '𝒑',
            'mbfitq' => '𝒒',
            'mbfitr' => '𝒓',
            'mbfits' => '𝒔',
            'mbfitt' => '𝒕',
            'mbfitu' => '𝒖',
            'mbfitv' => '𝒗',
            'mbfitw' => '𝒘',
            'mbfitx' => '𝒙',
            'mbfity' => '𝒚',
            'mbfitz' => '𝒛',
            'mbfscrA' => '𝓐',
            'mbfscrB' => '𝓑',
            'mbfscrC' => '𝓒',
            'mbfscrD' => '𝓓',
            'mbfscrE' => '𝓔',
            'mbfscrF' => '𝓕',
            'mbfscrG' => '𝓖',
            'mbfscrH' => '𝓗',
            'mbfscrI' => '𝓘',
            'mbfscrJ' => '𝓙',
            'mbfscrK' => '𝓚',
            'mbfscrL' => '𝓛',
            'mbfscrM' => '𝓜',
            'mbfscrN' => '𝓝',
            'mbfscrO' => '𝓞',
            'mbfscrP' => '𝓟',
            'mbfscrQ' => '𝓠',
            'mbfscrR' => '𝓡',
            'mbfscrS' => '𝓢',
            'mbfscrT' => '𝓣',
            'mbfscrU' => '𝓤',
            'mbfscrV' => '𝓥',
            'mbfscrW' => '𝓦',
            'mbfscrX' => '𝓧',
            'mbfscrY' => '𝓨',
            'mbfscrZ' => '𝓩',
            'mbfscra' => '𝓪',
            'mbfscrb' => '𝓫',
            'mbfscrc' => '𝓬',
            'mbfscrd' => '𝓭',
            'mbfscre' => '𝓮',
            'mbfscrf' => '𝓯',
            'mbfscrg' => '𝓰',
            'mbfscrh' => '𝓱',
            'mbfscri' => '𝓲',
            'mbfscrj' => '𝓳',
            'mbfscrk' => '𝓴',
            'mbfscrl' => '𝓵',
            'mbfscrm' => '𝓶',
            'mbfscrn' => '𝓷',
            'mbfscro' => '𝓸',
            'mbfscrp' => '𝓹',
            'mbfscrq' => '𝓺',
            'mbfscrr' => '𝓻',
            'mbfscrs' => '𝓼',
            'mbfscrt' => '𝓽',
            'mbfscru' => '𝓾',
            'mbfscrv' => '𝓿',
            'mbfscrw' => '𝔀',
            'mbfscrx' => '𝔁',
            'mbfscry' => '𝔂',
            'mbfscrz' => '𝔃',
            'mfrakA' => '𝔄',
            'mfrakB' => '𝔅',
            'mfrakC' => 'ℭ',
            'mfrakD' => '𝔇',
            'mfrakE' => '𝔈',
            'mfrakF' => '𝔉',
            'mfrakG' => '𝔊',
            'mfrakH' => 'ℌ',
            'mfrakJ' => '𝔍',
            'mfrakK' => '𝔎',
            'mfrakL' => '𝔏',
            'mfrakM' => '𝔐',
            'mfrakN' => '𝔑',
            'mfrakO' => '𝔒',
            'mfrakP' => '𝔓',
            'mfrakQ' => '𝔔',
            'mfrakS' => '𝔖',
            'mfrakT' => '𝔗',
            'mfrakU' => '𝔘',
            'mfrakV' => '𝔙',
            'mfrakW' => '𝔚',
            'mfrakX' => '𝔛',
            'mfrakY' => '𝔜',
            'mfrakZ' => 'ℨ',
            'mfraka' => '𝔞',
            'mfrakb' => '𝔟',
            'mfrakc' => '𝔠',
            'mfrakd' => '𝔡',
            'mfrake' => '𝔢',
            'mfrakf' => '𝔣',
            'mfrakg' => '𝔤',
            'mfrakh' => '𝔥',
            'mfraki' => '𝔦',
            'mfrakj' => '𝔧',
            'mfrakk' => '𝔨',
            'mfrakl' => '𝔩',
            'mfrakm' => '𝔪',
            'mfrakn' => '𝔫',
            'mfrako' => '𝔬',
            'mfrakp' => '𝔭',
            'mfrakq' => '𝔮',
            'mfrakr' => '𝔯',
            'mfraks' => '𝔰',
            'mfrakt' => '𝔱',
            'mfraku' => '𝔲',
            'mfrakv' => '𝔳',
            'mfrakw' => '𝔴',
            'mfrakx' => '𝔵',
            'mfraky' => '𝔶',
            'mfrakz' => '𝔷',
            'mbffrakA' => '𝕬',
            'mbffrakB' => '𝕭',
            'mbffrakC' => '𝕮',
            'mbffrakD' => '𝕯',
            'mbffrakE' => '𝕰',
            'mbffrakF' => '𝕱',
            'mbffrakG' => '𝕲',
            'mbffrakH' => '𝕳',
            'mbffrakI' => '𝕴',
            'mbffrakJ' => '𝕵',
            'mbffrakK' => '𝕶',
            'mbffrakL' => '𝕷',
            'mbffrakM' => '𝕸',
            'mbffrakN' => '𝕹',
            'mbffrakO' => '𝕺',
            'mbffrakP' => '𝕻',
            'mbffrakQ' => '𝕼',
            'mbffrakR' => '𝕽',
            'mbffrakS' => '𝕾',
            'mbffrakT' => '𝕿',
            'mbffrakU' => '𝖀',
            'mbffrakV' => '𝖁',
            'mbffrakW' => '𝖂',
            'mbffrakX' => '𝖃',
            'mbffrakY' => '𝖄',
            'mbffrakZ' => '𝖅',
            'mbffraka' => '𝖆',
            'mbffrakb' => '𝖇',
            'mbffrakc' => '𝖈',
            'mbffrakd' => '𝖉',
            'mbffrake' => '𝖊',
            'mbffrakf' => '𝖋',
            'mbffrakg' => '𝖌',
            'mbffrakh' => '𝖍',
            'mbffraki' => '𝖎',
            'mbffrakj' => '𝖏',
            'mbffrakk' => '𝖐',
            'mbffrakl' => '𝖑',
            'mbffrakm' => '𝖒',
            'mbffrakn' => '𝖓',
            'mbffrako' => '𝖔',
            'mbffrakp' => '𝖕',
            'mbffrakq' => '𝖖',
            'mbffrakr' => '𝖗',
            'mbffraks' => '𝖘',
            'mbffrakt' => '𝖙',
            'mbffraku' => '𝖚',
            'mbffrakv' => '𝖛',
            'mbffrakw' => '𝖜',
            'mbffrakx' => '𝖝',
            'mbffraky' => '𝖞',
            'mbffrakz' => '𝖟',
            'BbbA' => '𝔸',
            'BbbB' => '𝔹',
            'BbbC' => 'ℂ',
            'BbbD' => '𝔻',
            'BbbE' => '𝔼',
            'BbbF' => '𝔽',
            'BbbG' => '𝔾',
            'BbbH' => 'ℍ',
            'BbbI' => '𝕀',
            'BbbJ' => '𝕁',
            'BbbK' => '𝕂',
            'BbbL' => '𝕃',
            'BbbM' => '𝕄',
            'BbbN' => 'ℕ',
            'BbbO' => '𝕆',
            'BbbP' => 'ℙ',
            'BbbQ' => 'ℚ',
            'BbbR' => 'ℝ',
            'BbbS' => '𝕊',
            'BbbT' => '𝕋',
            'BbbU' => '𝕌',
            'BbbV' => '𝕍',
            'BbbW' => '𝕎',
            'BbbX' => '𝕏',
            'BbbY' => '𝕐',
            'BbbZ' => 'ℤ',
            'Bbba' => '𝕒',
            'Bbbb' => '𝕓',
            'Bbbc' => '𝕔',
            'Bbbd' => '𝕕',
            'Bbbe' => '𝕖',
            'Bbbf' => '𝕗',
            'Bbbg' => '𝕘',
            'Bbbh' => '𝕙',
            'Bbbi' => '𝕚',
            'Bbbj' => '𝕛',
            'Bbbl' => '𝕝',
            'Bbbm' => '𝕞',
            'Bbbn' => '𝕟',
            'Bbbo' => '𝕠',
            'Bbbp' => '𝕡',
            'Bbbq' => '𝕢',
            'Bbbr' => '𝕣',
            'Bbbs' => '𝕤',
            'Bbbt' => '𝕥',
            'Bbbu' => '𝕦',
            'Bbbv' => '𝕧',
            'Bbbw' => '𝕨',
            'Bbbx' => '𝕩',
            'Bbby' => '𝕪',
            'Bbbz' => '𝕫',
            'msansA' => '𝖠',
            'msansB' => '𝖡',
            'msansC' => '𝖢',
            'msansD' => '𝖣',
            'msansE' => '𝖤',
            'msansF' => '𝖥',
            'msansG' => '𝖦',
            'msansH' => '𝖧',
            'msansI' => '𝖨',
            'msansJ' => '𝖩',
            'msansK' => '𝖪',
            'msansL' => '𝖫',
            'msansM' => '𝖬',
            'msansN' => '𝖭',
            'msansO' => '𝖮',
            'msansP' => '𝖯',
            'msansQ' => '𝖰',
            'msansR' => '𝖱',
            'msansS' => '𝖲',
            'msansT' => '𝖳',
            'msansU' => '𝖴',
            'msansV' => '𝖵',
            'msansW' => '𝖶',
            'msansX' => '𝖷',
            'msansY' => '𝖸',
            'msansZ' => '𝖹',
            'msansa' => '𝖺',
            'msansb' => '𝖻',
            'msansc' => '𝖼',
            'msansd' => '𝖽',
            'msanse' => '𝖾',
            'msansf' => '𝖿',
            'msansg' => '𝗀',
            'msansh' => '𝗁',
            'msansi' => '𝗂',
            'msansj' => '𝗃',
            'msansk' => '𝗄',
            'msansl' => '𝗅',
            'msansm' => '𝗆',
            'msansn' => '𝗇',
            'msanso' => '𝗈',
            'msansp' => '𝗉',
            'msansq' => '𝗊',
            'msansr' => '𝗋',
            'msanss' => '𝗌',
            'msanst' => '𝗍',
            'msansu' => '𝗎',
            'msansv' => '𝗏',
            'msansw' => '𝗐',
            'msansx' => '𝗑',
            'msansy' => '𝗒',
            'msansz' => '𝗓',
            'mbfsansA' => '𝗔',
            'mbfsansB' => '𝗕',
            'mbfsansC' => '𝗖',
            'mbfsansD' => '𝗗',
            'mbfsansE' => '𝗘',
            'mbfsansF' => '𝗙',
            'mbfsansG' => '𝗚',
            'mbfsansH' => '𝗛',
            'mbfsansI' => '𝗜',
            'mbfsansJ' => '𝗝',
            'mbfsansK' => '𝗞',
            'mbfsansL' => '𝗟',
            'mbfsansM' => '𝗠',
            'mbfsansN' => '𝗡',
            'mbfsansO' => '𝗢',
            'mbfsansP' => '𝗣',
            'mbfsansQ' => '𝗤',
            'mbfsansR' => '𝗥',
            'mbfsansS' => '𝗦',
            'mbfsansT' => '𝗧',
            'mbfsansU' => '𝗨',
            'mbfsansV' => '𝗩',
            'mbfsansW' => '𝗪',
            'mbfsansX' => '𝗫',
            'mbfsansY' => '𝗬',
            'mbfsansZ' => '𝗭',
            'mbfsansa' => '𝗮',
            'mbfsansb' => '𝗯',
            'mbfsansc' => '𝗰',
            'mbfsansd' => '𝗱',
            'mbfsanse' => '𝗲',
            'mbfsansf' => '𝗳',
            'mbfsansg' => '𝗴',
            'mbfsansh' => '𝗵',
            'mbfsansi' => '𝗶',
            'mbfsansj' => '𝗷',
            'mbfsansk' => '𝗸',
            'mbfsansl' => '𝗹',
            'mbfsansm' => '𝗺',
            'mbfsansn' => '𝗻',
            'mbfsanso' => '𝗼',
            'mbfsansp' => '𝗽',
            'mbfsansq' => '𝗾',
            'mbfsansr' => '𝗿',
            'mbfsanss' => '𝘀',
            'mbfsanst' => '𝘁',
            'mbfsansu' => '𝘂',
            'mbfsansv' => '𝘃',
            'mbfsansw' => '𝘄',
            'mbfsansx' => '𝘅',
            'mbfsansy' => '𝘆',
            'mbfsansz' => '𝘇',
            'mitsansA' => '𝘈',
            'mitsansB' => '𝘉',
            'mitsansC' => '𝘊',
            'mitsansD' => '𝘋',
            'mitsansE' => '𝘌',
            'mitsansF' => '𝘍',
            'mitsansG' => '𝘎',
            'mitsansH' => '𝘏',
            'mitsansI' => '𝘐',
            'mitsansJ' => '𝘑',
            'mitsansK' => '𝘒',
            'mitsansL' => '𝘓',
            'mitsansM' => '𝘔',
            'mitsansN' => '𝘕',
            'mitsansO' => '𝘖',
            'mitsansP' => '𝘗',
            'mitsansQ' => '𝘘',
            'mitsansR' => '𝘙',
            'mitsansS' => '𝘚',
            'mitsansT' => '𝘛',
            'mitsansU' => '𝘜',
            'mitsansV' => '𝘝',
            'mitsansW' => '𝘞',
            'mitsansX' => '𝘟',
            'mitsansY' => '𝘠',
            'mitsansZ' => '𝘡',
            'mitsansa' => '𝘢',
            'mitsansb' => '𝘣',
            'mitsansc' => '𝘤',
            'mitsansd' => '𝘥',
            'mitsanse' => '𝘦',
            'mitsansf' => '𝘧',
            'mitsansg' => '𝘨',
            'mitsansh' => '𝘩',
            'mitsansi' => '𝘪',
            'mitsansj' => '𝘫',
            'mitsansk' => '𝘬',
            'mitsansl' => '𝘭',
            'mitsansm' => '𝘮',
            'mitsansn' => '𝘯',
            'mitsanso' => '𝘰',
            'mitsansp' => '𝘱',
            'mitsansq' => '𝘲',
            'mitsansr' => '𝘳',
            'mitsanss' => '𝘴',
            'mitsanst' => '𝘵',
            'mitsansu' => '𝘶',
            'mitsansv' => '𝘷',
            'mitsansw' => '𝘸',
            'mitsansx' => '𝘹',
            'mitsansy' => '𝘺',
            'mitsansz' => '𝘻',
            'mbfitsansA' => '𝘼',
            'mbfitsansB' => '𝘽',
            'mbfitsansC' => '𝘾',
            'mbfitsansD' => '𝘿',
            'mbfitsansE' => '𝙀',
            'mbfitsansF' => '𝙁',
            'mbfitsansG' => '𝙂',
            'mbfitsansH' => '𝙃',
            'mbfitsansI' => '𝙄',
            'mbfitsansJ' => '𝙅',
            'mbfitsansK' => '𝙆',
            'mbfitsansL' => '𝙇',
            'mbfitsansM' => '𝙈',
            'mbfitsansN' => '𝙉',
            'mbfitsansO' => '𝙊',
            'mbfitsansP' => '𝙋',
            'mbfitsansQ' => '𝙌',
            'mbfitsansR' => '𝙍',
            'mbfitsansS' => '𝙎',
            'mbfitsansT' => '𝙏',
            'mbfitsansU' => '𝙐',
            'mbfitsansV' => '𝙑',
            'mbfitsansW' => '𝙒',
            'mbfitsansX' => '𝙓',
            'mbfitsansY' => '𝙔',
            'mbfitsansZ' => '𝙕',
            'mbfitsansa' => '𝙖',
            'mbfitsansb' => '𝙗',
            'mbfitsansc' => '𝙘',
            'mbfitsansd' => '𝙙',
            'mbfitsanse' => '𝙚',
            'mbfitsansf' => '𝙛',
            'mbfitsansg' => '𝙜',
            'mbfitsansh' => '𝙝',
            'mbfitsansi' => '𝙞',
            'mbfitsansj' => '𝙟',
            'mbfitsansk' => '𝙠',
            'mbfitsansl' => '𝙡',
            'mbfitsansm' => '𝙢',
            'mbfitsansn' => '𝙣',
            'mbfitsanso' => '𝙤',
            'mbfitsansp' => '𝙥',
            'mbfitsansq' => '𝙦',
            'mbfitsansr' => '𝙧',
            'mbfitsanss' => '𝙨',
            'mbfitsanst' => '𝙩',
            'mbfitsansu' => '𝙪',
            'mbfitsansv' => '𝙫',
            'mbfitsansw' => '𝙬',
            'mbfitsansx' => '𝙭',
            'mbfitsansy' => '𝙮',
            'mbfitsansz' => '𝙯',
            'mttA' => '𝙰',
            'mttB' => '𝙱',
            'mttC' => '𝙲',
            'mttD' => '𝙳',
            'mttE' => '𝙴',
            'mttF' => '𝙵',
            'mttG' => '𝙶',
            'mttH' => '𝙷',
            'mttI' => '𝙸',
            'mttJ' => '𝙹',
            'mttK' => '𝙺',
            'mttL' => '𝙻',
            'mttM' => '𝙼',
            'mttN' => '𝙽',
            'mttO' => '𝙾',
            'mttP' => '𝙿',
            'mttQ' => '𝚀',
            'mttR' => '𝚁',
            'mttS' => '𝚂',
            'mttT' => '𝚃',
            'mttU' => '𝚄',
            'mttV' => '𝚅',
            'mttW' => '𝚆',
            'mttX' => '𝚇',
            'mttY' => '𝚈',
            'mttZ' => '𝚉',
            'mtta' => '𝚊',
            'mttb' => '𝚋',
            'mttc' => '𝚌',
            'mttd' => '𝚍',
            'mtte' => '𝚎',
            'mttf' => '𝚏',
            'mttg' => '𝚐',
            'mtth' => '𝚑',
            'mtti' => '𝚒',
            'mttj' => '𝚓',
            'mttk' => '𝚔',
            'mttl' => '𝚕',
            'mttm' => '𝚖',
            'mttn' => '𝚗',
            'mtto' => '𝚘',
            'mttp' => '𝚙',
            'mttq' => '𝚚',
            'mttr' => '𝚛',
            'mtts' => '𝚜',
            'mttt' => '𝚝',
            'mttu' => '𝚞',
            'mttv' => '𝚟',
            'mttw' => '𝚠',
            'mttx' => '𝚡',
            'mtty' => '𝚢',
            'mttz' => '𝚣',
        ];
        if (isset($letterSymbols[$command])) {
            return '<mi>' . $this->esc($letterSymbols[$command]) . '</mi>';
        }

        $operators = [
            ',' => '<mspace width="0.167em"/>',
            ':' => '<mspace width="0.222em"/>',
            ';' => '<mspace width="0.278em"/>',
            '!' => '<mspace width="0em"/>',
            'thinspace' => '<mspace width="0.167em"/>',
            'medspace' => '<mspace width="0.222em"/>',
            'thickspace' => '<mspace width="0.278em"/>',
            'negthinspace' => '<mspace width="0em"/>',
            'negmedspace' => '<mspace width="0em"/>',
            'negthickspace' => '<mspace width="0em"/>',
            'enspace' => '<mspace width="0.5em"/>',
            'quad' => '<mspace width="1em"/>',
            'qquad' => '<mspace width="2em"/>',
            'mathexclam' => '!',
            'mathoctothorpe' => '#',
            'mathdollar' => '$',
            'mathpercent' => '%',
            'mathampersand' => '&',
            'mathplus' => '+',
            'mathcomma' => ',',
            'mathperiod' => '.',
            'mathslash' => '/',
            'mathcolon' => ':',
            'mathsemicolon' => ';',
            'less' => '<',
            'equal' => '=',
            'greater' => '>',
            'mathquestion' => '?',
            'mathatsign' => '@',
            'mathsterling' => '£',
            'mathyen' => '¥',
            'mathsection' => '§',
            'mathparagraph' => '¶',
            'horizbar' => '―',
            'twolowline' => '‗',
            'smblkcircle' => '•',
            'enleadertwodots' => '‥',
            'unicodeellipsis' => '…',
            'dprime' => '″',
            'trprime' => '‴',
            'backdprime' => '‶',
            'backtrprime' => '‷',
            'caretinsert' => '‸',
            'Exclam' => '‼',
            'tieconcat' => '⁀',
            'hyphenbullet' => '⁃',
            'fracslash' => '⁄',
            'Question' => '⁇',
            'closure' => '⁐',
            'qprime' => '⁗',
            'euro' => '€',
            'enclosecircle' => '⃝',
            'enclosesquare' => '⃞',
            'enclosediamond' => '⃟',
            'enclosetriangle' => '⃤',
            'increment' => '∆',
            'smallin' => '∊',
            'nni' => '∌',
            'smallni' => '∍',
            'QED' => '∎',
            'minus' => '−',
            'vysmwhtcircle' => '∘',
            'vysmblkcircle' => '∙',
            'surd' => '√',
            'cuberoot' => '∛',
            'fourthroot' => '∜',
            'rightangle' => '∟',
            'intclockwise' => '∱',
            'varointclockwise' => '∲',
            'ointctrclockwise' => '∳',
            'mathratio' => '∶',
            'Colon' => '∷',
            'dotminus' => '∸',
            'dashcolon' => '∹',
            'dotsminusdots' => '∺',
            'kernelcontraction' => '∻',
            'invlazys' => '∾',
            'sinewave' => '∿',
            'times' => '×',
            'cdot' => '⋅',
            'cdotp' => '⋅',
            'dotplus' => '∔',
            'divslash' => '∕',
            'ldotp' => '.',
            'dots' => '…',
            'ldots' => '…',
            'mathellipsis' => '…',
            'dotsc' => '…',
            'dotso' => '…',
            'cdots' => '⋯',
            'dotsb' => '⋯',
            'dotsm' => '⋯',
            'dotsi' => '⋯',
            'vdots' => '⋮',
            'ddots' => '⋱',
            'iddots' => '⋰',
            'colon' => ':',
            'div' => '÷',
            'ast' => '∗',
            'star' => '⋆',
            'circ' => '∘',
            'bullet' => '•',
            'amalg' => '⨿',
            'upand' => '⅋',
            'wr' => '≀',
            'dag' => '†',
            'dagger' => '†',
            'ddag' => '‡',
            'ddagger' => '‡',
            'diamond' => '⋄',
            'bigcirc' => '◯',
            'triangleleft' => '◁',
            'triangleright' => '▷',
            'lhd' => '◁',
            'rhd' => '▷',
            'unlhd' => '⊴',
            'unrhd' => '⊵',
            'bigtriangleup' => '△',
            'bigtriangledown' => '▽',
            'boxplus' => '⊞',
            'boxminus' => '⊟',
            'boxtimes' => '⊠',
            'boxdot' => '⊡',
            'boxbar' => '◫',
            'boxdiag' => '⧄',
            'boxbslash' => '⧅',
            'boxast' => '⧆',
            'boxcircle' => '⧇',
            'boxbox' => '⧈',
            'ltimes' => '⋉',
            'rtimes' => '⋊',
            'leftthreetimes' => '⋋',
            'rightthreetimes' => '⋌',
            'curlyvee' => '⋎',
            'curlywedge' => '⋏',
            'barwedge' => '⊼',
            'veebar' => '⊻',
            'doublebarwedge' => '⩞',
            'Cup' => '⋓',
            'Cap' => '⋒',
            'doublecup' => '⋓',
            'doublecap' => '⋒',
            'intercal' => '⊺',
            'circledast' => '⊛',
            'circledcirc' => '⊚',
            'circleddash' => '⊝',
            'circledequal' => '⊜',
            'circlehbar' => '⦵',
            'circledvert' => '⦶',
            'circledparallel' => '⦷',
            'obslash' => '⦸',
            'operp' => '⦹',
            'obar' => '⌽',
            'olessthan' => '⧀',
            'ogreaterthan' => '⧁',
            'pm' => '±',
            'mp' => '∓',
            'le' => '≤',
            'leq' => '≤',
            'leqq' => '≦',
            'leqslant' => '⩽',
            'lt' => '<',
            'ge' => '≥',
            'geq' => '≥',
            'geqq' => '≧',
            'geqslant' => '⩾',
            'gt' => '>',
            'ne' => '≠',
            'neq' => '≠',
            'neqq' => '≠',
            'equiv' => '≡',
            'approx' => '≈',
            'napprox' => '≉',
            'approxident' => '≋',
            'simeq' => '≃',
            'sime' => '≃',
            'nsim' => '≁',
            'nsime' => '≄',
            'nsimeq' => '≄',
            'simneqq' => '≆',
            'sim' => '∼',
            'cong' => '≅',
            'ncong' => '≇',
            'backcong' => '≌',
            'propto' => '∝',
            'varpropto' => '∝',
            'coloneq' => '≔',
            'eqcolon' => '≕',
            'arceq' => '≘',
            'wedgeq' => '≙',
            'veeeq' => '≚',
            'stareq' => '≛',
            'eqdef' => '≝',
            'measeq' => '≞',
            'nequiv' => '≢',
            'Equiv' => '≣',
            'nasymp' => '≭',
            'lesssim' => '≲',
            'gtrsim' => '≳',
            'lessapprox' => '⪅',
            'gtrapprox' => '⪆',
            'll' => '≪',
            'gg' => '≫',
            'lll' => '⋘',
            'llless' => '⋘',
            'ggg' => '⋙',
            'gggtr' => '⋙',
            'lessgtr' => '≶',
            'gtrless' => '≷',
            'nlessgtr' => '≸',
            'ngtrless' => '≹',
            'lesseqgtr' => '⋚',
            'gtreqless' => '⋛',
            'lesseqqgtr' => '⪋',
            'gtreqqless' => '⪌',
            'lessdot' => '⋖',
            'gtrdot' => '⋗',
            'eqless' => '⋜',
            'eqgtr' => '⋝',
            'prec' => '≺',
            'succ' => '≻',
            'preceq' => '≼',
            'succeq' => '≽',
            'preccurlyeq' => '≼',
            'succcurlyeq' => '≽',
            'curlyeqprec' => '⋞',
            'curlyeqsucc' => '⋟',
            'precsim' => '≾',
            'succsim' => '≿',
            'precneq' => '⪱',
            'succneq' => '⪲',
            'preceqq' => '⪳',
            'succeqq' => '⪴',
            'precapprox' => '⪷',
            'succapprox' => '⪸',
            'precneqq' => '⪵',
            'succneqq' => '⪶',
            'precnsim' => '⋨',
            'succnsim' => '⋩',
            'precnapprox' => '⪹',
            'succnapprox' => '⪺',
            'Prec' => '⪻',
            'Succ' => '⪼',
            'asymp' => '≍',
            'approxeq' => '≊',
            'eqsim' => '≂',
            'thicksim' => '∼',
            'thickapprox' => '≈',
            'backsim' => '∽',
            'backsimeq' => '⋍',
            'doteq' => '≐',
            'Doteq' => '≑',
            'doteqdot' => '≑',
            'risingdotseq' => '≓',
            'fallingdotseq' => '≒',
            'bumpeq' => '≏',
            'Bumpeq' => '≎',
            'circeq' => '≗',
            'triangleq' => '≜',
            'eqcirc' => '≖',
            'questeq' => '≟',
            'in' => '∈',
            'notin' => '∉',
            'ni' => '∋',
            'owns' => '∋',
            'emptyset' => '∅',
            'varnothing' => '∅',
            'forall' => '∀',
            'exists' => '∃',
            'nexists' => '∄',
            'neg' => '¬',
            'lnot' => '¬',
            'top' => '⊤',
            'bot' => '⊥',
            'angle' => '∠',
            'measuredangle' => '∡',
            'sphericalangle' => '∢',
            'triangle' => '△',
            'therefore' => '∴',
            'because' => '∵',
            'prime' => '′',
            'backprime' => '‵',
            'complement' => '∁',
            'vartriangle' => '△',
            'triangledown' => '▽',
            'blacktriangle' => '▲',
            'blacktriangledown' => '▼',
            'blacklozenge' => '◆',
            'bigstar' => '★',
            'diagup' => '⟋',
            'diagdown' => '⟍',
            'circledS' => 'Ⓢ',
            'backepsilon' => '϶',
            'upbackepsilon' => '϶',
            'nmid' => '∤',
            'nparallel' => '∦',
            'parallel' => '∥',
            'perp' => '⊥',
            'mid' => '∣',
            'equalparallel' => '⋕',
            'shortmid' => '∣',
            'shortparallel' => '∥',
            'nshortmid' => '∤',
            'nshortparallel' => '∦',
            'vdash' => '⊢',
            'dashv' => '⊣',
            'models' => '⊨',
            'vDash' => '⊨',
            'Vdash' => '⊩',
            'Vvdash' => '⊪',
            'VDash' => '⊫',
            'nvdash' => '⊬',
            'nvDash' => '⊭',
            'nVdash' => '⊮',
            'nVDash' => '⊯',
            'assert' => '⊦',
            'prurel' => '⊰',
            'scurel' => '⊱',
            'origof' => '⊶',
            'imageof' => '⊷',
            'hermitmatrix' => '⊹',
            'measuredrightangle' => '⊾',
            'varlrtriangle' => '⊿',
            'smwhtdiamond' => '⋄',
            'unicodecdots' => '⋯',
            'adots' => '⋰',
            'disin' => '⋲',
            'varisins' => '⋳',
            'isins' => '⋴',
            'isindot' => '⋵',
            'varisinobar' => '⋶',
            'isinobar' => '⋷',
            'isinvb' => '⋸',
            'isinE' => '⋹',
            'nisd' => '⋺',
            'varnis' => '⋻',
            'nis' => '⋼',
            'varniobar' => '⋽',
            'niobar' => '⋾',
            'bagmember' => '⋿',
            'diameter' => '⌀',
            'house' => '⌂',
            'varbarwedge' => '⌅',
            'vardoublebarwedge' => '⌆',
            'invnot' => '⌐',
            'sqlozenge' => '⌑',
            'profline' => '⌒',
            'profsurf' => '⌓',
            'viewdata' => '⌗',
            'turnednot' => '⌙',
            'ulcorner' => '⌜',
            'urcorner' => '⌝',
            'llcorner' => '⌞',
            'lrcorner' => '⌟',
            'inttop' => '⌠',
            'intbottom' => '⌡',
            'varhexagonlrbonds' => '⌬',
            'conictaper' => '⌲',
            'topbot' => '⌶',
            'APLnotslash' => '⌿',
            'APLnotbackslash' => '⍀',
            'APLboxupcaret' => '⍓',
            'APLboxquestion' => '⍰',
            'rangledownzigzagarrow' => '⍼',
            'hexagon' => '⎔',
            'lparenuend' => '⎛',
            'lparenextender' => '⎜',
            'lparenlend' => '⎝',
            'rparenuend' => '⎞',
            'rparenextender' => '⎟',
            'rparenlend' => '⎠',
            'lbrackuend' => '⎡',
            'lbrackextender' => '⎢',
            'lbracklend' => '⎣',
            'rbrackuend' => '⎤',
            'rbrackextender' => '⎥',
            'rbracklend' => '⎦',
            'lbraceuend' => '⎧',
            'lbracemid' => '⎨',
            'lbracelend' => '⎩',
            'vbraceextender' => '⎪',
            'rbraceuend' => '⎫',
            'rbracemid' => '⎬',
            'rbracelend' => '⎭',
            'intextender' => '⎮',
            'harrowextender' => '⎯',
            'lmoustache' => '⎰',
            'rmoustache' => '⎱',
            'sumtop' => '⎲',
            'sumbottom' => '⎳',
            'bbrktbrk' => '⎶',
            'sqrtbottom' => '⎷',
            'lvboxline' => '⎸',
            'rvboxline' => '⎹',
            'varcarriagereturn' => '⏎',
            'obrbrak' => '⏠',
            'ubrbrak' => '⏡',
            'trapezium' => '⏢',
            'benzenr' => '⏣',
            'strns' => '⏤',
            'fltns' => '⏥',
            'accurrent' => '⏦',
            'elinters' => '⏧',
            'blanksymbol' => '␢',
            'mathvisiblespace' => '␣',
            'bdtriplevdash' => '┆',
            'blockuphalf' => '▀',
            'blocklowhalf' => '▄',
            'blockfull' => '█',
            'blocklefthalf' => '▌',
            'blockrighthalf' => '▐',
            'blockqtrshaded' => '░',
            'blockhalfshaded' => '▒',
            'blockthreeqtrshaded' => '▓',
            'mdlgblksquare' => '■',
            'mdlgwhtsquare' => '□',
            'squoval' => '▢',
            'blackinwhitesquare' => '▣',
            'squarehfill' => '▤',
            'squarevfill' => '▥',
            'squarehvfill' => '▦',
            'squarenwsefill' => '▧',
            'squareneswfill' => '▨',
            'squarecrossfill' => '▩',
            'smblksquare' => '▪',
            'smwhtsquare' => '▫',
            'hrectangleblack' => '▬',
            'hrectangle' => '▭',
            'vrectangleblack' => '▮',
            'vrectangle' => '▯',
            'parallelogramblack' => '▰',
            'parallelogram' => '▱',
            'bigblacktriangleup' => '▲',
            'smallblacktriangleright' => '▸',
            'smalltriangleright' => '▹',
            'blackpointerright' => '►',
            'whitepointerright' => '▻',
            'bigblacktriangledown' => '▼',
            'smallblacktriangleleft' => '◂',
            'smalltriangleleft' => '◃',
            'blackpointerleft' => '◄',
            'whitepointerleft' => '◅',
            'mdlgblkdiamond' => '◆',
            'mdlgwhtdiamond' => '◇',
            'blackinwhitediamond' => '◈',
            'fisheye' => '◉',
            'mdlgwhtlozenge' => '◊',
            'mdlgwhtcircle' => '○',
            'dottedcircle' => '◌',
            'circlevertfill' => '◍',
            'bullseye' => '◎',
            'mdlgblkcircle' => '●',
            'circlelefthalfblack' => '◐',
            'circlerighthalfblack' => '◑',
            'circlebottomhalfblack' => '◒',
            'circletophalfblack' => '◓',
            'circleurquadblack' => '◔',
            'blackcircleulquadwhite' => '◕',
            'blacklefthalfcircle' => '◖',
            'blackrighthalfcircle' => '◗',
            'inversebullet' => '◘',
            'inversewhitecircle' => '◙',
            'invwhiteupperhalfcircle' => '◚',
            'invwhitelowerhalfcircle' => '◛',
            'ularc' => '◜',
            'urarc' => '◝',
            'lrarc' => '◞',
            'llarc' => '◟',
            'topsemicircle' => '◠',
            'botsemicircle' => '◡',
            'lrblacktriangle' => '◢',
            'llblacktriangle' => '◣',
            'ulblacktriangle' => '◤',
            'urblacktriangle' => '◥',
            'smwhtcircle' => '◦',
            'squareleftblack' => '◧',
            'squarerightblack' => '◨',
            'squareulblack' => '◩',
            'squarelrblack' => '◪',
            'trianglecdot' => '◬',
            'triangleleftblack' => '◭',
            'trianglerightblack' => '◮',
            'lgwhtcircle' => '◯',
            'squareulquad' => '◰',
            'squarellquad' => '◱',
            'squarelrquad' => '◲',
            'squareurquad' => '◳',
            'circleulquad' => '◴',
            'circlellquad' => '◵',
            'circlelrquad' => '◶',
            'circleurquad' => '◷',
            'ultriangle' => '◸',
            'urtriangle' => '◹',
            'lltriangle' => '◺',
            'mdwhtsquare' => '◻',
            'mdblksquare' => '◼',
            'mdsmwhtsquare' => '◽',
            'mdsmblksquare' => '◾',
            'lrtriangle' => '◿',
            'bigwhitestar' => '☆',
            'astrosun' => '☉',
            'danger' => '☡',
            'blacksmiley' => '☻',
            'sun' => '☼',
            'rightmoon' => '☽',
            'leftmoon' => '☾',
            'female' => '♀',
            'male' => '♂',
            'varspadesuit' => '♤',
            'varheartsuit' => '♥',
            'vardiamondsuit' => '♦',
            'varclubsuit' => '♧',
            'quarternote' => '♩',
            'eighthnote' => '♪',
            'twonotes' => '♫',
            'acidfree' => '♾',
            'dicei' => '⚀',
            'diceii' => '⚁',
            'diceiii' => '⚂',
            'diceiv' => '⚃',
            'dicev' => '⚄',
            'dicevi' => '⚅',
            'circledrightdot' => '⚆',
            'circledtwodots' => '⚇',
            'blackcircledrightdot' => '⚈',
            'blackcircledtwodots' => '⚉',
            'Hermaphrodite' => '⚥',
            'mdwhtcircle' => '⚪',
            'mdblkcircle' => '⚫',
            'mdsmwhtcircle' => '⚬',
            'neuter' => '⚲',
            'checkmark' => '✓',
            'maltese' => '✠',
            'circledstar' => '✪',
            'varstar' => '✶',
            'dingasterisk' => '✽',
            'lbrbrak' => '❲',
            'rbrbrak' => '❳',
            'draftingarrow' => '➛',
            'threedangle' => '⟀',
            'whiteinwhitetriangle' => '⟁',
            'subsetcirc' => '⟃',
            'supsetcirc' => '⟄',
            'lbag' => '⟅',
            'rbag' => '⟆',
            'bsolhsub' => '⟈',
            'suphsol' => '⟉',
            'longdivision' => '⟌',
            'diamondcdot' => '⟐',
            'upin' => '⟒',
            'pullback' => '⟓',
            'pushout' => '⟔',
            'DashVDash' => '⟚',
            'dashVdash' => '⟛',
            'multimapinv' => '⟜',
            'vlongdash' => '⟝',
            'longdashv' => '⟞',
            'cirbot' => '⟟',
            'lozengeminus' => '⟠',
            'concavediamond' => '⟡',
            'concavediamondtickleft' => '⟢',
            'concavediamondtickright' => '⟣',
            'whitesquaretickleft' => '⟤',
            'whitesquaretickright' => '⟥',
            'lBrack' => '⟦',
            'rBrack' => '⟧',
            'lAngle' => '⟪',
            'rAngle' => '⟫',
            'Lbrbrak' => '⟬',
            'Rbrbrak' => '⟭',
            'lgroup' => '⟮',
            'rgroup' => '⟯',
            'UUparrow' => '⟰',
            'DDownarrow' => '⟱',
            'acwgapcirclearrow' => '⟲',
            'cwgapcirclearrow' => '⟳',
            'rightarrowonoplus' => '⟴',
            'arabicmaj' => '𞻰',
            'arabichad' => '𞻱',
            'subset' => '⊂',
            'subseteq' => '⊆',
            'subseteqq' => '⫅',
            'subsetneq' => '⊊',
            'subsetneqq' => '⫋',
            'subsetapprox' => '⫉',
            'subsetdot' => '⪽',
            'subsetplus' => '⪿',
            'submult' => '⫁',
            'subedot' => '⫃',
            'subsim' => '⫇',
            'nsubset' => '⊄',
            'nsubseteq' => '⊈',
            'nsubseteqq' => '⫅̸',
            'supset' => '⊃',
            'supseteq' => '⊇',
            'supseteqq' => '⫆',
            'supsetneq' => '⊋',
            'supsetneqq' => '⫌',
            'supsetapprox' => '⫊',
            'supsetdot' => '⪾',
            'supsetplus' => '⫀',
            'supmult' => '⫂',
            'supedot' => '⫄',
            'supsim' => '⫈',
            'nsupset' => '⊅',
            'nsupseteq' => '⊉',
            'nsupseteqq' => '⫆̸',
            'lsqhook' => '⫍',
            'rsqhook' => '⫎',
            'csub' => '⫏',
            'csup' => '⫐',
            'csube' => '⫑',
            'csupe' => '⫒',
            'subsup' => '⫓',
            'supsub' => '⫔',
            'subsub' => '⫕',
            'supsup' => '⫖',
            'suphsub' => '⫗',
            'supdsub' => '⫘',
            'forkv' => '⫙',
            'topfork' => '⫚',
            'mlcp' => '⫛',
            'forks' => '⫝̸',
            'forksnot' => '⫝',
            'shortlefttack' => '⫞',
            'shortdowntack' => '⫟',
            'shortuptack' => '⫠',
            'perps' => '⫡',
            'vDdash' => '⫢',
            'dashV' => '⫣',
            'Dashv' => '⫤',
            'DashV' => '⫥',
            'varVdash' => '⫦',
            'Barv' => '⫧',
            'vBar' => '⫨',
            'vBarv' => '⫩',
            'barV' => '⫪',
            'Vbar' => '⫫',
            'Not' => '⫬',
            'bNot' => '⫭',
            'revnmid' => '⫮',
            'cirmid' => '⫯',
            'midcir' => '⫰',
            'Subset' => '⋐',
            'Supset' => '⋑',
            'sqsubset' => '⊏',
            'sqsupset' => '⊐',
            'sqsubseteq' => '⊑',
            'sqsupseteq' => '⊒',
            'nsqsubseteq' => '⋢',
            'nsqsupseteq' => '⋣',
            'sqsubsetneq' => '⋤',
            'sqsupsetneq' => '⋥',
            'sqsubsetneqq' => '⋤',
            'sqsupsetneqq' => '⋥',
            'vartriangleleft' => '⊲',
            'vartriangleright' => '⊳',
            'trianglelefteq' => '⊴',
            'trianglerighteq' => '⊵',
            'nvartriangleleft' => '⋪',
            'nvartriangleright' => '⋫',
            'ntrianglelefteq' => '⋬',
            'ntrianglerighteq' => '⋭',
            'blacktriangleleft' => '◀',
            'blacktriangleright' => '▶',
            'nleq' => '≰',
            'ngeq' => '≱',
            'nleqq' => '≦̸',
            'ngeqq' => '≧̸',
            'nless' => '≮',
            'ngtr' => '≯',
            'nleqslant' => '≰',
            'ngeqslant' => '≱',
            'lneq' => '⪇',
            'gneq' => '⪈',
            'lneqq' => '≨',
            'gneqq' => '≩',
            'lvertneqq' => '≨︀',
            'gvertneqq' => '≩︀',
            'lnsim' => '⋦',
            'gnsim' => '⋧',
            'lnapprox' => '⪉',
            'gnapprox' => '⪊',
            'nlesssim' => '≴',
            'ngtrsim' => '≵',
            'nlessapprox' => '⪉',
            'ngtrapprox' => '⪊',
            'npreceq' => '⋠',
            'nsucceq' => '⋡',
            'npreccurlyeq' => '⋠',
            'nsucccurlyeq' => '⋡',
            'nprec' => '⊀',
            'nsucc' => '⊁',
            'nprecsim' => '⋨',
            'nsuccsim' => '⋩',
            'eqslantless' => '⪕',
            'eqslantgtr' => '⪖',
            'bowtie' => '⋈',
            'Join' => '⋈',
            'between' => '≬',
            'pitchfork' => '⋔',
            'leftouterjoin' => '⟕',
            'rightouterjoin' => '⟖',
            'fullouterjoin' => '⟗',
            'bigbot' => '⟘',
            'bigtop' => '⟙',
            'smile' => '⌣',
            'frown' => '⌢',
            'cup' => '∪',
            'cap' => '∩',
            'cupleftarrow' => '⊌',
            'cupdot' => '⊍',
            'barcup' => '⩂',
            'sqcup' => '⊔',
            'sqcap' => '⊓',
            'barcap' => '⩃',
            'uplus' => '⊎',
            'setminus' => '∖',
            'smallsetminus' => '∖',
            'wedge' => '∧',
            'land' => '∧',
            'vee' => '∨',
            'lor' => '∨',
            'veedot' => '⟇',
            'wedgedot' => '⟑',
            'oplus' => '⊕',
            'ominus' => '⊖',
            'otimes' => '⊗',
            'oslash' => '⊘',
            'odot' => '⊙',
            'divideontimes' => '⋇',
            'barvee' => '⊽',
            'intprod' => '⨼',
            'intprodr' => '⨽',
            'varveebar' => '⩡',
            'topcir' => '⫱',
            'nhpar' => '⫲',
            'parsim' => '⫳',
            'interleave' => '⫴',
            'nhVvert' => '⫵',
            'threedotcolon' => '⫶',
            'lllnest' => '⫷',
            'gggnest' => '⫸',
            'leqqslant' => '⫹',
            'geqqslant' => '⫺',
            'trslash' => '⫻',
            'biginterleave' => '⫼',
            'sslash' => '⫽',
            'talloblong' => '⫾',
            'bigtalloblong' => '⫿',
            'bmod' => 'mod',
            'clubsuit' => '♣',
            'spadesuit' => '♠',
            'heartsuit' => '♡',
            'diamondsuit' => '♢',
            'flat' => '♭',
            'natural' => '♮',
            'sharp' => '♯',
            'Box' => '□',
            'square' => '□',
            'Diamond' => '◇',
            'lozenge' => '◊',
            'to' => '→',
            'gets' => '←',
            'rightarrow' => '→',
            'leftarrow' => '←',
            'Rightarrow' => '⇒',
            'Leftarrow' => '⇐',
            'leftrightarrow' => '↔',
            'Leftrightarrow' => '⇔',
            'implies' => '⇒',
            'impliedby' => '⇐',
            'iff' => '⇔',
            'mapsto' => '↦',
            'hookrightarrow' => '↪',
            'hookleftarrow' => '↩',
            'longrightarrow' => '⟶',
            'longleftarrow' => '⟵',
            'longleftrightarrow' => '⟷',
            'Longrightarrow' => '⟹',
            'Longleftarrow' => '⟸',
            'Longleftrightarrow' => '⟺',
            'longmapsto' => '⟼',
            'nleftarrow' => '↚',
            'nrightarrow' => '↛',
            'nleftrightarrow' => '↮',
            'nLeftarrow' => '⇍',
            'nRightarrow' => '⇏',
            'nLeftrightarrow' => '⇎',
            'leftwavearrow' => '↜',
            'rightwavearrow' => '↝',
            'twoheaduparrow' => '↟',
            'twoheaddownarrow' => '↡',
            'mapsfrom' => '↤',
            'mapsup' => '↥',
            'mapsdown' => '↧',
            'updownarrowbar' => '↨',
            'downzigzagarrow' => '↯',
            'Ldsh' => '↲',
            'Rdsh' => '↳',
            'linefeed' => '↴',
            'carriagereturn' => '↵',
            'barovernorthwestarrow' => '↸',
            'barleftarrowrightarrowbar' => '↹',
            'acwopencirclearrow' => '↺',
            'cwopencirclearrow' => '↻',
            'updownarrows' => '⇅',
            'Nwarrow' => '⇖',
            'Nearrow' => '⇗',
            'Searrow' => '⇘',
            'Swarrow' => '⇙',
            'nHuparrow' => '⇞',
            'nHdownarrow' => '⇟',
            'leftdasharrow' => '⇠',
            'updasharrow' => '⇡',
            'rightdasharrow' => '⇢',
            'downdasharrow' => '⇣',
            'leftwhitearrow' => '⇦',
            'upwhitearrow' => '⇧',
            'rightwhitearrow' => '⇨',
            'downwhitearrow' => '⇩',
            'whitearrowupfrombar' => '⇪',
            'leftsquigarrow' => '⇜',
            'barleftarrow' => '⇤',
            'rightarrowbar' => '⇥',
            'circleonrightarrow' => '⇴',
            'downuparrows' => '⇵',
            'rightthreearrows' => '⇶',
            'nvleftarrow' => '⇷',
            'nvrightarrow' => '⇸',
            'nvleftrightarrow' => '⇹',
            'nVleftarrow' => '⇺',
            'nVrightarrow' => '⇻',
            'nVleftrightarrow' => '⇼',
            'leftarrowtriangle' => '⇽',
            'rightarrowtriangle' => '⇾',
            'leftrightarrowtriangle' => '⇿',
            'longmapsfrom' => '⟻',
            'Longmapsfrom' => '⟽',
            'Longmapsto' => '⟾',
            'longrightsquigarrow' => '⟿',
            'nvtwoheadrightarrow' => '⤀',
            'nVtwoheadrightarrow' => '⤁',
            'nvLeftarrow' => '⤂',
            'nvRightarrow' => '⤃',
            'nvLeftrightarrow' => '⤄',
            'twoheadmapsto' => '⤅',
            'Mapsfrom' => '⤆',
            'Mapsto' => '⤇',
            'downarrowbarred' => '⤈',
            'uparrowbarred' => '⤉',
            'Uuparrow' => '⤊',
            'Ddownarrow' => '⤋',
            'leftbkarrow' => '⤌',
            'rightbkarrow' => '⤍',
            'leftdbkarrow' => '⤎',
            'dbkarrow' => '⤏',
            'drbkarrow' => '⤐',
            'rightdotarrow' => '⤑',
            'baruparrow' => '⤒',
            'downarrowbar' => '⤓',
            'nvrightarrowtail' => '⤔',
            'nVrightarrowtail' => '⤕',
            'twoheadrightarrowtail' => '⤖',
            'nvtwoheadrightarrowtail' => '⤗',
            'nVtwoheadrightarrowtail' => '⤘',
            'lefttail' => '⤙',
            'righttail' => '⤚',
            'leftdbltail' => '⤛',
            'rightdbltail' => '⤜',
            'diamondleftarrow' => '⤝',
            'rightarrowdiamond' => '⤞',
            'diamondleftarrowbar' => '⤟',
            'barrightarrowdiamond' => '⤠',
            'nwsearrow' => '⤡',
            'neswarrow' => '⤢',
            'hknwarrow' => '⤣',
            'hknearrow' => '⤤',
            'hksearrow' => '⤥',
            'hkswarrow' => '⤦',
            'tona' => '⤧',
            'toea' => '⤨',
            'tosa' => '⤩',
            'towa' => '⤪',
            'rdiagovfdiag' => '⤫',
            'fdiagovrdiag' => '⤬',
            'seovnearrow' => '⤭',
            'neovsearrow' => '⤮',
            'fdiagovnearrow' => '⤯',
            'rdiagovsearrow' => '⤰',
            'neovnwarrow' => '⤱',
            'nwovnearrow' => '⤲',
            'rightcurvedarrow' => '⤳',
            'uprightcurvearrow' => '⤴',
            'downrightcurvedarrow' => '⤵',
            'leftdowncurvedarrow' => '⤶',
            'rightdowncurvedarrow' => '⤷',
            'cwrightarcarrow' => '⤸',
            'acwleftarcarrow' => '⤹',
            'acwoverarcarrow' => '⤺',
            'acwunderarcarrow' => '⤻',
            'curvearrowrightminus' => '⤼',
            'curvearrowleftplus' => '⤽',
            'cwundercurvearrow' => '⤾',
            'ccwundercurvearrow' => '⤿',
            'acwcirclearrow' => '⥀',
            'cwcirclearrow' => '⥁',
            'rightarrowshortleftarrow' => '⥂',
            'leftarrowshortrightarrow' => '⥃',
            'shortrightarrowleftarrow' => '⥄',
            'rightarrowplus' => '⥅',
            'leftarrowplus' => '⥆',
            'rightarrowx' => '⥇',
            'leftrightarrowcircle' => '⥈',
            'twoheaduparrowcircle' => '⥉',
            'leftrightharpoonupdown' => '⥊',
            'leftrightharpoondownup' => '⥋',
            'updownharpoonrightleft' => '⥌',
            'updownharpoonleftright' => '⥍',
            'leftrightharpoonupup' => '⥎',
            'updownharpoonrightright' => '⥏',
            'leftrightharpoondowndown' => '⥐',
            'updownharpoonleftleft' => '⥑',
            'barleftharpoonup' => '⥒',
            'rightharpoonupbar' => '⥓',
            'barupharpoonright' => '⥔',
            'downharpoonrightbar' => '⥕',
            'barleftharpoondown' => '⥖',
            'rightharpoondownbar' => '⥗',
            'barupharpoonleft' => '⥘',
            'downharpoonleftbar' => '⥙',
            'leftharpoonupbar' => '⥚',
            'barrightharpoonup' => '⥛',
            'upharpoonrightbar' => '⥜',
            'bardownharpoonright' => '⥝',
            'leftharpoondownbar' => '⥞',
            'barrightharpoondown' => '⥟',
            'upharpoonleftbar' => '⥠',
            'bardownharpoonleft' => '⥡',
            'leftharpoonsupdown' => '⥢',
            'upharpoonsleftright' => '⥣',
            'rightharpoonsupdown' => '⥤',
            'downharpoonsleftright' => '⥥',
            'leftrightharpoonsup' => '⥦',
            'leftrightharpoonsdown' => '⥧',
            'rightleftharpoonsup' => '⥨',
            'rightleftharpoonsdown' => '⥩',
            'leftharpoonupdash' => '⥪',
            'dashleftharpoondown' => '⥫',
            'rightharpoonupdash' => '⥬',
            'dashrightharpoondown' => '⥭',
            'updownharpoonsleftright' => '⥮',
            'downupharpoonsleftright' => '⥯',
            'rightimply' => '⥰',
            'equalrightarrow' => '⥱',
            'similarrightarrow' => '⥲',
            'leftarrowsimilar' => '⥳',
            'rightarrowsimilar' => '⥴',
            'rightarrowapprox' => '⥵',
            'ltlarr' => '⥶',
            'leftarrowless' => '⥷',
            'gtrarr' => '⥸',
            'subrarr' => '⥹',
            'leftarrowsubset' => '⥺',
            'suplarr' => '⥻',
            'leftfishtail' => '⥼',
            'rightfishtail' => '⥽',
            'upfishtail' => '⥾',
            'downfishtail' => '⥿',
            'Vvert' => '⦀',
            'mdsmblkcircle' => '⦁',
            'typecolon' => '⦂',
            'lBrace' => '⦃',
            'rBrace' => '⦄',
            'lParen' => '⦅',
            'rParen' => '⦆',
            'llparenthesis' => '⦇',
            'rrparenthesis' => '⦈',
            'llangle' => '⦉',
            'rrangle' => '⦊',
            'lbrackubar' => '⦋',
            'rbrackubar' => '⦌',
            'lbrackultick' => '⦍',
            'rbracklrtick' => '⦎',
            'lbracklltick' => '⦏',
            'rbrackurtick' => '⦐',
            'langledot' => '⦑',
            'rangledot' => '⦒',
            'lparenless' => '⦓',
            'rparengtr' => '⦔',
            'Lparengtr' => '⦕',
            'Rparenless' => '⦖',
            'lblkbrbrak' => '⦗',
            'rblkbrbrak' => '⦘',
            'fourvdots' => '⦙',
            'vzigzag' => '⦚',
            'measuredangleleft' => '⦛',
            'rightanglesqr' => '⦜',
            'rightanglemdot' => '⦝',
            'angles' => '⦞',
            'angdnr' => '⦟',
            'gtlpar' => '⦠',
            'sphericalangleup' => '⦡',
            'turnangle' => '⦢',
            'revangle' => '⦣',
            'angleubar' => '⦤',
            'revangleubar' => '⦥',
            'wideangledown' => '⦦',
            'wideangleup' => '⦧',
            'measanglerutone' => '⦨',
            'measanglelutonw' => '⦩',
            'measanglerdtose' => '⦪',
            'measangleldtosw' => '⦫',
            'measangleurtone' => '⦬',
            'measangleultonw' => '⦭',
            'measangledrtose' => '⦮',
            'measangledltosw' => '⦯',
            'revemptyset' => '⦰',
            'emptysetobar' => '⦱',
            'emptysetocirc' => '⦲',
            'emptysetoarr' => '⦳',
            'emptysetoarrl' => '⦴',
            'obot' => '⦺',
            'olcross' => '⦻',
            'odotslashdot' => '⦼',
            'uparrowoncircle' => '⦽',
            'circledwhitebullet' => '⦾',
            'circledbullet' => '⦿',
            'squaretopblack' => '⬒',
            'squarebotblack' => '⬓',
            'squareurblack' => '⬔',
            'squarellblack' => '⬕',
            'diamondleftblack' => '⬖',
            'diamondrightblack' => '⬗',
            'diamondtopblack' => '⬘',
            'diamondbotblack' => '⬙',
            'dottedsquare' => '⬚',
            'lgblksquare' => '⬛',
            'lgwhtsquare' => '⬜',
            'vysmblksquare' => '⬝',
            'vysmwhtsquare' => '⬞',
            'pentagonblack' => '⬟',
            'pentagon' => '⬠',
            'varhexagon' => '⬡',
            'varhexagonblack' => '⬢',
            'hexagonblack' => '⬣',
            'lgblkcircle' => '⬤',
            'mdblkdiamond' => '⬥',
            'mdwhtdiamond' => '⬦',
            'mdblklozenge' => '⬧',
            'mdwhtlozenge' => '⬨',
            'smblkdiamond' => '⬩',
            'smblklozenge' => '⬪',
            'smwhtlozenge' => '⬫',
            'blkhorzoval' => '⬬',
            'whthorzoval' => '⬭',
            'blkvertoval' => '⬮',
            'whtvertoval' => '⬯',
            'circleonleftarrow' => '⬰',
            'leftthreearrows' => '⬱',
            'leftarrowonoplus' => '⬲',
            'longleftsquigarrow' => '⬳',
            'nvtwoheadleftarrow' => '⬴',
            'nVtwoheadleftarrow' => '⬵',
            'twoheadmapsfrom' => '⬶',
            'twoheadleftdbkarrow' => '⬷',
            'leftdotarrow' => '⬸',
            'nvleftarrowtail' => '⬹',
            'nVleftarrowtail' => '⬺',
            'twoheadleftarrowtail' => '⬻',
            'nvtwoheadleftarrowtail' => '⬼',
            'nVtwoheadleftarrowtail' => '⬽',
            'leftarrowx' => '⬾',
            'leftcurvedarrow' => '⬿',
            'equalleftarrow' => '⭀',
            'bsimilarleftarrow' => '⭁',
            'leftarrowbackapprox' => '⭂',
            'rightarrowgtr' => '⭃',
            'rightarrowsupset' => '⭄',
            'LLeftarrow' => '⭅',
            'RRightarrow' => '⭆',
            'bsimilarrightarrow' => '⭇',
            'rightarrowbackapprox' => '⭈',
            'similarleftarrow' => '⭉',
            'leftarrowapprox' => '⭊',
            'leftarrowbsimilar' => '⭋',
            'rightarrowbsimilar' => '⭌',
            'medwhitestar' => '⭐',
            'medblackstar' => '⭑',
            'smwhitestar' => '⭒',
            'rightpentagonblack' => '⭓',
            'rightpentagon' => '⭔',
            'postalmark' => '〒',
            'hzigzag' => '〰',
            'cirscir' => '⧂',
            'cirE' => '⧃',
            'boxonbox' => '⧉',
            'triangleodot' => '⧊',
            'triangleubar' => '⧋',
            'triangles' => '⧌',
            'triangleserifs' => '⧍',
            'rtriltri' => '⧎',
            'ltrivb' => '⧏',
            'vbrtri' => '⧐',
            'lfbowtie' => '⧑',
            'rfbowtie' => '⧒',
            'fbowtie' => '⧓',
            'lftimes' => '⧔',
            'rftimes' => '⧕',
            'hourglass' => '⧖',
            'blackhourglass' => '⧗',
            'lvzigzag' => '⧘',
            'rvzigzag' => '⧙',
            'Lvzigzag' => '⧚',
            'Rvzigzag' => '⧛',
            'iinfin' => '⧜',
            'tieinfty' => '⧝',
            'nvinfty' => '⧞',
            'dualmap' => '⧟',
            'laplac' => '⧠',
            'lrtriangleeq' => '⧡',
            'shuffle' => '⧢',
            'eparsl' => '⧣',
            'smeparsl' => '⧤',
            'eqvparsl' => '⧥',
            'gleichstark' => '⧦',
            'thermod' => '⧧',
            'downtriangleleftblack' => '⧨',
            'downtrianglerightblack' => '⧩',
            'blackdiamonddownarrow' => '⧪',
            'mdlgblklozenge' => '⧫',
            'circledownarrow' => '⧬',
            'blackcircledownarrow' => '⧭',
            'errbarsquare' => '⧮',
            'errbarblacksquare' => '⧯',
            'errbardiamond' => '⧰',
            'errbarblackdiamond' => '⧱',
            'errbarcircle' => '⧲',
            'errbarblackcircle' => '⧳',
            'ruledelayed' => '⧴',
            'reversesolidus' => '⧵',
            'dsol' => '⧶',
            'rsolbar' => '⧷',
            'xsol' => '⧸',
            'xbsol' => '⧹',
            'doubleplus' => '⧺',
            'tripleplus' => '⧻',
            'lcurvyangle' => '⧼',
            'rcurvyangle' => '⧽',
            'tplus' => '⧾',
            'tminus' => '⧿',
            'bigcupdot' => '⨃',
            'bigsqcap' => '⨅',
            'conjquant' => '⨇',
            'disjquant' => '⨈',
            'bigtimes' => '⨉',
            'modtwosum' => '⨊',
            'sumint' => '⨋',
            'intbar' => '⨍',
            'intBar' => '⨎',
            'fint' => '⨏',
            'cirfnint' => '⨐',
            'awint' => '⨑',
            'rppolint' => '⨒',
            'scpolint' => '⨓',
            'npolint' => '⨔',
            'pointint' => '⨕',
            'sqint' => '⨖',
            'intlarhk' => '⨗',
            'intx' => '⨘',
            'intcap' => '⨙',
            'intcup' => '⨚',
            'upint' => '⨛',
            'lowint' => '⨜',
            'bigtriangleleft' => '⨞',
            'zcmp' => '⨟',
            'zpipe' => '⨠',
            'zproject' => '⨡',
            'ringplus' => '⨢',
            'plushat' => '⨣',
            'simplus' => '⨤',
            'plusdot' => '⨥',
            'plussim' => '⨦',
            'plussubtwo' => '⨧',
            'plustrif' => '⨨',
            'commaminus' => '⨩',
            'minusdot' => '⨪',
            'minusfdots' => '⨫',
            'minusrdots' => '⨬',
            'opluslhrim' => '⨭',
            'oplusrhrim' => '⨮',
            'vectimes' => '⨯',
            'dottimes' => '⨰',
            'timesbar' => '⨱',
            'btimes' => '⨲',
            'smashtimes' => '⨳',
            'otimeslhrim' => '⨴',
            'otimesrhrim' => '⨵',
            'otimeshat' => '⨶',
            'Otimes' => '⨷',
            'odiv' => '⨸',
            'triangleplus' => '⨹',
            'triangleminus' => '⨺',
            'triangletimes' => '⨻',
            'fcmp' => '⨾',
            'capdot' => '⩀',
            'uminus' => '⩁',
            'capwedge' => '⩄',
            'cupvee' => '⩅',
            'cupovercap' => '⩆',
            'capovercup' => '⩇',
            'cupbarcap' => '⩈',
            'capbarcup' => '⩉',
            'twocups' => '⩊',
            'twocaps' => '⩋',
            'closedvarcup' => '⩌',
            'closedvarcap' => '⩍',
            'Sqcap' => '⩎',
            'Sqcup' => '⩏',
            'closedvarcupsmashprod' => '⩐',
            'wedgeodot' => '⩑',
            'veeodot' => '⩒',
            'Wedge' => '⩓',
            'Vee' => '⩔',
            'wedgeonwedge' => '⩕',
            'veeonvee' => '⩖',
            'bigslopedvee' => '⩗',
            'bigslopedwedge' => '⩘',
            'veeonwedge' => '⩙',
            'wedgemidvert' => '⩚',
            'veemidvert' => '⩛',
            'midbarwedge' => '⩜',
            'midbarvee' => '⩝',
            'wedgebar' => '⩟',
            'wedgedoublebar' => '⩠',
            'doublebarvee' => '⩢',
            'veedoublebar' => '⩣',
            'dsub' => '⩤',
            'rsub' => '⩥',
            'eqdot' => '⩦',
            'dotequiv' => '⩧',
            'equivVert' => '⩨',
            'equivVvert' => '⩩',
            'dotsim' => '⩪',
            'simrdots' => '⩫',
            'simminussim' => '⩬',
            'congdot' => '⩭',
            'asteq' => '⩮',
            'hatapprox' => '⩯',
            'approxeqq' => '⩰',
            'eqqplus' => '⩱',
            'pluseqq' => '⩲',
            'eqqsim' => '⩳',
            'Coloneq' => '⩴',
            'eqeq' => '⩵',
            'eqeqeq' => '⩶',
            'ddotseq' => '⩷',
            'equivDD' => '⩸',
            'ltcir' => '⩹',
            'gtcir' => '⩺',
            'ltquest' => '⩻',
            'gtquest' => '⩼',
            'lesdot' => '⩿',
            'gesdot' => '⪀',
            'lesdoto' => '⪁',
            'gesdoto' => '⪂',
            'lesdotor' => '⪃',
            'gesdotol' => '⪄',
            'lsime' => '⪍',
            'gsime' => '⪎',
            'lsimg' => '⪏',
            'gsiml' => '⪐',
            'lgE' => '⪑',
            'glE' => '⪒',
            'lesges' => '⪓',
            'gesles' => '⪔',
            'elsdot' => '⪗',
            'egsdot' => '⪘',
            'eqqless' => '⪙',
            'eqqgtr' => '⪚',
            'eqqslantless' => '⪛',
            'eqqslantgtr' => '⪜',
            'simless' => '⪝',
            'simgtr' => '⪞',
            'simlE' => '⪟',
            'simgE' => '⪠',
            'Lt' => '⪡',
            'Gt' => '⪢',
            'partialmeetcontraction' => '⪣',
            'glj' => '⪤',
            'gla' => '⪥',
            'ltcc' => '⪦',
            'gtcc' => '⪧',
            'lescc' => '⪨',
            'gescc' => '⪩',
            'smt' => '⪪',
            'lat' => '⪫',
            'smte' => '⪬',
            'late' => '⪭',
            'bumpeqq' => '⪮',
            'curvearrowleft' => '↶',
            'curvearrowright' => '↷',
            'circlearrowleft' => '↺',
            'circlearrowright' => '↻',
            'Lsh' => '↰',
            'Rsh' => '↱',
            'dashleftarrow' => '⇠',
            'dashrightarrow' => '⇢',
            'leftleftarrows' => '⇇',
            'rightrightarrows' => '⇉',
            'leftrightarrows' => '⇆',
            'rightleftarrows' => '⇄',
            'looparrowleft' => '↫',
            'looparrowright' => '↬',
            'leftrightsquigarrow' => '↭',
            'Lleftarrow' => '⇚',
            'Rrightarrow' => '⇛',
            'uparrow' => '↑',
            'downarrow' => '↓',
            'updownarrow' => '↕',
            'Uparrow' => '⇑',
            'Downarrow' => '⇓',
            'Updownarrow' => '⇕',
            'nearrow' => '↗',
            'searrow' => '↘',
            'swarrow' => '↙',
            'nwarrow' => '↖',
            'leadsto' => '↝',
            'rightsquigarrow' => '⇝',
            'twoheadrightarrow' => '↠',
            'twoheadleftarrow' => '↞',
            'rightarrowtail' => '↣',
            'leftarrowtail' => '↢',
            'upuparrows' => '⇈',
            'downdownarrows' => '⇊',
            'upharpoonleft' => '↿',
            'upharpoonright' => '↾',
            'downharpoonleft' => '⇃',
            'downharpoonright' => '⇂',
            'rightharpoonup' => '⇀',
            'rightharpoondown' => '⇁',
            'leftharpoonup' => '↼',
            'leftharpoondown' => '↽',
            'rightleftharpoons' => '⇌',
            'leftrightharpoons' => '⇋',
            'restriction' => '↾',
            'multimap' => '⊸',
            'infty' => '∞',
            'partial' => '∂',
            'nabla' => '∇',
            'int' => '∫',
            'intop' => '∫',
            'smallint' => '∫',
            'oint' => '∮',
            'iint' => '∬',
            'iiint' => '∭',
            'iiiint' => '⨌',
            'idotsint' => '∫⋯∫',
            'oiint' => '∯',
            'oiiint' => '∰',
            'sum' => '∑',
            'Bbbsum' => '⅀',
            'prod' => '∏',
            'coprod' => '∐',
            'bigcup' => '⋃',
            'bigcap' => '⋂',
            'bigsqcup' => '⨆',
            'bigvee' => '⋁',
            'bigwedge' => '⋀',
            'bigoplus' => '⨁',
            'bigotimes' => '⨂',
            'bigodot' => '⨀',
            'biguplus' => '⨄',
            '{' => '{',
            '}' => '}',
            'lbrace' => '{',
            'rbrace' => '}',
            'lparen' => '(',
            'rparen' => ')',
            'lbrack' => '[',
            'rbrack' => ']',
            'langle' => '⟨',
            'rangle' => '⟩',
            'lfloor' => '⌊',
            'rfloor' => '⌋',
            'lceil' => '⌈',
            'rceil' => '⌉',
            'vert' => '|',
            'lvert' => '|',
            'rvert' => '|',
            '|' => '‖',
            'Vert' => '‖',
            'lVert' => '‖',
            'rVert' => '‖',
            'backslash' => '\\',
            'left' => '',
            'right' => '',
        ];
        if (!array_key_exists($command, $operators)) {
            return null;
        }
        if ($operators[$command] === '') {
            return '';
        }
        if (str_starts_with($operators[$command], '<mspace')) {
            return $operators[$command];
        }

        return '<mo>' . $this->esc($operators[$command]) . '</mo>';
    }

    private function mathMLRow(string $content): string
    {
        return $this->mathMLContentIsSingleElement($content)
            ? $content
            : '<mrow>' . $content . '</mrow>';
    }

    private function mathMLContentIsSingleElement(string $content): bool
    {
        $previous = libxml_use_internal_errors(true);
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $ok = $dom->loadXML('<root>' . $content . '</root>', LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        if (!$ok || !$dom->documentElement instanceof \DOMElement) {
            return false;
        }

        $elements = [];
        foreach ($dom->documentElement->childNodes as $child) {
            if ($child instanceof \DOMText && trim($child->wholeText) === '') {
                continue;
            }
            if (!$child instanceof \DOMElement) {
                return false;
            }
            $elements[] = $child;
        }
        if (count($elements) !== 1) {
            return false;
        }

        return in_array(strtolower($elements[0]->localName), [
            'munderover',
            'msubsup',
            'msup',
            'msub',
            'mfrac',
            'msqrt',
            'mroot',
            'mover',
            'munder',
            'menclose',
            'mtable',
            'mtr',
            'mtd',
            'mphantom',
            'mpadded',
            'mstyle',
            'mrow',
            'mtext',
            'mi',
            'mn',
            'mo',
            'mspace',
        ], true);
    }

    private function renderWebTeXMath(string $text, bool $display, string $class): string
    {
        $trimmed = trim($text);
        $stylePrefix = $display ? '\\displaystyle ' : '\\textstyle ';
        $src = $this->htmlMathUrl('webtex') . rawurlencode($stylePrefix . $trimmed);

        return '<img style="vertical-align:middle" src="' . $this->esc($src) . '"'
            . ' alt="' . $this->esc($trimmed) . '"'
            . ' title="' . $this->esc($trimmed) . '"'
            . ' class="math ' . $class . '" />';
    }

    private function renderGladTeXMath(string $text, bool $display): string
    {
        return '<eq env="' . ($display ? 'displaymath' : 'math') . '">'
            . $this->esc($text)
            . '</eq>';
    }

    private function htmlMathMethod(): string
    {
        $value = $this->htmlMathOptionValue();

        if (is_array($value)) {
            $value = $value['method'] ?? $value['type'] ?? $value['name'] ?? '';
        }

        $normalized = strtolower(str_replace(['-', '_', ' '], '', (string) $value));

        return match ($normalized) {
            'webtex' => 'webtex',
            'gladtex' => 'gladtex',
            'mathml' => 'mathml',
            'mathjax' => 'mathjax',
            'katex' => 'katex',
            default => 'plain',
        };
    }

    private function htmlMathOptionValue(): mixed
    {
        return $this->options['writerHTMLMathMethod']
            ?? $this->options['htmlMathMethod']
            ?? $this->options['mathMethod']
            ?? 'mathjax';
    }

    private function htmlMathUrl(string $method): string
    {
        $value = $this->htmlMathOptionValue();
        if (is_array($value)) {
            foreach (['url', 'baseUrl', 'src'] as $key) {
                if (isset($value[$key]) && is_scalar($value[$key])) {
                    return (string) $value[$key];
                }
            }
        }

        return $method === 'webtex' ? 'https://latex.codecogs.com/png.latex?' : '';
    }

    /**
     * @param list<string> $classes
     */
    private function highlightLanguage(array $classes): ?string
    {
        if (in_array('nolanguage', $classes, true)) {
            return null;
        }

        foreach ($classes as $class) {
            if ($class === 'haskell') {
                return $class;
            }
        }

        return null;
    }

    /**
     * @param list<string> $classes
     * @return list<string>
     */
    private function highlightedCodeClasses(array $classes, string $language): array
    {
        $ordered = ['sourceCode', $language];
        foreach ($classes as $class) {
            if (in_array($class, ['sourceCode', $language, 'nolanguage'], true)) {
                continue;
            }
            if (!in_array($class, $ordered, true)) {
                $ordered[] = $class;
            }
        }

        return $ordered;
    }

    private function highlightCodeText(string $text, string $language): string
    {
        if ($language !== 'haskell') {
            return $this->esc($text);
        }

        $parts = preg_split('/([!#$%&*+.\/<=>?@\\\\^|~:-]+)/u', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        if ($parts === false) {
            return $this->esc($text);
        }

        $html = '';
        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }
            if (preg_match('/^[!#$%&*+.\/<=>?@\\\\^|~:-]+$/u', $part) === 1) {
                $html .= '<span class="op">' . $this->esc($part) . '</span>';
                continue;
            }
            $html .= $this->esc($part);
        }

        return $html;
    }

    private function renderImage(AstNode $node): string
    {
        $attrs = $this->attrTuple($node);
        $sourceAttr = $attrs['attributes']['src'] ?? null;
        $titleAttr = $attrs['attributes']['title'] ?? null;
        $altAttr = $attrs['attributes']['alt'] ?? null;
        $attrs['attributes'] = array_filter(
            $attrs['attributes'],
            static fn (string $name): bool => !in_array($name, ['src', 'title', 'alt'], true),
            ARRAY_FILTER_USE_KEY
        );

        $source = (string) $node->attr('url', $node->attr('src', is_string($sourceAttr) ? $sourceAttr : ''));
        $parts = ['src="' . $this->esc($source) . '"'];
        $title = (string) $node->attr('title', '');
        if ($title === '' && is_string($titleAttr)) {
            $title = $titleAttr;
        }
        if ($title !== '') {
            $parts[] = 'title="' . $this->esc($title) . '"';
        }

        $alt = $node->attr('alt', null);
        if ((!is_string($alt) || $alt === '') && is_string($altAttr)) {
            $alt = $altAttr;
        }
        if (!is_string($alt) || $alt === '') {
            $alt = $this->plainInlineText($node->children);
        }

        $category = $this->mediaCategory($source);
        if ($category === 'video' || $category === 'audio') {
            return $this->renderMediaImage($category, $source, $title, $alt, $attrs);
        }
        if ($category !== null && $category !== 'image') {
            return $this->renderEmbeddedMedia($source, $title, $attrs);
        }

        if ($alt !== '') {
            $parts[] = 'alt="' . $this->esc($alt) . '"';
        }

        $renderedAttrs = $this->renderAttrTuple($attrs);
        if ($renderedAttrs !== '') {
            $parts[] = ltrim($renderedAttrs);
        }

        return '<img ' . implode(' ', $parts) . ' />';
    }

    /**
     * @param array{id:string, classes:list<string>, attributes:array<string, string>} $attrs
     */
    private function renderMediaImage(string $category, string $source, string $title, string $alt, array $attrs): string
    {
        $tag = $category === 'audio' ? 'audio' : 'video';
        $fallback = $alt === '' ? ucfirst($category) : $alt;
        $parts = ['src="' . $this->esc($source) . '"'];
        if ($title !== '') {
            $parts[] = 'title="' . $this->esc($title) . '"';
        }

        $renderedAttrs = $this->renderAttrTuple($attrs);
        if ($renderedAttrs !== '') {
            $parts[] = ltrim($renderedAttrs);
        }
        $parts[] = 'controls=""';

        return '<' . $tag . ' ' . implode(' ', $parts) . '><a href="'
            . $this->esc($source) . '">' . $this->esc($fallback) . '</a></' . $tag . '>';
    }

    /**
     * @param array{id:string, classes:list<string>, attributes:array<string, string>} $attrs
     */
    private function renderEmbeddedMedia(string $source, string $title, array $attrs): string
    {
        $parts = ['src="' . $this->esc($source) . '"'];
        if ($title !== '') {
            $parts[] = 'title="' . $this->esc($title) . '"';
        }

        $renderedAttrs = $this->renderAttrTuple($attrs);
        if ($renderedAttrs !== '') {
            $parts[] = ltrim($renderedAttrs);
        }

        return '<embed ' . implode(' ', $parts) . ' />';
    }

    private function mediaCategory(string $source): ?string
    {
        if (preg_match('/^data:([^;,]+)/i', $source, $m) === 1) {
            $mime = strtolower($m[1]);
            $slash = strpos($mime, '/');

            return $slash === false ? null : substr($mime, 0, $slash);
        }

        $sourceWithoutGzip = preg_replace('/\.gz$/i', '', $source) ?? $source;
        $path = parse_url($sourceWithoutGzip, PHP_URL_PATH);
        if (!is_string($path) || $path === '') {
            $path = $sourceWithoutGzip;
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return match ($extension) {
            'apng', 'avif', 'bmp', 'emf', 'gif', 'ico', 'jfif', 'jpeg', 'jpg', 'jxl', 'png', 'svg', 'svgz', 'tif', 'tiff', 'webp', 'wmf' => 'image',
            'avi', 'm4v', 'mkv', 'mov', 'mp4', 'mpe', 'mpeg', 'mpg', 'ogv', 'qt', 'webm' => 'video',
            'aac', 'flac', 'm4a', 'mid', 'midi', 'mp3', 'mpga', 'oga', 'opus', 'wav' => 'audio',
            'pdf' => 'application',
            default => null,
        };
    }

    private function renderQuoted(AstNode $node): string
    {
        if ((bool) ($this->options['htmlQTags'] ?? false)) {
            $quoted = $this->qTagContentsAndAttributes($node);

            return '<q' . $quoted['attrs'] . '>' . $quoted['contents'] . '</q>';
        }

        $open = $node->attr('kind') === 'single' ? "\u{2018}" : "\u{201C}";
        $close = $node->attr('kind') === 'single' ? "\u{2019}" : "\u{201D}";

        return $open . $this->renderInlines($node->children) . $close;
    }

    private function referenceLocation(): string
    {
        $value = (string) ($this->options['referenceLocation'] ?? $this->options['writerReferenceLocation'] ?? 'end_of_document');
        $normalized = strtolower(str_replace(['-', '_', ' '], '', $value));

        return match ($normalized) {
            'endofblock' => 'end_of_block',
            'endofsection' => 'end_of_section',
            default => 'end_of_document',
        };
    }

    /**
     * @param list<string> $blocks
     */
    private function appendRenderedBlock(array &$blocks, string $html): void
    {
        if ($html !== '') {
            $blocks[] = $html;
        }
    }

    private function renderPendingFootnotes(string $referenceLocation): string
    {
        $pending = array_slice($this->footnotes, $this->flushedFootnoteCount);
        if ($pending === []) {
            return '';
        }

        $this->flushedFootnoteCount = count($this->footnotes);
        $class = 'footnotes footnotes-' . str_replace('_', '-', $referenceLocation);
        $lines = ['<div class="' . $class . '">'];
        if ($referenceLocation !== 'end_of_block') {
            $lines[] = '<hr />';
        }

        $start = $pending[0]['number'];
        $lines[] = $start === 1 ? '<ol>' : '<ol start="' . $start . '">';
        foreach ($pending as $entry) {
            $lines[] = $this->renderFootnoteItem($entry['number'], $entry['node']);
        }
        $lines[] = '</ol>';
        $lines[] = '</div>';

        return implode("\n", $lines);
    }

    private function renderFootnoteItem(int $number, AstNode $node): string
    {
        $blocks = [];
        foreach ($node->children as $child) {
            $html = $this->renderBlock($child);
            if ($html !== '') {
                $blocks[] = $html;
            }
        }

        if ($blocks === []) {
            $blocks[] = '<p></p>';
        }

        $lastIndex = array_key_last($blocks);
        if ($lastIndex !== null) {
            $backlink = '<a href="#fnref' . $number . '" class="footnote-back" role="doc-backlink">' . "\u{21A9}\u{FE0E}" . '</a>';
            if (str_ends_with($blocks[$lastIndex], '</p>')) {
                $blocks[$lastIndex] = substr($blocks[$lastIndex], 0, -4) . $backlink . '</p>';
            } else {
                $blocks[$lastIndex] .= $backlink;
            }
        }

        return '<li id="fn' . $number . '">' . implode("\n", $blocks) . '</li>';
    }

    /**
     * @return array{contents:string, attrs:string}
     */
    private function qTagContentsAndAttributes(AstNode $node): array
    {
        if (count($node->children) === 1 && $node->children[0]->type === 'span') {
            $spanAttrs = $this->attrTuple($node->children[0]);
            if (
                $spanAttrs['id'] === ''
                && $spanAttrs['classes'] === []
                && array_keys($spanAttrs['attributes']) === ['cite']
            ) {
                return [
                    'contents' => $this->renderInlines($node->children[0]->children),
                    'attrs' => ' cite="' . $this->esc($spanAttrs['attributes']['cite']) . '"',
                ];
            }
        }

        return [
            'contents' => $this->renderInlines($node->children),
            'attrs' => '',
        ];
    }

    /**
     * @param array{id:string, classes:list<string>, attributes:array<string, string>} $attrs
     */
    private function isBareCodeRole(array $attrs, string $role): bool
    {
        return $attrs['id'] === ''
            && $attrs['classes'] === [$role]
            && $attrs['attributes'] === [];
    }

    private function renderAttributes(AstNode $node, ?callable $attributeFilter = null): string
    {
        return $this->renderAttrTuple($this->attrTuple($node), $attributeFilter);
    }

    private function renderHtmlAttributes(AstNode $node, ?callable $attributeFilter = null): string
    {
        return $this->renderAttrTuple($this->htmlAttrTuple($node), $attributeFilter);
    }

    /**
     * @return array{id:string, classes:list<string>, attributes:array<string, string>}
     */
    private function attrTuple(AstNode $node): array
    {
        $id = (string) $node->attr('id', '');
        $classes = $node->attr('classes', []);
        $attributes = $node->attr('attributes', []);

        return [
            'id' => $id,
            'classes' => is_array($classes) ? array_values(array_map('strval', $classes)) : [],
            'attributes' => is_array($attributes)
                ? array_map('strval', array_filter($attributes, static fn (mixed $value): bool => is_scalar($value)))
                : [],
        ];
    }

    /**
     * @return array{id:string, classes:list<string>, attributes:array<string, string>}
     */
    private function htmlAttrTuple(AstNode $node): array
    {
        $attrs = $this->attrTuple($node);
        $htmlAttributes = $node->attr('htmlAttributes', []);
        if (!is_array($htmlAttributes)) {
            return $attrs;
        }

        if ($attrs['id'] === '' && isset($htmlAttributes['id']) && is_scalar($htmlAttributes['id'])) {
            $attrs['id'] = (string) $htmlAttributes['id'];
        }

        if ($attrs['classes'] === [] && isset($htmlAttributes['class']) && is_scalar($htmlAttributes['class'])) {
            $classes = preg_split('/\s+/', trim((string) $htmlAttributes['class'])) ?: [];
            $attrs['classes'] = array_values(array_filter($classes, static fn (string $class): bool => $class !== ''));
        }

        foreach ($htmlAttributes as $name => $value) {
            $name = (string) $name;
            $lowerName = strtolower($name);
            if (str_starts_with($lowerName, 'data-')) {
                unset($attrs['attributes'][substr($name, 5)], $attrs['attributes'][substr($lowerName, 5)]);
            }

            if (
                in_array($lowerName, ['id', 'class'], true)
                || !is_scalar($value)
                || array_key_exists($name, $attrs['attributes'])
            ) {
                continue;
            }

            $attrs['attributes'][$name] = (string) $value;
        }

        return $attrs;
    }

    /**
     * @param array{id:string, classes:list<string>, attributes:array<string, string>} $attrs
     */
    private function renderAttrTuple(array $attrs, ?callable $attributeFilter = null): string
    {
        $parts = [];
        if ($attrs['id'] !== '') {
            $parts[] = 'id="' . $this->esc($attrs['id']) . '"';
        }
        if ($attrs['classes'] !== []) {
            $parts[] = 'class="' . $this->esc(implode(' ', $attrs['classes'])) . '"';
        }
        foreach ($attrs['attributes'] as $name => $value) {
            $name = (string) $name;
            if (preg_match('/^[A-Za-z_:][A-Za-z0-9_.:-]*$/', $name) !== 1) {
                continue;
            }
            if ($attributeFilter !== null && !$attributeFilter($name)) {
                continue;
            }
            $parts[] = $name . '="' . $this->esc($value) . '"';
        }

        return $parts === [] ? '' : ' ' . implode(' ', $parts);
    }

    private function allowedHeadingAttribute(string $name): bool
    {
        return in_array($name, ['lang', 'xml:lang', 'dir', 'title', 'style', 'align', 'role', 'epub:type', 'hidden'], true)
            || str_starts_with($name, 'data-')
            || str_starts_with($name, 'aria-');
    }

    private function isInlineNode(AstNode $node): bool
    {
        return in_array($node->type, [
            'text',
            'softbreak',
            'linebreak',
            'code',
            'image',
            'emph',
            'strong',
            'underline',
            'strikeout',
            'small_caps',
            'superscript',
            'subscript',
            'span',
            'quoted',
            'link',
            'note',
            'citation',
            'math',
            'raw_html_inline',
            'raw_inline',
        ], true);
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
                'citation' => (string) $node->attr('text', $this->plainInlineText($node->children)),
                'math' => (string) $node->attr('text', ''),
                'raw_html_inline' => '',
                default => $this->plainInlineText($node->children),
            };
        }

        return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    }

    private function esc(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
