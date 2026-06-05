<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class OdfReader
{
    public const MIMETYPE = 'application/vnd.oasis.opendocument.text';
    private const OFFICE_NS = 'urn:oasis:names:tc:opendocument:xmlns:office:1.0';
    private const TEXT_NS = 'urn:oasis:names:tc:opendocument:xmlns:text:1.0';
    private const STYLE_NS = 'urn:oasis:names:tc:opendocument:xmlns:style:1.0';
    private const TABLE_NS = 'urn:oasis:names:tc:opendocument:xmlns:table:1.0';
    private const DRAW_NS = 'urn:oasis:names:tc:opendocument:xmlns:drawing:1.0';
    private const SVG_NS = 'urn:oasis:names:tc:opendocument:xmlns:svg-compatible:1.0';
    private const XLINK_NS = 'http://www.w3.org/1999/xlink';
    private const MANIFEST_NS = 'urn:oasis:names:tc:opendocument:xmlns:manifest:1.0';
    private const DC_NS = 'http://purl.org/dc/elements/1.1/';
    private const META_NS = 'urn:oasis:names:tc:opendocument:xmlns:meta:1.0';
    private const FO_NS = 'urn:oasis:names:tc:opendocument:xmlns:xsl-fo-compatible:1.0';

    /**
     * @return array{
     *     document:AstNode,
     *     metadata:array<string, mixed>,
     *     manifest:list<array<string, mixed>>,
     *     styles:array<string, mixed>,
     *     listStyles:array<string, mixed>,
     *     media:list<array<string, mixed>>,
     *     importReport:array<string, mixed>
     * }
     */
    public function readPackage(ZipPackage $package): array
    {
        $this->assertOdtMimetype($package);

        $manifest = $this->readManifest($package);
        $styleCatalog = $this->readStyles($package);
        $content = $this->readContent($package, $styleCatalog);
        $contentStats = $this->contentNodeStats($content['blocks']);
        $styleCatalog = $content['styleCatalog'];
        $metadata = $this->readMeta($package);
        $media = $this->mediaReport($package, $manifest);

        $document = new AstNode('document', [
            'source' => 'odt',
            'metadata' => $metadata,
            'title' => (string) ($metadata['title'] ?? ''),
            'manifest' => [
                'mimetype' => self::MIMETYPE,
                'items' => $manifest,
            ],
            'styles' => [
                'count' => count($styleCatalog['styles']),
                'items' => array_values($styleCatalog['styles']),
            ],
            'listStyles' => [
                'count' => count($styleCatalog['listStyles']),
                'items' => array_values($styleCatalog['listStyles']),
            ],
        ], $content['blocks']);

        return [
            'document' => $document,
            'metadata' => $metadata,
            'manifest' => $manifest,
            'styles' => $styleCatalog['styles'],
            'listStyles' => $styleCatalog['listStyles'],
            'media' => $media,
            'importReport' => [
                'mimetype' => self::MIMETYPE,
                'manifest' => [
                    'count' => count($manifest),
                    'items' => $manifest,
                    'missingItems' => array_values(array_filter(
                        $manifest,
                        static fn (array $item): bool => ($item['exists'] ?? false) !== true,
                    )),
                ],
                'metadata' => $metadata,
                'styles' => [
                    'count' => count($styleCatalog['styles']),
                    'items' => array_values($styleCatalog['styles']),
                ],
                'listStyles' => [
                    'count' => count($styleCatalog['listStyles']),
                    'items' => array_values($styleCatalog['listStyles']),
                ],
                'media' => [
                    'count' => count($media),
                    'items' => $media,
                ],
                'content' => [
                    'blockCount' => count($content['blocks']),
                    'automaticStyleCount' => $content['automaticStyleCount'],
                    'noteCount' => $contentStats['noteCount'],
                    'bookmarkCount' => $contentStats['bookmarkCount'],
                    'bookmarkReferenceCount' => $contentStats['bookmarkReferenceCount'],
                ],
            ],
        ];
    }

    public function readDocument(ZipPackage $package): AstNode
    {
        return $this->readPackage($package)['document'];
    }

    private function assertOdtMimetype(ZipPackage $package): void
    {
        if (!$package->has('mimetype')) {
            throw new \RuntimeException('ODT package is missing the root mimetype entry');
        }

        $entries = $package->entries();
        if ($entries === [] || $entries[0]->name !== 'mimetype') {
            throw new \RuntimeException('ODT mimetype entry must be the first ZIP entry');
        }

        $mimetype = $package->entry('mimetype');
        if ($mimetype->compressionMethod !== 0) {
            throw new \RuntimeException('ODT mimetype entry must be stored without compression');
        }

        if ($package->read('mimetype') !== self::MIMETYPE) {
            throw new \RuntimeException('ODT mimetype entry must be application/vnd.oasis.opendocument.text');
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function readManifest(ZipPackage $package): array
    {
        if (!$package->has('META-INF/manifest.xml')) {
            throw new \RuntimeException('ODT package is missing META-INF/manifest.xml');
        }

        $dom = self::loadXml($package->read('META-INF/manifest.xml'), 'ODT manifest XML');
        $root = $dom->documentElement;
        if (!$root instanceof \DOMElement || $root->localName !== 'manifest' || $root->namespaceURI !== self::MANIFEST_NS) {
            throw new \InvalidArgumentException('ODT manifest XML must use manifest:manifest as its root element');
        }

        $items = [];
        foreach (self::childElements($root, 'file-entry', self::MANIFEST_NS) as $entryElement) {
            $fullPath = self::attr($entryElement, self::MANIFEST_NS, 'full-path');
            if ($fullPath === '') {
                throw new \RuntimeException('ODT manifest file-entry is missing manifest:full-path');
            }

            $mediaType = self::attr($entryElement, self::MANIFEST_NS, 'media-type');
            $version = self::attr($entryElement, self::MANIFEST_NS, 'version');
            $part = $fullPath === '/' ? null : $this->manifestPackagePart($fullPath);
            $exists = $part === null ? true : $package->has($part);
            $zipEntry = $exists && $part !== null ? $package->entry($part) : null;

            $items[] = [
                'fullPath' => $fullPath,
                'part' => $part,
                'mediaType' => $mediaType,
                'version' => $version === '' ? null : $version,
                'exists' => $exists,
                'byteLength' => $zipEntry instanceof ZipPackageEntry ? $zipEntry->uncompressedSize : null,
                'crc32' => $zipEntry instanceof ZipPackageEntry ? $zipEntry->crc32Hex() : null,
            ];
        }

        if ($items === []) {
            throw new \RuntimeException('ODT manifest does not contain file entries');
        }

        return $items;
    }

    /**
     * @return array{styles:array<string, array<string, mixed>>, listStyles:array<string, array<string, mixed>>}
     */
    private function readStyles(ZipPackage $package): array
    {
        $catalog = [
            'styles' => [],
            'listStyles' => [],
        ];

        if (!$package->has('styles.xml')) {
            return $catalog;
        }

        $dom = self::loadXml($package->read('styles.xml'), 'ODT styles XML');
        $root = $dom->documentElement;
        if (!$root instanceof \DOMElement || $root->localName !== 'document-styles' || $root->namespaceURI !== self::OFFICE_NS) {
            throw new \InvalidArgumentException('ODT styles.xml must use office:document-styles as its root element');
        }

        $this->mergeStyleCollections($catalog, $this->styleCollectionsFromRoot($root));

        return $catalog;
    }

    /**
     * @param array{styles:array<string, array<string, mixed>>, listStyles:array<string, array<string, mixed>>} $styleCatalog
     * @return array{blocks:list<AstNode>, styleCatalog:array{styles:array<string, array<string, mixed>>, listStyles:array<string, array<string, mixed>>}, automaticStyleCount:int}
     */
    private function readContent(ZipPackage $package, array $styleCatalog): array
    {
        if (!$package->has('content.xml')) {
            throw new \RuntimeException('ODT package is missing content.xml');
        }

        $dom = self::loadXml($package->read('content.xml'), 'ODT content XML');
        $root = $dom->documentElement;
        if (!$root instanceof \DOMElement || $root->localName !== 'document-content' || $root->namespaceURI !== self::OFFICE_NS) {
            throw new \InvalidArgumentException('ODT content.xml must use office:document-content as its root element');
        }

        $contentStyles = $this->styleCollectionsFromRoot($root);
        $this->mergeStyleCollections($styleCatalog, $contentStyles);
        $body = self::firstChildElement($root, 'body', self::OFFICE_NS);
        $text = $body instanceof \DOMElement ? self::firstChildElement($body, 'text', self::OFFICE_NS) : null;
        if (!$text instanceof \DOMElement) {
            throw new \RuntimeException('ODT content.xml is missing office:body/office:text');
        }

        return [
            'blocks' => $this->blockNodes($text, $package, $styleCatalog),
            'styleCatalog' => $styleCatalog,
            'automaticStyleCount' => count($contentStyles['styles']) + count($contentStyles['listStyles']),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function readMeta(ZipPackage $package): array
    {
        if (!$package->has('meta.xml')) {
            return [];
        }

        $dom = self::loadXml($package->read('meta.xml'), 'ODT meta XML');
        $root = $dom->documentElement;
        if (!$root instanceof \DOMElement || $root->localName !== 'document-meta' || $root->namespaceURI !== self::OFFICE_NS) {
            throw new \InvalidArgumentException('ODT meta.xml must use office:document-meta as its root element');
        }

        $metaElement = self::firstChildElement($root, 'meta', self::OFFICE_NS);
        if (!$metaElement instanceof \DOMElement) {
            throw new \RuntimeException('ODT meta.xml is missing office:meta');
        }

        $metadata = [
            'keywords' => [],
            'userDefined' => [],
        ];
        foreach (self::childElements($metaElement) as $child) {
            if ($child->namespaceURI === self::DC_NS) {
                $name = $child->localName;
                $text = self::normalizedText($child);
                if (in_array($name, ['title', 'creator', 'description', 'language', 'date', 'subject'], true)) {
                    $metadata[$name] = $text;
                } else {
                    $metadata['dc:' . $name] = $text;
                }
                continue;
            }

            if ($child->namespaceURI !== self::META_NS) {
                continue;
            }

            if ($child->localName === 'keyword') {
                $metadata['keywords'][] = self::normalizedText($child);
                continue;
            }
            if ($child->localName === 'initial-creator') {
                $metadata['initialCreator'] = self::normalizedText($child);
                continue;
            }
            if ($child->localName === 'creation-date') {
                $metadata['created'] = self::normalizedText($child);
                continue;
            }
            if ($child->localName === 'editing-cycles') {
                $metadata['editingCycles'] = self::normalizedText($child);
                continue;
            }
            if ($child->localName === 'document-statistic') {
                $metadata['statistics'] = $this->documentStatistics($child);
                continue;
            }
            if ($child->localName === 'user-defined') {
                $name = self::attr($child, self::META_NS, 'name');
                if ($name !== '') {
                    $metadata['userDefined'][$name] = self::normalizedText($child);
                }
            }
        }

        if ($metadata['keywords'] === []) {
            unset($metadata['keywords']);
        }
        if ($metadata['userDefined'] === []) {
            unset($metadata['userDefined']);
        }

        return $metadata;
    }

    /**
     * @return array<string, int>
     */
    private function documentStatistics(\DOMElement $element): array
    {
        $statistics = [];
        foreach (['page-count', 'table-count', 'image-count', 'object-count', 'paragraph-count', 'word-count', 'character-count'] as $name) {
            $value = self::attr($element, self::META_NS, $name);
            if ($value !== '' && ctype_digit($value)) {
                $statistics[self::camelCase($name)] = (int) $value;
            }
        }

        return $statistics;
    }

    /**
     * @param array{styles:array<string, array<string, mixed>>, listStyles:array<string, array<string, mixed>>} $catalog
     * @return list<AstNode>
     */
    private function blockNodes(\DOMElement $parent, ZipPackage $package, array $catalog): array
    {
        $blocks = [];
        foreach ($parent->childNodes as $child) {
            if (!$child instanceof \DOMElement) {
                continue;
            }

            if ($this->isElement($child, self::TEXT_NS, 'h')) {
                $blocks[] = $this->headingNode($child, $catalog, $package);
                continue;
            }
            if ($this->isElement($child, self::TEXT_NS, 'p')) {
                $paragraph = $this->paragraphNode($child, $catalog, $package);
                if ($paragraph !== null) {
                    $blocks[] = $paragraph;
                }
                continue;
            }
            if ($this->isElement($child, self::TEXT_NS, 'list')) {
                $blocks[] = $this->listNode($child, $package, $catalog);
                continue;
            }
            if ($this->isElement($child, self::TEXT_NS, 'section')) {
                $blocks[] = $this->sectionNode($child, $package, $catalog);
                continue;
            }
            if ($this->isElement($child, self::TABLE_NS, 'table')) {
                $blocks[] = $this->tableNode($child, $package, $catalog);
                continue;
            }
            if ($this->isElement($child, self::DRAW_NS, 'frame')) {
                $block = $this->frameBlockNode($child, $package, $catalog);
                if ($block !== null) {
                    $blocks[] = $block;
                }
                continue;
            }
            if ($this->isElement($child, self::OFFICE_NS, 'annotation')) {
                $blocks[] = $this->annotationBlockNode($child, $package, $catalog);
            }
        }

        return $blocks;
    }

    /**
     * @param array{styles:array<string, array<string, mixed>>, listStyles:array<string, array<string, mixed>>} $catalog
     */
    private function headingNode(\DOMElement $heading, array $catalog, ?ZipPackage $package = null): AstNode
    {
        $styleName = self::attr($heading, self::TEXT_NS, 'style-name');
        $style = $this->resolveStyle($styleName, $catalog);
        $level = self::intAttr($heading, self::TEXT_NS, 'outline-level', (int) ($style['headingLevel'] ?? 1));
        $level = max(1, min(6, $level));
        $attrs = [
            'level' => $level,
            'sourceFormat' => 'odt',
        ];
        if ($styleName !== '') {
            $attrs['styleName'] = $styleName;
            $attrs['style'] = $style;
        }

        return new AstNode('heading', $attrs, $this->coalesceTextNodes($this->inlineNodes($heading, $catalog, $package)));
    }

    /**
     * @param array{styles:array<string, array<string, mixed>>, listStyles:array<string, array<string, mixed>>} $catalog
     */
    private function paragraphNode(\DOMElement $paragraph, array $catalog, ?ZipPackage $package = null): ?AstNode
    {
        $styleName = self::attr($paragraph, self::TEXT_NS, 'style-name');
        $style = $this->resolveStyle($styleName, $catalog);
        $inlines = $this->coalesceTextNodes($this->inlineNodes($paragraph, $catalog, $package));
        $text = $this->plainInlineText($inlines);
        if ($inlines === [] && trim($text) === '') {
            return null;
        }

        $attrs = [
            'sourceFormat' => 'odt',
            'text' => $text,
        ];
        if ($styleName !== '') {
            $attrs['styleName'] = $styleName;
            $attrs['style'] = $style;
        }

        $headingLevel = (int) ($style['headingLevel'] ?? 0);
        if ($headingLevel > 0) {
            $attrs['level'] = max(1, min(6, $headingLevel));

            return new AstNode('heading', $attrs, $inlines);
        }

        return new AstNode('paragraph', $attrs, $inlines);
    }

    /**
     * @param array{styles:array<string, array<string, mixed>>, listStyles:array<string, array<string, mixed>>} $catalog
     */
    private function listNode(\DOMElement $list, ZipPackage $package, array $catalog): AstNode
    {
        $styleName = self::attr($list, self::TEXT_NS, 'style-name');
        $level = max(1, self::intAttr($list, self::TEXT_NS, 'continue-list', 1));
        $definition = $this->listDefinition($styleName, $level, $catalog);
        $ordered = $definition['type'] === 'number';
        $attrs = [
            'sourceFormat' => 'odt',
        ];
        if ($styleName !== '') {
            $attrs['styleName'] = $styleName;
        }
        if ($ordered) {
            $attrs['style'] = $this->orderedListStyle((string) ($definition['format'] ?? '1'));
            $attrs['start'] = (int) ($definition['start'] ?? 1);
        } else {
            $attrs['format'] = (string) ($definition['bulletChar'] ?? 'bullet');
        }

        $items = [];
        foreach (self::childElements($list) as $child) {
            if (!$this->isElement($child, self::TEXT_NS, 'list-item') && !$this->isElement($child, self::TEXT_NS, 'list-header')) {
                continue;
            }

            $itemBlocks = $this->blockNodes($child, $package, $catalog);
            $items[] = new AstNode('list_item', [
                'sourceFormat' => 'odt',
            ], $itemBlocks);
        }

        return new AstNode($ordered ? 'ordered_list' : 'bullet_list', $attrs, $items);
    }

    /**
     * @param array{styles:array<string, array<string, mixed>>, listStyles:array<string, array<string, mixed>>} $catalog
     */
    private function sectionNode(\DOMElement $section, ZipPackage $package, array $catalog): AstNode
    {
        $name = self::attr($section, self::TEXT_NS, 'name');
        $attrs = [
            'sourceFormat' => 'odt',
            'classes' => ['odf-section'],
            'attributes' => [],
        ];
        if ($name !== '') {
            $attrs['id'] = self::slug($name);
            $attrs['attributes']['data-odf-section-name'] = $name;
        }

        return new AstNode('div', $attrs, $this->blockNodes($section, $package, $catalog));
    }

    /**
     * @param array{styles:array<string, array<string, mixed>>, listStyles:array<string, array<string, mixed>>} $catalog
     */
    private function tableNode(\DOMElement $table, ZipPackage $package, array $catalog): AstNode
    {
        $columnWidths = $this->tableColumnWidths($table, $catalog);
        $children = [];
        $headerRows = [];
        $bodyRows = [];
        foreach (self::childElements($table) as $child) {
            if ($this->isElement($child, self::TABLE_NS, 'table-header-rows')) {
                foreach (self::childElements($child, 'table-row', self::TABLE_NS) as $row) {
                    array_push($headerRows, ...$this->repeatedRows($row, $package, $catalog));
                }
                continue;
            }
            if ($this->isElement($child, self::TABLE_NS, 'table-row')) {
                array_push($bodyRows, ...$this->repeatedRows($child, $package, $catalog));
            }
        }

        if ($headerRows !== []) {
            $children[] = new AstNode('table_head', [], $headerRows);
        }
        $children[] = new AstNode('table_body', [], $bodyRows);

        $attrs = [
            'sourceFormat' => 'odt',
            'caption' => '',
        ];
        if ($columnWidths !== []) {
            $attrs['widths'] = $columnWidths;
        }

        return new AstNode('table', $attrs, $children);
    }

    /**
     * @param array{styles:array<string, array<string, mixed>>, listStyles:array<string, array<string, mixed>>} $catalog
     * @return list<AstNode>
     */
    private function repeatedRows(\DOMElement $row, ZipPackage $package, array $catalog): array
    {
        $repeat = min(32, max(1, self::intAttr($row, self::TABLE_NS, 'number-rows-repeated', 1)));
        $rows = [];
        for ($index = 0; $index < $repeat; $index++) {
            $rows[] = $this->tableRowNode($row, $package, $catalog);
        }

        return $rows;
    }

    /**
     * @param array{styles:array<string, array<string, mixed>>, listStyles:array<string, array<string, mixed>>} $catalog
     */
    private function tableRowNode(\DOMElement $row, ZipPackage $package, array $catalog): AstNode
    {
        $cells = [];
        foreach (self::childElements($row) as $cellElement) {
            if ($this->isElement($cellElement, self::TABLE_NS, 'covered-table-cell')) {
                continue;
            }
            if (!$this->isElement($cellElement, self::TABLE_NS, 'table-cell')) {
                continue;
            }

            $repeat = min(32, max(1, self::intAttr($cellElement, self::TABLE_NS, 'number-columns-repeated', 1)));
            for ($index = 0; $index < $repeat; $index++) {
                $cells[] = $this->tableCellNode($cellElement, $package, $catalog);
            }
        }

        return new AstNode('table_row', [], $cells);
    }

    /**
     * @param array{styles:array<string, array<string, mixed>>, listStyles:array<string, array<string, mixed>>} $catalog
     */
    private function tableCellNode(\DOMElement $cell, ZipPackage $package, array $catalog): AstNode
    {
        $blocks = $this->blockNodes($cell, $package, $catalog);
        $attrs = [
            'sourceFormat' => 'odt',
            'text' => $this->plainBlockText($blocks),
        ];
        $colspan = self::intAttr($cell, self::TABLE_NS, 'number-columns-spanned', 1);
        $rowspan = self::intAttr($cell, self::TABLE_NS, 'number-rows-spanned', 1);
        $styleName = self::attr($cell, self::TABLE_NS, 'style-name');
        if ($colspan > 1) {
            $attrs['colspan'] = $colspan;
        }
        if ($rowspan > 1) {
            $attrs['rowspan'] = $rowspan;
        }
        if ($styleName !== '') {
            $attrs['styleName'] = $styleName;
        }

        return new AstNode('table_cell', $attrs, $blocks);
    }

    /**
     * @param array{styles:array<string, array<string, mixed>>, listStyles:array<string, array<string, mixed>>} $catalog
     * @return list<float>
     */
    private function tableColumnWidths(\DOMElement $table, array $catalog): array
    {
        $widths = [];
        foreach (self::childElements($table, 'table-column', self::TABLE_NS) as $column) {
            $repeat = min(32, max(1, self::intAttr($column, self::TABLE_NS, 'number-columns-repeated', 1)));
            $style = $this->resolveStyle(self::attr($column, self::TABLE_NS, 'style-name'), $catalog);
            $width = $this->lengthToPoints((string) ($style['tableColumnProperties']['columnWidth'] ?? ''));
            for ($index = 0; $index < $repeat; $index++) {
                $widths[] = $width;
            }
        }

        $positive = array_values(array_filter($widths, static fn (?float $width): bool => $width !== null && $width > 0.0));
        if ($positive === []) {
            return [];
        }

        $total = array_sum($positive);
        if ($total <= 0.0) {
            return [];
        }

        return array_map(static fn (float $width): float => $width / $total, $positive);
    }

    /**
     * @param array{styles:array<string, array<string, mixed>>, listStyles:array<string, array<string, mixed>>} $catalog
     */
    private function frameBlockNode(\DOMElement $frame, ZipPackage $package, array $catalog): ?AstNode
    {
        $textBox = self::firstChildElement($frame, 'text-box', self::DRAW_NS);
        if ($textBox instanceof \DOMElement) {
            $name = self::attr($frame, self::DRAW_NS, 'name');
            $attrs = [
                'sourceFormat' => 'odt',
                'classes' => ['odf-text-box'],
                'attributes' => [],
            ];
            if ($name !== '') {
                $attrs['attributes']['data-odf-frame-name'] = $name;
            }

            return new AstNode('div', $attrs, $this->blockNodes($textBox, $package, $catalog));
        }

        $image = $this->frameImageNode($frame, $package);
        if (!$image instanceof AstNode) {
            return null;
        }

        return new AstNode('figure', [
            'sourceFormat' => 'odt',
            'caption' => (string) $image->attr('alt', ''),
        ], [$image]);
    }

    /**
     * @param array{styles:array<string, array<string, mixed>>, listStyles:array<string, array<string, mixed>>} $catalog
     */
    private function annotationBlockNode(\DOMElement $annotation, ZipPackage $package, array $catalog): AstNode
    {
        $note = $this->annotationNoteNode($annotation, $package, $catalog);

        return new AstNode('blockquote', [
            'sourceFormat' => 'odt',
            'classes' => ['odf-annotation'],
        ], $note->children);
    }

    /**
     * @param array{styles:array<string, array<string, mixed>>, listStyles:array<string, array<string, mixed>>} $catalog
     * @return list<AstNode>
     */
    private function inlineNodes(\DOMElement $parent, array $catalog, ?ZipPackage $package = null): array
    {
        $nodes = [];
        foreach ($parent->childNodes as $child) {
            if ($child instanceof \DOMText || $child instanceof \DOMCdataSection) {
                if ($child->textContent !== '') {
                    $nodes[] = new AstNode('text', ['text' => $child->textContent]);
                }
                continue;
            }
            if (!$child instanceof \DOMElement) {
                continue;
            }

            if ($this->isElement($child, self::TEXT_NS, 'span')) {
                array_push($nodes, ...$this->spanNodes($child, $catalog, $package));
                continue;
            }
            if ($this->isElement($child, self::TEXT_NS, 'a')) {
                $attrs = ['url' => self::attr($child, self::XLINK_NS, 'href')];
                $title = self::attr($child, self::OFFICE_NS, 'title');
                if ($title !== '') {
                    $attrs['title'] = $title;
                }
                $nodes[] = new AstNode('link', $attrs, $this->coalesceTextNodes($this->inlineNodes($child, $catalog, $package)));
                continue;
            }
            if ($this->isElement($child, self::TEXT_NS, 's')) {
                $count = max(1, self::intAttr($child, self::TEXT_NS, 'c', 1));
                $nodes[] = new AstNode('text', ['text' => str_repeat(' ', $count)]);
                continue;
            }
            if ($this->isElement($child, self::TEXT_NS, 'tab')) {
                $nodes[] = new AstNode('text', ['text' => "\t"]);
                continue;
            }
            if ($this->isElement($child, self::TEXT_NS, 'line-break')) {
                $nodes[] = new AstNode('linebreak');
                continue;
            }
            if ($this->isElement($child, self::DRAW_NS, 'frame')) {
                $image = $this->frameImageNode($child, $package);
                if ($image instanceof AstNode) {
                    $nodes[] = $image;
                }
                continue;
            }
            if ($this->isElement($child, self::TEXT_NS, 'note')) {
                $nodes[] = $this->noteNode($child, $package, $catalog);
                continue;
            }
            if ($this->isElement($child, self::TEXT_NS, 'bookmark-start') || $this->isElement($child, self::TEXT_NS, 'bookmark')) {
                $bookmark = $this->bookmarkAnchorNode($child);
                if ($bookmark instanceof AstNode) {
                    $nodes[] = $bookmark;
                }
                continue;
            }
            if ($this->isElement($child, self::TEXT_NS, 'bookmark-ref')) {
                $bookmarkRef = $this->bookmarkReferenceNode($child, $catalog, $package);
                if ($bookmarkRef instanceof AstNode) {
                    $nodes[] = $bookmarkRef;
                }
                continue;
            }
            if ($this->isElement($child, self::OFFICE_NS, 'annotation')) {
                $nodes[] = $this->annotationNoteNode($child, null, $catalog);
            }
        }

        return $nodes;
    }

    /**
     * @param array{styles:array<string, array<string, mixed>>, listStyles:array<string, array<string, mixed>>} $catalog
     * @return list<AstNode>
     */
    private function spanNodes(\DOMElement $span, array $catalog, ?ZipPackage $package = null): array
    {
        $styleName = self::attr($span, self::TEXT_NS, 'style-name');
        $style = $this->resolveStyle($styleName, $catalog);
        $children = $this->coalesceTextNodes($this->inlineNodes($span, $catalog, $package));
        if ($children === []) {
            return [];
        }

        if ($styleName !== '') {
            $children = [new AstNode('span', [
                'sourceFormat' => 'odt',
                'styleName' => $styleName,
                'attributes' => ['data-odf-style-name' => $styleName],
            ], $children)];
        }

        $properties = $style['textProperties'] ?? [];
        if (!is_array($properties)) {
            return $children;
        }

        if (($properties['bold'] ?? false) === true) {
            $children = [new AstNode('strong', [], $children)];
        }
        if (($properties['italic'] ?? false) === true) {
            $children = [new AstNode('emph', [], $children)];
        }
        if (($properties['underline'] ?? false) === true) {
            $children = [new AstNode('underline', [], $children)];
        }
        if (($properties['strikeout'] ?? false) === true) {
            $children = [new AstNode('strikeout', [], $children)];
        }
        if (($properties['smallCaps'] ?? false) === true) {
            $children = [new AstNode('small_caps', [], $children)];
        }

        return $children;
    }

    /**
     * @param array{styles:array<string, array<string, mixed>>, listStyles:array<string, array<string, mixed>>} $catalog
     */
    private function noteNode(\DOMElement $note, ?ZipPackage $package, array $catalog): AstNode
    {
        $noteClass = self::attr($note, self::TEXT_NS, 'note-class');
        $noteClass = $noteClass === '' ? 'footnote' : $noteClass;
        $citation = self::firstChildElement($note, 'note-citation', self::TEXT_NS);
        $body = self::firstChildElement($note, 'note-body', self::TEXT_NS);
        $blocks = [];
        if ($body instanceof \DOMElement) {
            $blocks = $package instanceof ZipPackage
                ? $this->blockNodes($body, $package, $catalog)
                : $this->noteFallbackBlocks($body, $catalog);
        }

        $attrs = [
            'sourceFormat' => 'odt',
            'noteClass' => $noteClass,
        ];
        $id = self::attr($note, self::TEXT_NS, 'id');
        if ($id !== '') {
            $attrs['id'] = $id;
        }
        if ($citation instanceof \DOMElement) {
            $attrs['citation'] = self::normalizedText($citation);
        }

        return new AstNode('note', $attrs, $blocks);
    }

    /**
     * @param array{styles:array<string, array<string, mixed>>, listStyles:array<string, array<string, mixed>>} $catalog
     * @return list<AstNode>
     */
    private function noteFallbackBlocks(\DOMElement $body, array $catalog): array
    {
        $blocks = [];
        foreach (self::childElements($body, 'p', self::TEXT_NS) as $paragraph) {
            $node = $this->paragraphNode($paragraph, $catalog);
            if ($node instanceof AstNode) {
                $blocks[] = $node;
            }
        }

        return $blocks;
    }

    private function bookmarkAnchorNode(\DOMElement $bookmark): ?AstNode
    {
        $name = self::attr($bookmark, self::TEXT_NS, 'name');
        if ($name === '') {
            return null;
        }

        return new AstNode('span', [
            'sourceFormat' => 'odt',
            'id' => self::bookmarkId($name),
            'classes' => ['anchor', 'odf-bookmark'],
            'attributes' => [
                'data-odf-bookmark-name' => $name,
            ],
        ]);
    }

    /**
     * @param array{styles:array<string, array<string, mixed>>, listStyles:array<string, array<string, mixed>>} $catalog
     */
    private function bookmarkReferenceNode(\DOMElement $reference, array $catalog, ?ZipPackage $package): ?AstNode
    {
        $name = self::attr($reference, self::TEXT_NS, 'ref-name');
        if ($name === '') {
            $name = self::attr($reference, self::TEXT_NS, 'name');
        }
        if ($name === '') {
            return null;
        }

        $children = $this->coalesceTextNodes($this->inlineNodes($reference, $catalog, $package));
        if ($children === []) {
            $children = [new AstNode('text', ['text' => $name])];
        }

        $attrs = [
            'sourceFormat' => 'odt',
            'url' => '#' . self::bookmarkId($name),
            'classes' => ['odf-bookmark-ref'],
            'attributes' => [
                'data-odf-ref-name' => $name,
            ],
        ];
        $format = self::attr($reference, self::TEXT_NS, 'reference-format');
        if ($format !== '') {
            $attrs['attributes']['data-odf-reference-format'] = $format;
        }

        return new AstNode('link', $attrs, $children);
    }

    /**
     * @param array{styles:array<string, array<string, mixed>>, listStyles:array<string, array<string, mixed>>} $catalog
     */
    private function annotationNoteNode(\DOMElement $annotation, ?ZipPackage $package, array $catalog): AstNode
    {
        $creator = self::firstChildElement($annotation, 'creator', self::DC_NS);
        $date = self::firstChildElement($annotation, 'date', self::DC_NS);
        $blocks = $package instanceof ZipPackage
            ? $this->blockNodes($annotation, $package, $catalog)
            : $this->annotationInlineFallbackBlocks($annotation, $catalog);

        return new AstNode('note', [
            'sourceFormat' => 'odt',
            'author' => $creator instanceof \DOMElement ? self::normalizedText($creator) : '',
            'date' => $date instanceof \DOMElement ? self::normalizedText($date) : '',
        ], $blocks);
    }

    /**
     * @param array{styles:array<string, array<string, mixed>>, listStyles:array<string, array<string, mixed>>} $catalog
     * @return list<AstNode>
     */
    private function annotationInlineFallbackBlocks(\DOMElement $annotation, array $catalog): array
    {
        $blocks = [];
        foreach (self::childElements($annotation, 'p', self::TEXT_NS) as $paragraph) {
            $node = $this->paragraphNode($paragraph, $catalog);
            if ($node instanceof AstNode) {
                $blocks[] = $node;
            }
        }

        return $blocks;
    }

    private function frameImageNode(\DOMElement $frame, ?ZipPackage $package): ?AstNode
    {
        $image = self::firstChildElement($frame, 'image', self::DRAW_NS);
        if (!$image instanceof \DOMElement) {
            return null;
        }

        $href = self::attr($image, self::XLINK_NS, 'href');
        if ($href === '') {
            return null;
        }

        $title = self::firstChildElement($image, 'title', self::SVG_NS)
            ?? self::firstChildElement($frame, 'title', self::SVG_NS);
        $desc = self::firstChildElement($image, 'desc', self::SVG_NS)
            ?? self::firstChildElement($frame, 'desc', self::SVG_NS);
        $name = self::attr($frame, self::DRAW_NS, 'name');
        $alt = $desc instanceof \DOMElement ? self::normalizedText($desc) : ($title instanceof \DOMElement ? self::normalizedText($title) : $name);
        $part = $this->manifestPackagePart($href);
        $attrs = [
            'url' => $href,
            'alt' => $alt,
            'sourceFormat' => 'odt',
            'sourcePart' => $part,
        ];
        if ($title instanceof \DOMElement) {
            $attrs['title'] = self::normalizedText($title);
        }
        if ($package instanceof ZipPackage && $package->has($part)) {
            $attrs['bytes'] = strlen($package->read($part));
        }

        return new AstNode('image', $attrs, $alt === '' ? [] : [new AstNode('text', ['text' => $alt])]);
    }

    /**
     * @return array{styles:array<string, array<string, mixed>>, listStyles:array<string, array<string, mixed>>}
     */
    private function styleCollectionsFromRoot(\DOMElement $root): array
    {
        $styles = [];
        foreach ($root->getElementsByTagNameNS(self::STYLE_NS, 'style') as $style) {
            if (!$style instanceof \DOMElement) {
                continue;
            }
            $name = self::attr($style, self::STYLE_NS, 'name');
            if ($name === '') {
                continue;
            }
            $styles[$name] = $this->styleDefinition($style);
        }

        $listStyles = [];
        foreach ($root->getElementsByTagNameNS(self::TEXT_NS, 'list-style') as $listStyle) {
            if (!$listStyle instanceof \DOMElement) {
                continue;
            }
            $name = self::attr($listStyle, self::STYLE_NS, 'name');
            if ($name === '') {
                continue;
            }
            $listStyles[$name] = $this->listStyleDefinition($listStyle);
        }

        return [
            'styles' => $styles,
            'listStyles' => $listStyles,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function styleDefinition(\DOMElement $style): array
    {
        $definition = [
            'name' => self::attr($style, self::STYLE_NS, 'name'),
            'family' => self::attr($style, self::STYLE_NS, 'family'),
            'displayName' => self::nullable(self::attr($style, self::STYLE_NS, 'display-name')),
            'parentName' => self::nullable(self::attr($style, self::STYLE_NS, 'parent-style-name')),
            'listStyleName' => self::nullable(self::attr($style, self::STYLE_NS, 'list-style-name')),
            'headingLevel' => self::nullableInt(self::attr($style, self::STYLE_NS, 'default-outline-level')),
            'textProperties' => [],
            'paragraphProperties' => [],
            'tableColumnProperties' => [],
        ];

        $textProperties = self::firstChildElement($style, 'text-properties', self::STYLE_NS);
        if ($textProperties instanceof \DOMElement) {
            $definition['textProperties'] = $this->textProperties($textProperties);
        }

        $paragraphProperties = self::firstChildElement($style, 'paragraph-properties', self::STYLE_NS);
        if ($paragraphProperties instanceof \DOMElement) {
            $definition['paragraphProperties'] = [
                'textAlign' => self::nullable(self::attr($paragraphProperties, self::FO_NS, 'text-align')),
            ];
        }

        $columnProperties = self::firstChildElement($style, 'table-column-properties', self::STYLE_NS);
        if ($columnProperties instanceof \DOMElement) {
            $definition['tableColumnProperties'] = [
                'columnWidth' => self::nullable(self::attr($columnProperties, self::STYLE_NS, 'column-width')),
            ];
        }

        return $definition;
    }

    /**
     * @return array<string, bool|string|null>
     */
    private function textProperties(\DOMElement $properties): array
    {
        $fontWeight = strtolower(self::attr($properties, self::FO_NS, 'font-weight'));
        $fontStyle = strtolower(self::attr($properties, self::FO_NS, 'font-style'));
        $underline = strtolower(self::attr($properties, self::STYLE_NS, 'text-underline-style'));
        $strikeout = strtolower(self::attr($properties, self::STYLE_NS, 'text-line-through-style'));
        $variant = strtolower(self::attr($properties, self::FO_NS, 'font-variant'));

        $result = [];
        if ($fontWeight === 'bold' || $fontWeight === '700') {
            $result['bold'] = true;
        }
        if ($fontStyle === 'italic' || $fontStyle === 'oblique') {
            $result['italic'] = true;
        }
        if ($underline !== '' && $underline !== 'none') {
            $result['underline'] = true;
        }
        if ($strikeout !== '' && $strikeout !== 'none') {
            $result['strikeout'] = true;
        }
        if ($variant === 'small-caps') {
            $result['smallCaps'] = true;
        }

        return $result;
    }

    /**
     * @return array{name:string, levels:array<int, array<string, mixed>>}
     */
    private function listStyleDefinition(\DOMElement $listStyle): array
    {
        $levels = [];
        foreach (self::childElements($listStyle) as $levelStyle) {
            if (!$this->isElement($levelStyle, self::TEXT_NS, 'list-level-style-bullet')
                && !$this->isElement($levelStyle, self::TEXT_NS, 'list-level-style-number')
            ) {
                continue;
            }

            $level = max(1, self::intAttr($levelStyle, self::TEXT_NS, 'level', 1));
            $levels[$level] = [
                'type' => $levelStyle->localName === 'list-level-style-number' ? 'number' : 'bullet',
                'level' => $level,
                'format' => self::attr($levelStyle, self::STYLE_NS, 'num-format'),
                'bulletChar' => self::attr($levelStyle, self::TEXT_NS, 'bullet-char'),
                'start' => self::intAttr($levelStyle, self::TEXT_NS, 'start-value', 1),
            ];
        }

        return [
            'name' => self::attr($listStyle, self::STYLE_NS, 'name'),
            'levels' => $levels,
        ];
    }

    /**
     * @param array{styles:array<string, array<string, mixed>>, listStyles:array<string, array<string, mixed>>} $target
     * @param array{styles:array<string, array<string, mixed>>, listStyles:array<string, array<string, mixed>>} $source
     */
    private function mergeStyleCollections(array &$target, array $source): void
    {
        foreach ($source['styles'] as $name => $style) {
            $target['styles'][$name] = $style;
        }
        foreach ($source['listStyles'] as $name => $style) {
            $target['listStyles'][$name] = $style;
        }
    }

    /**
     * @param array{styles:array<string, array<string, mixed>>, listStyles:array<string, array<string, mixed>>} $catalog
     * @return array<string, mixed>
     */
    private function resolveStyle(string $name, array $catalog, array $seen = []): array
    {
        if ($name === '' || !isset($catalog['styles'][$name]) || isset($seen[$name])) {
            return [];
        }

        $style = $catalog['styles'][$name];
        $seen[$name] = true;
        $parentName = (string) ($style['parentName'] ?? '');
        $parent = $this->resolveStyle($parentName, $catalog, $seen);
        if ($parent === []) {
            return $style;
        }

        return $this->mergeResolvedStyle($parent, $style);
    }

    /**
     * @param array<string, mixed> $parent
     * @param array<string, mixed> $style
     * @return array<string, mixed>
     */
    private function mergeResolvedStyle(array $parent, array $style): array
    {
        $merged = $parent;
        foreach ($style as $key => $value) {
            if (is_array($value) && isset($merged[$key]) && is_array($merged[$key])) {
                $merged[$key] = array_merge($merged[$key], array_filter(
                    $value,
                    static fn (mixed $entry): bool => $entry !== null && $entry !== ''
                ));
                continue;
            }
            if ($value !== null && $value !== '') {
                $merged[$key] = $value;
            }
        }

        return $merged;
    }

    /**
     * @param array{styles:array<string, array<string, mixed>>, listStyles:array<string, array<string, mixed>>} $catalog
     * @return array<string, mixed>
     */
    private function listDefinition(string $styleName, int $level, array $catalog): array
    {
        $listStyle = $catalog['listStyles'][$styleName] ?? null;
        if (!is_array($listStyle)) {
            return [
                'type' => 'bullet',
                'level' => $level,
                'bulletChar' => 'bullet',
            ];
        }

        $levels = $listStyle['levels'] ?? [];
        if (!is_array($levels)) {
            return [
                'type' => 'bullet',
                'level' => $level,
                'bulletChar' => 'bullet',
            ];
        }

        $definition = $levels[$level] ?? reset($levels);

        return is_array($definition) ? $definition : [
            'type' => 'bullet',
            'level' => $level,
            'bulletChar' => 'bullet',
        ];
    }

    private function orderedListStyle(string $format): string
    {
        return match ($format) {
            'a' => 'lower_alpha',
            'A' => 'upper_alpha',
            'i' => 'lower_roman',
            'I' => 'upper_roman',
            default => 'decimal',
        };
    }

    /**
     * @param list<array<string, mixed>> $manifest
     * @return list<array<string, mixed>>
     */
    private function mediaReport(ZipPackage $package, array $manifest): array
    {
        $media = [];
        foreach ($manifest as $item) {
            $part = $item['part'] ?? null;
            $mediaType = (string) ($item['mediaType'] ?? '');
            if (!is_string($part) || $part === '') {
                continue;
            }
            if (in_array($part, ['content.xml', 'styles.xml', 'meta.xml', 'settings.xml'], true)) {
                continue;
            }
            if ($mediaType === '' || str_contains($mediaType, 'xml')) {
                continue;
            }

            $entry = $package->has($part) ? $package->entry($part) : null;
            $media[] = [
                'fullPath' => $item['fullPath'],
                'part' => $part,
                'mediaType' => $mediaType,
                'exists' => $entry instanceof ZipPackageEntry,
                'byteLength' => $entry instanceof ZipPackageEntry ? $entry->uncompressedSize : null,
                'crc32' => $entry instanceof ZipPackageEntry ? $entry->crc32Hex() : null,
            ];
        }

        return $media;
    }

    /**
     * @param list<AstNode> $nodes
     * @return array{noteCount:int, bookmarkCount:int, bookmarkReferenceCount:int}
     */
    private function contentNodeStats(array $nodes): array
    {
        $stats = [
            'noteCount' => 0,
            'bookmarkCount' => 0,
            'bookmarkReferenceCount' => 0,
        ];
        foreach ($nodes as $node) {
            if ($node->type === 'note') {
                $stats['noteCount']++;
            }
            if ($node->type === 'span' && $this->nodeHasClass($node, 'odf-bookmark')) {
                $stats['bookmarkCount']++;
            }
            if ($node->type === 'link' && $this->nodeHasClass($node, 'odf-bookmark-ref')) {
                $stats['bookmarkReferenceCount']++;
            }

            $childStats = $this->contentNodeStats($node->children);
            foreach ($childStats as $name => $count) {
                $stats[$name] += $count;
            }
        }

        return $stats;
    }

    private function nodeHasClass(AstNode $node, string $class): bool
    {
        $classes = $node->attr('classes', []);
        if (!is_array($classes)) {
            return false;
        }

        return in_array($class, array_map(static fn (mixed $value): string => (string) $value, $classes), true);
    }

    private function manifestPackagePart(string $path): string
    {
        $path = preg_replace('/[#?].*$/', '', $path) ?? $path;
        $path = ltrim($path, '/');
        if ($path === '') {
            throw new \RuntimeException('ODT package part path must not be empty');
        }
        if (str_contains($path, '..') || str_starts_with($path, '\\') || preg_match('/^[A-Za-z][A-Za-z0-9+.-]*:/', $path) === 1) {
            throw new \InvalidArgumentException('ODT package part path is not a safe package-relative path: ' . $path);
        }

        return $path;
    }

    /**
     * @return list<AstNode>
     */
    private function coalesceTextNodes(array $nodes): array
    {
        $coalesced = [];
        foreach ($nodes as $node) {
            if ($node->type === 'text' && $coalesced !== []) {
                $last = $coalesced[count($coalesced) - 1];
                if ($last->type === 'text') {
                    $attrs = $last->attrs;
                    $attrs['text'] = (string) ($attrs['text'] ?? '') . (string) $node->attr('text', '');
                    $coalesced[count($coalesced) - 1] = new AstNode('text', $attrs, $last->children);
                    continue;
                }
            }

            $coalesced[] = $node;
        }

        return $coalesced;
    }

    /**
     * @param list<AstNode> $nodes
     */
    private function plainInlineText(array $nodes): string
    {
        $text = '';
        foreach ($nodes as $node) {
            if ($node->type === 'text') {
                $text .= (string) $node->attr('text', '');
                continue;
            }
            if ($node->type === 'linebreak') {
                $text .= "\n";
                continue;
            }
            if ($node->type === 'image') {
                $text .= (string) $node->attr('alt', '');
                continue;
            }
            if ($node->type === 'note') {
                continue;
            }
            $text .= $this->plainInlineText($node->children);
        }

        return $text;
    }

    /**
     * @param list<AstNode> $blocks
     */
    private function plainBlockText(array $blocks): string
    {
        $parts = [];
        foreach ($blocks as $block) {
            if ($block->children !== []) {
                $parts[] = $this->plainInlineText($block->children);
            } else {
                $parts[] = (string) $block->attr('text', '');
            }
        }

        return trim(implode(' ', array_filter($parts, static fn (string $part): bool => $part !== '')));
    }

    private function lengthToPoints(string $length): ?float
    {
        if ($length === '' || preg_match('/^([0-9]+(?:\.[0-9]+)?)(cm|mm|in|pt)$/', $length, $matches) !== 1) {
            return null;
        }

        $value = (float) $matches[1];

        return match ($matches[2]) {
            'cm' => $value * 28.3464567,
            'mm' => $value * 2.83464567,
            'in' => $value * 72.0,
            default => $value,
        };
    }

    private function isElement(\DOMElement $element, string $namespace, string $localName): bool
    {
        return $element->namespaceURI === $namespace && $element->localName === $localName;
    }

    private static function loadXml(string $xml, string $label): \DOMDocument
    {
        $dom = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        try {
            $loaded = $dom->loadXML($xml, LIBXML_NONET);
            if (!$loaded) {
                $errors = libxml_get_errors();
                $message = $errors === [] ? 'unknown XML parse error' : trim($errors[0]->message);
                throw new \InvalidArgumentException("Unable to parse {$label}: {$message}");
            }
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        return $dom;
    }

    /**
     * @return list<\DOMElement>
     */
    private static function childElements(\DOMElement $element, ?string $localName = null, ?string $namespace = null): array
    {
        $elements = [];
        foreach ($element->childNodes as $child) {
            if (!$child instanceof \DOMElement) {
                continue;
            }
            if ($localName !== null && $child->localName !== $localName) {
                continue;
            }
            if ($namespace !== null && $child->namespaceURI !== $namespace) {
                continue;
            }
            $elements[] = $child;
        }

        return $elements;
    }

    private static function firstChildElement(\DOMElement $element, ?string $localName = null, ?string $namespace = null): ?\DOMElement
    {
        foreach (self::childElements($element, $localName, $namespace) as $child) {
            return $child;
        }

        return null;
    }

    private static function attr(\DOMElement $element, string $namespace, string $name): string
    {
        return trim($element->getAttributeNS($namespace, $name));
    }

    private static function intAttr(\DOMElement $element, string $namespace, string $name, int $default): int
    {
        $value = self::attr($element, $namespace, $name);

        return ctype_digit($value) ? (int) $value : $default;
    }

    private static function nullableInt(string $value): ?int
    {
        return ctype_digit($value) ? (int) $value : null;
    }

    private static function nullable(string $value): ?string
    {
        return $value === '' ? null : $value;
    }

    private static function normalizedText(\DOMElement $element): string
    {
        $text = preg_replace('/\s+/u', ' ', $element->textContent) ?? $element->textContent;

        return trim($text);
    }

    private static function camelCase(string $name): string
    {
        return preg_replace_callback(
            '/-([a-z])/',
            static fn (array $match): string => strtoupper($match[1]),
            $name
        ) ?? $name;
    }

    private static function slug(string $value): string
    {
        $slug = strtolower(preg_replace('/[^A-Za-z0-9]+/', '-', trim($value)) ?? '');
        $slug = trim($slug, '-');

        return $slug === '' ? 'odf-section' : $slug;
    }

    private static function bookmarkId(string $name): string
    {
        $id = strtolower(preg_replace('/[^A-Za-z0-9]+/', '-', trim($name)) ?? '');
        $id = trim($id, '-');

        return $id === '' ? 'odf-bookmark' : $id;
    }
}
