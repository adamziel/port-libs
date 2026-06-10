<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class EpubPackageReader
{
    private const EPUB_TYPE_NS = 'http://www.idpf.org/2007/ops';

    public function readDirectory(string $directory): AstNode
    {
        $root = realpath($directory);
        if ($root === false || !is_dir($root)) {
            throw new \RuntimeException('EPUB package directory does not exist: ' . $directory);
        }

        $rootfile = $this->readContainerRootfile($root);
        $opfPath = $this->resolveExistingPackagePath($root, $rootfile);
        $package = $this->readPackageDocument($root, $opfPath, $rootfile);
        $toc = $this->readNavigationDocument($root, $package);
        $ncx = $this->readNcxDocument($root, $package);
        $children = [];

        foreach ($package['spine'] as $spineItem) {
            if (($spineItem['mediaType'] ?? '') !== 'application/xhtml+xml' || ($spineItem['linear'] ?? true) !== true) {
                continue;
            }

            $path = (string) ($spineItem['path'] ?? '');
            if ($path === '') {
                continue;
            }

            array_push($children, ...$this->readXhtmlDocument($root, $path));
        }

        return new AstNode('document', [
            'meta' => $package['metadata'],
            'epub' => [
                'containerRootfile' => $rootfile,
                'packageVersion' => $package['version'],
                'uniqueIdentifierId' => $package['uniqueIdentifierId'],
                'metadataProperties' => $package['metadataProperties'],
                'manifest' => array_values($package['manifest']),
                'manifestById' => $package['manifest'],
                'spineTocId' => $package['spineTocId'],
                'spine' => $package['spine'],
                'toc' => $toc,
                'ncx' => $ncx,
            ],
        ], $children);
    }

    private function readContainerRootfile(string $root): string
    {
        $path = $root . DIRECTORY_SEPARATOR . 'META-INF' . DIRECTORY_SEPARATOR . 'container.xml';
        $document = $this->loadXmlFile($path);
        $xpath = new \DOMXPath($document);
        $rootfile = $xpath->query('/*[local-name()="container"]/*[local-name()="rootfiles"]/*[local-name()="rootfile"][1]');
        $element = $rootfile instanceof \DOMNodeList ? $rootfile->item(0) : null;
        if (!$element instanceof \DOMElement) {
            throw new \RuntimeException('EPUB container.xml does not contain a rootfile');
        }

        $fullPath = trim($element->getAttribute('full-path'));
        if ($fullPath === '') {
            throw new \RuntimeException('EPUB rootfile is missing a full-path');
        }

        return $this->normalizeRelativePath($fullPath);
    }

    /**
     * @return array{
     *     version:string,
     *     uniqueIdentifierId:string,
     *     metadata:array<string, mixed>,
     *     metadataProperties:list<array{property:string, value:string, refines:string}>,
     *     manifest:array<string, array{id:string, href:string, path:string, mediaType:string, properties:list<string>}>,
     *     spineTocId:string,
     *     spine:list<array{idref:string, href:string, path:string, mediaType:string, linear:bool, properties:list<string>}>
     * }
     */
    private function readPackageDocument(string $root, string $opfPath, string $rootfile): array
    {
        $document = $this->loadXmlFile($opfPath);
        $xpath = new \DOMXPath($document);
        $package = $xpath->query('/*[local-name()="package"][1]');
        $packageElement = $package instanceof \DOMNodeList ? $package->item(0) : null;
        if (!$packageElement instanceof \DOMElement) {
            throw new \RuntimeException('OPF package document is missing package element');
        }

        $opfDir = $this->relativeDirname($rootfile);
        $metadata = [
            'title' => '',
            'creators' => [],
            'language' => '',
            'identifier' => '',
            'date' => '',
            'publisher' => '',
        ];
        $metadataProperties = [];
        $metadataNodes = $xpath->query('./*[local-name()="metadata"]/*', $packageElement);
        if ($metadataNodes instanceof \DOMNodeList) {
            foreach ($metadataNodes as $node) {
                if (!$node instanceof \DOMElement) {
                    continue;
                }
                $name = $node->localName;
                $value = $this->normalizedText($node->textContent);
                if ($value === '') {
                    continue;
                }
                if ($name === 'title' && $metadata['title'] === '') {
                    $metadata['title'] = $value;
                } elseif ($name === 'creator') {
                    $metadata['creators'][] = $value;
                } elseif ($name === 'language' && $metadata['language'] === '') {
                    $metadata['language'] = $value;
                } elseif ($name === 'identifier' && $metadata['identifier'] === '') {
                    $metadata['identifier'] = $value;
                } elseif ($name === 'date' && $metadata['date'] === '') {
                    $metadata['date'] = $value;
                } elseif ($name === 'publisher' && $metadata['publisher'] === '') {
                    $metadata['publisher'] = $value;
                } elseif ($name === 'meta') {
                    $metadataProperties[] = [
                        'property' => trim($node->getAttribute('property')),
                        'value' => $value,
                        'refines' => trim($node->getAttribute('refines')),
                    ];
                }
            }
        }

        $manifest = [];
        $manifestNodes = $xpath->query('./*[local-name()="manifest"]/*[local-name()="item"]', $packageElement);
        if ($manifestNodes instanceof \DOMNodeList) {
            foreach ($manifestNodes as $node) {
                if (!$node instanceof \DOMElement) {
                    continue;
                }
                $id = trim($node->getAttribute('id'));
                $href = trim($node->getAttribute('href'));
                if ($id === '' || $href === '') {
                    continue;
                }
                $manifest[$id] = [
                    'id' => $id,
                    'href' => $href,
                    'path' => $this->resolvePackageHref($opfDir, $href),
                    'mediaType' => trim($node->getAttribute('media-type')),
                    'properties' => $this->tokens($node->getAttribute('properties')),
                ];
            }
        }

        $spine = [];
        $spineTocId = '';
        $spineElements = $xpath->query('./*[local-name()="spine"][1]', $packageElement);
        $spineElement = $spineElements instanceof \DOMNodeList ? $spineElements->item(0) : null;
        if ($spineElement instanceof \DOMElement) {
            $spineTocId = trim($spineElement->getAttribute('toc'));
            $spineNodes = $xpath->query('./*[local-name()="itemref"]', $spineElement);
            if ($spineNodes instanceof \DOMNodeList) {
                foreach ($spineNodes as $node) {
                    if (!$node instanceof \DOMElement) {
                        continue;
                    }
                    $idref = trim($node->getAttribute('idref'));
                    $item = $manifest[$idref] ?? null;
                    $spine[] = [
                        'idref' => $idref,
                        'href' => is_array($item) ? $item['href'] : '',
                        'path' => is_array($item) ? $item['path'] : '',
                        'mediaType' => is_array($item) ? $item['mediaType'] : '',
                        'linear' => strtolower(trim($node->getAttribute('linear'))) !== 'no',
                        'properties' => $this->tokens($node->getAttribute('properties')),
                    ];
                }
            }
        }

        return [
            'version' => trim($packageElement->getAttribute('version')),
            'uniqueIdentifierId' => trim($packageElement->getAttribute('unique-identifier')),
            'metadata' => $metadata,
            'metadataProperties' => $metadataProperties,
            'manifest' => $manifest,
            'spineTocId' => $spineTocId,
            'spine' => $spine,
        ];
    }

    /**
     * @param array{manifest:array<string, array{id:string, href:string, path:string, mediaType:string, properties:list<string>}>} $package
     * @return list<array{label:string, href:string, path:string, fragment:string, type:string, children:list<array<string, mixed>>}>
     */
    private function readNavigationDocument(string $root, array $package): array
    {
        $navItem = null;
        foreach ($package['manifest'] as $item) {
            if (in_array('nav', $item['properties'], true)) {
                $navItem = $item;
                break;
            }
        }
        if (!is_array($navItem)) {
            return [];
        }

        $document = $this->loadXmlFile($this->resolveExistingPackagePath($root, $navItem['path']));
        $navDir = $this->relativeDirname($navItem['path']);
        $entries = [];
        foreach ($document->getElementsByTagName('*') as $element) {
            if (!$element instanceof \DOMElement || $element->localName !== 'nav') {
                continue;
            }

            $type = $this->epubType($element);
            if ($type !== 'toc' && $type !== 'landmarks' && $type !== 'page-list') {
                continue;
            }

            $ol = $this->firstDirectChild($element, 'ol');
            if (!$ol instanceof \DOMElement) {
                continue;
            }

            foreach ($this->readNavList($ol, $navDir, $type) as $entry) {
                $entries[] = $entry;
            }
        }

        return $entries;
    }

    /**
     * @param array{manifest:array<string, array{id:string, href:string, path:string, mediaType:string, properties:list<string>}>, spineTocId:string, spine:list<array{idref:string}>} $package
     * @return list<array{label:string, href:string, path:string, fragment:string, playOrder:int, children:list<array<string, mixed>>}>
     */
    private function readNcxDocument(string $root, array $package): array
    {
        $ncxItem = null;
        $spineTocId = trim($package['spineTocId']);
        if ($spineTocId !== '') {
            $boundItem = $package['manifest'][$spineTocId] ?? null;
            if (is_array($boundItem) && $boundItem['mediaType'] === 'application/x-dtbncx+xml') {
                $ncxItem = $boundItem;
            }
        }

        if (!is_array($ncxItem)) {
            foreach ($package['manifest'] as $item) {
                if ($item['mediaType'] === 'application/x-dtbncx+xml') {
                    $ncxItem = $item;
                    break;
                }
            }
        }

        if (!is_array($ncxItem)) {
            return [];
        }

        $document = $this->loadXmlFile($this->resolveExistingPackagePath($root, $ncxItem['path']));
        $xpath = new \DOMXPath($document);
        $navMap = $xpath->query('/*[local-name()="ncx"]/*[local-name()="navMap"][1]');
        $navMapElement = $navMap instanceof \DOMNodeList ? $navMap->item(0) : null;
        if (!$navMapElement instanceof \DOMElement) {
            return [];
        }

        return $this->readNcxPoints($navMapElement, $this->relativeDirname($ncxItem['path']));
    }

    /**
     * @return list<AstNode>
     */
    private function readXhtmlDocument(string $root, string $path): array
    {
        $document = $this->loadXmlFile($this->resolveExistingPackagePath($root, $path));
        $body = $this->firstElementByLocalName($document, 'body');
        if (!$body instanceof \DOMElement) {
            return [];
        }

        return $this->blockNodesFromChildren($body, $this->relativeDirname($path));
    }

    /**
     * @return list<AstNode>
     */
    private function blockNodesFromChildren(\DOMNode $parent, string $baseDir): array
    {
        $blocks = [];
        foreach ($parent->childNodes as $child) {
            if ($child instanceof \DOMText) {
                $text = $this->normalizedText($child->wholeText);
                if ($text !== '') {
                    $blocks[] = new AstNode('paragraph', ['text' => $text], [new AstNode('text', ['text' => $text])]);
                }
                continue;
            }
            if ($child instanceof \DOMElement) {
                array_push($blocks, ...$this->blockNodesFromElement($child, $baseDir));
            }
        }

        return $blocks;
    }

    /**
     * @return list<AstNode>
     */
    private function blockNodesFromElement(\DOMElement $element, string $baseDir): array
    {
        $name = $element->localName;
        if (preg_match('/^h([1-6])$/', $name, $matches) === 1) {
            $children = $this->inlineNodesFromChildren($element, $baseDir);
            return [new AstNode('heading', [
                'level' => (int) $matches[1],
                'text' => $this->plainInlineText($children),
                'id' => trim($element->getAttribute('id')),
                'htmlAttributes' => $this->htmlAttributes($element),
            ], $children)];
        }

        if ($name === 'p') {
            $children = $this->inlineNodesFromChildren($element, $baseDir);
            return [new AstNode('paragraph', [
                'text' => $this->plainInlineText($children),
                'htmlAttributes' => $this->htmlAttributes($element),
            ], $children)];
        }

        if ($name === 'ul' || $name === 'ol') {
            return [$this->listNode($element, $baseDir, $name === 'ol')];
        }

        if ($name === 'blockquote') {
            return [new AstNode('blockquote', [], $this->blockNodesFromChildren($element, $baseDir))];
        }

        if ($name === 'pre') {
            $code = $this->firstDescendantByLocalName($element, 'code');
            return [new AstNode('code_block', [
                'text' => $code instanceof \DOMElement ? $code->textContent : $element->textContent,
                'classes' => $code instanceof \DOMElement ? $this->classList($code) : $this->classList($element),
            ])];
        }

        if ($name === 'figure') {
            $image = $this->firstDescendantByLocalName($element, 'img');
            if ($image instanceof \DOMElement) {
                $caption = $this->firstDescendantByLocalName($element, 'figcaption');
                $imageNode = $this->imageNode($image, $baseDir);
                return [new AstNode('figure', [
                    'caption' => $caption instanceof \DOMElement ? $this->normalizedText($caption->textContent) : (string) $imageNode->attr('alt', ''),
                    'htmlAttributes' => $this->htmlAttributes($element),
                ], [$imageNode])];
            }
        }

        if ($name === 'section' || $name === 'article' || $name === 'main' || $name === 'body') {
            return $this->blockNodesFromChildren($element, $baseDir);
        }

        if ($name === 'img') {
            $image = $this->imageNode($element, $baseDir);
            return [new AstNode('paragraph', ['text' => (string) $image->attr('alt', '')], [$image])];
        }

        $children = $this->inlineNodesFromChildren($element, $baseDir);
        if ($children !== []) {
            return [new AstNode('paragraph', ['text' => $this->plainInlineText($children)], $children)];
        }

        return $this->blockNodesFromChildren($element, $baseDir);
    }

    private function listNode(\DOMElement $element, string $baseDir, bool $ordered): AstNode
    {
        $items = [];
        foreach ($element->childNodes as $child) {
            if (!$child instanceof \DOMElement || $child->localName !== 'li') {
                continue;
            }

            $blocks = $this->blockNodesFromChildren($child, $baseDir);
            if ($blocks === []) {
                $children = $this->inlineNodesFromChildren($child, $baseDir);
                $blocks = [new AstNode('paragraph', ['text' => $this->plainInlineText($children)], $children)];
            }
            $items[] = new AstNode('list_item', [], $blocks);
        }

        return new AstNode($ordered ? 'ordered_list' : 'bullet_list', [
            'start' => $ordered && is_numeric($element->getAttribute('start')) ? (int) $element->getAttribute('start') : 1,
            'style' => $ordered ? $this->orderedListStyle($element) : 'default',
        ], $items);
    }

    /**
     * @return list<AstNode>
     */
    private function inlineNodesFromChildren(\DOMNode $parent, string $baseDir): array
    {
        $nodes = [];
        foreach ($parent->childNodes as $child) {
            array_push($nodes, ...$this->inlineNodesFromNode($child, $baseDir));
        }

        return $this->normalizeInlineTextNodes($nodes);
    }

    /**
     * @return list<AstNode>
     */
    private function inlineNodesFromNode(\DOMNode $node, string $baseDir): array
    {
        if ($node instanceof \DOMText) {
            $text = preg_replace('/\s+/u', ' ', $node->wholeText) ?? $node->wholeText;
            return $text === '' ? [] : [new AstNode('text', ['text' => $text])];
        }
        if (!$node instanceof \DOMElement) {
            return [];
        }

        $name = $node->localName;
        $children = $this->inlineNodesFromChildren($node, $baseDir);

        return match ($name) {
            'em', 'i' => [new AstNode('emph', [], $children)],
            'strong', 'b' => [new AstNode('strong', [], $children)],
            'code' => [new AstNode('code', ['text' => $node->textContent, 'classes' => $this->classList($node)])],
            'br' => [new AstNode('linebreak')],
            'a' => [new AstNode('link', [
                'url' => $this->resolveContentHref($baseDir, $node->getAttribute('href')),
                'title' => trim($node->getAttribute('title')),
                'htmlAttributes' => $this->htmlAttributes($node),
            ], $children === [] ? [new AstNode('text', ['text' => $this->normalizedText($node->textContent)])] : $children)],
            'img' => [$this->imageNode($node, $baseDir)],
            'sup' => [new AstNode('superscript', [], $children)],
            'sub' => [new AstNode('subscript', [], $children)],
            'span' => [new AstNode('span', ['htmlAttributes' => $this->htmlAttributes($node)], $children)],
            default => $children,
        };
    }

    private function imageNode(\DOMElement $element, string $baseDir): AstNode
    {
        $alt = trim($element->getAttribute('alt'));

        return new AstNode('image', [
            'url' => $this->resolveContentHref($baseDir, $element->getAttribute('src')),
            'alt' => $alt,
            'title' => trim($element->getAttribute('title')),
            'htmlAttributes' => $this->htmlAttributes($element),
        ], $alt === '' ? [] : [new AstNode('text', ['text' => $alt])]);
    }

    /**
     * @param list<AstNode> $nodes
     * @return list<AstNode>
     */
    private function normalizeInlineTextNodes(array $nodes): array
    {
        $normalized = [];
        foreach ($nodes as $node) {
            if ($node->type === 'text' && $normalized !== [] && end($normalized)->type === 'text') {
                $previous = array_pop($normalized);
                $normalized[] = new AstNode('text', [
                    'text' => (string) $previous->attr('text', '') . (string) $node->attr('text', ''),
                ]);
                continue;
            }

            $normalized[] = $node;
        }

        if ($normalized !== [] && $normalized[0]->type === 'text') {
            $text = ltrim((string) $normalized[0]->attr('text', ''));
            if ($text === '') {
                array_shift($normalized);
            } else {
                $normalized[0] = new AstNode('text', ['text' => $text]);
            }
        }

        $last = count($normalized) - 1;
        if ($last >= 0 && $normalized[$last]->type === 'text') {
            $text = rtrim((string) $normalized[$last]->attr('text', ''));
            if ($text === '') {
                array_pop($normalized);
            } else {
                $normalized[$last] = new AstNode('text', ['text' => $text]);
            }
        }

        return $normalized;
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
                'linebreak', 'softbreak' => "\n",
                'image' => (string) $node->attr('alt', ''),
                default => $this->plainInlineText($node->children),
            };
        }

        return $this->normalizedText($text);
    }

    /**
     * @return list<array{label:string, href:string, path:string, fragment:string, type:string, children:list<array<string, mixed>>}>
     */
    private function readNavList(\DOMElement $ol, string $baseDir, string $type): array
    {
        $entries = [];
        foreach ($ol->childNodes as $node) {
            if (!$node instanceof \DOMElement || $node->localName !== 'li') {
                continue;
            }

            $link = $this->firstDirectChild($node, 'a') ?? $this->firstDirectChild($node, 'span');
            if (!$link instanceof \DOMElement) {
                continue;
            }

            $href = $link->localName === 'a' ? trim($link->getAttribute('href')) : '';
            [$path, $fragment] = $this->splitResolvedHref($baseDir, $href);
            $children = [];
            foreach ($node->childNodes as $child) {
                if ($child instanceof \DOMElement && $child->localName === 'ol') {
                    $children = $this->readNavList($child, $baseDir, $type);
                    break;
                }
            }

            $entries[] = [
                'label' => $this->normalizedText($link->textContent),
                'href' => $href,
                'path' => $path,
                'fragment' => $fragment,
                'type' => $type,
                'children' => $children,
            ];
        }

        return $entries;
    }

    /**
     * @return list<array{label:string, href:string, path:string, fragment:string, playOrder:int, children:list<array<string, mixed>>}>
     */
    private function readNcxPoints(\DOMElement $parent, string $baseDir): array
    {
        $points = [];
        foreach ($parent->childNodes as $node) {
            if (!$node instanceof \DOMElement || $node->localName !== 'navPoint') {
                continue;
            }

            $label = $this->firstChildPathText($node, ['navLabel', 'text']);
            $content = $this->firstDirectChild($node, 'content');
            $href = $content instanceof \DOMElement ? trim($content->getAttribute('src')) : '';
            [$path, $fragment] = $this->splitResolvedHref($baseDir, $href);
            $points[] = [
                'label' => $label,
                'href' => $href,
                'path' => $path,
                'fragment' => $fragment,
                'playOrder' => is_numeric($node->getAttribute('playOrder')) ? (int) $node->getAttribute('playOrder') : 0,
                'children' => $this->readNcxPoints($node, $baseDir),
            ];
        }

        return $points;
    }

    /**
     * @param list<string> $path
     */
    private function firstChildPathText(\DOMElement $element, array $path): string
    {
        $current = $element;
        foreach ($path as $name) {
            $next = $this->firstDirectChild($current, $name);
            if (!$next instanceof \DOMElement) {
                return '';
            }
            $current = $next;
        }

        return $this->normalizedText($current->textContent);
    }

    private function firstDirectChild(\DOMNode $node, string $localName): ?\DOMElement
    {
        foreach ($node->childNodes as $child) {
            if ($child instanceof \DOMElement && $child->localName === $localName) {
                return $child;
            }
        }

        return null;
    }

    private function firstDescendantByLocalName(\DOMElement $element, string $name): ?\DOMElement
    {
        foreach ($element->getElementsByTagName('*') as $child) {
            if ($child instanceof \DOMElement && $child->localName === $name) {
                return $child;
            }
        }

        return null;
    }

    private function firstElementByLocalName(\DOMDocument $document, string $name): ?\DOMElement
    {
        foreach ($document->getElementsByTagName('*') as $element) {
            if ($element instanceof \DOMElement && $element->localName === $name) {
                return $element;
            }
        }

        return null;
    }

    /**
     * @return array<string, string>
     */
    private function htmlAttributes(\DOMElement $element): array
    {
        $attrs = [];
        foreach ($element->attributes ?? [] as $attr) {
            if (!$attr instanceof \DOMAttr) {
                continue;
            }
            $name = $attr->name;
            if (str_starts_with($name, 'xmlns')) {
                continue;
            }
            $attrs[$name] = $attr->value;
        }

        return $attrs;
    }

    /**
     * @return list<string>
     */
    private function classList(\DOMElement $element): array
    {
        return $this->tokens($element->getAttribute('class'));
    }

    private function orderedListStyle(\DOMElement $element): string
    {
        return match ($element->getAttribute('type')) {
            'a' => 'lower_alpha',
            'A' => 'upper_alpha',
            'i' => 'lower_roman',
            'I' => 'upper_roman',
            default => 'decimal',
        };
    }

    private function epubType(\DOMElement $element): string
    {
        $value = trim($element->getAttributeNS(self::EPUB_TYPE_NS, 'type'));
        if ($value === '') {
            $value = trim($element->getAttribute('epub:type'));
        }
        if ($value === '') {
            $value = trim($element->getAttribute('type'));
        }

        foreach ($this->tokens($value) as $token) {
            if ($token === 'toc' || $token === 'landmarks' || $token === 'page-list') {
                return $token;
            }
        }

        return '';
    }

    /**
     * @return list<string>
     */
    private function tokens(string $value): array
    {
        $tokens = preg_split('/\s+/', trim($value)) ?: [];

        return array_values(array_filter($tokens, static fn (string $token): bool => $token !== ''));
    }

    private function loadXmlFile(string $path): \DOMDocument
    {
        if (!is_file($path)) {
            throw new \RuntimeException('EPUB XML asset does not exist: ' . $path);
        }

        $xml = file_get_contents($path);
        if ($xml === false) {
            throw new \RuntimeException('Unable to read EPUB XML asset: ' . $path);
        }

        $previous = libxml_use_internal_errors(true);
        libxml_clear_errors();
        $document = new \DOMDocument();
        $document->preserveWhiteSpace = false;
        $loaded = $document->loadXML($xml, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        $errors = libxml_get_errors();
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (!$loaded) {
            $message = $errors === [] ? 'unknown XML parse error' : trim($errors[0]->message);
            throw new \RuntimeException('Unable to parse EPUB XML asset ' . $path . ': ' . $message);
        }

        return $document;
    }

    private function resolveExistingPackagePath(string $root, string $relative): string
    {
        $normalized = $this->normalizeRelativePath($relative);
        $absolute = realpath($root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $normalized));
        if ($absolute === false || !str_starts_with($absolute, $root . DIRECTORY_SEPARATOR)) {
            throw new \RuntimeException('EPUB package path escapes the package root: ' . $relative);
        }

        return $absolute;
    }

    private function resolvePackageHref(string $baseDir, string $href): string
    {
        $withoutFragment = explode('#', $href, 2)[0];
        $withoutQuery = explode('?', $withoutFragment, 2)[0];
        if ($withoutQuery === '' || preg_match('/^[A-Za-z][A-Za-z0-9+.-]*:/', $withoutQuery) === 1) {
            return $withoutQuery;
        }

        return $this->normalizeRelativePath($baseDir . '/' . rawurldecode($withoutQuery));
    }

    private function resolveContentHref(string $baseDir, string $href): string
    {
        $href = trim($href);
        if ($href === '' || preg_match('/^[A-Za-z][A-Za-z0-9+.-]*:/', $href) === 1 || str_starts_with($href, '#')) {
            return $href;
        }

        [$path, $fragment] = $this->splitResolvedHref($baseDir, $href);

        return $path . ($fragment === '' ? '' : '#' . $fragment);
    }

    /**
     * @return array{0:string, 1:string}
     */
    private function splitResolvedHref(string $baseDir, string $href): array
    {
        $parts = explode('#', $href, 2);
        $path = $parts[0] === '' ? '' : $this->resolvePackageHref($baseDir, $parts[0]);

        return [$path, $parts[1] ?? ''];
    }

    private function normalizeRelativePath(string $path): string
    {
        $path = str_replace('\\', '/', trim($path));
        $parts = [];
        foreach (explode('/', $path) as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }
            if ($part === '..') {
                if ($parts === []) {
                    throw new \RuntimeException('EPUB relative path escapes package root: ' . $path);
                }
                array_pop($parts);
                continue;
            }
            $parts[] = $part;
        }

        return implode('/', $parts);
    }

    private function relativeDirname(string $path): string
    {
        $dir = str_replace('\\', '/', dirname($path));

        return $dir === '.' ? '' : $dir;
    }

    private function normalizedText(string $text): string
    {
        return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    }
}
