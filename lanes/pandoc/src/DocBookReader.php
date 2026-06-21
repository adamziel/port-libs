<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class DocBookReader
{
    private const DOCBOOK_NAMESPACE = 'http://docbook.org/ns/docbook';
    private const XML_NAMESPACE = 'http://www.w3.org/XML/1998/namespace';
    private const XLINK_NAMESPACE = 'http://www.w3.org/1999/xlink';

    private const ROOT_NAMES = [
        'appendix',
        'article',
        'book',
        'chapter',
        'part',
        'preface',
        'refentry',
        'reference',
        'section',
        'sect1',
        'sect2',
        'sect3',
        'sect4',
        'sect5',
        'set',
    ];
    private const TABLE_ROOT_NAMES = ['informaltable', 'table'];
    private const SECTION_NAMES = [
        'appendix',
        'chapter',
        'part',
        'preface',
        'refentry',
        'section',
        'sect1',
        'sect2',
        'sect3',
        'sect4',
        'sect5',
        'simplesect',
    ];
    private const METADATA_NAMES = [
        'appendixinfo',
        'articleinfo',
        'bookinfo',
        'chapterinfo',
        'info',
        'partinfo',
        'prefaceinfo',
        'refentryinfo',
        'referenceinfo',
        'sectioninfo',
        'sect1info',
        'sect2info',
        'sect3info',
        'sect4info',
        'sect5info',
    ];
    private const PARAGRAPH_NAMES = ['para', 'simpara', 'formalpara'];
    private const ADMONITION_NAMES = ['caution', 'important', 'note', 'tip', 'warning'];
    private const LITERAL_BLOCK_NAMES = ['literallayout', 'programlisting', 'screen', 'synopsis'];
    private const BLOCK_CONTAINER_NAMES = [
        'abstract',
        'appendix',
        'article',
        'bibliodiv',
        'bibliography',
        'blockquote',
        'book',
        'calloutlist',
        'caution',
        'chapter',
        'example',
        'figure',
        'formalpara',
        'important',
        'informalexample',
        'informalfigure',
        'informaltable',
        'itemizedlist',
        'listitem',
        'mediaobject',
        'note',
        'orderedlist',
        'para',
        'part',
        'partintro',
        'preface',
        'procedure',
        'programlisting',
        'refentry',
        'reference',
        'screen',
        'section',
        'sect1',
        'sect2',
        'sect3',
        'sect4',
        'sect5',
        'sidebar',
        'simpara',
        'simplesect',
        'table',
        'tip',
        'variablelist',
        'warning',
    ];

    /**
     * @param array<string, mixed> $options
     */
    public function __construct(private readonly array $options = [])
    {
    }

    public function read(string $bytes): AstNode
    {
        $dom = XmlHtmlDom::loadXmlDocument($bytes, 'DOCBOOK input', false);
        $root = $dom->documentElement;
        if (!$root instanceof \DOMElement) {
            throw new \InvalidArgumentException('DocBook reader requires a document element.');
        }

        $rootName = $this->name($root);
        if (in_array($rootName, self::TABLE_ROOT_NAMES, true)) {
            return $this->readTableFragment($dom, $root, $bytes);
        }
        if (!$this->rootLooksLikeDocBook($root)) {
            throw new \InvalidArgumentException('DocBook reader root must be a DocBook structural element.');
        }

        $format = $this->format();
        $structure = XmlHtmlDom::summarizeDocBookStructure($dom, $format);
        $review = XmlHtmlDom::summarizeDocBookReviewPacket($dom, $format);
        $bibliography = XmlHtmlDom::summarizeDocBookBibliography($dom);
        $blocks = $this->documentBlocks($root, $structure);
        if ($blocks === []) {
            $text = XmlHtmlDom::normalizedText($root);
            if ($text !== '') {
                $blocks[] = $this->paragraph($text);
            }
        }

        $meta = $this->baseMetadata($dom, $root, $bytes, $format, [
            'reader' => self::class,
            'readerScope' => 'bounded-docbook-reader',
            'docbookStructure' => $structure,
            'docbookReviewPacket' => $review,
            'docbookBibliography' => $bibliography,
            'docbookVersion' => $structure['docbookVersion'] ?? null,
            'docbookTitle' => $structure['title'] ?? null,
            'docbookSubtitle' => $structure['subtitle'] ?? null,
            'docbookAbstractText' => $structure['abstractText'] ?? null,
            'docbookSectionCount' => $structure['sectionCount'] ?? 0,
            'docbookTableCount' => $structure['tableCount'] ?? 0,
            'docbookFigureCount' => $structure['figureCount'] ?? 0,
            'docbookBibliographyCount' => $structure['bibliographyCount'] ?? 0,
            'docbookBibliographyEntryCount' => $structure['bibliographyEntryCount'] ?? 0,
            'docbookXrefCount' => $structure['xrefCount'] ?? 0,
            'docbookMediaObjectCount' => $structure['mediaObjectCount'] ?? 0,
            'xmlElementCount' => count(XmlHtmlDom::descendantElements($root)) + 1,
            'xmlDetectedTables' => $this->countNodesOfType($blocks, 'table'),
            'xmlDetectedHeadings' => $this->countNodesOfType($blocks, 'heading'),
        ]);

        $title = $this->cleanText($structure['title'] ?? '');
        if ($title !== '') {
            $meta['title'] = $title;
            $meta['titleInlines'] = $this->textInlines($title);
        }
        $contributors = $structure['contributorNames'] ?? [];
        if (is_array($contributors) && $contributors !== []) {
            $authors = array_values(array_filter($contributors, 'is_string'));
            if ($authors !== []) {
                $meta['authors'] = $authors;
            }
        }

        return new AstNode('document', [
            'sourceFormat' => $format,
            'meta' => $meta,
        ], $blocks);
    }

    private function readTableFragment(\DOMDocument $dom, \DOMElement $root, string $bytes): AstNode
    {
        $table = $this->tableFromElement($root);
        if (!$table instanceof AstNode) {
            throw new \InvalidArgumentException('DocBook table fragment does not contain table rows.');
        }

        return new AstNode('document', [
            'sourceFormat' => $this->format(),
            'meta' => $this->baseMetadata($dom, $root, $bytes, $this->format(), [
                'reader' => self::class,
                'readerScope' => 'bounded-docbook-table-fragment-reader',
                'docbookFragmentRoot' => $root->localName,
                'xmlElementCount' => count(XmlHtmlDom::descendantElements($root)) + 1,
                'xmlDetectedTables' => 1,
                'xmlDetectedHeadings' => 0,
            ]),
        ], [$table]);
    }

    private function format(): string
    {
        $format = strtolower(trim((string) ($this->options['format'] ?? 'docbook')));

        return in_array($format, ['docbook4', 'docbook5'], true) ? $format : 'docbook';
    }

    private function rootLooksLikeDocBook(\DOMElement $root): bool
    {
        if (($root->namespaceURI ?? '') === self::DOCBOOK_NAMESPACE) {
            return true;
        }

        return in_array($this->name($root), self::ROOT_NAMES, true);
    }

    /**
     * @param array<string, mixed> $structure
     * @return list<AstNode>
     */
    private function documentBlocks(\DOMElement $root, array $structure): array
    {
        $blocks = [];
        $title = $this->cleanText($structure['title'] ?? $this->firstChildText($root, ['title']));
        if ($title !== '') {
            $blocks[] = $this->heading($title, 1, $this->nodeAttrs($root));
        }
        $subtitle = $this->cleanText($structure['subtitle'] ?? $this->firstChildText($root, ['subtitle']));
        if ($subtitle !== '') {
            $blocks[] = $this->paragraph($subtitle);
        }
        $abstract = $this->cleanText($structure['abstractText'] ?? '');
        if ($abstract !== '') {
            $blocks[] = $this->heading('Abstract', 2);
            $blocks[] = $this->paragraph($abstract);
        }

        foreach (XmlHtmlDom::childElements($root) as $child) {
            if ($this->isDocumentPreambleElement($child)) {
                continue;
            }

            array_push($blocks, ...$this->blocksFromElement($child, 2));
        }

        return $blocks;
    }

    /**
     * @return list<AstNode>
     */
    private function blocksFromElement(\DOMElement $element, int $headingLevel): array
    {
        $name = $this->name($element);
        if (in_array($name, self::METADATA_NAMES, true)) {
            return [];
        }
        if (in_array($name, self::TABLE_ROOT_NAMES, true)) {
            $table = $this->tableFromElement($element);

            return $table instanceof AstNode ? [$table] : [];
        }
        if (in_array($name, self::SECTION_NAMES, true)) {
            return $this->sectionBlocks($element, $headingLevel);
        }
        if ($name === 'bridgehead') {
            $text = XmlHtmlDom::normalizedText($element);

            return $text === '' ? [] : [$this->heading($text, $headingLevel, $this->nodeAttrs($element))];
        }
        if (in_array($name, self::PARAGRAPH_NAMES, true)) {
            $paragraph = $this->paragraphFromElement($element);

            return $paragraph instanceof AstNode ? [$paragraph] : [];
        }
        if ($name === 'itemizedlist') {
            $list = $this->listFromElement($element, false);

            return $list instanceof AstNode ? [$list] : [];
        }
        if ($name === 'orderedlist' || $name === 'procedure') {
            $list = $this->listFromElement($element, true);

            return $list instanceof AstNode ? [$list] : [];
        }
        if ($name === 'variablelist') {
            $list = $this->variableListFromElement($element);

            return $list instanceof AstNode ? [$list] : [];
        }
        if ($name === 'blockquote') {
            $blocks = $this->containerChildBlocks($element, $headingLevel);

            return $blocks === [] ? [] : [new AstNode('blockquote', $this->nodeAttrs($element), $blocks)];
        }
        if (in_array($name, self::ADMONITION_NAMES, true)) {
            return [$this->admonitionBlock($element, $headingLevel)];
        }
        if (in_array($name, self::LITERAL_BLOCK_NAMES, true)) {
            return [$this->codeBlockFromElement($element)];
        }
        if (in_array($name, ['figure', 'informalfigure', 'mediaobject'], true)) {
            $figure = $this->figureFromElement($element);

            return $figure instanceof AstNode ? [$figure] : $this->containerChildBlocks($element, $headingLevel);
        }
        if ($name === 'bibliography' || $name === 'bibliodiv') {
            return $this->bibliographyBlocks($element, $headingLevel);
        }
        if (in_array($name, ['example', 'informalexample', 'sidebar'], true)) {
            $blocks = $this->containerChildBlocks($element, $headingLevel);
            $attrs = $this->nodeAttrs($element);
            $attrs['classes'] = array_values(array_unique(array_merge($attrs['classes'] ?? [], ['docbook-' . $name])));

            return $blocks === [] ? [] : [new AstNode('div', $attrs, $blocks)];
        }

        $blocks = $this->containerChildBlocks($element, $headingLevel);
        if ($blocks !== []) {
            return $blocks;
        }
        if ($this->hasBlockContainerChild($element)) {
            return [];
        }

        $text = XmlHtmlDom::normalizedText($element);

        return $text === '' ? [] : [$this->paragraph($text, $this->nodeAttrs($element))];
    }

    /**
     * @return list<AstNode>
     */
    private function sectionBlocks(\DOMElement $section, int $headingLevel): array
    {
        $blocks = [];
        $title = $this->firstChildText($section, ['title']);
        if ($title !== '') {
            $blocks[] = $this->heading($title, $this->sectionHeadingLevel($section, $headingLevel), $this->nodeAttrs($section));
        }
        $subtitle = $this->firstChildText($section, ['subtitle']);
        if ($subtitle !== '') {
            $blocks[] = $this->paragraph($subtitle);
        }

        foreach (XmlHtmlDom::childElements($section) as $child) {
            if ($this->isSectionPreambleElement($child)) {
                continue;
            }

            array_push($blocks, ...$this->blocksFromElement($child, min(6, $this->sectionHeadingLevel($section, $headingLevel) + 1)));
        }

        return $blocks;
    }

    private function sectionHeadingLevel(\DOMElement $section, int $fallback): int
    {
        return match ($this->name($section)) {
            'sect1' => 2,
            'sect2' => 3,
            'sect3' => 4,
            'sect4' => 5,
            'sect5' => 6,
            default => max(1, min(6, $fallback)),
        };
    }

    /**
     * @return list<AstNode>
     */
    private function containerChildBlocks(\DOMElement $element, int $headingLevel): array
    {
        $blocks = [];
        foreach (XmlHtmlDom::childElements($element) as $child) {
            if ($this->isSectionPreambleElement($child)) {
                continue;
            }

            array_push($blocks, ...$this->blocksFromElement($child, $headingLevel));
        }

        return $blocks;
    }

    private function paragraphFromElement(\DOMElement $element): ?AstNode
    {
        $inlines = $this->inlineNodes($element);
        $text = $this->plainInlineText($inlines);
        if ($text === '') {
            return null;
        }

        return new AstNode('paragraph', array_replace($this->nodeAttrs($element), ['text' => $text]), $inlines);
    }

    private function admonitionBlock(\DOMElement $element, int $headingLevel): AstNode
    {
        $name = $this->name($element);
        $blocks = [];
        $title = $this->firstChildText($element, ['title']);
        if ($title !== '') {
            $blocks[] = $this->heading($title, $headingLevel);
        }
        foreach (XmlHtmlDom::childElements($element) as $child) {
            if ($this->isSectionPreambleElement($child)) {
                continue;
            }

            array_push($blocks, ...$this->blocksFromElement($child, min(6, $headingLevel + 1)));
        }
        if ($blocks === []) {
            $text = XmlHtmlDom::normalizedText($element);
            if ($text !== '') {
                $blocks[] = $this->paragraph($text);
            }
        }

        $attrs = $this->nodeAttrs($element);
        $attrs['classes'] = array_values(array_unique(array_merge($attrs['classes'] ?? [], ['docbook-admonition', 'docbook-' . $name])));

        return new AstNode('div', $attrs, $blocks);
    }

    private function codeBlockFromElement(\DOMElement $element): AstNode
    {
        $text = (string) $element->textContent;
        $attrs = $this->nodeAttrs($element);
        $attrs['text'] = trim($text, "\r\n");
        if ($this->name($element) === 'programlisting') {
            $language = trim((string) (XmlHtmlDom::attribute($element, 'language') ?? ''));
            if ($language !== '') {
                $attrs['classes'] = array_values(array_unique(array_merge($attrs['classes'] ?? [], [$language])));
            }
        }

        return new AstNode('code_block', $attrs);
    }

    private function listFromElement(\DOMElement $list, bool $ordered): ?AstNode
    {
        $items = [];
        foreach (XmlHtmlDom::childElements($list) as $child) {
            if (!in_array($this->name($child), ['listitem', 'step', 'item'], true)) {
                continue;
            }

            $blocks = $this->containerChildBlocks($child, 2);
            if ($blocks === []) {
                $inlines = $this->inlineNodes($child);
                $text = $this->plainInlineText($inlines);
                if ($text === '') {
                    continue;
                }
                $blocks = [new AstNode('plain', ['text' => $text], $inlines)];
            }
            $items[] = new AstNode('list_item', array_replace($this->nodeAttrs($child), [
                'text' => $this->plainBlockText($blocks),
            ]), $blocks);
        }

        if ($items === []) {
            return null;
        }

        $attrs = $this->nodeAttrs($list);
        $start = $this->positiveIntAttr($list, ['startingnumber', 'start']);
        if ($ordered && $start > 1) {
            $attrs['start'] = $start;
        }

        return new AstNode($ordered ? 'ordered_list' : 'bullet_list', $attrs, $items);
    }

    private function variableListFromElement(\DOMElement $list): ?AstNode
    {
        $items = [];
        foreach (XmlHtmlDom::childElements($list, 'varlistentry') as $entry) {
            $terms = XmlHtmlDom::childElements($entry, 'term');
            $termInlines = [];
            foreach ($terms as $index => $term) {
                if ($index > 0) {
                    $termInlines[] = new AstNode('text', ['text' => '; ']);
                }
                array_push($termInlines, ...$this->inlineNodes($term));
            }
            $termText = $this->plainInlineText($termInlines);
            if ($termText === '') {
                $termText = XmlHtmlDom::normalizedText($entry);
                $termInlines = $this->textInlines($termText);
            }

            $definitions = [];
            foreach (XmlHtmlDom::childElements($entry, 'listitem') as $listItem) {
                $blocks = $this->containerChildBlocks($listItem, 2);
                if ($blocks === []) {
                    $text = XmlHtmlDom::normalizedText($listItem);
                    if ($text !== '') {
                        $blocks = [$this->paragraph($text)];
                    }
                }
                if ($blocks !== []) {
                    $definitions[] = new AstNode('definition', [], $blocks);
                }
            }
            if ($definitions === []) {
                continue;
            }

            $items[] = new AstNode('definition_item', ['term' => $termText], array_merge([
                new AstNode('term', ['text' => $termText], $termInlines),
            ], $definitions));
        }

        return $items === [] ? null : new AstNode('definition_list', $this->nodeAttrs($list), $items);
    }

    /**
     * @return list<AstNode>
     */
    private function bibliographyBlocks(\DOMElement $bibliography, int $headingLevel): array
    {
        $blocks = [];
        $title = $this->firstChildText($bibliography, ['title']);
        if ($title !== '') {
            $blocks[] = $this->heading($title, $headingLevel, $this->nodeAttrs($bibliography));
        }

        $entries = [];
        foreach (XmlHtmlDom::childElements($bibliography) as $child) {
            $name = $this->name($child);
            if ($name === 'bibliodiv') {
                array_push($blocks, ...$this->bibliographyBlocks($child, min(6, $headingLevel + 1)));
                continue;
            }
            if (!in_array($name, ['biblioentry', 'bibliomixed'], true)) {
                continue;
            }

            $id = $this->elementId($child) ?? $this->firstChildText($child, ['abbrev']);
            $titleText = $this->firstChildText($child, ['title', 'citetitle']);
            $text = $this->bibliographyEntryText($child);
            $term = $this->cleanText($id !== '' && $id !== null ? $id : ($titleText !== '' ? $titleText : 'entry-' . (count($entries) + 1)));
            if ($text === '') {
                continue;
            }
            $entries[] = new AstNode('definition_item', [
                'term' => $term,
                'docbookBibliographyEntryId' => $id,
            ], [
                new AstNode('term', ['text' => $term], $this->textInlines($term)),
                new AstNode('definition', [], [
                    $this->paragraph($text, $this->nodeAttrs($child)),
                ]),
            ]);
        }

        if ($entries !== []) {
            $attrs = $this->nodeAttrs($bibliography);
            $attrs['class'] = 'docbook-bibliography';
            $attrs['classes'] = array_values(array_unique(array_merge($attrs['classes'] ?? [], ['docbook-bibliography'])));
            $blocks[] = new AstNode('definition_list', $attrs, $entries);
        }

        return $blocks;
    }

    private function bibliographyEntryText(\DOMElement $entry): string
    {
        if ($this->name($entry) === 'bibliomixed') {
            return XmlHtmlDom::normalizedText($entry);
        }

        $parts = [];
        foreach (XmlHtmlDom::childElements($entry) as $child) {
            $name = $this->name($child);
            $text = match ($name) {
                'abbrev', 'biblioset' => '',
                'author', 'editor', 'othercredit' => $this->personNameText($child),
                'authorgroup' => $this->authorGroupText($child),
                'publisher' => $this->publisherText($child),
                default => XmlHtmlDom::normalizedText($child),
            };
            $text = $this->cleanText($text);
            if ($text !== '') {
                $parts[] = $text;
            }
        }

        return $this->cleanText(implode('. ', array_values(array_unique($parts))));
    }

    private function authorGroupText(\DOMElement $group): string
    {
        $names = [];
        foreach (XmlHtmlDom::childElements($group) as $child) {
            if (in_array($this->name($child), ['author', 'editor', 'othercredit'], true)) {
                $name = $this->personNameText($child);
                if ($name !== '') {
                    $names[] = $name;
                }
            }
        }

        return implode('; ', $names);
    }

    private function personNameText(\DOMElement $element): string
    {
        $person = $this->name($element) === 'personname'
            ? $element
            : XmlHtmlDom::firstDescendantElement($element, 'personname');
        if ($person instanceof \DOMElement) {
            $parts = [];
            foreach (['honorific', 'firstname', 'givenname', 'othername', 'surname', 'lineage'] as $name) {
                foreach (XmlHtmlDom::childElements($person, $name) as $child) {
                    $text = XmlHtmlDom::normalizedText($child);
                    if ($text !== '') {
                        $parts[] = $text;
                    }
                }
            }
            if ($parts !== []) {
                return implode(' ', $parts);
            }
        }

        $org = XmlHtmlDom::firstDescendantElement($element, 'orgname');
        if ($org instanceof \DOMElement) {
            return XmlHtmlDom::normalizedText($org);
        }

        return XmlHtmlDom::normalizedText($element);
    }

    private function publisherText(\DOMElement $publisher): string
    {
        $parts = [];
        foreach (['publishername', 'address'] as $name) {
            $child = XmlHtmlDom::firstChildElement($publisher, $name);
            $text = $child instanceof \DOMElement ? XmlHtmlDom::normalizedText($child) : '';
            if ($text !== '') {
                $parts[] = $text;
            }
        }

        return implode(', ', $parts);
    }

    private function figureFromElement(\DOMElement $element): ?AstNode
    {
        $image = $this->imageFromElement($element);
        $caption = $this->firstChildText($element, ['title', 'caption']);
        if (!$image instanceof AstNode && $caption === '') {
            return null;
        }

        $attrs = $this->nodeAttrs($element);
        if ($caption !== '') {
            $attrs['caption'] = $caption;
        }

        return new AstNode('figure', $attrs, $image instanceof AstNode ? [$image] : []);
    }

    private function imageFromElement(\DOMElement $element): ?AstNode
    {
        $imageData = $this->name($element) === 'imagedata'
            ? $element
            : XmlHtmlDom::firstDescendantElement($element, 'imagedata');
        if (!$imageData instanceof \DOMElement) {
            return null;
        }

        $url = trim((string) (
            XmlHtmlDom::attribute($imageData, 'fileref')
            ?? XmlHtmlDom::attribute($imageData, 'entityref')
            ?? XmlHtmlDom::attribute($imageData, 'href')
            ?? XmlHtmlDom::attribute($imageData, 'href', self::XLINK_NAMESPACE)
            ?? ''
        ));
        if ($url === '') {
            return null;
        }

        $alt = $this->mediaAltText($element);
        $attrs = $this->nodeAttrs($imageData);
        $attrs['url'] = $url;
        $attrs['alt'] = $alt;
        $attrs['title'] = '';
        $attrs['attributes'] = array_replace($attrs['attributes'] ?? [], [
            'data-docbook-media-source' => $this->name($element),
        ]);

        return new AstNode('image', $attrs, $this->textInlines($alt));
    }

    private function mediaAltText(\DOMElement $element): string
    {
        foreach (['alt', 'textobject', 'phrase', 'caption', 'title'] as $name) {
            $candidate = $name === 'alt'
                ? XmlHtmlDom::attribute($element, 'alt')
                : null;
            if ($candidate === null) {
                $child = XmlHtmlDom::firstDescendantElement($element, $name);
                $candidate = $child instanceof \DOMElement ? XmlHtmlDom::normalizedText($child) : null;
            }
            $text = $this->cleanText($candidate);
            if ($text !== '') {
                return $text;
            }
        }

        return '';
    }

    private function tableFromElement(\DOMElement $table): ?AstNode
    {
        $tgroup = XmlHtmlDom::firstDescendantElement($table, 'tgroup');
        $sectionRoot = $tgroup instanceof \DOMElement ? $tgroup : $table;
        $thead = XmlHtmlDom::firstChildElement($sectionRoot, 'thead');
        $tbody = XmlHtmlDom::firstChildElement($sectionRoot, 'tbody');
        $tfoot = XmlHtmlDom::firstChildElement($sectionRoot, 'tfoot');
        $directRows = $tbody instanceof \DOMElement || $thead instanceof \DOMElement || $tfoot instanceof \DOMElement
            ? []
            : XmlHtmlDom::childElements($sectionRoot, 'row');

        [$columnNames, $widths] = $tgroup instanceof \DOMElement
            ? $this->readColumnSpecs($tgroup)
            : [[], null];

        $maxColumns = count($columnNames);
        $headRows = $thead instanceof \DOMElement
            ? $this->readTableRows($thead, $columnNames, true, $maxColumns)
            : [];
        $bodyRows = $tbody instanceof \DOMElement
            ? $this->readTableRows($tbody, $columnNames, false, $maxColumns)
            : [];
        $footRows = $tfoot instanceof \DOMElement
            ? $this->readTableRows($tfoot, $columnNames, false, $maxColumns)
            : [];

        if ($directRows !== []) {
            foreach ($directRows as $row) {
                $cells = $this->tableCells($row, $columnNames, false);
                if ($cells === []) {
                    continue;
                }
                $bodyRows[] = new AstNode('table_row', [], $cells);
                $maxColumns = max($maxColumns, $this->rowColumnSpan($cells));
            }
        }

        if ($headRows === [] && $bodyRows === [] && $footRows === []) {
            return null;
        }
        if ($maxColumns === 0) {
            foreach ([...$headRows, ...$bodyRows, ...$footRows] as $row) {
                $maxColumns = max($maxColumns, $this->rowColumnSpan($row->children));
            }
        }

        $attrs = $this->nodeAttrs($table);
        $caption = $this->firstChildText($table, ['title', 'caption']);
        $attrs['caption'] = $caption;
        $attrs['alignments'] = array_fill(0, $maxColumns, 'default');
        if ($widths !== null) {
            $attrs['widths'] = $widths;
        }

        $children = [
            new AstNode('table_head', [], $headRows),
            new AstNode('table_body', [], $bodyRows),
        ];
        if ($footRows !== []) {
            $children[] = new AstNode('table_foot', [], $footRows);
        }

        return new AstNode('table', $attrs, $children);
    }

    /**
     * @param list<string> $columnNames
     * @return list<AstNode>
     */
    private function readTableRows(\DOMElement $section, array $columnNames, bool $header, int &$maxColumns): array
    {
        $rows = [];
        foreach (XmlHtmlDom::childElements($section, 'row') as $rowElement) {
            $cells = $this->tableCells($rowElement, $columnNames, $header);
            if ($cells === []) {
                continue;
            }

            $rows[] = new AstNode('table_row', ['header' => $header], $cells);
            $maxColumns = max($maxColumns, $this->rowColumnSpan($cells));
        }

        return $rows;
    }

    /**
     * @return array{0:list<string>, 1:list<float>|null}
     */
    private function readColumnSpecs(\DOMElement $tgroup): array
    {
        $names = [];
        $widthParts = [];
        foreach (XmlHtmlDom::childElements($tgroup, 'colspec') as $index => $colspec) {
            $name = trim((string) XmlHtmlDom::attribute($colspec, 'colname'));
            $names[] = $name !== '' ? $name : 'col_' . ($index + 1);

            $width = trim((string) XmlHtmlDom::attribute($colspec, 'colwidth'));
            $widthParts[] = preg_match('/^([0-9]+(?:\.[0-9]+)?)\*$/', $width, $match) === 1
                ? (float) $match[1]
                : null;
        }

        $widths = null;
        if ($widthParts !== [] && !in_array(null, $widthParts, true)) {
            $total = array_sum($widthParts);
            if ($total > 0.0) {
                $widths = array_map(
                    static fn (float $width): float => $width / $total,
                    $widthParts
                );
            }
        }

        return [$names, $widths];
    }

    /**
     * @param list<string> $columnNames
     * @return list<AstNode>
     */
    private function tableCells(\DOMElement $row, array $columnNames, bool $header): array
    {
        $cells = [];
        foreach (XmlHtmlDom::childElements($row, 'entry') as $entry) {
            $children = $this->inlineNodes($entry);
            $text = $this->plainInlineText($children);
            $attrs = array_replace($this->nodeAttrs($entry), [
                'text' => $text,
                'header' => $header,
            ]);

            $align = $this->normalizeAlignment((string) XmlHtmlDom::attribute($entry, 'align'));
            if ($align !== 'default') {
                $attrs['align'] = $align;
            }
            $colspan = $this->columnSpan($entry, $columnNames);
            if ($colspan > 1) {
                $attrs['colspan'] = $colspan;
            }
            $moreRows = trim((string) XmlHtmlDom::attribute($entry, 'morerows'));
            if (preg_match('/^\d+$/', $moreRows) === 1 && (int) $moreRows > 0) {
                $attrs['rowspan'] = (int) $moreRows + 1;
            }

            $cells[] = new AstNode('table_cell', $attrs, $children);
        }

        return $cells;
    }

    /**
     * @param list<AstNode> $cells
     */
    private function rowColumnSpan(array $cells): int
    {
        $count = 0;
        foreach ($cells as $cell) {
            $count += max(1, (int) $cell->attr('colspan', 1));
        }

        return $count;
    }

    /**
     * @param list<string> $columnNames
     */
    private function columnSpan(\DOMElement $entry, array $columnNames): int
    {
        $explicit = $this->positiveIntAttr($entry, ['colspan']);
        if ($explicit > 1) {
            return $explicit;
        }

        $startName = trim((string) XmlHtmlDom::attribute($entry, 'namest'));
        $endName = trim((string) XmlHtmlDom::attribute($entry, 'nameend'));
        if ($startName === '' || $endName === '') {
            return 1;
        }

        $start = array_search($startName, $columnNames, true);
        $end = array_search($endName, $columnNames, true);
        if (!is_int($start) || !is_int($end) || $end < $start) {
            return 1;
        }

        return $end - $start + 1;
    }

    private function normalizeAlignment(string $alignment): string
    {
        return match (strtolower(trim($alignment))) {
            'center' => 'center',
            'left' => 'left',
            'right' => 'right',
            default => 'default',
        };
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
            if ($name === 'title' && $child->parentNode instanceof \DOMElement && $this->hasBlockContainerChild($child->parentNode)) {
                continue;
            }
            if ($name === 'linebreak') {
                $nodes[] = new AstNode('linebreak');
                continue;
            }
            if (in_array($name, ['link', 'ulink', 'xref', 'biblioref'], true)) {
                $children = $this->inlineNodes($child);
                if ($children === []) {
                    $label = $this->xrefLabel($child);
                    $children = $this->textInlines($label);
                }
                $nodes[] = new AstNode('link', ['url' => $this->linkTarget($child), 'title' => ''], $children);
                continue;
            }
            if (in_array($name, ['inlinemediaobject', 'mediaobject', 'imagedata'], true)) {
                $image = $this->imageFromElement($child);
                if ($image instanceof AstNode) {
                    $nodes[] = $image;
                }
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
                'emphasis' => new AstNode($this->emphasisIsStrong($child) ? 'strong' : 'emph', [], $children),
                'superscript' => new AstNode('superscript', [], $children),
                'subscript' => new AstNode('subscript', [], $children),
                'code', 'command', 'computeroutput', 'constant', 'filename', 'literal', 'option', 'replaceable', 'userinput' => new AstNode('code', ['text' => $this->plainInlineText($children)]),
                'phrase' => new AstNode('span', $this->nodeAttrs($child), $children),
                default => count($children) === 1 ? $children[0] : new AstNode('span', $this->nodeAttrs($child), $children),
            };
        }

        return $this->trimInlineBoundary($this->coalesceTextNodes($nodes));
    }

    private function emphasisIsStrong(\DOMElement $element): bool
    {
        $role = strtolower(trim((string) (XmlHtmlDom::attribute($element, 'role') ?? XmlHtmlDom::attribute($element, 'remap') ?? '')));

        return in_array($role, ['bold', 'strong'], true);
    }

    private function xrefLabel(\DOMElement $element): string
    {
        $text = XmlHtmlDom::normalizedText($element);
        if ($text !== '') {
            return $text;
        }

        $target = $this->linkTarget($element);

        return $target === '#' ? 'xref' : ltrim($target, '#');
    }

    private function linkTarget(\DOMElement $element): string
    {
        foreach ([
            ['url', null],
            ['href', null],
            ['href', self::XLINK_NAMESPACE],
            ['linkend', null],
            ['endterm', null],
        ] as [$name, $namespace]) {
            $value = XmlHtmlDom::attribute($element, $name, $namespace);
            if ($value === null || trim($value) === '') {
                continue;
            }

            return in_array($name, ['linkend', 'endterm'], true) && !str_starts_with($value, '#') ? '#' . $value : $value;
        }

        return '#';
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

    /**
     * @param array<string, mixed> $attrs
     */
    private function paragraph(string $text, array $attrs = []): AstNode
    {
        $text = $this->cleanText($text);

        return new AstNode('paragraph', array_replace($attrs, ['text' => $text]), $this->textInlines($text));
    }

    /**
     * @param array<string, mixed> $attrs
     */
    private function heading(string $text, int $level, array $attrs = []): AstNode
    {
        $text = $this->cleanText($text);
        $level = max(1, min(6, $level));

        return new AstNode('heading', array_replace($attrs, [
            'level' => $level,
            'text' => $text,
        ]), $this->textInlines($text));
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
                'image' => (string) $node->attr('alt', ''),
                default => $this->plainInlineText($node->children),
            };
        }

        return $this->cleanText($text);
    }

    /**
     * @param list<AstNode> $blocks
     */
    private function plainBlockText(array $blocks): string
    {
        $parts = [];
        foreach ($blocks as $block) {
            $text = $block->attr('text', null);
            if (is_string($text) && trim($text) !== '') {
                $parts[] = $text;
                continue;
            }
            $childText = $this->plainInlineText($block->children);
            if ($childText !== '') {
                $parts[] = $childText;
            }
        }

        return $this->cleanText(implode(' ', $parts));
    }

    private function cleanText(mixed $value): string
    {
        if (!is_scalar($value)) {
            return '';
        }

        return trim(preg_replace('/\s+/u', ' ', (string) $value) ?? (string) $value);
    }

    private function firstChildText(\DOMElement $element, array $names): string
    {
        foreach ($names as $name) {
            $child = XmlHtmlDom::firstChildElement($element, $name);
            if (!$child instanceof \DOMElement) {
                continue;
            }

            $text = XmlHtmlDom::normalizedText($child);
            if ($text !== '') {
                return $text;
            }
        }

        return '';
    }

    private function isDocumentPreambleElement(\DOMElement $element): bool
    {
        $name = $this->name($element);

        return in_array($name, self::METADATA_NAMES, true)
            || in_array($name, ['title', 'subtitle', 'abstract'], true);
    }

    private function isSectionPreambleElement(\DOMElement $element): bool
    {
        $name = $this->name($element);

        return in_array($name, self::METADATA_NAMES, true)
            || in_array($name, ['title', 'subtitle'], true);
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

    /**
     * @return array<string, mixed>
     */
    private function nodeAttrs(\DOMElement $element): array
    {
        $attrs = [
            'classes' => [],
            'attributes' => [],
        ];
        $id = $this->elementId($element);
        if ($id !== null) {
            $attrs['identifier'] = $id;
        }
        foreach (['role', 'condition', 'revisionflag', 'status'] as $name) {
            $value = XmlHtmlDom::attribute($element, $name);
            if ($value === null || trim($value) === '') {
                continue;
            }
            $attrs['attributes']['data-docbook-' . $name] = trim($value);
            if ($name === 'role' || $name === 'status') {
                $attrs['classes'][] = 'docbook-' . preg_replace('/[^a-z0-9_-]+/i', '-', strtolower(trim($value)));
            }
        }

        if ($attrs['classes'] === []) {
            unset($attrs['classes']);
        }
        if ($attrs['attributes'] === []) {
            unset($attrs['attributes']);
        }

        return $attrs;
    }

    private function elementId(\DOMElement $element): ?string
    {
        foreach ([
            ['id', self::XML_NAMESPACE],
            ['id', null],
        ] as [$name, $namespace]) {
            $value = XmlHtmlDom::attribute($element, $name, $namespace);
            if ($value !== null && trim($value) !== '') {
                return trim($value);
            }
        }

        return null;
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
            'payloadExposurePolicy' => 'docbook-dom-text-and-structural-metadata-only',
        ], $extra);
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
