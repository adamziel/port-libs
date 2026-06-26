<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class XmlReader
{
    /** @var list<string> */
    private const PASS_THROUGH_CONTAINERS = [
        'abstract',
        'article',
        'back',
        'body',
        'book',
        'chapter',
        'content',
        'doc',
        'document',
        'front',
        'info',
        'main',
        'part',
        'root',
        'section',
        'sect1',
        'sect2',
        'sect3',
        'sect4',
        'sect5',
        'sect6',
        'simplesect',
        'subsection',
        'topic',
    ];

    /** @var list<string> */
    private const INLINE_ELEMENTS = [
        'a',
        'b',
        'cite',
        'code',
        'del',
        'emphasis',
        'em',
        'i',
        'inline',
        'italic',
        'link',
        'literal',
        'monospace',
        'q',
        'ref',
        'span',
        'strong',
        'sub',
        'sup',
        'tt',
        'u',
        'xref',
    ];

    /** @var list<string> */
    private const BLOCK_ELEMENTS = [
        'blockquote',
        'caption',
        'codeblock',
        'definition',
        'dl',
        'epigraph',
        'figcaption',
        'figure',
        'graphic',
        'hr',
        'image',
        'imagedata',
        'img',
        'informaltable',
        'itemizedlist',
        'li',
        'list',
        'listitem',
        'mediaobject',
        'ol',
        'orderedlist',
        'p',
        'para',
        'paragraph',
        'pre',
        'programlisting',
        'pullquote',
        'quote',
        'row',
        'screen',
        'simpara',
        'sourcecode',
        'table',
        'tbody',
        'td',
        'tfoot',
        'th',
        'thead',
        'tr',
        'ul',
        'variablelist',
        'varlistentry',
    ];

    private int $paragraphCount = 0;

    private int $headingCount = 0;

    private int $listCount = 0;

    private int $tableCount = 0;

    private int $figureCount = 0;

    private int $codeBlockCount = 0;

    private int $genericContainerCount = 0;

    public function read(string $source): AstNode
    {
        $this->paragraphCount = 0;
        $this->headingCount = 0;
        $this->listCount = 0;
        $this->tableCount = 0;
        $this->figureCount = 0;
        $this->codeBlockCount = 0;
        $this->genericContainerCount = 0;

        $dom = XmlHtmlDom::loadXmlDocument($source, 'Pandoc XML input');
        $root = $dom->documentElement;
        if (!$root instanceof \DOMElement) {
            throw new \InvalidArgumentException('Pandoc XML input must contain a document element');
        }

        $namespaceReview = XmlHtmlDom::summarizeXmlNamespaceUsage($dom);
        $body = $this->documentBody($root);
        $blocks = $this->shouldParseRootAsBlock($body)
            ? $this->parseBlockElement($body, 1)
            : $this->parseChildrenAsBlocks($body, 1);
        if ($blocks === []) {
            $inlines = $this->trimInlineEdges($this->parseInlineChildren($body));
            if ($inlines !== []) {
                $blocks[] = $this->paragraph($inlines, $body);
            }
        }

        return new AstNode('document', [
            'meta' => [
                'xmlReaderStatus' => 'partial',
                'xmlRootName' => $root->localName,
                'xmlRootQualifiedName' => $root->tagName,
                'xmlRootNamespaceUri' => (string) ($root->namespaceURI ?? ''),
                'xmlElementCount' => $namespaceReview['elementCount'],
                'xmlAttributeCount' => $namespaceReview['attributeCount'],
                'xmlNamespaceUriCount' => $namespaceReview['namespaceUriCount'],
                'xmlParagraphCount' => $this->paragraphCount,
                'xmlHeadingCount' => $this->headingCount,
                'xmlListCount' => $this->listCount,
                'xmlTableCount' => $this->tableCount,
                'xmlFigureCount' => $this->figureCount,
                'xmlCodeBlockCount' => $this->codeBlockCount,
                'xmlGenericContainerCount' => $this->genericContainerCount,
                'xmlNamespaceReview' => $namespaceReview,
            ],
        ], $blocks);
    }

    public function readXmlFile(string $path): AstNode
    {
        $source = file_get_contents($path);
        if (!is_string($source)) {
            throw new \RuntimeException("Unable to read '{$path}'.");
        }

        return $this->read($source);
    }

    private function documentBody(\DOMElement $root): \DOMElement
    {
        $local = $this->localName($root);
        if (!in_array($local, ['html', 'xhtml'], true)) {
            return $root;
        }

        foreach ($root->getElementsByTagName('*') as $element) {
            if ($element instanceof \DOMElement && $this->localName($element) === 'body') {
                return $element;
            }
        }

        return $root;
    }

    private function shouldParseRootAsBlock(\DOMElement $element): bool
    {
        return $this->isTableElement($element)
            || $this->isListElement($element)
            || in_array($this->localName($element), ['p', 'para', 'paragraph', 'simpara'], true)
            || in_array($this->localName($element), ['dl', 'variablelist'], true)
            || in_array($this->localName($element), ['figure', 'mediaobject'], true)
            || $this->isImageElement($element)
            || $this->isCodeBlockElement($element)
            || in_array($this->localName($element), ['blockquote', 'quote', 'epigraph', 'pullquote'], true);
    }

    /**
     * @return list<AstNode>
     */
    private function parseChildrenAsBlocks(\DOMElement $element, int $depth): array
    {
        $blocks = [];
        $inlineBuffer = [];

        foreach ($element->childNodes as $child) {
            if ($child instanceof \DOMText || $child instanceof \DOMCdataSection) {
                $this->appendTextInline($inlineBuffer, (string) $child->nodeValue);
                continue;
            }

            if (!$child instanceof \DOMElement) {
                continue;
            }

            if (!$this->isInlineElement($child) && $this->isBlockElement($child)) {
                $this->flushInlineBuffer($inlineBuffer, $blocks, $element);
                foreach ($this->parseBlockElement($child, $depth) as $block) {
                    $blocks[] = $block;
                }
                continue;
            }

            foreach ($this->parseInlineElement($child) as $inline) {
                $inlineBuffer[] = $inline;
            }
        }

        $this->flushInlineBuffer($inlineBuffer, $blocks, $element);

        return $blocks;
    }

    /**
     * @return list<AstNode>
     */
    private function parseBlockElement(\DOMElement $element, int $depth): array
    {
        $local = $this->localName($element);

        if ($this->isHeadingElement($element)) {
            ++$this->headingCount;

            return [new AstNode('heading', [
                'level' => $this->headingLevel($element, $depth),
                'id' => $this->elementId($element) !== '' ? $this->elementId($element) : $this->slugify($this->elementText($element)),
                'htmlAttributes' => $this->htmlAttributes($element),
            ], $this->trimInlineEdges($this->parseInlineChildren($element)))];
        }

        if (in_array($local, ['p', 'para', 'paragraph', 'simpara', 'subtitle'], true)) {
            $inlines = $this->trimInlineEdges($this->parseInlineChildren($element));

            return $inlines === [] ? [] : [$this->paragraph($inlines, $element)];
        }

        if ($this->isListElement($element)) {
            return [$this->parseList($element)];
        }

        if (in_array($local, ['dl', 'variablelist'], true)) {
            return [$this->parseDefinitionList($element)];
        }

        if ($this->isTableElement($element)) {
            return [$this->parseTable($element)];
        }

        if (in_array($local, ['figure', 'mediaobject'], true)) {
            return [$this->parseFigure($element)];
        }

        if ($this->isImageElement($element)) {
            return [$this->figureFromImage($element, '')];
        }

        if ($this->isCodeBlockElement($element)) {
            return [$this->codeBlock($element)];
        }

        if (in_array($local, ['blockquote', 'quote', 'epigraph', 'pullquote'], true)) {
            return [new AstNode('blockquote', [
                'htmlAttributes' => $this->htmlAttributes($element),
            ], $this->parseChildrenAsBlocks($element, $depth + 1))];
        }

        if ($local === 'hr') {
            return [new AstNode('horizontal_rule')];
        }

        $childDepth = $this->isSectionContainer($element) ? $depth + 1 : $depth;
        $children = $this->parseChildrenAsBlocks($element, $childDepth);
        if ($children === []) {
            $inlines = $this->trimInlineEdges($this->parseInlineChildren($element));

            return $inlines === [] ? [] : [$this->paragraph($inlines, $element)];
        }

        if (in_array($local, self::PASS_THROUGH_CONTAINERS, true)) {
            return $children;
        }

        ++$this->genericContainerCount;

        return [new AstNode('div', [
            'classes' => ['xml-element', 'xml-' . $this->sanitizeClass($local)],
            'htmlAttributes' => $this->htmlAttributes($element),
        ], $children)];
    }

    private function paragraph(array $inlines, \DOMElement $source): AstNode
    {
        ++$this->paragraphCount;

        return new AstNode('paragraph', [
            'htmlAttributes' => $this->htmlAttributes($source),
        ], $inlines);
    }

    private function parseList(\DOMElement $element): AstNode
    {
        ++$this->listCount;
        $ordered = $this->listIsOrdered($element);
        $items = [];
        foreach ($this->directChildElements($element) as $child) {
            if (!$this->isListItemElement($child)) {
                continue;
            }
            $itemBlocks = $this->parseChildrenAsBlocks($child, 1);
            if ($itemBlocks === []) {
                $inlines = $this->trimInlineEdges($this->parseInlineChildren($child));
                if ($inlines !== []) {
                    $itemBlocks[] = new AstNode('paragraph', [], $inlines);
                }
            }
            $items[] = new AstNode('list_item', [
                'htmlAttributes' => $this->htmlAttributes($child),
            ], $itemBlocks);
        }

        return new AstNode($ordered ? 'ordered_list' : 'bullet_list', [
            'start' => $this->positiveIntAttribute($element, ['start', 'first'], 1),
            'htmlAttributes' => $this->htmlAttributes($element),
        ], $items);
    }

    private function parseDefinitionList(\DOMElement $element): AstNode
    {
        $items = [];
        if ($this->localName($element) === 'dl') {
            $pendingTerm = null;
            foreach ($this->directChildElements($element) as $child) {
                $local = $this->localName($child);
                if ($local === 'dt') {
                    $pendingTerm = $this->trimInlineEdges($this->parseInlineChildren($child));
                    continue;
                }
                if ($local !== 'dd') {
                    continue;
                }
                $term = $pendingTerm ?? [new AstNode('text', ['text' => ''])];
                $items[] = $this->definitionItem($term, $this->parseChildrenAsBlocks($child, 1), $child);
                $pendingTerm = null;
            }
        } else {
            foreach ($this->directChildElements($element) as $entry) {
                if ($this->localName($entry) !== 'varlistentry') {
                    continue;
                }
                $term = [];
                $definitions = [];
                foreach ($this->directChildElements($entry) as $child) {
                    if ($this->localName($child) === 'term') {
                        $term = $this->trimInlineEdges($this->parseInlineChildren($child));
                    } elseif ($this->isListItemElement($child)) {
                        $definitions = $this->parseChildrenAsBlocks($child, 1);
                    }
                }
                $items[] = $this->definitionItem($term, $definitions, $entry);
            }
        }

        return new AstNode('definition_list', [
            'htmlAttributes' => $this->htmlAttributes($element),
        ], $items);
    }

    /**
     * @param list<AstNode> $term
     * @param list<AstNode> $definitions
     */
    private function definitionItem(array $term, array $definitions, \DOMElement $source): AstNode
    {
        if ($definitions === []) {
            $definitions[] = new AstNode('paragraph', [], $this->trimInlineEdges($this->parseInlineChildren($source)));
        }

        return new AstNode('definition_item', [
            'htmlAttributes' => $this->htmlAttributes($source),
        ], [
            new AstNode('term', [], $term),
            new AstNode('definition', [], $definitions),
        ]);
    }

    private function parseTable(\DOMElement $element): AstNode
    {
        ++$this->tableCount;
        $captionInlines = $this->captionInlines($element);
        $columnMap = $this->tableColumnMap($element);
        $spanMap = $this->tableSpanMap($element, $columnMap);
        $headRows = [];
        $bodyRows = [];
        $footRows = [];

        foreach ($this->tableRows($element) as $row) {
            $section = $this->tableRowSection($row);
            $parsed = $this->parseTableRow($row, $section === 'head', $columnMap, $spanMap);
            if ($section === 'head') {
                $headRows[] = $parsed;
            } elseif ($section === 'foot') {
                $footRows[] = $parsed;
            } else {
                $bodyRows[] = $parsed;
            }
        }

        $children = [];
        if ($headRows !== []) {
            $children[] = new AstNode('table_head', [], $headRows);
        }
        $children[] = new AstNode('table_body', [], $bodyRows);
        if ($footRows !== []) {
            $children[] = new AstNode('table_foot', [], $footRows);
        }

        return new AstNode('table', [
            'caption' => $this->plainText($captionInlines),
            'captionInlines' => $captionInlines,
            'htmlAttributes' => ['data-pandoc-source' => 'xml'] + $this->htmlAttributes($element),
        ], $children);
    }

    /**
     * @param array<string, int> $columnMap
     * @param array<string, int> $spanMap
     */
    private function parseTableRow(\DOMElement $row, bool $headerSection, array $columnMap = [], array $spanMap = []): AstNode
    {
        $cells = [];
        foreach ($this->directChildElements($row) as $cell) {
            $local = $this->localName($cell);
            if (!in_array($local, ['td', 'th', 'entry', 'cell'], true)) {
                continue;
            }
            $inlines = $this->tableCellInlines($cell);
            $alignment = $this->tableCellAlignment($cell);
            $attrs = [
                'header' => $headerSection || $local === 'th' || strtolower($cell->getAttribute('role')) === 'header',
                'colspan' => $this->tableCellColspan($cell, $columnMap, $spanMap),
                'rowspan' => $this->tableCellRowspan($cell),
                'htmlAttributes' => $this->htmlAttributes($cell),
            ];
            if ($alignment !== '') {
                $attrs['align'] = $alignment;
            }
            $cells[] = new AstNode('table_cell', $attrs, $inlines);
        }

        return new AstNode('table_row', [
            'htmlAttributes' => $this->htmlAttributes($row),
        ], $cells);
    }

    /**
     * @return list<\DOMElement>
     */
    private function tableRows(\DOMElement $table): array
    {
        $rows = [];
        $walker = function (\DOMElement $element) use (&$walker, &$rows, $table): void {
            foreach ($this->directChildElements($element) as $child) {
                if ($child !== $table && $this->isTableElement($child)) {
                    continue;
                }
                if (in_array($this->localName($child), ['tr', 'row'], true)) {
                    $rows[] = $child;
                    continue;
                }
                $walker($child);
            }
        };
        $walker($table);

        return $rows;
    }

    /**
     * @return list<AstNode>
     */
    private function tableCellInlines(\DOMElement $cell): array
    {
        $inlines = [];
        foreach ($cell->childNodes as $child) {
            if ($child instanceof \DOMText || $child instanceof \DOMCdataSection) {
                $this->appendTextInline($inlines, (string) $child->nodeValue);
                continue;
            }
            if (!$child instanceof \DOMElement) {
                continue;
            }

            $local = $this->localName($child);
            if (in_array($local, ['p', 'para', 'paragraph', 'simpara'], true)) {
                if ($this->trimInlineEdges($inlines) !== []) {
                    $inlines[] = new AstNode('linebreak');
                }
                foreach ($this->parseInlineChildren($child) as $inline) {
                    $inlines[] = $inline;
                }
                continue;
            }

            foreach ($this->parseInlineElement($child) as $inline) {
                $inlines[] = $inline;
            }
        }

        return $this->trimInlineEdges($inlines);
    }

    private function tableCellAlignment(\DOMElement $cell): string
    {
        $align = strtolower($this->attributeValue($cell, ['align']));
        if ($align === '') {
            return '';
        }

        return match ($align) {
            'left', 'right', 'center' => $align,
            'centre' => 'center',
            default => '',
        };
    }

    /**
     * @param array<string, int> $columnMap
     * @param array<string, int> $spanMap
     */
    private function tableCellColspan(\DOMElement $cell, array $columnMap, array $spanMap): int
    {
        $htmlColspan = $this->positiveIntAttribute($cell, ['colspan', 'cols'], 0);
        if ($htmlColspan > 0) {
            return $htmlColspan;
        }

        $spanName = $this->attributeValue($cell, ['spanname']);
        if ($spanName !== '' && isset($spanMap[$spanName])) {
            return $spanMap[$spanName];
        }

        $nameStart = $this->attributeValue($cell, ['namest']);
        $nameEnd = $this->attributeValue($cell, ['nameend']);
        if ($nameStart !== '' && $nameEnd !== '' && isset($columnMap[$nameStart], $columnMap[$nameEnd])) {
            return max(1, $columnMap[$nameEnd] - $columnMap[$nameStart] + 1);
        }

        return 1;
    }

    private function tableCellRowspan(\DOMElement $cell): int
    {
        $htmlRowspan = $this->positiveIntAttribute($cell, ['rowspan', 'rows'], 0);
        if ($htmlRowspan > 0) {
            return $htmlRowspan;
        }

        $moreRows = $this->positiveIntAttribute($cell, ['morerows'], 0);
        if ($moreRows > 0) {
            return $moreRows + 1;
        }

        return 1;
    }

    /**
     * @return array<string, int>
     */
    private function tableColumnMap(\DOMElement $table): array
    {
        $columns = [];
        $walker = function (\DOMElement $element) use (&$walker, &$columns, $table): void {
            foreach ($this->directChildElements($element) as $child) {
                if ($child !== $table && $this->isTableElement($child)) {
                    continue;
                }
                if ($this->localName($child) === 'colspec') {
                    $name = $this->attributeValue($child, ['colname']);
                    if ($name !== '' && !isset($columns[$name])) {
                        $columns[$name] = count($columns) + 1;
                    }
                    continue;
                }
                $walker($child);
            }
        };
        $walker($table);

        return $columns;
    }

    /**
     * @param array<string, int> $columnMap
     * @return array<string, int>
     */
    private function tableSpanMap(\DOMElement $table, array $columnMap): array
    {
        $spans = [];
        $walker = function (\DOMElement $element) use (&$walker, &$spans, $table, $columnMap): void {
            foreach ($this->directChildElements($element) as $child) {
                if ($child !== $table && $this->isTableElement($child)) {
                    continue;
                }
                if ($this->localName($child) === 'spanspec') {
                    $name = $this->attributeValue($child, ['spanname']);
                    $nameStart = $this->attributeValue($child, ['namest']);
                    $nameEnd = $this->attributeValue($child, ['nameend']);
                    if ($name !== '' && isset($columnMap[$nameStart], $columnMap[$nameEnd])) {
                        $spans[$name] = max(1, $columnMap[$nameEnd] - $columnMap[$nameStart] + 1);
                    }
                    continue;
                }
                $walker($child);
            }
        };
        $walker($table);

        return $spans;
    }

    private function parseFigure(\DOMElement $element): AstNode
    {
        $image = $this->firstDescendantImage($element);
        if (!$image instanceof \DOMElement) {
            ++$this->genericContainerCount;

            return new AstNode('div', [
                'classes' => ['xml-element', 'xml-figure'],
                'htmlAttributes' => $this->htmlAttributes($element),
            ], $this->parseChildrenAsBlocks($element, 1));
        }

        return $this->figureFromImage($image, $this->plainText($this->captionInlines($element)), $element);
    }

    private function figureFromImage(\DOMElement $image, string $caption, ?\DOMElement $figure = null): AstNode
    {
        ++$this->figureCount;
        $captionInlines = $figure instanceof \DOMElement ? $this->captionInlines($figure) : [];
        if ($captionInlines === [] && $caption !== '') {
            $captionInlines = [new AstNode('text', ['text' => $caption])];
        }
        $alt = $this->imageAlt($image);
        if ($alt === '') {
            $alt = $caption;
        }

        $attributes = ['data-pandoc-source' => 'xml'] + $this->imageAttributes($image);

        $figureAttrs = [
            'caption' => $this->plainText($captionInlines),
            'captionInlines' => $captionInlines,
            'classes' => ['xml-image'],
            'htmlAttributes' => $figure instanceof \DOMElement ? $this->htmlAttributes($figure) : $this->htmlAttributes($image),
        ];
        $figureId = $figure instanceof \DOMElement ? $this->elementId($figure) : $this->elementId($image);
        if ($figureId !== '') {
            $figureAttrs['id'] = $figureId;
        }

        return new AstNode('figure', $figureAttrs, [new AstNode('image', [
            'url' => $this->imageUrl($image),
            'alt' => $alt,
            'title' => $this->attributeValue($image, ['title']),
            'attributes' => $attributes,
        ], $captionInlines)]);
    }

    private function codeBlock(\DOMElement $element): AstNode
    {
        ++$this->codeBlockCount;
        $language = $this->attributeValue($element, ['language', 'lang', 'type']);
        $classes = [];
        if ($language !== '') {
            $classes[] = $this->sanitizeClass($language);
        }

        return new AstNode('code_block', [
            'text' => trim((string) $element->textContent, "\n\r"),
            'language' => $language,
            'classes' => $classes,
            'htmlAttributes' => $this->htmlAttributes($element),
        ]);
    }

    /**
     * @return list<AstNode>
     */
    private function parseInlineChildren(\DOMElement $element): array
    {
        $inlines = [];
        foreach ($element->childNodes as $child) {
            if ($child instanceof \DOMText || $child instanceof \DOMCdataSection) {
                $this->appendTextInline($inlines, (string) $child->nodeValue);
                continue;
            }
            if ($child instanceof \DOMElement) {
                foreach ($this->parseInlineElement($child) as $inline) {
                    $inlines[] = $inline;
                }
            }
        }

        return $inlines;
    }

    /**
     * @return list<AstNode>
     */
    private function parseInlineElement(\DOMElement $element): array
    {
        $local = $this->localName($element);
        if ($local === 'br') {
            return [new AstNode('linebreak')];
        }
        if ($this->isImageElement($element)) {
            return [$this->figureFromImage($element, '')->children[0]];
        }
        if ($this->isMathElement($element)) {
            return [new AstNode('raw_html_inline', ['html' => $this->elementXml($element)])];
        }

        $children = $this->trimInlineEdges($this->parseInlineChildren($element));

        return match ($local) {
            'b', 'strong' => [new AstNode('strong', [], $children)],
            'emphasis' => strtolower($this->attributeValue($element, ['role'])) === 'strong'
                ? [new AstNode('strong', [], $children)]
                : [new AstNode('emph', [], $children)],
            'cite', 'em', 'i', 'italic' => [new AstNode('emph', [], $children)],
            'code', 'literal', 'monospace', 'tt' => [new AstNode('code', [
                'text' => $this->elementText($element),
                'htmlAttributes' => $this->htmlAttributes($element),
            ])],
            'del' => [new AstNode('strikeout', [], $children)],
            'sub' => [new AstNode('subscript', [], $children)],
            'sup' => [new AstNode('superscript', [], $children)],
            'u' => [new AstNode('underline', [], $children)],
            'a', 'link', 'ref', 'xref' => [$this->linkInline($element, $children)],
            default => [new AstNode('span', [
                'classes' => ['xml-inline', 'xml-' . $this->sanitizeClass($local)],
                'htmlAttributes' => $this->htmlAttributes($element),
            ], $children)],
        };
    }

    private function isBlockElement(\DOMElement $element): bool
    {
        $local = $this->localName($element);
        if ($this->isHeadingElement($element) || in_array($local, self::BLOCK_ELEMENTS, true) || in_array($local, self::PASS_THROUGH_CONTAINERS, true)) {
            return true;
        }

        return !$this->isInlineElement($element);
    }

    private function isInlineElement(\DOMElement $element): bool
    {
        return in_array($this->localName($element), self::INLINE_ELEMENTS, true)
            || $this->isMathElement($element);
    }

    private function isHeadingElement(\DOMElement $element): bool
    {
        $local = $this->localName($element);
        if (preg_match('/^h[1-6]$/', $local) === 1) {
            return true;
        }
        if (!in_array($local, ['head', 'heading', 'title'], true)) {
            return false;
        }

        $parent = $element->parentNode;
        if ($parent instanceof \DOMElement && in_array($this->localName($parent), ['figure', 'table', 'caption', 'mediaobject'], true)) {
            return false;
        }

        return true;
    }

    private function headingLevel(\DOMElement $element, int $depth): int
    {
        if (preg_match('/^h([1-6])$/', $this->localName($element), $match) === 1) {
            return (int) $match[1];
        }

        return max(1, min(6, $depth));
    }

    private function isListElement(\DOMElement $element): bool
    {
        $local = $this->localName($element);
        if (in_array($local, ['ul', 'ol', 'itemizedlist', 'orderedlist'], true)) {
            return true;
        }

        return $local === 'list' && $this->directListItems($element) !== [];
    }

    private function listIsOrdered(\DOMElement $element): bool
    {
        $local = $this->localName($element);
        $type = strtolower($this->attributeValue($element, ['type', 'list-type', 'style']));

        return in_array($local, ['ol', 'orderedlist'], true)
            || in_array($type, ['ordered', 'order', 'numbered', 'decimal', '1'], true);
    }

    /**
     * @return list<\DOMElement>
     */
    private function directListItems(\DOMElement $element): array
    {
        return array_values(array_filter(
            $this->directChildElements($element),
            fn (\DOMElement $child): bool => $this->isListItemElement($child)
        ));
    }

    private function isListItemElement(\DOMElement $element): bool
    {
        return in_array($this->localName($element), ['li', 'item', 'listitem'], true);
    }

    private function isSectionContainer(\DOMElement $element): bool
    {
        return in_array($this->localName($element), ['section', 'chapter', 'part', 'subsection', 'sect1', 'sect2', 'sect3', 'sect4', 'sect5', 'sect6', 'simplesect'], true);
    }

    private function isTableElement(\DOMElement $element): bool
    {
        return in_array($this->localName($element), ['table', 'informaltable'], true);
    }

    private function isImageElement(\DOMElement $element): bool
    {
        return in_array($this->localName($element), ['graphic', 'image', 'imagedata', 'img', 'inlinegraphic', 'media'], true)
            && $this->imageUrl($element) !== '';
    }

    private function isCodeBlockElement(\DOMElement $element): bool
    {
        return in_array($this->localName($element), ['codeblock', 'listing', 'pre', 'programlisting', 'screen', 'sourcecode'], true);
    }

    private function isMathElement(\DOMElement $element): bool
    {
        return $this->localName($element) === 'math'
            || (string) ($element->namespaceURI ?? '') === 'http://www.w3.org/1998/Math/MathML';
    }

    /**
     * @param list<AstNode> $children
     */
    private function linkInline(\DOMElement $element, array $children): AstNode
    {
        $target = $this->xmlLinkTarget($element);
        $htmlAttributes = $this->htmlAttributes($element);
        if ($target['droppedScheme'] !== '') {
            $htmlAttributes['data-pandoc-dropped-href-scheme'] = $target['droppedScheme'];

            return new AstNode('span', [
                'classes' => ['xml-inline', 'xml-link'],
                'htmlAttributes' => $htmlAttributes,
            ], $children === [] ? [new AstNode('text', ['text' => $target['label']])] : $children);
        }

        return new AstNode('link', [
            'url' => $target['url'],
            'title' => $this->attributeValue($element, ['title']),
            'htmlAttributes' => $htmlAttributes,
        ], $children === [] ? [new AstNode('text', ['text' => $target['label']])] : $children);
    }

    /**
     * @return array{url:string, label:string, droppedScheme:string}
     */
    private function xmlLinkTarget(\DOMElement $element): array
    {
        $linkend = $this->attributeValue($element, ['linkend']);
        if ($linkend !== '') {
            $linkend = ltrim($linkend, '#');

            return [
                'url' => '#' . $linkend,
                'label' => $linkend,
                'droppedScheme' => '',
            ];
        }

        $url = $this->attributeValue($element, ['href', 'xlink:href', 'target', 'rid']);
        $droppedScheme = $this->unsafeLinkScheme($url);
        if ($droppedScheme !== '') {
            return [
                'url' => '',
                'label' => '',
                'droppedScheme' => $droppedScheme,
            ];
        }

        return [
            'url' => $url,
            'label' => $url,
            'droppedScheme' => '',
        ];
    }

    private function unsafeLinkScheme(string $url): string
    {
        $url = trim($url);
        if ($url === '' || preg_match('/^([A-Za-z][A-Za-z0-9+.-]*):/', $url, $match) !== 1) {
            return '';
        }

        $scheme = strtolower($match[1]);

        return in_array($scheme, ['http', 'https', 'mailto', 'tel', 'ftp'], true) ? '' : $scheme;
    }

    /**
     * @return list<AstNode>
     */
    private function captionInlines(\DOMElement $element): array
    {
        foreach ($this->directChildElements($element) as $child) {
            if (in_array($this->localName($child), ['caption', 'figcaption', 'title'], true)) {
                return $this->trimInlineEdges($this->parseInlineChildren($child));
            }
        }

        return [];
    }

    private function firstDescendantImage(\DOMElement $element): ?\DOMElement
    {
        if ($this->isImageElement($element)) {
            return $element;
        }
        foreach ($element->childNodes as $child) {
            if (!$child instanceof \DOMElement) {
                continue;
            }
            $image = $this->firstDescendantImage($child);
            if ($image instanceof \DOMElement) {
                return $image;
            }
        }

        return null;
    }

    private function tableRowSection(\DOMElement $row): string
    {
        $parent = $row->parentNode;
        while ($parent instanceof \DOMElement) {
            $local = $this->localName($parent);
            if ($local === 'thead') {
                return 'head';
            }
            if ($local === 'tfoot') {
                return 'foot';
            }
            if ($this->isTableElement($parent)) {
                break;
            }
            $parent = $parent->parentNode;
        }

        foreach ($this->directChildElements($row) as $cell) {
            if ($this->localName($cell) === 'th') {
                return 'head';
            }
        }

        return 'body';
    }

    /**
     * @param list<AstNode> $inlineBuffer
     * @param list<AstNode> $blocks
     */
    private function flushInlineBuffer(array &$inlineBuffer, array &$blocks, \DOMElement $source): void
    {
        $inlines = $this->trimInlineEdges($inlineBuffer);
        if ($inlines !== []) {
            $blocks[] = $this->paragraph($inlines, $source);
        }
        $inlineBuffer = [];
    }

    /**
     * @param list<AstNode> $inlines
     */
    private function appendTextInline(array &$inlines, string $text): void
    {
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;
        if (trim($text) === '') {
            return;
        }

        $last = array_key_last($inlines);
        if ($last !== null && $inlines[$last]->type === 'text') {
            $previous = (string) $inlines[$last]->attr('text', '');
            $inlines[$last] = new AstNode('text', ['text' => $previous . $text]);
            return;
        }

        $inlines[] = new AstNode('text', ['text' => $text]);
    }

    /**
     * @param list<AstNode> $inlines
     * @return list<AstNode>
     */
    private function trimInlineEdges(array $inlines): array
    {
        while ($inlines !== [] && $inlines[0]->type === 'text') {
            $text = ltrim((string) $inlines[0]->attr('text', ''));
            if ($text !== '') {
                $inlines[0] = new AstNode('text', ['text' => $text]);
                break;
            }
            array_shift($inlines);
        }

        while ($inlines !== []) {
            $last = count($inlines) - 1;
            if ($inlines[$last]->type !== 'text') {
                break;
            }
            $text = rtrim((string) $inlines[$last]->attr('text', ''));
            if ($text !== '') {
                $inlines[$last] = new AstNode('text', ['text' => $text]);
                break;
            }
            array_pop($inlines);
        }

        return array_values($inlines);
    }

    /**
     * @return list<\DOMElement>
     */
    private function directChildElements(\DOMElement $element): array
    {
        $children = [];
        foreach ($element->childNodes as $child) {
            if ($child instanceof \DOMElement) {
                $children[] = $child;
            }
        }

        return $children;
    }

    private function htmlAttributes(\DOMElement $element): array
    {
        $attrs = [
            'data-xml-element' => $element->localName,
        ];
        if ($element->tagName !== $element->localName) {
            $attrs['data-xml-qname'] = $element->tagName;
        }
        if ((string) ($element->namespaceURI ?? '') !== '') {
            $attrs['data-xml-namespace'] = (string) $element->namespaceURI;
        }

        $id = $this->elementId($element);
        if ($id !== '') {
            $attrs['id'] = $id;
        }

        foreach (['class', 'dir', 'role', 'title'] as $name) {
            if ($element->hasAttribute($name)) {
                $attrs[$name] = $element->getAttribute($name);
            }
        }

        $lang = $element->getAttributeNS('http://www.w3.org/XML/1998/namespace', 'lang');
        if ($lang === '' && $element->hasAttribute('lang')) {
            $lang = $element->getAttribute('lang');
        }
        if ($lang !== '') {
            $attrs['lang'] = $lang;
            $attrs['xml:lang'] = $lang;
        }

        return array_filter($attrs, static fn (string $value): bool => $value !== '');
    }

    private function imageAttributes(\DOMElement $element): array
    {
        $attrs = $this->htmlAttributes($element);
        foreach (['width', 'height', 'srcset', 'sizes', 'loading', 'decoding'] as $name) {
            $value = $this->attributeValue($element, [$name]);
            if ($value !== '') {
                $attrs[$name] = $value;
            }
        }

        return $attrs;
    }

    private function imageUrl(\DOMElement $element): string
    {
        return $this->attributeValue($element, ['src', 'href', 'xlink:href', 'fileref', 'url', 'data']);
    }

    private function imageAlt(\DOMElement $element): string
    {
        $alt = $this->attributeValue($element, ['alt', 'aria-label']);
        if ($alt !== '') {
            return $alt;
        }

        return $this->elementText($element);
    }

    /**
     * @param list<string> $names
     */
    private function attributeValue(\DOMElement $element, array $names): string
    {
        foreach ($names as $name) {
            if ($name === 'xlink:href') {
                $value = $element->getAttributeNS('http://www.w3.org/1999/xlink', 'href');
                if ($value !== '') {
                    return $value;
                }
            }
            if ($element->hasAttribute($name)) {
                return trim($element->getAttribute($name));
            }
        }

        return '';
    }

    /**
     * @param list<string> $names
     */
    private function positiveIntAttribute(\DOMElement $element, array $names, int $default): int
    {
        foreach ($names as $name) {
            $value = $this->attributeValue($element, [$name]);
            if (preg_match('/^\d+$/', $value) === 1 && (int) $value > 0) {
                return (int) $value;
            }
        }

        return $default;
    }

    private function elementId(\DOMElement $element): string
    {
        $id = $element->getAttribute('id');
        if ($id !== '') {
            return $id;
        }

        return $element->getAttributeNS('http://www.w3.org/XML/1998/namespace', 'id');
    }

    private function elementText(\DOMElement $element): string
    {
        return trim(preg_replace('/\s+/u', ' ', (string) $element->textContent) ?? (string) $element->textContent);
    }

    /**
     * @param list<AstNode> $inlines
     */
    private function plainText(array $inlines): string
    {
        $parts = [];
        foreach ($inlines as $inline) {
            if ($inline->type === 'text' || $inline->type === 'code') {
                $parts[] = (string) $inline->attr('text', '');
                continue;
            }
            if ($inline->type === 'linebreak' || $inline->type === 'softbreak') {
                $parts[] = ' ';
                continue;
            }
            if ($inline->children !== []) {
                $parts[] = $this->plainText($inline->children);
            }
        }

        return trim(preg_replace('/\s+/u', ' ', implode('', $parts)) ?? implode('', $parts));
    }

    private function slugify(string $text): string
    {
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9]+/', '-', $text) ?? ''));
        $slug = trim($slug, '-');

        return $slug === '' ? 'xml-heading' : $slug;
    }

    private function sanitizeClass(string $class): string
    {
        $class = strtolower(trim($class));
        $class = preg_replace('/[^a-z0-9_-]+/', '-', $class) ?? '';
        $class = trim($class, '-');

        return $class === '' ? 'element' : $class;
    }

    private function localName(\DOMElement $element): string
    {
        return strtolower($element->localName);
    }

    private function elementXml(\DOMElement $element): string
    {
        $owner = $element->ownerDocument;
        if (!$owner instanceof \DOMDocument) {
            return '';
        }

        return $owner->saveXML($element) ?: '';
    }
}
