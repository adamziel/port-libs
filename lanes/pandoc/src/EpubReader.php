<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class EpubReader
{
    public const MIMETYPE = 'application/epub+zip';
    public const OCF_CONTAINER_NS = 'urn:oasis:names:tc:opendocument:xmlns:container';
    public const OPF_NS = 'http://www.idpf.org/2007/opf';
    public const DC_NS = 'http://purl.org/dc/elements/1.1/';
    public const XHTML_NS = 'http://www.w3.org/1999/xhtml';
    public const EPUB_OPS_NS = 'http://www.idpf.org/2007/ops';
    public const NCX_NS = 'http://www.daisy.org/z3986/2005/ncx/';
    public const XMLENC_NS = 'http://www.w3.org/2001/04/xmlenc#';
    public const OPF_MEDIA_TYPE = 'application/oebps-package+xml';
    public const XHTML_MEDIA_TYPE = 'application/xhtml+xml';
    public const NCX_MEDIA_TYPE = 'application/x-dtbncx+xml';
    public const IDPF_FONT_OBFUSCATION_ALGORITHM = 'http://www.idpf.org/2008/embedding';

    /**
     * @return array{
     *     document:AstNode,
     *     metadata:array<string, mixed>,
     *     container:array<string, mixed>,
     *     opfPart:string,
     *     package:array<string, mixed>,
     *     manifest:list<array<string, mixed>>,
     *     spine:list<array<string, mixed>>,
     *     nav:?array<string, mixed>,
     *     ncx:?array<string, mixed>,
     *     encryption:array<string, mixed>,
     *     xhtmlAssets:list<array<string, mixed>>,
     *     assets:list<array<string, mixed>>,
     *     importReport:array<string, mixed>
     * }
     */
    public function readPackage(ZipPackage $package): array
    {
        $this->assertEpubMimetype($package);
        $container = $this->readContainer($package);
        $opfPart = (string) $container['opfPart'];

        $opf = $this->readOpf($package, $opfPart);
        $document = $this->documentNode(
            $opf['metadata'],
            $opfPart,
            $opf['spine'],
            $opf['xhtmlAssets']
        );

        return [
            'document' => $document,
            'metadata' => $opf['metadata'],
            'container' => $container,
            'opfPart' => $opfPart,
            'package' => $opf['package'],
            'manifest' => $opf['manifest'],
            'spine' => $opf['spine'],
            'nav' => $opf['nav'],
            'ncx' => $opf['ncx'],
            'encryption' => $opf['encryption'],
            'xhtmlAssets' => $opf['xhtmlAssets'],
            'assets' => $opf['assets'],
            'importReport' => [
                'container' => $container,
                'metadata' => $opf['metadata'],
                'package' => $opf['package'],
                'manifest' => [
                    'count' => count($opf['manifest']),
                    'items' => $opf['manifest'],
                    'missingItems' => array_values(array_filter(
                        $opf['manifest'],
                        static fn (array $item): bool => ($item['exists'] ?? false) !== true,
                    )),
                ],
                'spine' => [
                    'count' => count($opf['spine']),
                    'items' => $opf['spine'],
                ],
                'nav' => $opf['nav'],
                'ncx' => $opf['ncx'],
                'encryption' => $opf['encryption'],
                'assets' => [
                    'count' => count($opf['assets']),
                    'items' => $opf['assets'],
                ],
            ],
        ];
    }

    public function readDocument(ZipPackage $package): AstNode
    {
        return $this->readPackage($package)['document'];
    }

    private function assertEpubMimetype(ZipPackage $package): void
    {
        if (!$package->has('mimetype')) {
            throw new \RuntimeException('EPUB package is missing the root mimetype entry');
        }

        $entries = $package->entries();
        if ($entries === [] || $entries[0]->name !== 'mimetype') {
            throw new \RuntimeException('EPUB mimetype entry must be the first ZIP entry');
        }

        $mimetypeEntry = $package->entry('mimetype');
        if ($mimetypeEntry->compressionMethod !== 0) {
            throw new \RuntimeException('EPUB mimetype entry must be stored without compression');
        }

        if ($mimetypeEntry->centralExtraFields() !== [] || $package->localExtraFields('mimetype') !== []) {
            throw new \RuntimeException('EPUB mimetype entry must not carry ZIP extra fields');
        }

        if ($package->read('mimetype') !== self::MIMETYPE) {
            throw new \RuntimeException('EPUB mimetype entry must be application/epub+zip');
        }
    }

    /**
     * @return array{opfPart:string, rootfiles:list<array{path:string, mediaType:string, exists:bool}>}
     */
    private function readContainer(ZipPackage $package): array
    {
        if (!$package->has('META-INF/container.xml')) {
            throw new \RuntimeException('EPUB package is missing META-INF/container.xml');
        }

        $dom = self::loadXml($package->read('META-INF/container.xml'), 'EPUB container XML');
        $root = $dom->documentElement;
        if (!$root instanceof \DOMElement || $root->localName !== 'container' || $root->namespaceURI !== self::OCF_CONTAINER_NS) {
            throw new \InvalidArgumentException('EPUB container XML must use the OCF container namespace');
        }

        $rootfilesElement = self::firstChildElement($root, 'rootfiles', self::OCF_CONTAINER_NS);
        if (!$rootfilesElement instanceof \DOMElement) {
            throw new \RuntimeException('EPUB container XML is missing rootfiles');
        }

        $rootfiles = [];
        foreach (self::childElements($rootfilesElement, 'rootfile', self::OCF_CONTAINER_NS) as $rootfile) {
            $path = trim($rootfile->getAttribute('full-path'));
            $mediaType = trim($rootfile->getAttribute('media-type'));
            if ($path === '') {
                throw new \RuntimeException('EPUB rootfile is missing full-path');
            }

            $part = OpcPackagePath::canonicalPartName($path);
            $rootfiles[] = [
                'path' => $part,
                'mediaType' => $mediaType,
                'exists' => $package->has($part),
            ];
        }

        if ($rootfiles === []) {
            throw new \RuntimeException('EPUB container XML does not list an OPF rootfile');
        }

        $selected = null;
        foreach ($rootfiles as $rootfile) {
            if ($rootfile['mediaType'] === self::OPF_MEDIA_TYPE) {
                $selected = $rootfile;
                break;
            }
        }
        $selected ??= $rootfiles[0];

        if ($selected['mediaType'] !== self::OPF_MEDIA_TYPE) {
            throw new \RuntimeException('EPUB rootfile media-type must be application/oebps-package+xml');
        }

        if ($selected['exists'] !== true) {
            throw new \RuntimeException('EPUB OPF rootfile is missing from the package: ' . $selected['path']);
        }

        return [
            'opfPart' => $selected['path'],
            'rootfiles' => $rootfiles,
        ];
    }

    /**
     * @return array{
     *     metadata:array<string, mixed>,
     *     package:array<string, mixed>,
     *     manifest:list<array<string, mixed>>,
     *     spine:list<array<string, mixed>>,
     *     nav:?array<string, mixed>,
     *     ncx:?array<string, mixed>,
     *     encryption:array<string, mixed>,
     *     xhtmlAssets:list<array<string, mixed>>,
     *     assets:list<array<string, mixed>>
     * }
     */
    private function readOpf(ZipPackage $package, string $opfPart): array
    {
        $dom = self::loadXml($package->read($opfPart), 'EPUB OPF package XML');
        $root = $dom->documentElement;
        if (!$root instanceof \DOMElement || $root->localName !== 'package' || $root->namespaceURI !== self::OPF_NS) {
            throw new \InvalidArgumentException('EPUB OPF root must be an OPF package element');
        }

        $metadataElement = self::firstChildElement($root, 'metadata', self::OPF_NS);
        $manifestElement = self::firstChildElement($root, 'manifest', self::OPF_NS);
        $spineElement = self::firstChildElement($root, 'spine', self::OPF_NS);
        if (!$metadataElement instanceof \DOMElement) {
            throw new \RuntimeException('EPUB OPF package is missing metadata');
        }
        if (!$manifestElement instanceof \DOMElement) {
            throw new \RuntimeException('EPUB OPF package is missing manifest');
        }
        if (!$spineElement instanceof \DOMElement) {
            throw new \RuntimeException('EPUB OPF package is missing spine');
        }

        $uniqueIdentifier = trim($root->getAttribute('unique-identifier'));
        $metadata = $this->readMetadata($metadataElement, $uniqueIdentifier);
        $manifestById = $this->readManifest($package, $opfPart, $manifestElement);
        $encryption = $this->readEncryption($package, $manifestById);
        $manifestById = $this->attachEncryptionToManifest($manifestById, $encryption);
        $spine = $this->readSpine($spineElement, $manifestById);
        $manifest = array_values($manifestById);
        $navItem = $this->firstManifestItemWithProperty($manifest, 'nav');
        $ncxItem = $this->ncxManifestItem($spineElement, $manifestById, $manifest);

        return [
            'metadata' => $metadata,
            'package' => [
                'version' => trim($root->getAttribute('version')),
                'uniqueIdentifierId' => $uniqueIdentifier === '' ? null : $uniqueIdentifier,
                'opfPart' => $opfPart,
                'language' => self::xmlLang($root),
                'prefix' => trim($root->getAttribute('prefix')),
            ],
            'manifest' => $manifest,
            'spine' => $spine,
            'nav' => $navItem === null ? null : $this->readNavDocument($package, $navItem),
            'ncx' => $ncxItem === null ? null : $this->readNcxDocument($package, $ncxItem),
            'encryption' => $encryption,
            'xhtmlAssets' => $this->xhtmlAssets($package, $manifest),
            'assets' => $this->assetReport($manifest),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function readMetadata(\DOMElement $metadataElement, string $uniqueIdentifier): array
    {
        $dc = [];
        $metaProperties = [];
        $metaNames = [];
        $raw = [];
        $uniqueIdentifierValue = null;

        foreach (self::childElements($metadataElement) as $child) {
            $text = self::normalizedText($child);
            if ($child->namespaceURI === self::DC_NS) {
                $name = $child->localName;
                $entry = [
                    'name' => $name,
                    'text' => $text,
                    'id' => self::nullableAttribute($child, 'id'),
                    'scheme' => self::nullableAttribute($child, 'opf:scheme') ?? self::nullableAttribute($child, 'scheme'),
                    'language' => self::xmlLang($child),
                ];
                $dc[$name][] = $entry;
                $raw[] = ['type' => 'dc'] + $entry;
                if ($uniqueIdentifier !== '' && $entry['id'] === $uniqueIdentifier && $name === 'identifier') {
                    $uniqueIdentifierValue = $text;
                }
                continue;
            }

            if ($child->namespaceURI !== self::OPF_NS || $child->localName !== 'meta') {
                continue;
            }

            $property = trim($child->getAttribute('property'));
            $name = trim($child->getAttribute('name'));
            $entry = [
                'property' => $property === '' ? null : $property,
                'name' => $name === '' ? null : $name,
                'content' => self::nullableAttribute($child, 'content'),
                'refines' => self::nullableAttribute($child, 'refines'),
                'id' => self::nullableAttribute($child, 'id'),
                'scheme' => self::nullableAttribute($child, 'scheme'),
                'text' => $text,
            ];

            if ($property !== '') {
                $metaProperties[$property][] = $entry;
            }
            if ($name !== '') {
                $metaNames[$name][] = $entry;
            }
            $raw[] = ['type' => 'meta'] + $entry;
        }

        $identifiers = array_map(
            static fn (array $entry): string => $entry['text'],
            $dc['identifier'] ?? []
        );

        return [
            'title' => $dc['title'][0]['text'] ?? '',
            'creators' => array_map(static fn (array $entry): string => $entry['text'], $dc['creator'] ?? []),
            'language' => $dc['language'][0]['text'] ?? null,
            'identifier' => $uniqueIdentifierValue ?? ($identifiers[0] ?? null),
            'identifiers' => $identifiers,
            'subjects' => array_map(static fn (array $entry): string => $entry['text'], $dc['subject'] ?? []),
            'description' => $dc['description'][0]['text'] ?? null,
            'publisher' => $dc['publisher'][0]['text'] ?? null,
            'date' => $dc['date'][0]['text'] ?? null,
            'modified' => $metaProperties['dcterms:modified'][0]['text'] ?? null,
            'coverItemId' => $metaNames['cover'][0]['content'] ?? null,
            'dc' => $dc,
            'metaProperties' => $metaProperties,
            'metaNames' => $metaNames,
            'raw' => $raw,
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function readManifest(ZipPackage $package, string $opfPart, \DOMElement $manifestElement): array
    {
        $manifest = [];
        foreach (self::childElements($manifestElement, 'item', self::OPF_NS) as $item) {
            $id = trim($item->getAttribute('id'));
            $href = trim($item->getAttribute('href'));
            $mediaType = trim($item->getAttribute('media-type'));
            if ($id === '' || $href === '' || $mediaType === '') {
                throw new \RuntimeException('EPUB manifest item must include id, href, and media-type');
            }
            if (isset($manifest[$id])) {
                throw new \RuntimeException('Duplicate EPUB manifest id: ' . $id);
            }

            $target = OpcPackagePath::resolveInternalTarget($opfPart, $href);
            $part = OpcPackagePath::stripQueryAndFragment($target);
            $exists = $package->has($part);
            $entry = $exists ? $package->entry($part) : null;
            $manifest[$id] = [
                'id' => $id,
                'href' => $href,
                'target' => $target,
                'part' => $part,
                'mediaType' => $mediaType,
                'properties' => self::spaceDelimited($item->getAttribute('properties')),
                'fallback' => self::nullableAttribute($item, 'fallback'),
                'mediaOverlay' => self::nullableAttribute($item, 'media-overlay'),
                'exists' => $exists,
                'byteLength' => $entry instanceof ZipPackageEntry ? $entry->uncompressedSize : null,
                'crc32' => $entry instanceof ZipPackageEntry ? $entry->crc32Hex() : null,
                'encrypted' => false,
                'canExposeBytes' => true,
                'encryption' => null,
            ];
        }

        return $manifest;
    }

    /**
     * @param array<string, array<string, mixed>> $manifestById
     *
     * @return array{
     *     present:bool,
     *     part:?string,
     *     items:list<array<string, mixed>>,
     *     encryptedParts:list<string>,
     *     obfuscatedFonts:list<array<string, mixed>>,
     *     diagnostics:list<array<string, mixed>>
     * }
     */
    private function readEncryption(ZipPackage $package, array $manifestById): array
    {
        $encryptionPart = '/META-INF/encryption.xml';
        if (!$package->has($encryptionPart)) {
            return [
                'present' => false,
                'part' => null,
                'items' => [],
                'encryptedParts' => [],
                'obfuscatedFonts' => [],
                'diagnostics' => [],
            ];
        }

        $dom = self::loadXml($package->read($encryptionPart), 'EPUB OCF encryption XML');
        $root = $dom->documentElement;
        if (!$root instanceof \DOMElement || $root->localName !== 'encryption' || $root->namespaceURI !== self::OCF_CONTAINER_NS) {
            throw new \InvalidArgumentException('EPUB encryption XML must use the OCF encryption namespace');
        }

        $manifestByPart = self::manifestByPart($manifestById);
        $items = [];
        $diagnostics = [];

        foreach (self::encryptedDataElements($dom) as $index => $encryptedData) {
            $method = self::firstChildElement($encryptedData, 'EncryptionMethod', self::XMLENC_NS);
            $cipherData = self::firstChildElement($encryptedData, 'CipherData', self::XMLENC_NS);
            $cipherReference = $cipherData instanceof \DOMElement
                ? self::firstChildElement($cipherData, 'CipherReference', self::XMLENC_NS)
                : null;
            $uri = $cipherReference instanceof \DOMElement ? trim($cipherReference->getAttribute('URI')) : '';

            if ($uri === '') {
                $diagnostics[] = [
                    'type' => 'missing-cipher-reference',
                    'index' => $index,
                    'message' => 'EncryptedData entry is missing CipherReference URI',
                ];
                continue;
            }

            try {
                $part = self::encryptionCipherPart($uri);
            } catch (\InvalidArgumentException $exception) {
                $diagnostics[] = [
                    'type' => 'invalid-cipher-reference',
                    'index' => $index,
                    'uri' => $uri,
                    'message' => $exception->getMessage(),
                ];
                continue;
            }

            $manifestItem = $manifestByPart[$part] ?? null;
            $mediaType = is_array($manifestItem) ? (string) $manifestItem['mediaType'] : null;
            $item = [
                'index' => $index,
                'uri' => $uri,
                'part' => $part,
                'algorithm' => $method instanceof \DOMElement ? self::nullableAttribute($method, 'Algorithm') : null,
                'manifestId' => is_array($manifestItem) ? (string) $manifestItem['id'] : null,
                'mediaType' => $mediaType,
                'exists' => $package->has($part),
                'obfuscatedFont' => self::isObfuscatedFont(
                    $method instanceof \DOMElement ? self::nullableAttribute($method, 'Algorithm') : null,
                    $mediaType,
                    $part
                ),
                'canExposeBytes' => false,
            ];

            if (!is_array($manifestItem)) {
                $diagnostics[] = [
                    'type' => 'encrypted-resource-not-in-manifest',
                    'index' => $index,
                    'part' => $part,
                    'message' => 'Encrypted OCF resource is not listed in the OPF manifest',
                ];
            }
            if ($item['exists'] !== true) {
                $diagnostics[] = [
                    'type' => 'encrypted-resource-missing',
                    'index' => $index,
                    'part' => $part,
                    'message' => 'Encrypted OCF resource is missing from the ZIP package',
                ];
            }

            $items[] = $item;
        }

        return [
            'present' => true,
            'part' => $encryptionPart,
            'items' => $items,
            'encryptedParts' => array_map(static fn (array $item): string => (string) $item['part'], $items),
            'obfuscatedFonts' => array_values(array_filter(
                $items,
                static fn (array $item): bool => $item['obfuscatedFont'] === true,
            )),
            'diagnostics' => $diagnostics,
        ];
    }

    /**
     * @param array<string, array<string, mixed>> $manifestById
     * @param array<string, mixed> $encryption
     *
     * @return array<string, array<string, mixed>>
     */
    private function attachEncryptionToManifest(array $manifestById, array $encryption): array
    {
        $encryptionByPart = [];
        foreach ($encryption['items'] ?? [] as $item) {
            if (!is_array($item) || !isset($item['part'])) {
                continue;
            }

            $encryptionByPart[(string) $item['part']][] = $item;
        }

        foreach ($manifestById as $id => $item) {
            $entries = $encryptionByPart[(string) $item['part']] ?? [];
            if ($entries === []) {
                continue;
            }

            $manifestById[$id]['encrypted'] = true;
            $manifestById[$id]['canExposeBytes'] = false;
            $manifestById[$id]['encryption'] = [
                'items' => $entries,
                'algorithm' => $entries[0]['algorithm'] ?? null,
                'obfuscatedFont' => self::containsObfuscatedFont($entries),
                'canExposeBytes' => false,
            ];
        }

        return $manifestById;
    }

    /**
     * @param array<string, array<string, mixed>> $manifestById
     *
     * @return list<array<string, mixed>>
     */
    private function readSpine(\DOMElement $spineElement, array $manifestById): array
    {
        $spine = [];
        foreach (self::childElements($spineElement, 'itemref', self::OPF_NS) as $index => $itemref) {
            $idref = trim($itemref->getAttribute('idref'));
            if ($idref === '') {
                throw new \RuntimeException('EPUB spine itemref is missing idref');
            }
            if (!isset($manifestById[$idref])) {
                throw new \RuntimeException('EPUB spine references missing manifest id: ' . $idref);
            }

            $manifestItem = $manifestById[$idref];
            if (($manifestItem['exists'] ?? false) !== true) {
                throw new \RuntimeException('EPUB spine item is missing from the package: ' . $manifestItem['part']);
            }

            $spine[] = [
                'index' => $index,
                'idref' => $idref,
                'target' => $manifestItem['target'],
                'part' => $manifestItem['part'],
                'href' => $manifestItem['href'],
                'mediaType' => $manifestItem['mediaType'],
                'linear' => strtolower(trim($itemref->getAttribute('linear'))) !== 'no',
                'properties' => self::spaceDelimited($itemref->getAttribute('properties')),
                'encrypted' => self::isEncryptedManifestItem($manifestItem),
                'canExposeBytes' => (bool) ($manifestItem['canExposeBytes'] ?? true),
                'encryption' => $manifestItem['encryption'] ?? null,
            ];
        }

        return $spine;
    }

    /**
     * @param list<array<string, mixed>> $manifest
     */
    private function firstManifestItemWithProperty(array $manifest, string $property): ?array
    {
        foreach ($manifest as $item) {
            if (in_array($property, $item['properties'], true)) {
                return $item;
            }
        }

        return null;
    }

    /**
     * @param array<string, array<string, mixed>> $manifestById
     * @param list<array<string, mixed>> $manifest
     */
    private function ncxManifestItem(\DOMElement $spineElement, array $manifestById, array $manifest): ?array
    {
        $tocId = trim($spineElement->getAttribute('toc'));
        if ($tocId !== '') {
            if (!isset($manifestById[$tocId])) {
                throw new \RuntimeException('EPUB spine toc references missing manifest id: ' . $tocId);
            }

            return $manifestById[$tocId];
        }

        foreach ($manifest as $item) {
            if ($item['mediaType'] === self::NCX_MEDIA_TYPE) {
                return $item;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $item
     *
     * @return array{
     *     part:string,
     *     items:list<array<string, mixed>>,
     *     sections:list<array<string, mixed>>,
     *     landmarks:list<array<string, mixed>>,
     *     pageList:list<array<string, mixed>>
     * }
     */
    private function readNavDocument(ZipPackage $package, array $item): array
    {
        if (self::isEncryptedManifestItem($item)) {
            return [
                'part' => (string) $item['part'],
                'items' => [],
                'sections' => [],
                'landmarks' => [],
                'pageList' => [],
                'encrypted' => true,
                'encryption' => $item['encryption'] ?? null,
            ];
        }

        $dom = self::loadXml($package->read((string) $item['part']), 'EPUB navigation XHTML');
        $sections = [];
        $tocItems = null;
        $fallbackItems = null;
        foreach (self::navigationElements($dom) as $nav) {
            $types = self::epubTypes($nav);
            $list = self::firstChildElement($nav, 'ol', self::XHTML_NS);
            $items = $list instanceof \DOMElement ? $this->readNavList($list, (string) $item['part']) : [];
            $section = [
                'type' => $types[0] ?? null,
                'types' => $types,
                'title' => self::navHeading($nav),
                'items' => $items,
            ];

            $sections[] = $section;
            $fallbackItems ??= $items;
            if (in_array('toc', $types, true) && $tocItems === null) {
                $tocItems = $items;
            }
        }

        if ($sections === []) {
            return [
                'part' => (string) $item['part'],
                'items' => [],
                'sections' => [],
                'landmarks' => [],
                'pageList' => [],
            ];
        }

        return [
            'part' => (string) $item['part'],
            'items' => $tocItems ?? $fallbackItems ?? [],
            'sections' => $sections,
            'landmarks' => self::navItemsForType($sections, 'landmarks'),
            'pageList' => self::navItemsForType($sections, 'page-list'),
        ];
    }

    /**
     * @return list<\DOMElement>
     */
    private static function navigationElements(\DOMDocument $dom): array
    {
        $elements = [];
        foreach ($dom->getElementsByTagNameNS(self::XHTML_NS, 'nav') as $nav) {
            if (!$nav instanceof \DOMElement) {
                continue;
            }
            $elements[] = $nav;
        }

        return $elements;
    }

    /**
     * @param list<array<string, mixed>> $sections
     *
     * @return list<array<string, mixed>>
     */
    private static function navItemsForType(array $sections, string $type): array
    {
        foreach ($sections as $section) {
            if (in_array($type, $section['types'] ?? [], true)) {
                return $section['items'] ?? [];
            }
        }

        return [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function readNavList(\DOMElement $list, string $navPart): array
    {
        $items = [];
        foreach (self::childElements($list, 'li', self::XHTML_NS) as $li) {
            $link = self::firstChildElement($li, 'a', self::XHTML_NS);
            $label = $link instanceof \DOMElement ? $link : self::firstChildElement($li, 'span', self::XHTML_NS);
            $href = $link instanceof \DOMElement ? trim($link->getAttribute('href')) : '';
            $childList = self::firstChildElement($li, 'ol', self::XHTML_NS);
            $types = self::epubTypes($link ?? $label ?? $li);

            $items[] = [
                'title' => $label instanceof \DOMElement ? self::normalizedText($label) : self::normalizedText($li),
                'href' => $href === '' ? null : $href,
                'target' => $href === '' ? null : OpcPackagePath::resolveInternalTarget($navPart, $href),
                'type' => $types[0] ?? null,
                'types' => $types,
                'children' => $childList instanceof \DOMElement ? $this->readNavList($childList, $navPart) : [],
            ];
        }

        return $items;
    }

    /**
     * @param array<string, mixed> $item
     *
     * @return array{part:string, items:list<array<string, mixed>>}
     */
    private function readNcxDocument(ZipPackage $package, array $item): array
    {
        if (self::isEncryptedManifestItem($item)) {
            return [
                'part' => (string) $item['part'],
                'items' => [],
                'encrypted' => true,
                'encryption' => $item['encryption'] ?? null,
            ];
        }

        $dom = self::loadXml($package->read((string) $item['part']), 'EPUB NCX XML');
        $root = $dom->documentElement;
        if (!$root instanceof \DOMElement || $root->localName !== 'ncx' || $root->namespaceURI !== self::NCX_NS) {
            throw new \InvalidArgumentException('EPUB NCX document must use the NCX namespace');
        }

        $navMap = self::firstChildElement($root, 'navMap', self::NCX_NS);

        return [
            'part' => (string) $item['part'],
            'items' => $navMap instanceof \DOMElement ? $this->readNcxPoints($navMap, (string) $item['part']) : [],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function readNcxPoints(\DOMElement $parent, string $ncxPart): array
    {
        $items = [];
        foreach (self::childElements($parent, 'navPoint', self::NCX_NS) as $point) {
            $navLabel = self::firstChildElement($point, 'navLabel', self::NCX_NS);
            $label = $navLabel instanceof \DOMElement
                ? self::firstDescendantElement($navLabel, 'text', self::NCX_NS)
                : null;
            $content = self::firstChildElement($point, 'content', self::NCX_NS);
            $src = $content instanceof \DOMElement ? trim($content->getAttribute('src')) : '';

            $items[] = [
                'id' => self::nullableAttribute($point, 'id'),
                'playOrder' => self::nullableAttribute($point, 'playOrder'),
                'title' => $label instanceof \DOMElement ? self::normalizedText($label) : '',
                'href' => $src === '' ? null : $src,
                'target' => $src === '' ? null : OpcPackagePath::resolveInternalTarget($ncxPart, $src),
                'children' => $this->readNcxPoints($point, $ncxPart),
            ];
        }

        return $items;
    }

    /**
     * @param list<array<string, mixed>> $manifest
     *
     * @return list<array<string, mixed>>
     */
    private function xhtmlAssets(ZipPackage $package, array $manifest): array
    {
        $assets = [];
        foreach ($manifest as $item) {
            if (
                $item['mediaType'] !== self::XHTML_MEDIA_TYPE
                || ($item['exists'] ?? false) !== true
                || self::isEncryptedManifestItem($item)
            ) {
                continue;
            }

            $assets[] = [
                'id' => $item['id'],
                'href' => $item['href'],
                'target' => $item['target'],
                'part' => $item['part'],
                'properties' => $item['properties'],
                'html' => $package->read((string) $item['part']),
            ];
        }

        return $assets;
    }

    /**
     * @param list<array<string, mixed>> $manifest
     *
     * @return list<array<string, mixed>>
     */
    private function assetReport(array $manifest): array
    {
        $assets = [];
        foreach ($manifest as $item) {
            if ($item['mediaType'] === self::XHTML_MEDIA_TYPE) {
                continue;
            }

            $assets[] = [
                'id' => $item['id'],
                'target' => $item['target'],
                'part' => $item['part'],
                'mediaType' => $item['mediaType'],
                'properties' => $item['properties'],
                'exists' => $item['exists'],
                'byteLength' => $item['byteLength'],
                'crc32' => $item['crc32'],
                'encrypted' => self::isEncryptedManifestItem($item),
                'canExposeBytes' => (bool) ($item['canExposeBytes'] ?? true),
                'encryption' => $item['encryption'] ?? null,
            ];
        }

        return $assets;
    }

    /**
     * @param array<string, mixed> $metadata
     * @param list<array<string, mixed>> $spine
     * @param list<array<string, mixed>> $xhtmlAssets
     */
    private function documentNode(array $metadata, string $opfPart, array $spine, array $xhtmlAssets): AstNode
    {
        $assetsByPart = [];
        foreach ($xhtmlAssets as $asset) {
            $assetsByPart[(string) $asset['part']] = $asset;
        }

        $children = [];
        foreach ($spine as $item) {
            if ($item['mediaType'] !== self::XHTML_MEDIA_TYPE) {
                continue;
            }

            $asset = $assetsByPart[(string) $item['part']] ?? null;
            if (!is_array($asset)) {
                continue;
            }

            $children[] = new AstNode('raw_html', [
                'format' => 'html',
                'html' => $asset['html'],
                'part' => $item['part'],
                'id' => $item['idref'],
                'linear' => $item['linear'],
                'source' => 'epub3-spine',
            ]);
        }

        return new AstNode('document', [
            'source' => 'epub3',
            'opfPart' => $opfPart,
            'metadata' => $metadata,
            'title' => $metadata['title'] ?? '',
        ], $children);
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

    private static function firstDescendantElement(\DOMElement $element, string $localName, string $namespace): ?\DOMElement
    {
        foreach ($element->getElementsByTagNameNS($namespace, $localName) as $child) {
            if ($child instanceof \DOMElement) {
                return $child;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private static function spaceDelimited(string $value): array
    {
        $value = trim($value);
        if ($value === '') {
            return [];
        }

        return preg_split('/\s+/', $value) ?: [];
    }

    /**
     * @return list<string>
     */
    private static function epubTypes(\DOMElement $element): array
    {
        $type = trim($element->getAttributeNS(self::EPUB_OPS_NS, 'type'));
        if ($type === '') {
            $type = trim($element->getAttribute('epub:type'));
        }

        return self::spaceDelimited($type);
    }

    /**
     * @param array<string, array<string, mixed>> $manifestById
     *
     * @return array<string, array<string, mixed>>
     */
    private static function manifestByPart(array $manifestById): array
    {
        $byPart = [];
        foreach ($manifestById as $item) {
            $byPart[(string) $item['part']] = $item;
        }

        return $byPart;
    }

    /**
     * @return list<\DOMElement>
     */
    private static function encryptedDataElements(\DOMDocument $dom): array
    {
        $elements = [];
        foreach ($dom->getElementsByTagNameNS(self::XMLENC_NS, 'EncryptedData') as $element) {
            if ($element instanceof \DOMElement) {
                $elements[] = $element;
            }
        }

        return $elements;
    }

    private static function encryptionCipherPart(string $uri): string
    {
        if (preg_match('/^[A-Za-z][A-Za-z0-9+.-]*:/', $uri) === 1) {
            throw new \InvalidArgumentException('EPUB encryption CipherReference URI must be package-relative');
        }

        if (str_contains($uri, '?') || str_contains($uri, '#')) {
            throw new \InvalidArgumentException('EPUB encryption CipherReference URI must identify a package part without query or fragment');
        }

        return OpcPackagePath::canonicalPartName($uri);
    }

    private static function isEncryptedManifestItem(array $item): bool
    {
        return ($item['encrypted'] ?? false) === true;
    }

    private static function isObfuscatedFont(?string $algorithm, ?string $mediaType, string $part): bool
    {
        if ($algorithm !== self::IDPF_FONT_OBFUSCATION_ALGORITHM) {
            return false;
        }

        return self::isFontResource($mediaType, $part);
    }

    private static function isFontResource(?string $mediaType, string $part): bool
    {
        $normalizedMediaType = strtolower((string) $mediaType);
        if (in_array($normalizedMediaType, [
            'application/font-sfnt',
            'application/font-woff',
            'application/vnd.ms-opentype',
            'application/x-font-opentype',
            'application/x-font-otf',
            'application/x-font-ttf',
            'font/otf',
            'font/sfnt',
            'font/ttf',
            'font/woff',
            'font/woff2',
        ], true)) {
            return true;
        }

        $extension = strtolower(pathinfo($part, PATHINFO_EXTENSION));

        return in_array($extension, ['otf', 'ttf', 'woff', 'woff2'], true);
    }

    /**
     * @param list<array<string, mixed>> $entries
     */
    private static function containsObfuscatedFont(array $entries): bool
    {
        foreach ($entries as $entry) {
            if (($entry['obfuscatedFont'] ?? false) === true) {
                return true;
            }
        }

        return false;
    }

    private static function navHeading(\DOMElement $nav): ?string
    {
        foreach (self::childElements($nav) as $child) {
            if ($child->namespaceURI !== self::XHTML_NS) {
                continue;
            }
            if (preg_match('/^h[1-6]$/', $child->localName) === 1) {
                return self::normalizedText($child);
            }
        }

        return null;
    }

    private static function normalizedText(\DOMElement $element): string
    {
        $text = preg_replace('/\s+/u', ' ', $element->textContent) ?? $element->textContent;

        return trim($text);
    }

    private static function nullableAttribute(\DOMElement $element, string $name): ?string
    {
        if (!$element->hasAttribute($name)) {
            return null;
        }

        $value = trim($element->getAttribute($name));

        return $value === '' ? null : $value;
    }

    private static function xmlLang(\DOMElement $element): ?string
    {
        $lang = trim($element->getAttributeNS('http://www.w3.org/XML/1998/namespace', 'lang'));
        if ($lang === '') {
            $lang = trim($element->getAttribute('xml:lang'));
        }

        return $lang === '' ? null : $lang;
    }

    private static function loadXml(string $xml, string $label): \DOMDocument
    {
        return XmlHtmlDom::loadXmlDocument($xml, $label, preserveWhiteSpace: false);
    }
}
