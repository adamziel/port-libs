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
    private const MANIFEST_NS = 'urn:oasis:names:tc:opendocument:xmlns:manifest:1.0';

    /** @var array<string, array{strong?: bool, emph?: bool}> */
    private array $textStyles = [];

    /** @var array<string, array<int, array{ordered: bool, style?: string, delimiter?: string, start?: int}>> */
    private array $listStyles = [];

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
        $package = ZipOpcPackage::open($path, 'ODT');

        try {
            $content_xml = $package->requireRead('content.xml', 'ODT package is missing content.xml.');
            $styles_xml = $package->read('styles.xml');
            $meta_xml = $package->read('meta.xml');
            $manifest_xml = $package->read('META-INF/manifest.xml');
            $entries = $package->entryNames();
            $image_resources = [];
            foreach ($entries as $name) {
                if ($this->pathLooksLikeImage($name)) {
                    $image_resources[] = $this->normalizePackagePath($name);
                }
            }
        } finally {
            $package->close();
        }
        $manifest = is_string($manifest_xml)
            ? $this->manifestSummary($manifest_xml, $entries)
            : ['entries' => [], 'diagnostics' => []];

        return $this->readPackage(
            $content_xml,
            $styles_xml ?? '',
            $meta_xml ?? '',
            $entries,
            array_values(array_unique($image_resources)),
            $manifest['entries'],
            $manifest['diagnostics'],
        );
    }

    /**
     * @param list<string> $entries
     * @param list<string> $image_resources
     * @param list<array<string, mixed>> $manifest_entries
     * @param list<array<string, mixed>> $manifest_diagnostics
     */
    private function readPackage(
        string $content_xml,
        string $styles_xml = '',
        string $meta_xml = '',
        array $entries = [],
        array $image_resources = [],
        array $manifest_entries = [],
        array $manifest_diagnostics = []
    ): AstNode
    {
        $content = $this->loadXml($content_xml, 'ODT content.xml');
        $this->textStyles = array_replace(
            $styles_xml !== '' ? $this->collectTextStyles($this->loadXml($styles_xml, 'ODT styles.xml')) : [],
            $this->collectTextStyles($content),
        );
        $this->listStyles = array_replace_recursive(
            $styles_xml !== '' ? $this->collectListStyles($this->loadXml($styles_xml, 'ODT styles.xml')) : [],
            $this->collectListStyles($content),
        );

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
        $metadata['odtPackageEntries'] = count($entries);
        $metadata['odtReferencedResources'] = $referenced_resources;
        $metadata['odtImageResources'] = $image_resources !== []
            ? $image_resources
            : array_values(array_filter($referenced_resources, fn (string $path): bool => $this->pathLooksLikeImage($path)));
        if ($manifest_entries !== []) {
            $metadata['odtManifestEntries'] = $manifest_entries;
            $metadata['odtManifestEntryCount'] = count($manifest_entries);
            $metadata['odtManifestMediaTypes'] = $this->manifestMediaTypes($manifest_entries);
        }
        if ($manifest_diagnostics !== []) {
            $metadata['odtPackageDiagnostics'] = $manifest_diagnostics;
            $metadata['odtPackageDiagnosticCount'] = count($manifest_diagnostics);
        }

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
            default => $this->parseInlines($element),
        };
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
     * @return array<string, array{strong?: bool, emph?: bool}>
     */
    private function collectTextStyles(\DOMDocument $dom): array
    {
        $styles = [];
        foreach ($dom->getElementsByTagName('*') as $style) {
            if (!$style instanceof \DOMElement || $style->localName !== 'style') {
                continue;
            }
            $family = $this->attr($style, self::STYLE_NS, 'family');
            if ($family !== '' && $family !== 'text') {
                continue;
            }
            $name = $this->attr($style, self::STYLE_NS, 'name');
            if ($name === '') {
                continue;
            }
            $entry = [];
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
            if ($entry !== []) {
                $styles[$name] = $entry;
            }
        }

        return $styles;
    }

    /**
     * @return array<string, array<int, array{ordered: bool, style?: string, delimiter?: string, start?: int}>>
     */
    private function collectListStyles(\DOMDocument $dom): array
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

    /**
     * @param list<string> $package_entries
     * @return array{entries: list<array<string, mixed>>, diagnostics: list<array<string, mixed>>}
     */
    private function manifestSummary(string $manifest_xml, array $package_entries): array
    {
        try {
            $dom = $this->loadXml($manifest_xml, 'ODT manifest.xml');
        } catch (\InvalidArgumentException) {
            return [
                'entries' => [],
                'diagnostics' => [
                    $this->odtDiagnostic('error', 'malformed-manifest-xml', 'ODT META-INF/manifest.xml is not valid XML.'),
                ],
            ];
        }

        $package_entry_set = [];
        foreach ($package_entries as $entry) {
            $path = $this->normalizePackagePath($entry);
            if ($path !== '') {
                $package_entry_set[$path] = true;
            }
        }

        $manifest_entries = [];
        $diagnostics = [];
        $seen_paths = [];
        foreach ($dom->getElementsByTagName('*') as $element) {
            if (!$element instanceof \DOMElement || $element->localName !== 'file-entry') {
                continue;
            }

            $full_path = $this->attr($element, self::MANIFEST_NS, 'full-path');
            if ($full_path === '') {
                $diagnostics[] = $this->odtDiagnostic(
                    'warning',
                    'missing-manifest-full-path',
                    'ODT manifest file-entry is missing manifest:full-path.'
                );
                continue;
            }

            $media_type = $this->attr($element, self::MANIFEST_NS, 'media-type');
            $entry = [
                'fullPath' => $full_path,
            ];
            if ($media_type !== '') {
                $entry['mediaType'] = $media_type;
            }
            $size = $this->attr($element, self::MANIFEST_NS, 'size');
            if ($size !== '' && ctype_digit($size)) {
                $entry['size'] = (int) $size;
            }

            $path_issue = $this->manifestPathIssue($full_path);
            if ($path_issue !== '') {
                $diagnostics[] = $this->odtDiagnostic(
                    'warning',
                    'invalid-manifest-full-path',
                    'ODT manifest file-entry has an unsafe package path.',
                    ['path' => $full_path, 'reason' => $path_issue]
                );
                $manifest_entries[] = $entry;
                continue;
            }

            $normalized_path = $full_path === '/' ? '' : $this->normalizePackagePath($full_path);
            $entry['path'] = $full_path === '/' ? '/' : $normalized_path;
            if (isset($seen_paths[$entry['path']])) {
                $diagnostics[] = $this->odtDiagnostic(
                    'warning',
                    'duplicate-manifest-entry',
                    'ODT manifest declares the same package path more than once.',
                    ['path' => $entry['path']]
                );
            }
            $seen_paths[$entry['path']] = true;

            if (
                $normalized_path !== ''
                && !str_ends_with($full_path, '/')
                && !isset($package_entry_set[$normalized_path])
            ) {
                $diagnostics[] = $this->odtDiagnostic(
                    'warning',
                    'missing-manifest-resource',
                    'ODT manifest declares a package resource that is missing from the ZIP payload.',
                    ['path' => $normalized_path]
                );
            }

            $manifest_entries[] = $entry;
        }

        return [
            'entries' => $manifest_entries,
            'diagnostics' => $diagnostics,
        ];
    }

    /**
     * @param list<array<string, mixed>> $manifest_entries
     * @return array<string, int>
     */
    private function manifestMediaTypes(array $manifest_entries): array
    {
        $media_types = [];
        foreach ($manifest_entries as $entry) {
            $media_type = (string) ($entry['mediaType'] ?? '');
            if ($media_type === '') {
                continue;
            }
            $media_types[$media_type] = ($media_types[$media_type] ?? 0) + 1;
        }
        ksort($media_types);

        return $media_types;
    }

    /**
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    private function odtDiagnostic(string $severity, string $code, string $message, array $context = []): array
    {
        return array_merge([
            'severity' => $severity,
            'code' => $code,
            'message' => $message,
        ], $context);
    }

    private function manifestPathIssue(string $path): string
    {
        if ($path === '' || $path === '/') {
            return '';
        }
        if (str_contains($path, '\\')) {
            return 'backslash';
        }
        if ($this->isAbsoluteUrl($path)) {
            return 'absolute-url';
        }
        if (str_starts_with($path, '/')) {
            return 'absolute-path';
        }
        foreach (explode('/', $path) as $segment) {
            if ($segment === '..') {
                return 'traversal';
            }
        }

        return '';
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
        return ZipOpcPackage::normalizePath($path);
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
