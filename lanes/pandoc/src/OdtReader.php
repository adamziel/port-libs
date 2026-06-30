<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class OdtReader
{
    private const TEXT_NS = 'urn:oasis:names:tc:opendocument:xmlns:text:1.0';
    private const TABLE_NS = 'urn:oasis:names:tc:opendocument:xmlns:table:1.0';
    private const STYLE_NS = 'urn:oasis:names:tc:opendocument:xmlns:style:1.0';
    private const FO_NS = 'urn:oasis:names:tc:opendocument:xmlns:xsl-fo-compatible:1.0';
    private const XLINK_NS = 'http://www.w3.org/1999/xlink';

    /** @var array<string, array{strong?: bool, emph?: bool, family?: string, parentName?: string, nextStyleName?: string, sourcePart?: string, element?: string}> */
    private array $textStyles = [];

    /** @var array<string, array<int, array{ordered: bool, style?: string, delimiter?: string, start?: int}>> */
    private array $listStyles = [];

    /** @var list<array<string, mixed>> */
    private array $styleDiagnostics = [];

    /** @var list<string> */
    private array $referencedResources = [];

    public function read(string $bytes): AstNode
    {
        $path = tempnam(sys_get_temp_dir(), 'pandoc-odt-');
        if ($path === false) {
            throw new \RuntimeException('Unable to create temporary ODT path.');
        }

        try {
            if (file_put_contents($path, $bytes) === false) {
                throw new \RuntimeException('Unable to write temporary ODT package.');
            }

            return $this->readOdtFile($path);
        } finally {
            @unlink($path);
        }
    }

    public function readOdtFile(string $path): AstNode
    {
        if (!class_exists(\ZipArchive::class)) {
            throw new \RuntimeException('ODT analysis needs PHP ZipArchive, which is unavailable in this runtime.');
        }

        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) {
            throw new \InvalidArgumentException("Unable to open ODT package '{$path}'.");
        }

        try {
            $content_xml = $zip->getFromName('content.xml');
            if (!is_string($content_xml)) {
                throw new \InvalidArgumentException('ODT package is missing content.xml.');
            }
            $styles_xml = $zip->getFromName('styles.xml');
            $meta_xml = $zip->getFromName('meta.xml');
            $entries = [];
            $image_resources = [];
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i);
                $name = is_array($stat) ? (string) ($stat['name'] ?? '') : '';
                if ($name === '') {
                    continue;
                }
                $entries[] = $name;
                if ($this->pathLooksLikeImage($name)) {
                    $image_resources[] = $this->normalizePackagePath($name);
                }
            }
        } finally {
            $zip->close();
        }

        return $this->readPackage(
            $content_xml,
            is_string($styles_xml) ? $styles_xml : '',
            is_string($meta_xml) ? $meta_xml : '',
            $entries,
            array_values(array_unique($image_resources)),
        );
    }

    /**
     * @param list<string> $entries
     * @param list<string> $image_resources
     */
    private function readPackage(string $content_xml, string $styles_xml = '', string $meta_xml = '', array $entries = [], array $image_resources = []): AstNode
    {
        $content = $this->loadXml($content_xml, 'ODT content.xml');
        $styles = $styles_xml !== '' ? $this->loadXml($styles_xml, 'ODT styles.xml') : null;
        $this->styleDiagnostics = [];
        $this->textStyles = array_replace(
            $styles instanceof \DOMDocument ? $this->collectTextStyles($styles, 'styles.xml') : [],
            $this->collectTextStyles($content, 'content.xml'),
        );
        $this->listStyles = array_replace_recursive(
            $styles instanceof \DOMDocument ? $this->collectListStyles($styles, 'styles.xml') : [],
            $this->collectListStyles($content, 'content.xml'),
        );
        array_push($this->styleDiagnostics, ...$this->textStyleReferenceDiagnostics($this->textStyles));
        array_push($this->styleDiagnostics, ...$this->contentStyleReferenceDiagnostics($content));

        $metadata = $meta_xml !== '' ? $this->metadata($this->loadXml($meta_xml, 'ODT meta.xml')) : [];
        $body = $this->firstElementByLocalName($content, 'body');
        $text = $body instanceof \DOMElement ? $this->firstChildElementByLocalName($body, 'text') : null;
        if (!$text instanceof \DOMElement) {
            $text = $this->firstElementByLocalName($content, 'text');
        }

        $this->referencedResources = [];
        $children = $text instanceof \DOMElement ? $this->parseBlockChildren($text) : [];
        if ($children === []) {
            $children[] = new AstNode('paragraph', ['text' => 'No readable ODT body content was found.'], [
                new AstNode('text', ['text' => 'No readable ODT body content was found.']),
            ]);
        }

        $referenced_resources = array_values(array_unique($this->referencedResources));
        $metadata['odtTextStyleCount'] = count($this->textStyles);
        $metadata['odtListStyleCount'] = count($this->listStyles);
        $metadata['odtStyleDiagnosticCount'] = count($this->styleDiagnostics);
        $metadata['odtStyleDiagnosticCodeCounts'] = $this->diagnosticCodeCounts($this->styleDiagnostics);
        $metadata['odtStyleDiagnostics'] = $this->styleDiagnostics;
        $metadata['odtPackageEntries'] = count($entries);
        $metadata['odtReferencedResources'] = $referenced_resources;
        $metadata['odtImageResources'] = $image_resources !== []
            ? $image_resources
            : array_values(array_filter($referenced_resources, fn (string $path): bool => $this->pathLooksLikeImage($path)));

        return new AstNode('document', ['meta' => $metadata], $children);
    }

    /**
     * @return list<AstNode>
     */
    private function parseBlockChildren(\DOMNode $parent): array
    {
        $blocks = [];
        foreach ($parent->childNodes as $child) {
            if (!$child instanceof \DOMElement) {
                continue;
            }
            $block = $this->parseBlock($child);
            if ($block instanceof AstNode) {
                $blocks[] = $block;
            } elseif (is_array($block)) {
                array_push($blocks, ...$block);
            }
        }

        return $blocks;
    }

    private function parseBlock(\DOMElement $element): AstNode|array|null
    {
        return match ($element->localName) {
            'h' => $this->heading($element),
            'p' => $this->paragraph($element),
            'list' => $this->list($element),
            'table' => $this->table($element),
            'section', 'div' => $this->parseBlockChildren($element),
            default => null,
        };
    }

    private function heading(\DOMElement $element): AstNode
    {
        $level = max(1, min(6, (int) ($this->attr($element, self::TEXT_NS, 'outline-level') ?: '1')));
        $inlines = $this->parseInlines($element);
        $text = $this->plainText($inlines);

        return new AstNode('heading', ['level' => $level, 'text' => $text], $inlines);
    }

    private function paragraph(\DOMElement $element): ?AstNode
    {
        $inlines = $this->parseInlines($element);
        $text = trim($this->plainText($inlines));
        if ($text === '' && $inlines === []) {
            return null;
        }

        return new AstNode('paragraph', ['text' => $text], $inlines);
    }

    private function list(\DOMElement $element): AstNode
    {
        $items = [];
        foreach ($element->childNodes as $child) {
            if (!$child instanceof \DOMElement || $child->localName !== 'list-item') {
                continue;
            }
            $blocks = $this->parseBlockChildren($child);
            if ($blocks === []) {
                $text = trim($child->textContent);
                if ($text !== '') {
                    $blocks[] = new AstNode('plain', ['text' => $text], [new AstNode('text', ['text' => $text])]);
                }
            }
            $items[] = new AstNode('list_item', [], $blocks);
        }

        $attrs = $this->listAttributes($element);

        return new AstNode(($attrs['ordered'] ?? false) ? 'ordered_list' : 'bullet_list', $attrs['attrs'] ?? [], $items);
    }

    /**
     * @return array{ordered: bool, attrs: array<string, mixed>}
     */
    private function listAttributes(\DOMElement $element): array
    {
        $styleName = $this->attr($element, self::TEXT_NS, 'style-name');
        $level = $this->listLevel($element);
        $style = $styleName !== '' ? ($this->listStyles[$styleName][$level] ?? $this->listStyles[$styleName][1] ?? null) : null;
        if (!is_array($style) || !($style['ordered'] ?? false)) {
            return ['ordered' => false, 'attrs' => []];
        }

        $attrs = [
            'start' => $this->listStart($element, $style),
            'style' => $style['style'] ?? 'decimal',
            'delimiter' => $style['delimiter'] ?? 'period',
        ];

        return ['ordered' => true, 'attrs' => $attrs];
    }

    private function listLevel(\DOMElement $element): int
    {
        $level = 1;
        for ($parent = $element->parentNode; $parent instanceof \DOMElement; $parent = $parent->parentNode) {
            if ($parent->localName === 'list') {
                $level++;
            }
        }

        return $level;
    }

    /**
     * @param array{ordered: bool, style?: string, delimiter?: string, start?: int} $style
     */
    private function listStart(\DOMElement $element, array $style): int
    {
        $start = $this->attr($element, self::TEXT_NS, 'start-value');
        if ($start !== '' && is_numeric($start)) {
            return max(1, (int) $start);
        }

        foreach ($element->childNodes as $child) {
            if (!$child instanceof \DOMElement || $child->localName !== 'list-item') {
                continue;
            }
            $itemStart = $this->attr($child, self::TEXT_NS, 'start-value');
            if ($itemStart !== '' && is_numeric($itemStart)) {
                return max(1, (int) $itemStart);
            }
            break;
        }

        return max(1, (int) ($style['start'] ?? 1));
    }

    private function table(\DOMElement $element): AstNode
    {
        $rows = [];
        foreach ($element->childNodes as $child) {
            if (!$child instanceof \DOMElement || $child->localName !== 'table-row') {
                continue;
            }
            $repeat = max(1, min(50, (int) ($this->attr($child, self::TABLE_NS, 'number-rows-repeated') ?: '1')));
            $row = $this->tableRow($child);
            for ($i = 0; $i < $repeat; $i++) {
                $rows[] = $row;
            }
        }

        return new AstNode('table', [], [
            new AstNode('table_head'),
            new AstNode('table_body', [], $rows),
        ]);
    }

    private function tableRow(\DOMElement $row): AstNode
    {
        $cells = [];
        foreach ($row->childNodes as $cell) {
            if (!$cell instanceof \DOMElement || !in_array($cell->localName, ['table-cell', 'covered-table-cell'], true)) {
                continue;
            }
            if ($cell->localName === 'covered-table-cell') {
                continue;
            }
            $repeat = max(1, min(50, (int) ($this->attr($cell, self::TABLE_NS, 'number-columns-repeated') ?: '1')));
            $node = $this->tableCell($cell);
            for ($i = 0; $i < $repeat; $i++) {
                $cells[] = $node;
            }
        }

        return new AstNode('table_row', [], $cells);
    }

    private function tableCell(\DOMElement $cell): AstNode
    {
        $attrs = [
            'colspan' => max(1, (int) ($this->attr($cell, self::TABLE_NS, 'number-columns-spanned') ?: '1')),
            'rowspan' => max(1, (int) ($this->attr($cell, self::TABLE_NS, 'number-rows-spanned') ?: '1')),
        ];
        $blocks = $this->parseBlockChildren($cell);
        $text = trim(implode(' ', array_map(fn (AstNode $block): string => $this->nodeText($block), $blocks)));
        if ($blocks === [] && trim($cell->textContent) !== '') {
            $text = trim($cell->textContent);
            $blocks[] = new AstNode('plain', ['text' => $text], [new AstNode('text', ['text' => $text])]);
        }
        $attrs['text'] = $text;

        return new AstNode('table_cell', $attrs, $blocks);
    }

    /**
     * @return list<AstNode>
     */
    private function parseInlines(\DOMNode $parent): array
    {
        $inlines = [];
        foreach ($parent->childNodes as $child) {
            if ($child instanceof \DOMText || $child instanceof \DOMCdataSection) {
                $text = preg_replace('/\s+/u', ' ', $child->nodeValue) ?? $child->nodeValue;
                if ($text !== '') {
                    $inlines[] = new AstNode('text', ['text' => $text]);
                }
                continue;
            }
            if (!$child instanceof \DOMElement) {
                continue;
            }
            array_push($inlines, ...$this->parseInlineElement($child));
        }

        return $this->mergeAdjacentText($inlines);
    }

    /**
     * @return list<AstNode>
     */
    private function parseInlineElement(\DOMElement $element): array
    {
        return match ($element->localName) {
            'span' => $this->styledSpan($element),
            'a' => [new AstNode('link', [
                'url' => $this->attr($element, self::XLINK_NS, 'href'),
                'title' => '',
            ], $this->parseInlines($element))],
            'line-break' => [new AstNode('linebreak')],
            'tab' => [new AstNode('text', ['text' => "\t"])],
            's' => [new AstNode('text', ['text' => str_repeat(' ', max(1, (int) ($this->attr($element, self::TEXT_NS, 'c') ?: '1')))])],
            'frame' => $this->frame($element),
            'note' => [$this->note($element)],
            default => $this->parseInlines($element),
        };
    }

    private function note(\DOMElement $element): AstNode
    {
        $noteClass = $this->attr($element, self::TEXT_NS, 'note-class');
        if ($noteClass === '') {
            $noteClass = 'footnote';
        }

        $attrs = [
            'sourceFormat' => 'odt',
            'noteClass' => $noteClass,
        ];
        $id = $this->attr($element, self::TEXT_NS, 'id');
        if ($id !== '') {
            $attrs['id'] = $id;
        }

        $citation = $this->firstChildElementByLocalName($element, 'note-citation');
        if ($citation instanceof \DOMElement) {
            $citationText = $this->plainText($this->parseInlines($citation));
            if ($citationText === '') {
                $citationText = trim(preg_replace('/\s+/', ' ', $citation->textContent) ?? $citation->textContent);
            }
            $attrs['citation'] = $citationText;
        }

        $blocks = [];
        $body = $this->firstChildElementByLocalName($element, 'note-body');
        if ($body instanceof \DOMElement) {
            $blocks = $this->parseBlockChildren($body);
            if ($blocks === []) {
                $inlines = $this->parseInlines($body);
                $text = $this->plainText($inlines);
                if ($text !== '' || $inlines !== []) {
                    $blocks[] = new AstNode('paragraph', ['text' => $text], $inlines);
                }
            }
        }

        return new AstNode('note', $attrs, $blocks);
    }

    /**
     * @return list<AstNode>
     */
    private function styledSpan(\DOMElement $element): array
    {
        $children = $this->parseInlines($element);
        $style = $this->textStyles[$this->attr($element, self::TEXT_NS, 'style-name')] ?? [];
        if (($style['strong'] ?? false) && $children !== []) {
            $children = [new AstNode('strong', [], $children)];
        }
        if (($style['emph'] ?? false) && $children !== []) {
            $children = [new AstNode('emph', [], $children)];
        }

        return $children;
    }

    /**
     * @return list<AstNode>
     */
    private function frame(\DOMElement $element): array
    {
        foreach ($element->childNodes as $child) {
            if (!$child instanceof \DOMElement || $child->localName !== 'image') {
                continue;
            }
            $url = $this->attr($child, self::XLINK_NS, 'href');
            if ($url === '') {
                continue;
            }
            if ($this->isPackageRelativeResourceUrl($url)) {
                $url = $this->normalizePackagePath($url);
                $this->referencedResources[] = $url;
            }

            return [new AstNode('image', [
                'url' => $url,
                'title' => '',
                'alt' => trim($element->textContent),
            ], [])];
        }

        return $this->parseInlines($element);
    }

    /**
     * @param list<AstNode> $inlines
     * @return list<AstNode>
     */
    private function mergeAdjacentText(array $inlines): array
    {
        $merged = [];
        foreach ($inlines as $inline) {
            $last = $merged[array_key_last($merged)] ?? null;
            if ($inline->type === 'text' && $last instanceof AstNode && $last->type === 'text') {
                $merged[array_key_last($merged)] = new AstNode('text', [
                    'text' => (string) $last->attr('text', '') . (string) $inline->attr('text', ''),
                ]);
                continue;
            }
            $merged[] = $inline;
        }

        return $merged;
    }

    /**
     * @return array<string, array{strong?: bool, emph?: bool, family?: string, parentName?: string, sourcePart?: string, element?: string}>
     */
    private function collectTextStyles(\DOMDocument $dom, string $sourcePart = ''): array
    {
        $styles = [];
        foreach ($dom->getElementsByTagName('*') as $style) {
            if (!$style instanceof \DOMElement || $style->localName !== 'style') {
                continue;
            }
            $this->appendStyleDefinitionDiagnostics($style, $sourcePart);
            $family = $this->attr($style, self::STYLE_NS, 'family');
            if ($family !== '' && $family !== 'text') {
                continue;
            }
            $name = $this->attr($style, self::STYLE_NS, 'name');
            if ($name === '') {
                continue;
            }
            $entry = [
                'family' => $family,
                'sourcePart' => $sourcePart,
                'element' => $this->odtElementName($style),
            ];
            $parentName = $this->attr($style, self::STYLE_NS, 'parent-style-name');
            if ($parentName !== '') {
                $entry['parentName'] = $parentName;
            }
            $nextStyleName = $this->attr($style, self::STYLE_NS, 'next-style-name');
            if ($nextStyleName !== '') {
                $entry['nextStyleName'] = $nextStyleName;
            }
            foreach ($style->childNodes as $props) {
                if (!$props instanceof \DOMElement || $props->localName !== 'text-properties') {
                    continue;
                }
                $weight = strtolower($this->attr($props, self::FO_NS, 'font-weight') ?: $this->attr($props, self::STYLE_NS, 'font-weight'));
                $fontStyle = strtolower($this->attr($props, self::FO_NS, 'font-style') ?: $this->attr($props, self::STYLE_NS, 'font-style'));
                if ($weight === 'bold' || (is_numeric($weight) && (int) $weight >= 600)) {
                    $entry['strong'] = true;
                }
                if ($fontStyle === 'italic' || $fontStyle === 'oblique') {
                    $entry['emph'] = true;
                }
            }
            $styles[$name] = $entry;
        }

        return $styles;
    }

    /**
     * @param array<string, array{strong?: bool, emph?: bool, family?: string, parentName?: string, nextStyleName?: string, sourcePart?: string, element?: string}> $styles
     * @return list<array<string, mixed>>
     */
    private function textStyleReferenceDiagnostics(array $styles): array
    {
        $diagnostics = [];
        foreach ($styles as $styleName => $style) {
            $this->appendMissingTextStyleReferenceDiagnostic(
                $diagnostics,
                $styles,
                $style,
                'odt-style-missing-parent',
                $styleName,
                'parentStyleName',
                (string) ($style['parentName'] ?? '')
            );
            $this->appendMissingTextStyleReferenceDiagnostic(
                $diagnostics,
                $styles,
                $style,
                'odt-style-missing-next-style',
                $styleName,
                'nextStyleName',
                (string) ($style['nextStyleName'] ?? '')
            );
        }

        return $diagnostics;
    }

    /**
     * @param list<array<string, mixed>> $diagnostics
     * @param array<string, array<string, mixed>> $styles
     * @param array<string, mixed> $style
     */
    private function appendMissingTextStyleReferenceDiagnostic(
        array &$diagnostics,
        array $styles,
        array $style,
        string $code,
        string $styleName,
        string $referenceKey,
        string $referenceName
    ): void {
        if ($referenceName === '' || isset($styles[$referenceName])) {
            return;
        }

        $diagnostic = [
            'code' => $code,
            'sourcePart' => (string) ($style['sourcePart'] ?? ''),
            'element' => (string) ($style['element'] ?? 'style:style'),
            'styleName' => $styleName,
            $referenceKey => $referenceName,
        ];
        $family = (string) ($style['family'] ?? '');
        if ($family !== '') {
            $diagnostic['family'] = $family;
        }
        $diagnostics[] = $diagnostic;
    }

    /**
     * @return array<string, array<int, array{ordered: bool, style?: string, delimiter?: string, start?: int}>>
     */
    private function collectListStyles(\DOMDocument $dom, string $sourcePart = ''): array
    {
        $styles = [];
        foreach ($dom->getElementsByTagName('*') as $style) {
            if (!$style instanceof \DOMElement || $style->localName !== 'list-style') {
                continue;
            }
            $name = $this->attr($style, self::STYLE_NS, 'name');
            if ($name === '') {
                $name = $this->attr($style, self::TEXT_NS, 'style-name');
            }
            if ($name === '') {
                $this->styleDiagnostics[] = [
                    'code' => 'odt-list-style-missing-name',
                    'sourcePart' => $sourcePart,
                    'element' => $this->odtElementName($style),
                ];
                continue;
            }

            foreach ($style->childNodes as $levelStyle) {
                if (!$levelStyle instanceof \DOMElement) {
                    continue;
                }
                if (!in_array($levelStyle->localName, ['list-level-style-number', 'list-level-style-bullet'], true)) {
                    continue;
                }

                $level = max(1, (int) ($this->attr($levelStyle, self::TEXT_NS, 'level') ?: '1'));
                if ($levelStyle->localName === 'list-level-style-bullet') {
                    $styles[$name][$level] = ['ordered' => false];
                    continue;
                }

                $format = $this->attr($levelStyle, self::STYLE_NS, 'num-format');
                if ($format === '') {
                    $format = $levelStyle->getAttribute('style:num-format');
                }
                $entry = [
                    'ordered' => true,
                    'style' => $this->orderedListStyle($format),
                    'delimiter' => $this->orderedListDelimiter(
                        $this->attr($levelStyle, self::STYLE_NS, 'num-prefix'),
                        $this->attr($levelStyle, self::STYLE_NS, 'num-suffix')
                    ),
                ];

                $start = $this->attr($levelStyle, self::TEXT_NS, 'start-value');
                if ($start !== '' && is_numeric($start)) {
                    $entry['start'] = max(1, (int) $start);
                }

                $styles[$name][$level] = $entry;
            }
        }

        return $styles;
    }

    private function appendStyleDefinitionDiagnostics(\DOMElement $style, string $sourcePart): void
    {
        $name = $this->attr($style, self::STYLE_NS, 'name');
        $family = $this->attr($style, self::STYLE_NS, 'family');

        if ($name === '') {
            $diagnostic = [
                'code' => 'odt-style-missing-name',
                'sourcePart' => $sourcePart,
                'element' => $this->odtElementName($style),
            ];
            if ($family !== '') {
                $diagnostic['family'] = $family;
            }
            $this->styleDiagnostics[] = $diagnostic;

            return;
        }

        if ($family === '') {
            $this->styleDiagnostics[] = [
                'code' => 'odt-style-missing-family',
                'sourcePart' => $sourcePart,
                'element' => $this->odtElementName($style),
                'styleName' => $name,
            ];

            return;
        }

        if (!$this->isKnownStyleFamily($family)) {
            $this->styleDiagnostics[] = [
                'code' => 'odt-style-unknown-family',
                'sourcePart' => $sourcePart,
                'element' => $this->odtElementName($style),
                'styleName' => $name,
                'family' => $family,
            ];
        }
    }

    private function isKnownStyleFamily(string $family): bool
    {
        return in_array($family, [
            'chart',
            'control',
            'drawing-page',
            'graphic',
            'paragraph',
            'presentation',
            'ruby',
            'section',
            'table',
            'table-cell',
            'table-column',
            'table-page',
            'table-row',
            'text',
        ], true);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function contentStyleReferenceDiagnostics(\DOMDocument $dom): array
    {
        $diagnostics = [];
        $seen = [];
        foreach ($dom->getElementsByTagName('*') as $element) {
            if (!$element instanceof \DOMElement) {
                continue;
            }

            if ($element->namespaceURI === self::TEXT_NS && $element->localName === 'span') {
                $this->appendMissingContentStyleDiagnostic(
                    $diagnostics,
                    $seen,
                    'odt-content-missing-text-style',
                    $element,
                    'styleName',
                    $this->attr($element, self::TEXT_NS, 'style-name'),
                    $this->textStyles
                );
                continue;
            }

            if ($element->namespaceURI === self::TEXT_NS && $element->localName === 'list') {
                $this->appendMissingContentStyleDiagnostic(
                    $diagnostics,
                    $seen,
                    'odt-content-missing-list-style',
                    $element,
                    'listStyleName',
                    $this->attr($element, self::TEXT_NS, 'style-name'),
                    $this->listStyles
                );
            }
        }

        return $diagnostics;
    }

    /**
     * @param list<array<string, mixed>> $diagnostics
     * @param array<string, true> $seen
     * @param array<string, mixed> $targets
     */
    private function appendMissingContentStyleDiagnostic(
        array &$diagnostics,
        array &$seen,
        string $code,
        \DOMElement $element,
        string $referenceKey,
        string $referenceName,
        array $targets
    ): void {
        if ($referenceName === '' || isset($targets[$referenceName])) {
            return;
        }

        $elementName = $this->odtElementName($element);
        $seenKey = $code . "\0" . $elementName . "\0" . $referenceName;
        if (isset($seen[$seenKey])) {
            return;
        }

        $seen[$seenKey] = true;
        $diagnostics[] = [
            'code' => $code,
            'sourcePart' => 'content.xml',
            'element' => $elementName,
            $referenceKey => $referenceName,
        ];
    }

    /**
     * @param list<array<string, mixed>> $diagnostics
     * @return array<string, int>
     */
    private function diagnosticCodeCounts(array $diagnostics): array
    {
        $counts = [];
        foreach ($diagnostics as $diagnostic) {
            $code = (string) ($diagnostic['code'] ?? '');
            if ($code === '') {
                continue;
            }
            $counts[$code] = ($counts[$code] ?? 0) + 1;
        }
        ksort($counts);

        return $counts;
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

    private function orderedListDelimiter(string $prefix, string $suffix): string
    {
        if ($prefix === '(' && $suffix === ')') {
            return 'two_parens';
        }
        if ($suffix === ')') {
            return 'one_paren';
        }
        if ($suffix === '.') {
            return 'period';
        }

        return 'default';
    }

    /**
     * @return array<string, mixed>
     */
    private function metadata(\DOMDocument $dom): array
    {
        $meta = [];
        foreach ($dom->getElementsByTagName('*') as $element) {
            if (!$element instanceof \DOMElement) {
                continue;
            }
            $text = trim(preg_replace('/\s+/', ' ', $element->textContent) ?? $element->textContent);
            if ($text === '') {
                continue;
            }
            $key = match ($element->localName) {
                'title' => 'title',
                'creator' => 'author',
                'description' => 'description',
                'date', 'creation-date' => 'date',
                'keyword' => 'keywords',
                default => '',
            };
            if ($key === '') {
                continue;
            }
            if (isset($meta[$key])) {
                $meta[$key] = is_array($meta[$key]) ? array_merge($meta[$key], [$text]) : [$meta[$key], $text];
            } else {
                $meta[$key] = $text;
            }
            if ($key === 'title') {
                $meta['titleInlines'] = [new AstNode('text', ['text' => $text])];
            }
        }

        return $meta;
    }

    private function loadXml(string $xml, string $label): \DOMDocument
    {
        if (!class_exists(\DOMDocument::class)) {
            throw new \RuntimeException($label . ' needs DOMDocument, which is unavailable in this runtime.');
        }
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $ok = $dom->loadXML($xml, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        if (!$ok) {
            throw new \InvalidArgumentException($label . ' is not valid XML.');
        }

        return $dom;
    }

    private function firstElementByLocalName(\DOMDocument $dom, string $localName): ?\DOMElement
    {
        foreach ($dom->getElementsByTagName('*') as $element) {
            if ($element instanceof \DOMElement && $element->localName === $localName) {
                return $element;
            }
        }

        return null;
    }

    private function firstChildElementByLocalName(\DOMElement $parent, string $localName): ?\DOMElement
    {
        foreach ($parent->childNodes as $child) {
            if ($child instanceof \DOMElement && $child->localName === $localName) {
                return $child;
            }
        }

        return null;
    }

    private function attr(\DOMElement $element, string $namespace, string $name): string
    {
        $value = $element->getAttributeNS($namespace, $name);
        if ($value !== '') {
            return $value;
        }
        foreach ($element->attributes ?? [] as $attribute) {
            if ($attribute instanceof \DOMAttr && $attribute->localName === $name) {
                return $attribute->value;
            }
        }

        return '';
    }

    private function odtElementName(\DOMElement $element): string
    {
        $prefix = match ($element->namespaceURI) {
            self::TEXT_NS => 'text',
            self::TABLE_NS => 'table',
            self::STYLE_NS => 'style',
            self::FO_NS => 'fo',
            self::XLINK_NS => 'xlink',
            default => '',
        };

        return $prefix === '' ? $element->localName : $prefix . ':' . $element->localName;
    }

    private function isPackageRelativeResourceUrl(string $url): bool
    {
        $url = trim($url);
        return $url !== ''
            && !str_starts_with($url, '#')
            && !str_starts_with(strtolower($url), 'data:')
            && !str_starts_with(strtolower($url), 'mailto:')
            && !$this->isAbsoluteUrl($url);
    }

    private function isAbsoluteUrl(string $url): bool
    {
        return (bool) preg_match('/^[a-z][a-z0-9+.-]*:/i', $url) || str_starts_with($url, '//');
    }

    private function normalizePackagePath(string $path): string
    {
        $parts = [];
        foreach (explode('/', str_replace('\\', '/', $path)) as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }
            if ($part === '..') {
                array_pop($parts);
                continue;
            }
            $parts[] = $part;
        }

        return implode('/', $parts);
    }

    private function pathLooksLikeImage(string $path): bool
    {
        return (bool) preg_match('/\.(?:apng|avif|bmp|gif|ico|jpe?g|png|svgz?|tiff?|webp)$/i', $path);
    }

    /**
     * @param list<AstNode> $inlines
     */
    private function plainText(array $inlines): string
    {
        $text = '';
        foreach ($inlines as $inline) {
            $text .= $this->nodeText($inline);
        }

        return trim(preg_replace('/\s+/', ' ', $text) ?? $text);
    }

    private function nodeText(AstNode $node): string
    {
        if ($node->type === 'note') {
            return '';
        }
        if (isset($node->attrs['text'])) {
            return (string) $node->attrs['text'];
        }
        if ($node->type === 'linebreak') {
            return ' ';
        }
        $text = '';
        foreach ($node->children as $child) {
            $text .= $this->nodeText($child);
        }

        return $text;
    }
}
