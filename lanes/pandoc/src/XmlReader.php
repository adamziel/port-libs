<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class XmlReader
{
    private const JATS_ROOTS = ['article', 'book', 'book-part'];
    private const JATS_BODY_ROOTS = ['body', 'book-body'];
    private const JATS_METADATA_ROOTS = ['front', 'article-meta', 'book-meta', 'book-part-meta', 'journal-meta'];
    private const TABLE_CELL_NAMES = ['td', 'th', 'entry'];
    private const BLOCK_CONTAINER_NAMES = [
        'article',
        'book',
        'book-part',
        'body',
        'book-body',
        'back',
        'book-back',
        'book-part-back',
        'section',
        'sec',
        'chapter',
        'part',
        'appendix',
        'app',
        'front',
        'article-meta',
        'book-meta',
        'book-part-meta',
        'journal-meta',
        'abstract',
        'kwd-group',
        'ref-list',
        'list',
        'ordered-list',
        'bullet-list',
        'fig',
        'caption',
        'table-wrap',
        'table',
        'thead',
        'tbody',
        'tfoot',
    ];
    private const PARAGRAPH_NAMES = ['p', 'para', 'paragraph', 'license-p', 'title-group'];
    private const TITLE_NAMES = [
        'title',
        'article-title',
        'book-title',
        'subtitle',
        'trans-title',
        'alt-title',
    ];

    /**
     * @param array<string, mixed> $options
     */
    public function __construct(
        private readonly string $format = 'xml',
        private readonly array $options = [],
    ) {
    }

    public function read(string $bytes): AstNode
    {
        $format = $this->normalizedFormat();
        $dom = XmlHtmlDom::loadXmlDocument($bytes, strtoupper($format) . ' input', false);
        $root = $dom->documentElement;
        if (!$root instanceof \DOMElement) {
            throw new \InvalidArgumentException('XML reader requires a document element.');
        }

        if ($format === 'jats' || $format === 'bits') {
            return $this->readJats($dom, $root, $bytes, $format);
        }

        return $this->readGenericXml($dom, $root, $bytes, $format);
    }

    private function normalizedFormat(): string
    {
        $format = strtolower(trim($this->format));

        return in_array($format, ['jats', 'bits'], true) ? $format : 'xml';
    }

    private function readGenericXml(\DOMDocument $dom, \DOMElement $root, string $bytes, string $format): AstNode
    {
        $blocks = $this->blocksFromElement($root, 1, false);
        if ($blocks === []) {
            $text = XmlHtmlDom::normalizedText($root);
            if ($text !== '') {
                $blocks[] = $this->paragraph($text);
            }
        }

        return new AstNode('document', [
            'sourceFormat' => $format,
            'meta' => $this->baseMetadata($dom, $root, $bytes, $format, [
                'reader' => self::class,
                'readerScope' => 'bounded-generic-xml-reader',
                'xmlElementCount' => count(XmlHtmlDom::descendantElements($root)) + 1,
                'xmlDetectedTables' => $this->countNodesOfType($blocks, 'table'),
                'xmlDetectedHeadings' => $this->countNodesOfType($blocks, 'heading'),
            ]),
        ], $blocks);
    }

    private function readJats(\DOMDocument $dom, \DOMElement $root, string $bytes, string $format): AstNode
    {
        if (!in_array($root->localName, self::JATS_ROOTS, true)) {
            throw new \InvalidArgumentException('JATS/BITS reader root must be article, book, or book-part.');
        }

        $packet = XmlHtmlDom::summarizeJatsFrontMatter($dom, $format);
        $blocks = [];
        $title = $this->cleanText($packet['title'] ?? '');
        if ($title !== '') {
            $blocks[] = $this->heading($title, 1);
        }
        $subtitle = $this->cleanText($packet['subtitle'] ?? '');
        if ($subtitle !== '') {
            $blocks[] = $this->paragraph($subtitle);
        }
        $abstractText = $this->cleanText($packet['abstractText'] ?? '');
        if ($abstractText !== '') {
            $blocks[] = $this->heading('Abstract', 2);
            $blocks[] = $this->paragraph($abstractText);
        }

        $body = $this->firstJatsBodyElement($root);
        if ($body instanceof \DOMElement) {
            array_push($blocks, ...$this->blocksFromElement($body, 2, true));
        }

        if ($blocks === []) {
            foreach ($this->blocksFromElement($root, 1, true) as $block) {
                $blocks[] = $block;
            }
        }

        $meta = $this->baseMetadata($dom, $root, $bytes, $format, [
            'reader' => self::class,
            'readerScope' => 'bounded-jats-bits-reader',
            'jatsPacket' => $packet,
            'jatsTitle' => $title === '' ? null : $title,
            'jatsAbstractText' => $abstractText === '' ? null : $abstractText,
            'jatsSectionCount' => $packet['sectionCount'] ?? 0,
            'jatsTableWrapCount' => $packet['tableWrapCount'] ?? 0,
            'jatsReferenceCount' => $packet['referenceCount'] ?? 0,
            'xmlDetectedTables' => $this->countNodesOfType($blocks, 'table'),
            'xmlDetectedHeadings' => $this->countNodesOfType($blocks, 'heading'),
        ]);
        if ($title !== '') {
            $meta['title'] = $title;
            $meta['titleInlines'] = $this->textInlines($title);
        }
        $contributors = $packet['contributorNames'] ?? [];
        if (is_array($contributors) && $contributors !== []) {
            $meta['authors'] = array_values(array_filter($contributors, 'is_string'));
        }

        return new AstNode('document', [
            'sourceFormat' => $format,
            'meta' => $meta,
        ], $blocks);
    }

    /**
     * @param array<string, mixed> $extra
     * @return array<string, mixed>
     */
    private function baseMetadata(\DOMDocument $dom, \DOMElement $root, string $bytes, string $format, array $extra = []): array
    {
        return array_replace([
            'sourceFormat' => $format,
            'sourceBytes' => strlen($bytes),
            'sourceSha256' => hash('sha256', $bytes),
            'rootName' => $root->localName,
            'rootNamespace' => $root->namespaceURI,
            'rootAttributes' => $this->attributes($root),
            'namespaceSummary' => XmlHtmlDom::summarizeXmlNamespaceScopes($dom),
            'payloadExposurePolicy' => 'xml-dom-text-and-structural-metadata-only',
        ], $extra);
    }

    /**
     * @return list<AstNode>
     */
    private function blocksFromElement(\DOMElement $element, int $headingLevel, bool $jatsMode): array
    {
        $name = $this->name($element);
        if ($jatsMode && in_array($name, self::JATS_METADATA_ROOTS, true)) {
            return [];
        }
        if (in_array($name, ['table', 'informaltable'], true)) {
            $table = $this->tableFromElement($element);

            return $table === null ? [] : [$table];
        }
        if ($name === 'table-wrap') {
            $table = $this->tableWrapFromElement($element);

            return $table === null ? [] : [$table];
        }
        if (in_array($name, ['ul', 'bullet-list', 'list'], true)) {
            $list = $this->listFromElement($element, false);

            return $list === null ? [] : [$list];
        }
        if (in_array($name, ['ol', 'ordered-list'], true)) {
            $list = $this->listFromElement($element, true);

            return $list === null ? [] : [$list];
        }
        if (in_array($name, ['h1', 'h2', 'h3', 'h4', 'h5', 'h6'], true)) {
            $level = (int) substr($name, 1);
            $text = XmlHtmlDom::normalizedText($element);

            return $text === '' ? [] : [$this->heading($text, $level)];
        }
        if (in_array($name, self::TITLE_NAMES, true)) {
            $text = XmlHtmlDom::normalizedText($element);

            return $text === '' ? [] : [$this->heading($text, $headingLevel)];
        }
        if (in_array($name, self::PARAGRAPH_NAMES, true)) {
            $paragraph = $this->paragraphFromElement($element);

            return $paragraph === null ? [] : [$paragraph];
        }

        $blocks = [];
        foreach (XmlHtmlDom::childElements($element) as $child) {
            $childLevel = $headingLevel;
            if (
                in_array($name, ['sec', 'section', 'chapter', 'part', 'appendix', 'app'], true)
                && in_array($this->name($child), ['sec', 'section', 'chapter', 'part', 'appendix', 'app'], true)
            ) {
                $childLevel = min(6, $headingLevel + 1);
            }

            array_push($blocks, ...$this->blocksFromElement($child, $childLevel, $jatsMode));
        }

        if ($blocks !== []) {
            return $blocks;
        }
        if ($this->hasBlockContainerChild($element)) {
            return [];
        }

        $text = XmlHtmlDom::normalizedText($element);

        return $text === '' ? [] : [$this->paragraph($text)];
    }

    private function paragraphFromElement(\DOMElement $element): ?AstNode
    {
        $inlines = $this->inlineNodes($element);
        $text = $this->plainInlineText($inlines);
        if ($text === '') {
            return null;
        }

        return new AstNode('paragraph', ['text' => $text], $inlines);
    }

    private function tableWrapFromElement(\DOMElement $tableWrap): ?AstNode
    {
        $table = XmlHtmlDom::firstDescendantElement($tableWrap, 'table')
            ?? XmlHtmlDom::firstDescendantElement($tableWrap, 'informaltable');
        if (!$table instanceof \DOMElement) {
            return null;
        }

        $attrs = $this->nodeAttrs($tableWrap);
        $caption = $this->tableWrapCaption($tableWrap);
        if ($caption !== '') {
            $attrs['caption'] = $caption;
        }

        return $this->tableFromElement($table, $attrs);
    }

    /**
     * @param array<string, mixed> $attrs
     */
    private function tableFromElement(\DOMElement $table, array $attrs = []): ?AstNode
    {
        $headRows = [];
        $bodyRows = [];
        $footRows = [];

        foreach ($this->tableRows($table) as $row) {
            $cells = $this->tableCells($row);
            if ($cells === []) {
                continue;
            }
            $rowNode = new AstNode('table_row', $this->nodeAttrs($row), $cells);
            $parentName = $row->parentNode instanceof \DOMElement ? $this->name($row->parentNode) : '';
            if ($parentName === 'thead') {
                $headRows[] = $rowNode;
            } elseif ($parentName === 'tfoot') {
                $footRows[] = $rowNode;
            } elseif ($this->rowIsHeader($cells) && $headRows === [] && $bodyRows === []) {
                $headRows[] = $rowNode;
            } else {
                $bodyRows[] = $rowNode;
            }
        }

        if ($headRows === [] && $bodyRows !== []) {
            $headRows[] = array_shift($bodyRows);
        }
        if ($headRows === [] && $bodyRows === [] && $footRows === []) {
            return null;
        }

        $children = [];
        if ($headRows !== []) {
            $children[] = new AstNode('table_head', [], $headRows);
        }
        $children[] = new AstNode('table_body', [], $bodyRows);
        if ($footRows !== []) {
            $children[] = new AstNode('table_foot', [], $footRows);
        }

        return new AstNode('table', $attrs, $children);
    }

    /**
     * @return list<\DOMElement>
     */
    private function tableRows(\DOMElement $table): array
    {
        $rows = [];
        foreach (XmlHtmlDom::descendantElements($table) as $candidate) {
            $name = $this->name($candidate);
            if ($name === 'tr' || $name === 'row') {
                $rows[] = $candidate;
            }
        }

        return $rows;
    }

    /**
     * @return list<AstNode>
     */
    private function tableCells(\DOMElement $row): array
    {
        $cells = [];
        foreach (XmlHtmlDom::childElements($row) as $cell) {
            $name = $this->name($cell);
            if (!in_array($name, self::TABLE_CELL_NAMES, true)) {
                continue;
            }

            $attrs = $this->nodeAttrs($cell);
            if ($name === 'th') {
                $attrs['header'] = true;
            }
            $rowspan = $this->positiveIntAttr($cell, ['rowspan', 'morerows']);
            if ($rowspan > 1) {
                $attrs['rowspan'] = $name === 'entry' && XmlHtmlDom::attribute($cell, 'morerows') !== null
                    ? $rowspan + 1
                    : $rowspan;
            }
            $colspan = $this->positiveIntAttr($cell, ['colspan', 'namest']);
            if ($colspan > 1) {
                $attrs['colspan'] = $colspan;
            }

            $inlines = $this->inlineNodes($cell);
            $text = $this->plainInlineText($inlines);
            $attrs['text'] = $text;
            $cells[] = new AstNode('table_cell', $attrs, [
                new AstNode('plain', ['text' => $text], $inlines),
            ]);
        }

        return $cells;
    }

    private function listFromElement(\DOMElement $list, bool $ordered): ?AstNode
    {
        $items = [];
        foreach (XmlHtmlDom::childElements($list) as $child) {
            if (!in_array($this->name($child), ['li', 'item', 'list-item'], true)) {
                continue;
            }
            $inlines = $this->inlineNodes($child);
            $text = $this->plainInlineText($inlines);
            if ($text === '') {
                continue;
            }
            $items[] = new AstNode('list_item', ['text' => $text], $inlines);
        }

        return $items === [] ? null : new AstNode($ordered ? 'ordered_list' : 'bullet_list', [], $items);
    }

    /**
     * @return list<AstNode>
     */
    private function inlineNodes(\DOMNode $node): array
    {
        $nodes = [];
        foreach ($node->childNodes as $child) {
            if ($child instanceof \DOMText || $child instanceof \DOMCdataSection) {
                $this->appendTextNode($nodes, preg_replace('/\s+/u', ' ', $child->nodeValue ?? '') ?? '');
                continue;
            }
            if (!$child instanceof \DOMElement) {
                continue;
            }

            $name = $this->name($child);
            if ($name === 'br') {
                $nodes[] = new AstNode('linebreak');
                continue;
            }
            if (in_array($name, ['xref', 'ext-link', 'uri', 'a', 'link'], true)) {
                $children = $this->inlineNodes($child);
                $text = $this->plainInlineText($children);
                if ($text === '') {
                    $text = XmlHtmlDom::normalizedText($child);
                    $children = $this->textInlines($text);
                }
                $url = $this->linkTarget($child);
                $nodes[] = new AstNode('link', ['url' => $url, 'title' => ''], $children);
                continue;
            }

            $children = $this->inlineNodes($child);
            if ($children === []) {
                $text = XmlHtmlDom::normalizedText($child);
                $children = $this->textInlines($text);
            }
            if ($children === []) {
                continue;
            }

            $nodes[] = match ($name) {
                'bold', 'b', 'strong' => new AstNode('strong', [], $children),
                'italic', 'i', 'em', 'emph' => new AstNode('emph', [], $children),
                'sup' => new AstNode('superscript', [], $children),
                'sub' => new AstNode('subscript', [], $children),
                'code', 'monospace' => new AstNode('code', ['text' => $this->plainInlineText($children)]),
                default => count($children) === 1 ? $children[0] : new AstNode('span', $this->nodeAttrs($child), $children),
            };
        }

        return $this->trimInlineBoundary($this->coalesceTextNodes($nodes));
    }

    private function appendTextNode(array &$nodes, string $text): void
    {
        if ($text === '') {
            return;
        }

        $nodes[] = new AstNode('text', ['text' => $text]);
    }

    /**
     * @param list<AstNode> $nodes
     * @return list<AstNode>
     */
    private function coalesceTextNodes(array $nodes): array
    {
        $coalesced = [];
        foreach ($nodes as $node) {
            if ($node->type === 'text' && $coalesced !== []) {
                $lastIndex = array_key_last($coalesced);
                $last = $coalesced[$lastIndex];
                if ($last instanceof AstNode && $last->type === 'text') {
                    $coalesced[$lastIndex] = new AstNode('text', [
                        'text' => (string) $last->attr('text', '') . (string) $node->attr('text', ''),
                    ]);
                    continue;
                }
            }
            $coalesced[] = $node;
        }

        return $coalesced;
    }

    /**
     * @param list<AstNode> $nodes
     * @return list<AstNode>
     */
    private function trimInlineBoundary(array $nodes): array
    {
        if ($nodes === []) {
            return [];
        }

        $first = $nodes[0];
        if ($first->type === 'text') {
            $text = ltrim((string) $first->attr('text', ''));
            if ($text === '') {
                array_shift($nodes);
            } else {
                $nodes[0] = new AstNode('text', ['text' => $text]);
            }
        }
        if ($nodes === []) {
            return [];
        }

        $lastIndex = array_key_last($nodes);
        $last = $nodes[$lastIndex];
        if ($last->type === 'text') {
            $text = rtrim((string) $last->attr('text', ''));
            if ($text === '') {
                array_pop($nodes);
            } else {
                $nodes[$lastIndex] = new AstNode('text', ['text' => $text]);
            }
        }

        return array_values($nodes);
    }

    /**
     * @return list<AstNode>
     */
    private function textInlines(string $text): array
    {
        $text = $this->cleanText($text);

        return $text === '' ? [] : [new AstNode('text', ['text' => $text])];
    }

    private function paragraph(string $text): AstNode
    {
        $text = $this->cleanText($text);

        return new AstNode('paragraph', ['text' => $text], $this->textInlines($text));
    }

    private function heading(string $text, int $level): AstNode
    {
        $text = $this->cleanText($text);
        $level = max(1, min(6, $level));

        return new AstNode('heading', ['level' => $level, 'text' => $text], $this->textInlines($text));
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
                'linebreak', 'softbreak' => ' ',
                default => $this->plainInlineText($node->children),
            };
        }

        return $this->cleanText($text);
    }

    private function cleanText(mixed $value): string
    {
        if (!is_scalar($value)) {
            return '';
        }

        return trim(preg_replace('/\s+/u', ' ', (string) $value) ?? (string) $value);
    }

    private function linkTarget(\DOMElement $element): string
    {
        foreach ([
            ['href', null],
            ['href', 'http://www.w3.org/1999/xlink'],
            ['rid', null],
            ['id', null],
        ] as [$name, $namespace]) {
            $value = XmlHtmlDom::attribute($element, $name, $namespace);
            if ($value === null || trim($value) === '') {
                continue;
            }

            return $name === 'rid' || ($name === 'id' && !str_starts_with($value, '#')) ? '#' . $value : $value;
        }

        $text = XmlHtmlDom::normalizedText($element);

        return preg_match('/^[a-z][a-z0-9+.-]*:/i', $text) === 1 ? $text : '#';
    }

    /**
     * @return array<string, mixed>
     */
    private function nodeAttrs(\DOMElement $element): array
    {
        $attrs = [];
        $id = XmlHtmlDom::attribute($element, 'id');
        if ($id !== null && trim($id) !== '') {
            $attrs['identifier'] = $id;
        }
        $role = XmlHtmlDom::attribute($element, 'specific-use') ?? XmlHtmlDom::attribute($element, 'content-type');
        if ($role !== null && trim($role) !== '') {
            $attrs['classes'] = [$role];
        }

        return $attrs;
    }

    /**
     * @return array<string, string>
     */
    private function attributes(\DOMElement $element): array
    {
        $attrs = [];
        foreach ($element->attributes ?? [] as $attribute) {
            if (!$attribute instanceof \DOMAttr) {
                continue;
            }
            $attrs[$attribute->name] = $attribute->value;
        }

        return $attrs;
    }

    private function name(\DOMElement $element): string
    {
        return strtolower($element->localName);
    }

    private function firstJatsBodyElement(\DOMElement $root): ?\DOMElement
    {
        foreach (self::JATS_BODY_ROOTS as $name) {
            $body = XmlHtmlDom::firstChildElement($root, $name);
            if ($body instanceof \DOMElement) {
                return $body;
            }
        }
        foreach (self::JATS_BODY_ROOTS as $name) {
            $body = XmlHtmlDom::firstDescendantElement($root, $name);
            if ($body instanceof \DOMElement) {
                return $body;
            }
        }

        return null;
    }

    private function tableWrapCaption(\DOMElement $tableWrap): string
    {
        $caption = XmlHtmlDom::firstChildElement($tableWrap, 'caption') ?? XmlHtmlDom::firstDescendantElement($tableWrap, 'caption');

        return $caption instanceof \DOMElement ? XmlHtmlDom::normalizedText($caption) : '';
    }

    /**
     * @param list<AstNode> $cells
     */
    private function rowIsHeader(array $cells): bool
    {
        foreach ($cells as $cell) {
            if ($cell->attr('header') !== true) {
                return false;
            }
        }

        return $cells !== [];
    }

    /**
     * @param list<string> $names
     */
    private function positiveIntAttr(\DOMElement $element, array $names): int
    {
        foreach ($names as $name) {
            $value = XmlHtmlDom::attribute($element, $name);
            if ($value === null || !is_numeric($value)) {
                continue;
            }

            return max(1, (int) $value);
        }

        return 1;
    }

    private function hasBlockContainerChild(\DOMElement $element): bool
    {
        foreach (XmlHtmlDom::childElements($element) as $child) {
            if (in_array($this->name($child), self::BLOCK_CONTAINER_NAMES, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<AstNode> $nodes
     */
    private function countNodesOfType(array $nodes, string $type): int
    {
        $count = 0;
        foreach ($nodes as $node) {
            if ($node->type === $type) {
                $count++;
            }
            $count += $this->countNodesOfType($node->children, $type);
        }

        return $count;
    }
}
