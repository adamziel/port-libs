<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class EpubReader
{
    private const OPF_MEDIA_TYPE = 'application/oebps-package+xml';
    private const DC_NAMESPACE = 'http://purl.org/dc/elements/1.1/';

    public function __construct(private readonly array $options = [])
    {
    }

    public function read(string $bytes): AstNode
    {
        $path = tempnam(sys_get_temp_dir(), 'pandoc-epub-');
        if ($path === false) {
            throw new \RuntimeException('Unable to create temporary EPUB path.');
        }

        try {
            if (file_put_contents($path, $bytes) === false) {
                throw new \RuntimeException('Unable to write temporary EPUB package.');
            }

            return $this->readEpubFile($path);
        } finally {
            @unlink($path);
        }
    }

    public function readEpubFile(string $path): AstNode
    {
        if (!class_exists(\ZipArchive::class)) {
            throw new \RuntimeException('EPUB analysis needs PHP ZipArchive, which is unavailable in this runtime.');
        }

        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) {
            throw new \InvalidArgumentException("Unable to open EPUB package '{$path}'.");
        }

        try {
            $container_xml = $zip->getFromName('META-INF/container.xml');
            if (!is_string($container_xml)) {
                throw new \InvalidArgumentException('EPUB package is missing META-INF/container.xml.');
            }

            $rootfile = $this->rootfilePath($container_xml);
            $opf_xml = $zip->getFromName($rootfile);
            if (!is_string($opf_xml)) {
                throw new \InvalidArgumentException("EPUB package is missing OPF rootfile '{$rootfile}'.");
            }

            return $this->readPackage($zip, $rootfile, $opf_xml);
        } finally {
            $zip->close();
        }
    }

    private function readPackage(\ZipArchive $zip, string $rootfile, string $opf_xml): AstNode
    {
        $dom = $this->loadXml($opf_xml, 'EPUB OPF package');
        $package = $dom->documentElement;
        if (!$package instanceof \DOMElement || $package->localName !== 'package') {
            throw new \InvalidArgumentException('EPUB OPF root must be a package element.');
        }

        $base_path = $this->dirname($rootfile);
        $metadata = $this->metadata($package);
        $manifest = $this->manifest($package);
        $spine_ids = $this->spineIds($package);
        $toc = $this->toc($zip, $base_path, $manifest, $this->spineTocId($package));
        $children = [];
        $resources = [];
        $referenced_resources = [];
        $image_resources = $this->imageResources($base_path, $manifest);

        foreach ($spine_ids as $idref) {
            if (!isset($manifest[$idref])) {
                continue;
            }
            $item = $manifest[$idref];
            $href = $this->normalizeZipPath($base_path . '/' . $item['href']);
            $media_type = strtolower($item['media-type']);
            if (!str_contains($media_type, 'html') && !str_ends_with($href, '.xhtml') && !str_ends_with($href, '.html')) {
                continue;
            }
            $xhtml = $zip->getFromName($href);
            if (!is_string($xhtml)) {
                continue;
            }
            $resources[] = $href;
            $rewritten = $this->rewriteRelativeLinks($this->bodyMarkup($xhtml), $this->dirname($href), $referenced_resources);
            $document = (new MarkdownReader(['htmlNativeDivs' => true]))->read($rewritten);
            array_push($children, ...$document->children);
        }

        if ($children === []) {
            $children[] = new AstNode('paragraph', ['text' => 'No readable EPUB spine content was found.'], [
                new AstNode('text', ['text' => 'No readable EPUB spine content was found.']),
            ]);
        }

        $metadata['epubRootfile'] = $rootfile;
        $metadata['epubSpineItems'] = count($spine_ids);
        $metadata['epubReadableResources'] = $resources;
        $metadata['epubReferencedResources'] = array_values(array_unique($referenced_resources));
        $metadata['epubImageResources'] = $image_resources;
        $metadata['epubTocResources'] = $toc['resources'];
        $metadata['epubTocEntryCount'] = count($toc['entries']);
        $metadata['epubLandmarkEntryCount'] = count($toc['landmarks']);
        if ($toc['entries'] !== []) {
            $metadata['epubTocEntries'] = $toc['entries'];
        }
        if ($toc['landmarks'] !== []) {
            $metadata['epubLandmarkEntries'] = $toc['landmarks'];
        }

        return new AstNode('document', ['meta' => $metadata], $children);
    }

    private function rootfilePath(string $container_xml): string
    {
        $dom = $this->loadXml($container_xml, 'EPUB container');
        $xpath = new \DOMXPath($dom);
        $rootfiles = $xpath->query('//*[local-name()="rootfile"]');
        if (!$rootfiles instanceof \DOMNodeList) {
            throw new \InvalidArgumentException('EPUB container rootfile list cannot be read.');
        }

        $fallback = '';
        foreach ($rootfiles as $rootfile) {
            if (!$rootfile instanceof \DOMElement) {
                continue;
            }
            $path = trim($rootfile->getAttribute('full-path'));
            if ($path === '') {
                continue;
            }
            if ($fallback === '') {
                $fallback = $path;
            }
            if (trim($rootfile->getAttribute('media-type')) === self::OPF_MEDIA_TYPE) {
                return $this->normalizeZipPath($path);
            }
        }

        if ($fallback !== '') {
            return $this->normalizeZipPath($fallback);
        }

        throw new \InvalidArgumentException('EPUB container does not declare an OPF rootfile.');
    }

    /**
     * @return array<string, mixed>
     */
    private function metadata(\DOMElement $package): array
    {
        $meta = [];
        foreach ($package->childNodes as $child) {
            if (!$child instanceof \DOMElement || $child->localName !== 'metadata') {
                continue;
            }
            foreach ($child->childNodes as $entry) {
                if (!$entry instanceof \DOMElement) {
                    continue;
                }
                $name = $entry->localName;
                $text = trim(preg_replace('/\s+/', ' ', $entry->textContent) ?? $entry->textContent);
                if ($text === '') {
                    continue;
                }
                if ($entry->namespaceURI === self::DC_NAMESPACE || in_array($name, ['title', 'creator', 'date', 'language', 'identifier', 'subject', 'description'], true)) {
                    $key = match ($name) {
                        'creator' => 'author',
                        'language' => 'lang',
                        default => $name,
                    };
                    if (isset($meta[$key])) {
                        $meta[$key] = is_array($meta[$key]) ? array_merge($meta[$key], [$text]) : [$meta[$key], $text];
                    } else {
                        $meta[$key] = $text;
                    }
                    if ($key === 'title') {
                        $meta['titleInlines'] = [new AstNode('text', ['text' => $text])];
                    }
                    continue;
                }
                if ($name === 'meta' && trim($entry->getAttribute('property')) !== '') {
                    $meta['epubProperties'][trim($entry->getAttribute('property'))][] = $text;
                }
            }
        }

        return $meta;
    }

    /**
     * @return array<string, array{href: string, media-type: string, properties: list<string>}>
     */
    private function manifest(\DOMElement $package): array
    {
        $items = [];
        foreach ($package->getElementsByTagName('*') as $element) {
            if (!$element instanceof \DOMElement || $element->localName !== 'item') {
                continue;
            }
            $id = trim($element->getAttribute('id'));
            $href = trim($element->getAttribute('href'));
            if ($id === '' || $href === '') {
                continue;
            }
            $items[$id] = [
                'href' => html_entity_decode($href, ENT_QUOTES | ENT_XML1, 'UTF-8'),
                'media-type' => trim($element->getAttribute('media-type')),
                'properties' => array_values(array_filter(
                    preg_split('/\s+/', trim($element->getAttribute('properties'))) ?: [],
                    static fn (string $property): bool => $property !== ''
                )),
            ];
        }

        return $items;
    }

    /**
     * @param array<string, array{href: string, media-type: string, properties: list<string>}> $manifest
     * @return list<string>
     */
    private function imageResources(string $base_path, array $manifest): array
    {
        $resources = [];
        foreach ($manifest as $item) {
            $href = $this->normalizeZipPath($base_path . '/' . $item['href']);
            $media_type = strtolower($item['media-type']);
            if (str_starts_with($media_type, 'image/') || $this->pathLooksLikeImage($href)) {
                $resources[] = $href;
            }
        }

        return array_values(array_unique($resources));
    }

    /**
     * @return list<string>
     */
    private function spineIds(\DOMElement $package): array
    {
        $ids = [];
        foreach ($package->getElementsByTagName('*') as $element) {
            if (!$element instanceof \DOMElement || $element->localName !== 'itemref') {
                continue;
            }
            $idref = trim($element->getAttribute('idref'));
            if ($idref !== '') {
                $ids[] = $idref;
            }
        }

        return $ids;
    }

    private function spineTocId(\DOMElement $package): string
    {
        foreach ($package->getElementsByTagName('*') as $element) {
            if (!$element instanceof \DOMElement || $element->localName !== 'spine') {
                continue;
            }

            return trim($element->getAttribute('toc'));
        }

        return '';
    }

    /**
     * @param array<string, array{href: string, media-type: string, properties: list<string>}> $manifest
     * @return array{resources: list<string>, entries: list<array{text: string, href: string, level: int}>, landmarks: list<array{text: string, href: string, level: int, epubTypes: list<string>}>}
     */
    private function toc(\ZipArchive $zip, string $base_path, array $manifest, string $spine_toc_id): array
    {
        $resources = [];
        $nav_entries = [];
        $ncx_entries = [];
        $landmark_entries = [];
        foreach ($manifest as $id => $item) {
            $href = $this->normalizeZipPath($base_path . '/' . $item['href']);
            $media_type = strtolower($item['media-type']);
            $properties = array_map('strtolower', $item['properties']);
            $is_nav = in_array('nav', $properties, true);
            $is_ncx = $id === $spine_toc_id || str_contains($media_type, 'x-dtbncx') || str_ends_with(strtolower($href), '.ncx');
            if (!$is_nav && !$is_ncx) {
                continue;
            }

            $xml = $zip->getFromName($href);
            if (!is_string($xml)) {
                continue;
            }
            $resources[] = $href;
            try {
                if ($is_nav) {
                    array_push($nav_entries, ...$this->xhtmlTocEntries($xml, $this->dirname($href)));
                    array_push($landmark_entries, ...$this->xhtmlLandmarkEntries($xml, $this->dirname($href)));
                }
                if ($is_ncx) {
                    array_push($ncx_entries, ...$this->ncxTocEntries($xml, $this->dirname($href)));
                }
            } catch (\InvalidArgumentException) {
                continue;
            }
        }

        return [
            'resources' => array_values(array_unique($resources)),
            'entries' => $nav_entries !== [] ? $nav_entries : $ncx_entries,
            'landmarks' => $landmark_entries,
        ];
    }

    /**
     * @return list<array{text: string, href: string, level: int}>
     */
    private function xhtmlTocEntries(string $xml, string $base_path): array
    {
        $dom = $this->loadXml($xml, 'EPUB nav document');
        $navs = [];
        foreach ($dom->getElementsByTagName('*') as $element) {
            if (!$element instanceof \DOMElement || $element->localName !== 'nav') {
                continue;
            }
            $type = strtolower($this->epubTypeAttribute($element));
            if (preg_match('/(?:^|\s)toc(?:\s|$)/', $type) === 1) {
                $navs = [$element];
                break;
            }
            $navs[] = $element;
        }

        $entries = [];
        foreach ($navs as $nav) {
            array_push($entries, ...$this->xhtmlNavListEntries($nav, $base_path, 1));
        }

        return $entries;
    }

    /**
     * @return list<array{text: string, href: string, level: int, epubTypes: list<string>}>
     */
    private function xhtmlLandmarkEntries(string $xml, string $base_path): array
    {
        $dom = $this->loadXml($xml, 'EPUB nav document');
        $navs = [];
        foreach ($dom->getElementsByTagName('*') as $element) {
            if (!$element instanceof \DOMElement || $element->localName !== 'nav') {
                continue;
            }
            if ($this->hasToken($this->epubTypeAttribute($element), 'landmarks')) {
                $navs[] = $element;
            }
        }

        $entries = [];
        foreach ($navs as $nav) {
            array_push($entries, ...$this->xhtmlLandmarkListEntries($nav, $base_path, 1));
        }

        return $entries;
    }

    /**
     * @return list<array{text: string, href: string, level: int}>
     */
    private function xhtmlNavListEntries(\DOMNode $parent, string $base_path, int $level): array
    {
        $entries = [];
        foreach ($parent->childNodes as $child) {
            if (!$child instanceof \DOMElement) {
                continue;
            }
            if ($child->localName === 'li') {
                $entry = $this->xhtmlNavListItemEntry($child, $base_path, $level);
                if ($entry !== null) {
                    $entries[] = $entry;
                }
                foreach ($child->childNodes as $nested) {
                    if ($nested instanceof \DOMElement && in_array($nested->localName, ['ol', 'ul'], true)) {
                        array_push($entries, ...$this->xhtmlNavListEntries($nested, $base_path, $level + 1));
                    }
                }
                continue;
            }
            if (in_array($child->localName, ['ol', 'ul'], true)) {
                array_push($entries, ...$this->xhtmlNavListEntries($child, $base_path, $level));
                continue;
            }
            array_push($entries, ...$this->xhtmlNavListEntries($child, $base_path, $level));
        }

        return $entries;
    }

    /**
     * @return array{text: string, href: string, level: int}|null
     */
    private function xhtmlNavListItemEntry(\DOMElement $item, string $base_path, int $level): ?array
    {
        foreach ($item->getElementsByTagName('*') as $element) {
            if (!$element instanceof \DOMElement || $element->localName !== 'a') {
                continue;
            }
            $href = html_entity_decode($element->getAttribute('href'), ENT_QUOTES | ENT_XML1, 'UTF-8');
            $text = trim(preg_replace('/\s+/u', ' ', $element->textContent) ?? $element->textContent);
            if ($href === '' || $text === '') {
                return null;
            }

            return [
                'text' => $text,
                'href' => $this->rewriteRelativeResourceUrl($href, $base_path),
                'level' => $level,
            ];
        }

        return null;
    }

    /**
     * @return list<array{text: string, href: string, level: int, epubTypes: list<string>}>
     */
    private function xhtmlLandmarkListEntries(\DOMNode $parent, string $base_path, int $level): array
    {
        $entries = [];
        foreach ($parent->childNodes as $child) {
            if (!$child instanceof \DOMElement) {
                continue;
            }
            if ($child->localName === 'li') {
                $entry = $this->xhtmlLandmarkListItemEntry($child, $base_path, $level);
                if ($entry !== null) {
                    $entries[] = $entry;
                }
                foreach ($child->childNodes as $nested) {
                    if ($nested instanceof \DOMElement && in_array($nested->localName, ['ol', 'ul'], true)) {
                        array_push($entries, ...$this->xhtmlLandmarkListEntries($nested, $base_path, $level + 1));
                    }
                }
                continue;
            }
            if (in_array($child->localName, ['ol', 'ul'], true)) {
                array_push($entries, ...$this->xhtmlLandmarkListEntries($child, $base_path, $level));
                continue;
            }
            array_push($entries, ...$this->xhtmlLandmarkListEntries($child, $base_path, $level));
        }

        return $entries;
    }

    /**
     * @return array{text: string, href: string, level: int, epubTypes: list<string>}|null
     */
    private function xhtmlLandmarkListItemEntry(\DOMElement $item, string $base_path, int $level): ?array
    {
        foreach ($item->childNodes as $element) {
            if (!$element instanceof \DOMElement) {
                continue;
            }
            if (!in_array($element->localName, ['a', 'span'], true)) {
                continue;
            }
            $text = trim(preg_replace('/\s+/u', ' ', $element->textContent) ?? $element->textContent);
            if ($text === '') {
                return null;
            }
            $href = $element->localName === 'a'
                ? html_entity_decode($element->getAttribute('href'), ENT_QUOTES | ENT_XML1, 'UTF-8')
                : '';
            $epub_types = $this->tokenList($this->epubTypeAttribute($element));
            if ($epub_types === []) {
                $epub_types = $this->tokenList($this->epubTypeAttribute($item));
            }

            return [
                'text' => $text,
                'href' => $href === '' ? '' : $this->rewriteRelativeResourceUrl($href, $base_path),
                'level' => $level,
                'epubTypes' => $epub_types,
            ];
        }

        return null;
    }

    /**
     * @return list<array{text: string, href: string, level: int}>
     */
    private function ncxTocEntries(string $xml, string $base_path): array
    {
        $dom = $this->loadXml($xml, 'EPUB NCX table of contents');
        $navMap = null;
        foreach ($dom->getElementsByTagName('*') as $element) {
            if ($element instanceof \DOMElement && $element->localName === 'navMap') {
                $navMap = $element;
                break;
            }
        }

        return $navMap instanceof \DOMElement ? $this->ncxNavPointEntries($navMap, $base_path, 1) : [];
    }

    /**
     * @return list<array{text: string, href: string, level: int}>
     */
    private function ncxNavPointEntries(\DOMNode $parent, string $base_path, int $level): array
    {
        $entries = [];
        foreach ($parent->childNodes as $child) {
            if (!$child instanceof \DOMElement || $child->localName !== 'navPoint') {
                continue;
            }

            $text = trim(preg_replace('/\s+/u', ' ', $this->firstDescendantText($child, 'text')) ?? $this->firstDescendantText($child, 'text'));
            $content = $this->firstDescendantElement($child, 'content');
            $href = $content instanceof \DOMElement ? html_entity_decode($content->getAttribute('src'), ENT_QUOTES | ENT_XML1, 'UTF-8') : '';
            if ($text !== '' && $href !== '') {
                $entries[] = [
                    'text' => $text,
                    'href' => $this->rewriteRelativeResourceUrl($href, $base_path),
                    'level' => $level,
                ];
            }
            array_push($entries, ...$this->ncxNavPointEntries($child, $base_path, $level + 1));
        }

        return $entries;
    }

    private function bodyMarkup(string $xhtml): string
    {
        try {
            $dom = $this->loadXml($xhtml, 'EPUB XHTML content document');
        } catch (\InvalidArgumentException) {
            return $xhtml;
        }

        foreach ($dom->getElementsByTagName('*') as $element) {
            if (!$element instanceof \DOMElement || $element->localName !== 'body') {
                continue;
            }
            $parts = [];
            foreach ($element->childNodes as $child) {
                if ($child instanceof \DOMText && trim($child->textContent) === '') {
                    continue;
                }
                $serialized = $dom->saveXML($child);
                if (is_string($serialized) && trim($serialized) !== '') {
                    $parts[] = trim($serialized);
                }
            }

            return implode("\n", $parts);
        }

        return $xhtml;
    }

    /**
     * @param list<string> $referenced_resources
     */
    private function rewriteRelativeLinks(string $html, string $base_path, array &$referenced_resources): string
    {
        return preg_replace_callback('/\b(src|href)=(["\'])([^"\']+)\2/i', function (array $match) use ($base_path, &$referenced_resources): string {
            $url = html_entity_decode($match[3], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $rewritten = $this->rewriteRelativeResourceUrl($url, $base_path);
            if ($this->isPackageRelativeResourceUrl($url)) {
                $referenced_resources[] = $rewritten;
            }
            if ($rewritten === $url) {
                return $match[0];
            }

            return $match[1] . '=' . $match[2] . $rewritten . $match[2];
        }, $html) ?? $html;
    }

    private function rewriteRelativeResourceUrl(string $url, string $base_path): string
    {
        if (!$this->isPackageRelativeResourceUrl($url)) {
            return $url;
        }

        return $this->normalizeZipPath($base_path . '/' . $url);
    }

    private function isPackageRelativeResourceUrl(string $url): bool
    {
        return !$this->isAbsoluteUrl($url)
            && !str_starts_with($url, '#')
            && !str_starts_with(strtolower($url), 'data:')
            && !str_starts_with(strtolower($url), 'mailto:');
    }

    private function isAbsoluteUrl(string $url): bool
    {
        return (bool) preg_match('/^[a-z][a-z0-9+.-]*:/i', $url) || str_starts_with($url, '//');
    }

    private function pathLooksLikeImage(string $path): bool
    {
        return (bool) preg_match('/\.(?:apng|avif|bmp|gif|ico|jpe?g|png|svgz?|tiff?|webp)$/i', $path);
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

    private function attributeByLocalName(\DOMElement $element, string $name): string
    {
        if ($element->hasAttribute($name)) {
            return $element->getAttribute($name);
        }
        foreach ($element->attributes ?? [] as $attribute) {
            if ($attribute instanceof \DOMAttr && $attribute->localName === $name) {
                return $attribute->value;
            }
        }

        return '';
    }

    private function epubTypeAttribute(\DOMElement $element): string
    {
        foreach ($element->attributes ?? [] as $attribute) {
            if (!$attribute instanceof \DOMAttr || $attribute->localName !== 'type') {
                continue;
            }
            if ($attribute->prefix === 'epub' || $attribute->namespaceURI === 'http://www.idpf.org/2007/ops') {
                return $attribute->value;
            }
        }

        return $this->attributeByLocalName($element, 'type');
    }

    /**
     * @return list<string>
     */
    private function tokenList(string $value): array
    {
        return array_values(array_filter(
            preg_split('/\s+/', strtolower(trim($value))) ?: [],
            static fn (string $token): bool => $token !== ''
        ));
    }

    private function hasToken(string $value, string $token): bool
    {
        return in_array(strtolower($token), $this->tokenList($value), true);
    }

    private function firstDescendantText(\DOMElement $element, string $localName): string
    {
        $descendant = $this->firstDescendantElement($element, $localName);

        return $descendant instanceof \DOMElement ? $descendant->textContent : '';
    }

    private function firstDescendantElement(\DOMElement $element, string $localName): ?\DOMElement
    {
        foreach ($element->getElementsByTagName('*') as $descendant) {
            if ($descendant instanceof \DOMElement && $descendant->localName === $localName) {
                return $descendant;
            }
        }

        return null;
    }

    private function dirname(string $path): string
    {
        $dir = str_replace('\\', '/', dirname($path));
        return $dir === '.' ? '' : $dir;
    }

    private function normalizeZipPath(string $path): string
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
}
