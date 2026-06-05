<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class OdtReader
{
    public const ODT_MIMETYPE = 'application/vnd.oasis.opendocument.text';

    public const OFFICE_NS = 'urn:oasis:names:tc:opendocument:xmlns:office:1.0';
    public const TEXT_NS = 'urn:oasis:names:tc:opendocument:xmlns:text:1.0';
    public const STYLE_NS = 'urn:oasis:names:tc:opendocument:xmlns:style:1.0';
    public const TABLE_NS = 'urn:oasis:names:tc:opendocument:xmlns:table:1.0';
    public const DRAW_NS = 'urn:oasis:names:tc:opendocument:xmlns:drawing:1.0';
    public const XLINK_NS = 'http://www.w3.org/1999/xlink';
    public const SVG_NS = 'urn:oasis:names:tc:opendocument:xmlns:svg-compatible:1.0';
    public const MANIFEST_NS = 'urn:oasis:names:tc:opendocument:xmlns:manifest:1.0';
    public const META_NS = 'urn:oasis:names:tc:opendocument:xmlns:meta:1.0';
    public const DC_NS = 'http://purl.org/dc/elements/1.1/';
    public const FO_NS = 'urn:oasis:names:tc:opendocument:xmlns:xsl-fo-compatible:1.0';

    /**
     * @return array{document:AstNode, metadata:array<string, string>, importReport:array<string, mixed>, manifest:list<array{path:string, mediaType:string, encrypted:bool, size:?int}>}
     */
    public function readPackage(ZipPackage $package): array
    {
        if (!$package->has('content.xml')) {
            throw new \RuntimeException('ODT package is missing content.xml');
        }

        $mimetype = $package->has('mimetype') ? trim($package->read('mimetype')) : '';
        if ($mimetype !== '' && $mimetype !== self::ODT_MIMETYPE) {
            throw new \RuntimeException('ODT package mimetype is not application/vnd.oasis.opendocument.text');
        }

        $manifest = $this->readManifest($package);
        $styleCatalog = $this->loadStyleCatalog($package);
        $contentDom = self::loadXml($package->read('content.xml'), 'ODT content.xml');
        $contentRoot = $contentDom->documentElement;
        if (!$contentRoot instanceof \DOMElement || $contentRoot->namespaceURI !== self::OFFICE_NS) {
            throw new \InvalidArgumentException('ODT content.xml must use an office document root');
        }

        $this->mergeAutomaticStyles($styleCatalog, $contentRoot);
        $body = $this->firstChildElement($contentRoot, self::OFFICE_NS, 'body');
        $text = $body instanceof \DOMElement ? $this->firstChildElement($body, self::OFFICE_NS, 'text') : null;
        if (!$text instanceof \DOMElement) {
            throw new \InvalidArgumentException('ODT content.xml is missing office:body/office:text');
        }

        $document = new AstNode('document', [
            'sourceFormat' => 'odt',
            'mimetype' => $mimetype === '' ? self::ODT_MIMETYPE : $mimetype,
        ], $this->bodyBlocks($text, $package, $styleCatalog));
        $metadata = $this->readMetadata($package, $contentRoot);

        return [
            'document' => $document,
            'metadata' => $metadata,
            'importReport' => $this->importReport($package, $manifest, $document, $styleCatalog, $mimetype),
            'manifest' => $manifest,
        ];
    }

    public function readDocument(ZipPackage $package): AstNode
    {
        return $this->readPackage($package)['document'];
    }

    /**
     * @return list<array{path:string, mediaType:string, encrypted:bool, size:?int}>
     */
    private function readManifest(ZipPackage $package): array
    {
        if (!$package->has('META-INF/manifest.xml')) {
            return [];
        }

        $dom = self::loadXml($package->read('META-INF/manifest.xml'), 'ODT manifest.xml');
        $root = $dom->documentElement;
        if (!$root instanceof \DOMElement || !$this->isElement($root, self::MANIFEST_NS, 'manifest')) {
            return [];
        }

        $entries = [];
        foreach ($root->childNodes as $child) {
            if (!$child instanceof \DOMElement || !$this->isElement($child, self::MANIFEST_NS, 'file-entry')) {
                continue;
            }

            $path = $this->manifestAttr($child, 'full-path');
            if ($path === null || $path === '') {
                continue;
            }

            $size = $this->manifestAttr($child, 'size');
            $entries[] = [
                'path' => $path,
                'mediaType' => (string) ($this->manifestAttr($child, 'media-type') ?? ''),
                'encrypted' => $this->firstChildElement($child, self::MANIFEST_NS, 'encryption-data') instanceof \DOMElement,
                'size' => $size !== null && preg_match('/^\d+$/', $size) === 1 ? (int) $size : null,
            ];
        }

        return $entries;
    }

    /**
     * @return array{paragraphStyles:array<string, array<string, mixed>>, textStyles:array<string, array<string, mixed>>, listStyles:array<string, array<int, array<string, mixed>>>}
     */
    private function loadStyleCatalog(ZipPackage $package): array
    {
        $catalog = [
            'paragraphStyles' => [],
            'textStyles' => [],
            'listStyles' => [],
        ];

        if ($package->has('styles.xml')) {
            $dom = self::loadXml($package->read('styles.xml'), 'ODT styles.xml');
            $root = $dom->documentElement;
            if ($root instanceof \DOMElement) {
                $this->mergeStyleElements($catalog, $root);
            }
        }

        return $catalog;
    }

    /**
     * @param array{paragraphStyles:array<string, array<string, mixed>>, textStyles:array<string, array<string, mixed>>, listStyles:array<string, array<int, array<string, mixed>>>} $catalog
     */
    private function mergeAutomaticStyles(array &$catalog, \DOMElement $contentRoot): void
    {
        foreach ($contentRoot->childNodes as $child) {
            if ($child instanceof \DOMElement && $this->isElement($child, self::OFFICE_NS, 'automatic-styles')) {
                $this->mergeStyleElements($catalog, $child);
            }
        }
    }

    /**
     * @param array{paragraphStyles:array<string, array<string, mixed>>, textStyles:array<string, array<string, mixed>>, listStyles:array<string, array<int, array<string, mixed>>>} $catalog
     */
    private function mergeStyleElements(array &$catalog, \DOMElement $container): void
    {
        foreach ($container->childNodes as $child) {
            if (!$child instanceof \DOMElement) {
                continue;
            }

            if (
                $this->isElement($child, self::OFFICE_NS, 'styles')
                || $this->isElement($child, self::OFFICE_NS, 'automatic-styles')
            ) {
                $this->mergeStyleElements($catalog, $child);
                continue;
            }

            if ($this->isElement($child, self::STYLE_NS, 'style')) {
                $name = $this->styleAttr($child, 'name');
                $family = strtolower((string) ($this->styleAttr($child, 'family') ?? ''));
                if ($name === null || $name === '') {
                    continue;
                }

                $style = $this->styleDefinition($child, $family);
                if ($family === 'paragraph') {
                    $catalog['paragraphStyles'][$name] = $style;
                } elseif ($family === 'text') {
                    $catalog['textStyles'][$name] = $style;
                }
                continue;
            }

            if ($this->isElement($child, self::TEXT_NS, 'list-style')) {
                $name = $this->styleAttr($child, 'name');
                if ($name !== null && $name !== '') {
                    $catalog['listStyles'][$name] = $this->listStyleDefinition($child);
                }
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function styleDefinition(\DOMElement $styleElement, string $family): array
    {
        $definition = [
            'family' => $family,
            'parent' => $this->styleAttr($styleElement, 'parent-style-name'),
        ];

        $textProperties = $this->firstChildElement($styleElement, self::STYLE_NS, 'text-properties');
        if ($textProperties instanceof \DOMElement) {
            $fontWeight = strtolower((string) ($this->foAttr($textProperties, 'font-weight') ?? ''));
            if ($fontWeight === 'bold' || (is_numeric($fontWeight) && (int) $fontWeight >= 600)) {
                $definition['strong'] = true;
            }

            $fontStyle = strtolower((string) ($this->foAttr($textProperties, 'font-style') ?? ''));
            if ($fontStyle === 'italic' || $fontStyle === 'oblique') {
                $definition['emph'] = true;
            }

            $underline = strtolower((string) ($this->styleAttr($textProperties, 'text-underline-style') ?? ''));
            if ($underline !== '' && $underline !== 'none') {
                $definition['underline'] = true;
            }

            $strike = strtolower((string) ($this->styleAttr($textProperties, 'text-line-through-style') ?? ''));
            if ($strike !== '' && $strike !== 'none') {
                $definition['strikeout'] = true;
            }

            $position = strtolower((string) ($this->styleAttr($textProperties, 'text-position') ?? ''));
            if (str_contains($position, 'super')) {
                $definition['superscript'] = true;
            } elseif (str_contains($position, 'sub')) {
                $definition['subscript'] = true;
            }
        }

        $paragraphProperties = $this->firstChildElement($styleElement, self::STYLE_NS, 'paragraph-properties');
        if ($paragraphProperties instanceof \DOMElement) {
            $alignment = strtolower((string) ($this->foAttr($paragraphProperties, 'text-align') ?? ''));
            if (in_array($alignment, ['left', 'right', 'center'], true)) {
                $definition['align'] = $alignment;
            }
        }

        return $definition;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function listStyleDefinition(\DOMElement $listStyle): array
    {
        $levels = [];
        foreach ($listStyle->childNodes as $child) {
            if (!$child instanceof \DOMElement) {
                continue;
            }

            $isNumber = $this->isElement($child, self::TEXT_NS, 'list-level-style-number');
            $isBullet = $this->isElement($child, self::TEXT_NS, 'list-level-style-bullet');
            if (!$isNumber && !$isBullet) {
                continue;
            }

            $level = $this->positiveIntAttr($child, self::TEXT_NS, 'level', 1);
            $definition = [
                'ordered' => $isNumber,
                'level' => $level,
                'style' => $this->orderedListStyle((string) ($this->styleAttr($child, 'num-format') ?? '1')),
                'format' => $isNumber ? (string) ($this->styleAttr($child, 'num-format') ?? '1') : (string) ($this->textAttr($child, 'bullet-char') ?? ''),
                'displayLevels' => $this->positiveIntAttr($child, self::TEXT_NS, 'display-levels', 1),
            ];

            $start = $this->textAttr($child, 'start-value');
            if ($start !== null && preg_match('/^\d+$/', $start) === 1) {
                $definition['start'] = max(1, (int) $start);
            }

            $levels[$level] = $definition;
        }

        return $levels;
    }

    /**
     * @param array{paragraphStyles:array<string, array<string, mixed>>, textStyles:array<string, array<string, mixed>>, listStyles:array<string, array<int, array<string, mixed>>>} $styles
     * @return list<AstNode>
     */
    private function bodyBlocks(\DOMElement $text, ZipPackage $package, array $styles): array
    {
        $blocks = [];
        foreach ($text->childNodes as $child) {
            if ($child instanceof \DOMElement) {
                array_push($blocks, ...$this->blockNodes($child, $package, $styles));
            }
        }

        return $blocks;
    }

    /**
     * @param array{paragraphStyles:array<string, array<string, mixed>>, textStyles:array<string, array<string, mixed>>, listStyles:array<string, array<int, array<string, mixed>>>} $styles
     * @return list<AstNode>
     */
    private function blockNodes(\DOMElement $element, ZipPackage $package, array $styles): array
    {
        if ($this->isElement($element, self::TEXT_NS, 'h')) {
            $level = $this->positiveIntAttr($element, self::TEXT_NS, 'outline-level', 1);
            $children = $this->inlineNodes($element, $package, $styles);

            return [new AstNode('heading', [
                'level' => min(6, $level),
                'text' => $this->plainInlineText($children),
                'id' => $this->slugify($this->plainInlineText($children)),
                'style' => $this->textAttr($element, 'style-name'),
                'sourceFormat' => 'odt',
            ], $children)];
        }

        if ($this->isElement($element, self::TEXT_NS, 'p')) {
            $paragraph = $this->paragraphNode($element, $package, $styles);

            return $paragraph instanceof AstNode ? [$paragraph] : [];
        }

        if ($this->isElement($element, self::TEXT_NS, 'list')) {
            return [$this->listNode($element, $package, $styles)];
        }

        if ($this->isElement($element, self::TABLE_NS, 'table')) {
            return [$this->tableNode($element, $package, $styles)];
        }

        if ($this->isElement($element, self::TEXT_NS, 'section')) {
            $children = [];
            foreach ($element->childNodes as $child) {
                if ($child instanceof \DOMElement) {
                    array_push($children, ...$this->blockNodes($child, $package, $styles));
                }
            }

            return [new AstNode('div', [
                'sourceFormat' => 'odt-section',
                'name' => $this->textAttr($element, 'name'),
            ], $children)];
        }

        if ($this->isElement($element, self::DRAW_NS, 'frame')) {
            $node = $this->frameBlockNode($element, $package, $styles);

            return $node instanceof AstNode ? [$node] : [];
        }

        if ($this->isElement($element, self::OFFICE_NS, 'annotation')) {
            return [new AstNode('paragraph', [], [$this->annotationSpan($element, $package, $styles)])];
        }

        return [];
    }

    /**
     * @param array{paragraphStyles:array<string, array<string, mixed>>, textStyles:array<string, array<string, mixed>>, listStyles:array<string, array<int, array<string, mixed>>>} $styles
     */
    private function paragraphNode(\DOMElement $paragraph, ZipPackage $package, array $styles): ?AstNode
    {
        $children = $this->inlineNodes($paragraph, $package, $styles);
        $text = $this->plainInlineText($children);
        if ($children === [] && $text === '') {
            return null;
        }

        $attrs = ['sourceFormat' => 'odt'];
        $styleName = $this->textAttr($paragraph, 'style-name');
        if ($styleName !== null && $styleName !== '') {
            $attrs['style'] = $styleName;
            $style = $this->resolveStyle($styleName, $styles['paragraphStyles']);
            if (isset($style['align'])) {
                $attrs['htmlAttributes'] = ['style' => 'text-align:' . $style['align']];
            }
        }

        return new AstNode('paragraph', $attrs, $children);
    }

    /**
     * @param array{paragraphStyles:array<string, array<string, mixed>>, textStyles:array<string, array<string, mixed>>, listStyles:array<string, array<int, array<string, mixed>>>} $styles
     * @return list<AstNode>
     */
    private function inlineNodes(\DOMElement $element, ZipPackage $package, array $styles): array
    {
        $nodes = [];
        foreach ($element->childNodes as $child) {
            if ($child instanceof \DOMText || $child instanceof \DOMCdataSection) {
                $value = $child->nodeValue ?? '';
                if ($value !== '' && trim($value) !== '') {
                    $nodes[] = new AstNode('text', ['text' => $value]);
                }
                continue;
            }

            if (!$child instanceof \DOMElement) {
                continue;
            }

            if ($this->isElement($child, self::TEXT_NS, 'span')) {
                $spanNodes = $this->inlineNodes($child, $package, $styles);
                $styleName = $this->textAttr($child, 'style-name');
                array_push($nodes, ...$this->applyTextStyle($spanNodes, $styleName, $styles));
                continue;
            }

            if ($this->isElement($child, self::TEXT_NS, 'a')) {
                $nodes[] = new AstNode('link', [
                    'url' => (string) ($this->xlinkAttr($child, 'href') ?? ''),
                    'title' => (string) ($this->officeAttr($child, 'title') ?? ''),
                    'sourceFormat' => 'odt',
                ], $this->inlineNodes($child, $package, $styles));
                continue;
            }

            if ($this->isElement($child, self::TEXT_NS, 's')) {
                $nodes[] = new AstNode('text', ['text' => str_repeat(' ', $this->positiveIntAttr($child, self::TEXT_NS, 'c', 1))]);
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

            if ($this->isElement($child, self::TEXT_NS, 'note')) {
                $nodes[] = $this->noteNode($child, $package, $styles);
                continue;
            }

            if ($this->isElement($child, self::OFFICE_NS, 'annotation')) {
                $nodes[] = $this->annotationSpan($child, $package, $styles);
                continue;
            }

            if ($this->isElement($child, self::DRAW_NS, 'frame')) {
                $frame = $this->frameInlineNode($child, $package, $styles);
                if ($frame instanceof AstNode) {
                    $nodes[] = $frame;
                }
                continue;
            }

            if ($this->isElement($child, self::TEXT_NS, 'bookmark') || $this->isElement($child, self::TEXT_NS, 'bookmark-start')) {
                $name = $this->textAttr($child, 'name');
                if ($name !== null && $name !== '') {
                    $nodes[] = new AstNode('span', ['id' => $name, 'classes' => ['anchor']]);
                }
                continue;
            }
        }

        return $this->coalesceTextNodes($nodes);
    }

    /**
     * @param list<AstNode> $nodes
     * @param array{paragraphStyles:array<string, array<string, mixed>>, textStyles:array<string, array<string, mixed>>, listStyles:array<string, array<int, array<string, mixed>>>} $styles
     * @return list<AstNode>
     */
    private function applyTextStyle(array $nodes, ?string $styleName, array $styles): array
    {
        if ($nodes === [] || $styleName === null || $styleName === '') {
            return $nodes;
        }

        $style = $this->resolveStyle($styleName, $styles['textStyles']);
        foreach (['strong', 'emph', 'underline', 'strikeout', 'superscript', 'subscript'] as $type) {
            if (($style[$type] ?? false) === true) {
                $nodes = [new AstNode($type, ['style' => $styleName, 'sourceFormat' => 'odt'], $nodes)];
            }
        }

        return $nodes;
    }

    /**
     * @param array<string, array<string, mixed>> $styles
     * @return array<string, mixed>
     */
    private function resolveStyle(string $styleName, array $styles): array
    {
        $resolved = [];
        $seen = [];
        $current = $styleName;
        $stack = [];
        while ($current !== '' && isset($styles[$current]) && !isset($seen[$current])) {
            $seen[$current] = true;
            $stack[] = $styles[$current];
            $parent = $styles[$current]['parent'] ?? null;
            $current = is_string($parent) ? $parent : '';
        }

        while ($style = array_pop($stack)) {
            foreach ($style as $key => $value) {
                if ($key === 'parent' || $key === 'family' || $value === null) {
                    continue;
                }

                $resolved[$key] = $value;
            }
        }

        return $resolved;
    }

    /**
     * @param array{paragraphStyles:array<string, array<string, mixed>>, textStyles:array<string, array<string, mixed>>, listStyles:array<string, array<int, array<string, mixed>>>} $styles
     */
    private function noteNode(\DOMElement $note, ZipPackage $package, array $styles): AstNode
    {
        $citation = '';
        $blocks = [];
        foreach ($note->childNodes as $child) {
            if (!$child instanceof \DOMElement) {
                continue;
            }

            if ($this->isElement($child, self::TEXT_NS, 'note-citation')) {
                $citation = trim($child->textContent);
                continue;
            }

            if ($this->isElement($child, self::TEXT_NS, 'note-body')) {
                foreach ($child->childNodes as $bodyChild) {
                    if ($bodyChild instanceof \DOMElement) {
                        array_push($blocks, ...$this->blockNodes($bodyChild, $package, $styles));
                    }
                }
            }
        }

        return new AstNode('note', [
            'sourceFormat' => 'odt',
            'sourceType' => (string) ($this->textAttr($note, 'note-class') ?? 'footnote'),
            'citation' => $citation,
        ], $blocks);
    }

    /**
     * @param array{paragraphStyles:array<string, array<string, mixed>>, textStyles:array<string, array<string, mixed>>, listStyles:array<string, array<int, array<string, mixed>>>} $styles
     */
    private function annotationSpan(\DOMElement $annotation, ZipPackage $package, array $styles): AstNode
    {
        $children = [];
        $attrs = [
            'classes' => ['odt-annotation'],
            'attributes' => [],
        ];

        foreach ($annotation->childNodes as $child) {
            if (!$child instanceof \DOMElement) {
                continue;
            }

            if ($this->isElement($child, self::DC_NS, 'creator')) {
                $attrs['attributes']['data-odt-annotation-author'] = trim($child->textContent);
                continue;
            }

            if ($this->isElement($child, self::DC_NS, 'date')) {
                $attrs['attributes']['data-odt-annotation-date'] = trim($child->textContent);
                continue;
            }

            if ($this->isElement($child, self::TEXT_NS, 'p')) {
                $paragraph = $this->paragraphNode($child, $package, $styles);
                if ($paragraph instanceof AstNode) {
                    array_push($children, ...$paragraph->children);
                }
            }
        }

        if ($children === []) {
            $children[] = new AstNode('text', ['text' => trim($annotation->textContent)]);
        }

        /** @var array<string, string> $attributes */
        $attributes = array_filter(
            $attrs['attributes'],
            static fn (mixed $value): bool => is_string($value) && $value !== ''
        );
        $attrs['attributes'] = $attributes;

        return new AstNode('span', $attrs, $this->coalesceTextNodes($children));
    }

    /**
     * @param array{paragraphStyles:array<string, array<string, mixed>>, textStyles:array<string, array<string, mixed>>, listStyles:array<string, array<int, array<string, mixed>>>} $styles
     */
    private function listNode(\DOMElement $list, ZipPackage $package, array $styles): AstNode
    {
        $styleName = $this->textAttr($list, 'style-name');
        $levelDefinition = $styleName !== null ? ($styles['listStyles'][$styleName][1] ?? null) : null;
        $ordered = is_array($levelDefinition) ? (bool) ($levelDefinition['ordered'] ?? false) : false;
        $attrs = [
            'sourceFormat' => 'odt',
            'styleName' => $styleName,
        ];

        $start = $this->textAttr($list, 'start-value');
        if ($ordered) {
            $attrs['style'] = (string) ($levelDefinition['style'] ?? 'decimal');
            $attrs['start'] = $start !== null && preg_match('/^\d+$/', $start) === 1
                ? max(1, (int) $start)
                : (int) ($levelDefinition['start'] ?? 1);
            $attrs['restart'] = strtolower((string) ($this->textAttr($list, 'continue-numbering') ?? 'false')) !== 'true';
        }

        $items = [];
        foreach ($list->childNodes as $child) {
            if (!$child instanceof \DOMElement || !$this->isElement($child, self::TEXT_NS, 'list-item')) {
                continue;
            }

            $itemBlocks = [];
            foreach ($child->childNodes as $itemChild) {
                if ($itemChild instanceof \DOMElement) {
                    array_push($itemBlocks, ...$this->blockNodes($itemChild, $package, $styles));
                }
            }

            if ($itemBlocks !== []) {
                $items[] = new AstNode('list_item', ['sourceFormat' => 'odt'], $itemBlocks);
            }
        }

        return new AstNode($ordered ? 'ordered_list' : 'bullet_list', $attrs, $items);
    }

    /**
     * @param array{paragraphStyles:array<string, array<string, mixed>>, textStyles:array<string, array<string, mixed>>, listStyles:array<string, array<int, array<string, mixed>>>} $styles
     */
    private function tableNode(\DOMElement $table, ZipPackage $package, array $styles): AstNode
    {
        $rows = [];
        foreach ($table->childNodes as $child) {
            if (!$child instanceof \DOMElement || !$this->isElement($child, self::TABLE_NS, 'table-row')) {
                continue;
            }

            $repeat = $this->positiveIntAttr($child, self::TABLE_NS, 'number-rows-repeated', 1);
            $row = $this->tableRowNode($child, $package, $styles);
            for ($index = 0; $index < $repeat; $index++) {
                $rows[] = $row;
            }
        }

        $name = $this->tableAttr($table, 'name');
        $attrs = [
            'caption' => $name ?? '',
            'sourceFormat' => 'odt',
        ];
        if ($name !== null && $name !== '') {
            $attrs['htmlAttributes'] = ['data-odt-table-name' => $name];
        }

        $body = new AstNode('table_body', [], $rows);
        $tableNode = new AstNode('table', $attrs, [$body]);
        $diagnostics = TableGeometry::diagnostics($tableNode);
        if ($diagnostics !== []) {
            $attrs['diagnostics'] = $diagnostics;
            $tableNode = new AstNode('table', $attrs, [$body]);
        }

        return $tableNode;
    }

    /**
     * @param array{paragraphStyles:array<string, array<string, mixed>>, textStyles:array<string, array<string, mixed>>, listStyles:array<string, array<int, array<string, mixed>>>} $styles
     */
    private function tableRowNode(\DOMElement $row, ZipPackage $package, array $styles): AstNode
    {
        $cells = [];
        foreach ($row->childNodes as $child) {
            if (!$child instanceof \DOMElement || !$this->isElement($child, self::TABLE_NS, 'table-cell')) {
                continue;
            }

            $repeat = $this->positiveIntAttr($child, self::TABLE_NS, 'number-columns-repeated', 1);
            $cell = $this->tableCellNode($child, $package, $styles);
            for ($index = 0; $index < $repeat; $index++) {
                $cells[] = $cell;
            }
        }

        return new AstNode('table_row', [], $cells);
    }

    /**
     * @param array{paragraphStyles:array<string, array<string, mixed>>, textStyles:array<string, array<string, mixed>>, listStyles:array<string, array<int, array<string, mixed>>>} $styles
     */
    private function tableCellNode(\DOMElement $cell, ZipPackage $package, array $styles): AstNode
    {
        $blocks = [];
        foreach ($cell->childNodes as $child) {
            if ($child instanceof \DOMElement) {
                array_push($blocks, ...$this->blockNodes($child, $package, $styles));
            }
        }

        $attrs = [
            'text' => $this->plainBlockText($blocks),
        ];
        $colspan = $this->positiveIntAttr($cell, self::TABLE_NS, 'number-columns-spanned', 1);
        $rowspan = $this->positiveIntAttr($cell, self::TABLE_NS, 'number-rows-spanned', 1);
        if ($colspan > 1) {
            $attrs['colspan'] = $colspan;
        }
        if ($rowspan > 1) {
            $attrs['rowspan'] = $rowspan;
        }

        return new AstNode('table_cell', $attrs, $blocks);
    }

    /**
     * @param array{paragraphStyles:array<string, array<string, mixed>>, textStyles:array<string, array<string, mixed>>, listStyles:array<string, array<int, array<string, mixed>>>} $styles
     */
    private function frameBlockNode(\DOMElement $frame, ZipPackage $package, array $styles): ?AstNode
    {
        $textBox = $this->firstChildElement($frame, self::DRAW_NS, 'text-box');
        if ($textBox instanceof \DOMElement) {
            $blocks = [];
            foreach ($textBox->childNodes as $child) {
                if ($child instanceof \DOMElement) {
                    array_push($blocks, ...$this->blockNodes($child, $package, $styles));
                }
            }

            return new AstNode('div', [
                'sourceFormat' => 'odt-text-box',
                'name' => $this->drawAttr($frame, 'name'),
            ], $blocks);
        }

        $image = $this->frameInlineNode($frame, $package, $styles);
        if (!$image instanceof AstNode) {
            return null;
        }

        return new AstNode('figure', [
            'caption' => (string) $image->attr('alt', ''),
            'sourceFormat' => 'odt',
            'classes' => ['odt-frame-image'],
        ], [$image]);
    }

    /**
     * @param array{paragraphStyles:array<string, array<string, mixed>>, textStyles:array<string, array<string, mixed>>, listStyles:array<string, array<int, array<string, mixed>>>} $styles
     */
    private function frameInlineNode(\DOMElement $frame, ZipPackage $package, array $styles): ?AstNode
    {
        $image = $this->firstDescendantElement($frame, self::DRAW_NS, 'image');
        if (!$image instanceof \DOMElement) {
            return null;
        }

        $href = (string) ($this->xlinkAttr($image, 'href') ?? '');
        if ($href === '') {
            return null;
        }

        $title = $this->svgAttr($frame, 'title') ?? $this->drawAttr($frame, 'name') ?? '';
        $attrs = [
            'url' => $href,
            'alt' => $title,
            'title' => $title,
            'sourceFormat' => 'odt',
            'sourcePart' => $this->packagePartFromHref($href),
            'exists' => $this->packagePartFromHref($href) !== null ? $package->has($this->packagePartFromHref($href) ?? '') : null,
        ];

        $width = $this->svgAttr($frame, 'width');
        $height = $this->svgAttr($frame, 'height');
        if ($width !== null || $height !== null) {
            $attrs['attributes'] = array_filter([
                'data-odt-width' => $width,
                'data-odt-height' => $height,
            ], static fn (mixed $value): bool => is_string($value) && $value !== '');
        }

        return new AstNode('image', $attrs);
    }

    private function packagePartFromHref(string $href): ?string
    {
        if ($href === '' || str_contains($href, '://') || str_starts_with($href, '#')) {
            return null;
        }

        $path = ltrim($href, '/');
        if ($path === '' || str_contains($path, '..')) {
            return null;
        }

        return $path;
    }

    /**
     * @param list<array{path:string, mediaType:string, encrypted:bool, size:?int}> $manifest
     * @param array{paragraphStyles:array<string, array<string, mixed>>, textStyles:array<string, array<string, mixed>>, listStyles:array<string, array<int, array<string, mixed>>>} $styleCatalog
     * @return array<string, mixed>
     */
    private function importReport(ZipPackage $package, array $manifest, AstNode $document, array $styleCatalog, string $mimetype): array
    {
        $manifestByPath = [];
        foreach ($manifest as $entry) {
            $manifestByPath[$entry['path']] = $entry;
        }

        $images = $this->imageImportReport($package, $document, $manifestByPath);
        $encrypted = array_values(array_filter(
            $manifest,
            static fn (array $entry): bool => $entry['encrypted'] === true
        ));

        return [
            'mimetype' => $mimetype === '' ? self::ODT_MIMETYPE : $mimetype,
            'manifestEntryCount' => count($manifest),
            'encryptedEntryCount' => count($encrypted),
            'encryptedEntries' => array_map(static fn (array $entry): string => $entry['path'], $encrypted),
            'media' => $images,
            'styles' => [
                'paragraphCount' => count($styleCatalog['paragraphStyles']),
                'textCount' => count($styleCatalog['textStyles']),
                'listCount' => count($styleCatalog['listStyles']),
            ],
            'annotations' => [
                'count' => $this->countNodesOfType($document, 'span', 'odt-annotation'),
            ],
            'sections' => [
                'count' => $this->countNodesOfType($document, 'div', null, 'odt-section'),
            ],
            'textBoxes' => [
                'count' => $this->countNodesOfType($document, 'div', null, 'odt-text-box'),
            ],
        ];
    }

    /**
     * @param array<string, array{path:string, mediaType:string, encrypted:bool, size:?int}> $manifestByPath
     * @return array{count:int, embeddedCount:int, missingCount:int, items:list<array{href:string, part:?string, exists:?bool, bytes:?int, mediaType:?string, encrypted:bool, alt:string}>}
     */
    private function imageImportReport(ZipPackage $package, AstNode $document, array $manifestByPath): array
    {
        $images = [];
        $this->collectImages($document, $images);

        $items = [];
        foreach ($images as $image) {
            $href = (string) $image->attr('url', '');
            $part = $image->attr('sourcePart');
            $part = is_string($part) && $part !== '' ? $part : null;
            $exists = $part !== null ? $package->has($part) : null;
            $manifest = $part !== null ? ($manifestByPath[$part] ?? null) : null;
            $items[] = [
                'href' => $href,
                'part' => $part,
                'exists' => $exists,
                'bytes' => $part !== null && $exists === true ? strlen($package->read($part)) : null,
                'mediaType' => is_array($manifest) ? $manifest['mediaType'] : null,
                'encrypted' => is_array($manifest) ? $manifest['encrypted'] : false,
                'alt' => (string) $image->attr('alt', ''),
            ];
        }

        return [
            'count' => count($items),
            'embeddedCount' => count(array_filter($items, static fn (array $item): bool => $item['exists'] === true)),
            'missingCount' => count(array_filter($items, static fn (array $item): bool => $item['exists'] === false)),
            'items' => $items,
        ];
    }

    /**
     * @param list<AstNode> $images
     */
    private function collectImages(AstNode $node, array &$images): void
    {
        if ($node->type === 'image') {
            $images[] = $node;
        }

        foreach ($node->children as $child) {
            $this->collectImages($child, $images);
        }
    }

    private function countNodesOfType(AstNode $node, string $type, ?string $class = null, ?string $sourceFormat = null): int
    {
        $count = 0;
        if ($node->type === $type) {
            $matches = true;
            if ($class !== null) {
                $classes = $node->attr('classes', []);
                $matches = is_array($classes) && in_array($class, $classes, true);
            }
            if ($sourceFormat !== null) {
                $matches = $matches && $node->attr('sourceFormat') === $sourceFormat;
            }
            if ($matches) {
                $count++;
            }
        }

        foreach ($node->children as $child) {
            $count += $this->countNodesOfType($child, $type, $class, $sourceFormat);
        }

        return $count;
    }

    /**
     * @return array<string, string>
     */
    private function readMetadata(ZipPackage $package, \DOMElement $contentRoot): array
    {
        $metadata = [];
        if ($package->has('meta.xml')) {
            $dom = self::loadXml($package->read('meta.xml'), 'ODT meta.xml');
            $root = $dom->documentElement;
            if ($root instanceof \DOMElement) {
                $meta = $this->firstDescendantElement($root, self::OFFICE_NS, 'meta');
                if ($meta instanceof \DOMElement) {
                    $metadata = $this->metadataFromElement($meta);
                }
            }
        }

        if ($metadata === []) {
            $meta = $this->firstChildElement($contentRoot, self::OFFICE_NS, 'meta');
            if ($meta instanceof \DOMElement) {
                $metadata = $this->metadataFromElement($meta);
            }
        }

        return $metadata;
    }

    /**
     * @return array<string, string>
     */
    private function metadataFromElement(\DOMElement $meta): array
    {
        $map = [
            'title' => [self::DC_NS, 'title'],
            'creator' => [self::DC_NS, 'creator'],
            'description' => [self::DC_NS, 'description'],
            'subject' => [self::DC_NS, 'subject'],
            'date' => [self::DC_NS, 'date'],
            'generator' => [self::META_NS, 'generator'],
            'initialCreator' => [self::META_NS, 'initial-creator'],
            'creationDate' => [self::META_NS, 'creation-date'],
            'keyword' => [self::META_NS, 'keyword'],
            'editingCycles' => [self::META_NS, 'editing-cycles'],
        ];

        $metadata = [];
        foreach ($map as $name => [$namespace, $localName]) {
            $child = $this->firstChildElement($meta, $namespace, $localName);
            if ($child instanceof \DOMElement && trim($child->textContent) !== '') {
                $metadata[$name] = trim($child->textContent);
            }
        }

        return $metadata;
    }

    private function orderedListStyle(string $numFormat): string
    {
        return match ($numFormat) {
            'a' => 'lower_alpha',
            'A' => 'upper_alpha',
            'i' => 'lower_roman',
            'I' => 'upper_roman',
            default => 'decimal',
        };
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

            $text .= $this->plainInlineText($node->children);
        }

        return $text;
    }

    /**
     * @param list<AstNode> $blocks
     */
    private function plainBlockText(array $blocks): string
    {
        $texts = [];
        foreach ($blocks as $block) {
            $texts[] = $this->plainInlineText($block->children);
        }

        return trim(implode("\n", array_filter($texts, static fn (string $text): bool => $text !== '')));
    }

    /**
     * @param list<AstNode> $nodes
     * @return list<AstNode>
     */
    private function coalesceTextNodes(array $nodes): array
    {
        $coalesced = [];
        foreach ($nodes as $node) {
            $lastIndex = count($coalesced) - 1;
            if ($node->type === 'text' && $lastIndex >= 0 && $coalesced[$lastIndex]->type === 'text') {
                $coalesced[$lastIndex] = new AstNode('text', [
                    'text' => (string) $coalesced[$lastIndex]->attr('text', '') . (string) $node->attr('text', ''),
                ]);
                continue;
            }

            $coalesced[] = $node;
        }

        return $coalesced;
    }

    private function slugify(string $text): string
    {
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9]+/', '-', $text) ?? '', '-'));

        return $slug === '' ? 'section' : $slug;
    }

    private function positiveIntAttr(\DOMElement $element, string $namespace, string $localName, int $default): int
    {
        $value = $this->namespacedAttr($element, $namespace, $localName);
        if ($value === null || preg_match('/^\d+$/', $value) !== 1) {
            return $default;
        }

        return max(1, (int) $value);
    }

    private function textAttr(\DOMElement $element, string $localName): ?string
    {
        return $this->namespacedAttr($element, self::TEXT_NS, $localName);
    }

    private function styleAttr(\DOMElement $element, string $localName): ?string
    {
        return $this->namespacedAttr($element, self::STYLE_NS, $localName);
    }

    private function tableAttr(\DOMElement $element, string $localName): ?string
    {
        return $this->namespacedAttr($element, self::TABLE_NS, $localName);
    }

    private function drawAttr(\DOMElement $element, string $localName): ?string
    {
        return $this->namespacedAttr($element, self::DRAW_NS, $localName);
    }

    private function xlinkAttr(\DOMElement $element, string $localName): ?string
    {
        return $this->namespacedAttr($element, self::XLINK_NS, $localName);
    }

    private function svgAttr(\DOMElement $element, string $localName): ?string
    {
        return $this->namespacedAttr($element, self::SVG_NS, $localName);
    }

    private function officeAttr(\DOMElement $element, string $localName): ?string
    {
        return $this->namespacedAttr($element, self::OFFICE_NS, $localName);
    }

    private function foAttr(\DOMElement $element, string $localName): ?string
    {
        return $this->namespacedAttr($element, self::FO_NS, $localName);
    }

    private function manifestAttr(\DOMElement $element, string $localName): ?string
    {
        return $this->namespacedAttr($element, self::MANIFEST_NS, $localName);
    }

    private function namespacedAttr(\DOMElement $element, string $namespace, string $localName): ?string
    {
        if ($element->hasAttributeNS($namespace, $localName)) {
            return $element->getAttributeNS($namespace, $localName);
        }

        return null;
    }

    private function isElement(\DOMElement $element, string $namespace, string $localName): bool
    {
        return $element->namespaceURI === $namespace && $element->localName === $localName;
    }

    private function firstChildElement(\DOMElement $element, string $namespace, string $localName): ?\DOMElement
    {
        foreach ($element->childNodes as $child) {
            if ($child instanceof \DOMElement && $this->isElement($child, $namespace, $localName)) {
                return $child;
            }
        }

        return null;
    }

    private function firstDescendantElement(\DOMElement $element, string $namespace, string $localName): ?\DOMElement
    {
        foreach ($element->getElementsByTagNameNS($namespace, $localName) as $child) {
            if ($child instanceof \DOMElement) {
                return $child;
            }
        }

        return null;
    }

    private static function loadXml(string $xml, string $label): \DOMDocument
    {
        $dom = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        try {
            $loaded = $dom->loadXML($xml, LIBXML_NONET | LIBXML_COMPACT);
            if ($loaded === false) {
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
}
