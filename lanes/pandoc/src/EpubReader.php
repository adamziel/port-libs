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
    public const SMIL_NS = 'http://www.w3.org/ns/SMIL';
    public const OPF_MEDIA_TYPE = 'application/oebps-package+xml';
    public const XHTML_MEDIA_TYPE = 'application/xhtml+xml';
    public const NCX_MEDIA_TYPE = 'application/x-dtbncx+xml';
    public const SMIL_MEDIA_TYPE = 'application/smil+xml';
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
     *     guide:array<string, mixed>,
     *     collections:list<array<string, mixed>>,
     *     renditions:array<string, mixed>,
     *     encryption:array<string, mixed>,
     *     mediaOverlays:array<string, array<string, mixed>>,
     *     xhtmlAssets:list<array<string, mixed>>,
     *     assets:list<array<string, mixed>>,
     *     assetReport:array<string, mixed>,
     *     importReport:array<string, mixed>
     * }
     */
    public function readPackage(ZipPackage $package): array
    {
        $this->assertEpubMimetype($package);
        $container = $this->readContainer($package);
        $opfPart = (string) $container['opfPart'];

        $opf = $this->readOpf($package, $opfPart);
        $renditions = $this->readRenditions($package, $container, $opfPart, $opf);
        $document = $this->documentNode(
            $opf['metadata'],
            $opfPart,
            $opf['spine'],
            $opf['xhtmlAssets'],
            $opf['guide'],
            $opf['collections'],
            $renditions
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
            'guide' => $opf['guide'],
            'collections' => $opf['collections'],
            'renditions' => $renditions,
            'encryption' => $opf['encryption'],
            'mediaOverlays' => $opf['mediaOverlays'],
            'xhtmlAssets' => $opf['xhtmlAssets'],
            'assets' => $opf['assets'],
            'assetReport' => $opf['assetReport'],
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
                'guide' => $opf['guide'],
                'collections' => $opf['collections'],
                'renditions' => $renditions,
                'encryption' => $opf['encryption'],
                'mediaOverlays' => $opf['mediaOverlays'],
                'assets' => $opf['assetReport'],
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
     * @return array{opfPart:string, selectedRootfileIndex:int, rootfiles:list<array{index:int, path:string, mediaType:string, exists:bool, selected:bool}>}
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
        foreach (self::childElements($rootfilesElement, 'rootfile', self::OCF_CONTAINER_NS) as $index => $rootfile) {
            $path = trim($rootfile->getAttribute('full-path'));
            $mediaType = trim($rootfile->getAttribute('media-type'));
            if ($path === '') {
                throw new \RuntimeException('EPUB rootfile is missing full-path');
            }

            $part = OpcPackagePath::canonicalPartName($path);
            $rootfiles[] = [
                'index' => $index,
                'path' => $part,
                'mediaType' => $mediaType,
                'exists' => $package->has($part),
                'selected' => false,
            ];
        }

        if ($rootfiles === []) {
            throw new \RuntimeException('EPUB container XML does not list an OPF rootfile');
        }

        $selected = null;
        $selectedIndex = null;
        foreach ($rootfiles as $index => $rootfile) {
            if ($rootfile['mediaType'] === self::OPF_MEDIA_TYPE) {
                $selected = $rootfile;
                $selectedIndex = $index;
                break;
            }
        }
        if ($selected === null) {
            $selected = $rootfiles[0];
            $selectedIndex = 0;
        }

        if ($selected['mediaType'] !== self::OPF_MEDIA_TYPE) {
            throw new \RuntimeException('EPUB rootfile media-type must be application/oebps-package+xml');
        }

        if ($selected['exists'] !== true) {
            throw new \RuntimeException('EPUB OPF rootfile is missing from the package: ' . $selected['path']);
        }

        foreach ($rootfiles as $index => $rootfile) {
            $rootfiles[$index]['selected'] = $index === $selectedIndex;
        }

        return [
            'opfPart' => $selected['path'],
            'selectedRootfileIndex' => $selectedIndex,
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
     *     guide:array<string, mixed>,
     *     collections:list<array<string, mixed>>,
     *     encryption:array<string, mixed>,
     *     mediaOverlays:array<string, array<string, mixed>>,
     *     xhtmlAssets:list<array<string, mixed>>,
     *     assets:list<array<string, mixed>>,
     *     assetReport:array<string, mixed>
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
        $guide = $this->readGuide($package, $opfPart, self::firstChildElement($root, 'guide', self::OPF_NS), $manifestById);
        $collections = $this->readCollections($package, $opfPart, $root, $manifestById);
        $spine = $this->readSpine($spineElement, $manifestById);
        $mediaOverlays = $this->readMediaOverlays($package, $manifestById);
        $manifest = array_values($manifestById);
        $navItem = $this->firstManifestItemWithProperty($manifest, 'nav');
        $ncxItem = $this->ncxManifestItem($spineElement, $manifestById, $manifest);
        $assetReport = $this->assetReport($package, $opfPart, $manifest, $metadata);

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
            'guide' => $guide,
            'collections' => $collections,
            'encryption' => $encryption,
            'mediaOverlays' => $mediaOverlays,
            'xhtmlAssets' => $this->xhtmlAssets($package, $manifest),
            'assets' => $assetReport['items'],
            'assetReport' => $assetReport,
        ];
    }

    /**
     * @param array<string, mixed> $container
     * @param array<string, mixed> $selectedOpf
     *
     * @return array{
     *     selectedPath:string,
     *     selectedIndex:?int,
     *     count:int,
     *     alternateCount:int,
     *     items:list<array<string, mixed>>,
     *     diagnostics:list<array<string, mixed>>
     * }
     */
    private function readRenditions(ZipPackage $package, array $container, string $selectedOpfPart, array $selectedOpf): array
    {
        $items = [];
        $diagnostics = [];
        $selectedIndex = null;

        foreach (($container['rootfiles'] ?? []) as $rootfile) {
            if (!is_array($rootfile) || ($rootfile['mediaType'] ?? null) !== self::OPF_MEDIA_TYPE) {
                continue;
            }

            $selected = (bool) ($rootfile['selected'] ?? false) || (string) $rootfile['path'] === $selectedOpfPart;
            $item = $selected
                ? $this->renditionSummaryFromOpf($rootfile, $selectedOpf)
                : $this->readAlternateRenditionSummary($package, $rootfile);

            if ($selected) {
                $selectedIndex = count($items);
            }

            foreach ($item['diagnostics'] ?? [] as $diagnostic) {
                $diagnostics[] = [
                    'index' => $item['index'],
                    'path' => $item['path'],
                ] + $diagnostic;
            }

            $items[] = $item;
        }

        return [
            'selectedPath' => $selectedOpfPart,
            'selectedIndex' => $selectedIndex,
            'count' => count($items),
            'alternateCount' => count(array_filter(
                $items,
                static fn (array $item): bool => ($item['selected'] ?? false) !== true,
            )),
            'items' => $items,
            'diagnostics' => $diagnostics,
        ];
    }

    /**
     * @param array<string, mixed> $rootfile
     * @param array<string, mixed> $opf
     *
     * @return array<string, mixed>
     */
    private function renditionSummaryFromOpf(array $rootfile, array $opf): array
    {
        $metadata = is_array($opf['metadata'] ?? null) ? $opf['metadata'] : [];

        return [
            'index' => (int) ($rootfile['index'] ?? 0),
            'path' => (string) $rootfile['path'],
            'mediaType' => (string) $rootfile['mediaType'],
            'exists' => (bool) ($rootfile['exists'] ?? false),
            'selected' => (bool) ($rootfile['selected'] ?? false),
            'package' => is_array($opf['package'] ?? null) ? $opf['package'] : null,
            'metadata' => self::renditionMetadataSummary($metadata),
            'renditionProperties' => self::renditionProperties($metadata),
            'manifestCount' => is_array($opf['manifest'] ?? null) ? count($opf['manifest']) : null,
            'spineCount' => is_array($opf['spine'] ?? null) ? count($opf['spine']) : null,
            'diagnostics' => [],
        ];
    }

    /**
     * @param array<string, mixed> $rootfile
     *
     * @return array<string, mixed>
     */
    private function readAlternateRenditionSummary(ZipPackage $package, array $rootfile): array
    {
        $summary = [
            'index' => (int) ($rootfile['index'] ?? 0),
            'path' => (string) $rootfile['path'],
            'mediaType' => (string) $rootfile['mediaType'],
            'exists' => (bool) ($rootfile['exists'] ?? false),
            'selected' => false,
            'package' => null,
            'metadata' => self::renditionMetadataSummary([]),
            'renditionProperties' => [],
            'manifestCount' => null,
            'spineCount' => null,
            'diagnostics' => [],
        ];

        if (($rootfile['exists'] ?? false) !== true) {
            $summary['diagnostics'][] = [
                'type' => 'missing-alternate-rendition-rootfile',
                'message' => 'EPUB alternate rendition OPF rootfile is missing from the package',
            ];

            return $summary;
        }

        try {
            $dom = self::loadXml($package->read((string) $rootfile['path']), 'EPUB alternate rendition OPF package XML');
        } catch (\Throwable $exception) {
            $summary['diagnostics'][] = [
                'type' => 'invalid-alternate-rendition-opf',
                'message' => $exception->getMessage(),
            ];

            return $summary;
        }

        $root = $dom->documentElement;
        if (!$root instanceof \DOMElement || $root->localName !== 'package' || $root->namespaceURI !== self::OPF_NS) {
            $summary['diagnostics'][] = [
                'type' => 'invalid-alternate-rendition-opf',
                'message' => 'EPUB alternate rendition root must be an OPF package element',
            ];

            return $summary;
        }

        $metadataElement = self::firstChildElement($root, 'metadata', self::OPF_NS);
        $manifestElement = self::firstChildElement($root, 'manifest', self::OPF_NS);
        $spineElement = self::firstChildElement($root, 'spine', self::OPF_NS);
        $uniqueIdentifier = trim($root->getAttribute('unique-identifier'));
        $metadata = [];

        if ($metadataElement instanceof \DOMElement) {
            $metadata = $this->readMetadata($metadataElement, $uniqueIdentifier);
        } else {
            $summary['diagnostics'][] = [
                'type' => 'missing-alternate-rendition-metadata',
                'message' => 'EPUB alternate rendition OPF package is missing metadata',
            ];
        }

        if (!$manifestElement instanceof \DOMElement) {
            $summary['diagnostics'][] = [
                'type' => 'missing-alternate-rendition-manifest',
                'message' => 'EPUB alternate rendition OPF package is missing manifest',
            ];
        }

        if (!$spineElement instanceof \DOMElement) {
            $summary['diagnostics'][] = [
                'type' => 'missing-alternate-rendition-spine',
                'message' => 'EPUB alternate rendition OPF package is missing spine',
            ];
        }

        $summary['package'] = [
            'version' => trim($root->getAttribute('version')),
            'uniqueIdentifierId' => $uniqueIdentifier === '' ? null : $uniqueIdentifier,
            'opfPart' => (string) $rootfile['path'],
            'language' => self::xmlLang($root),
            'prefix' => trim($root->getAttribute('prefix')),
        ];
        $summary['metadata'] = self::renditionMetadataSummary($metadata);
        $summary['renditionProperties'] = self::renditionProperties($metadata);
        $summary['manifestCount'] = $manifestElement instanceof \DOMElement
            ? count(self::childElements($manifestElement, 'item', self::OPF_NS))
            : null;
        $summary['spineCount'] = $spineElement instanceof \DOMElement
            ? count(self::childElements($spineElement, 'itemref', self::OPF_NS))
            : null;

        return $summary;
    }

    /**
     * @param array<string, mixed> $metadata
     *
     * @return array{title:string, identifier:?string, language:?string, creators:list<string>, modified:?string}
     */
    private static function renditionMetadataSummary(array $metadata): array
    {
        return [
            'title' => (string) ($metadata['title'] ?? ''),
            'identifier' => isset($metadata['identifier']) ? (string) $metadata['identifier'] : null,
            'language' => isset($metadata['language']) ? (string) $metadata['language'] : null,
            'creators' => array_values(array_filter(
                is_array($metadata['creators'] ?? null) ? $metadata['creators'] : [],
                static fn (mixed $creator): bool => is_string($creator) && $creator !== '',
            )),
            'modified' => isset($metadata['modified']) ? (string) $metadata['modified'] : null,
        ];
    }

    /**
     * @param array<string, mixed> $metadata
     *
     * @return array<string, string>
     */
    private static function renditionProperties(array $metadata): array
    {
        $properties = [];
        $metaProperties = is_array($metadata['metaProperties'] ?? null) ? $metadata['metaProperties'] : [];
        foreach ($metaProperties as $property => $entries) {
            if (!is_string($property) || !str_starts_with($property, 'rendition:') || !is_array($entries)) {
                continue;
            }

            $key = substr($property, strlen('rendition:'));
            if ($key === '') {
                continue;
            }

            foreach ($entries as $entry) {
                if (!is_array($entry)) {
                    continue;
                }

                $text = trim((string) ($entry['text'] ?? ''));
                if ($text !== '') {
                    $properties[$key] = $text;
                    break;
                }
            }
        }

        return $properties;
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

            $content = $this->resolveSpineContentItem($manifestItem, $manifestById);
            $contentItem = $content['item'];
            $spine[] = [
                'index' => $index,
                'idref' => $idref,
                'target' => $manifestItem['target'],
                'part' => $manifestItem['part'],
                'href' => $manifestItem['href'],
                'mediaType' => $manifestItem['mediaType'],
                'linear' => strtolower(trim($itemref->getAttribute('linear'))) !== 'no',
                'properties' => self::spaceDelimited($itemref->getAttribute('properties')),
                'mediaOverlay' => $manifestItem['mediaOverlay'],
                'encrypted' => self::isEncryptedManifestItem($manifestItem),
                'canExposeBytes' => (bool) ($manifestItem['canExposeBytes'] ?? true),
                'encryption' => $manifestItem['encryption'] ?? null,
                'contentId' => is_array($contentItem) ? (string) $contentItem['id'] : null,
                'contentTarget' => is_array($contentItem) ? (string) $contentItem['target'] : null,
                'contentPart' => is_array($contentItem) ? (string) $contentItem['part'] : null,
                'contentHref' => is_array($contentItem) ? (string) $contentItem['href'] : null,
                'contentMediaType' => is_array($contentItem) ? (string) $contentItem['mediaType'] : null,
                'contentProperties' => is_array($contentItem) ? $contentItem['properties'] : [],
                'contentEncrypted' => is_array($contentItem) && self::isEncryptedManifestItem($contentItem),
                'contentCanExposeBytes' => is_array($contentItem) ? (bool) ($contentItem['canExposeBytes'] ?? true) : false,
                'contentIsFallback' => is_array($contentItem) && $content['isFallback'],
                'fallbackChain' => $content['chain'],
                'fallbackDiagnostics' => $content['diagnostics'],
            ];
        }

        return $spine;
    }

    /**
     * @param array<string, mixed> $manifestItem
     * @param array<string, array<string, mixed>> $manifestById
     *
     * @return array{
     *     item:?array<string, mixed>,
     *     chain:list<array<string, mixed>>,
     *     diagnostics:list<array<string, mixed>>,
     *     isFallback:bool
     * }
     */
    private function resolveSpineContentItem(array $manifestItem, array $manifestById): array
    {
        $current = $manifestItem;
        $visited = [(string) $manifestItem['id'] => true];
        $chain = [];
        $diagnostics = [];
        $isFallback = false;

        while (true) {
            if (self::canExposeXhtmlContent($current)) {
                return [
                    'item' => $current,
                    'chain' => $chain,
                    'diagnostics' => $diagnostics,
                    'isFallback' => $isFallback,
                ];
            }

            $fallbackId = trim((string) ($current['fallback'] ?? ''));
            if ($fallbackId === '') {
                $diagnostics[] = self::spineFallbackDiagnostic($current);

                return [
                    'item' => null,
                    'chain' => $chain,
                    'diagnostics' => $diagnostics,
                    'isFallback' => $isFallback,
                ];
            }

            if (isset($visited[$fallbackId])) {
                $diagnostics[] = [
                    'type' => 'cyclic-spine-fallback-chain',
                    'id' => (string) $current['id'],
                    'fallback' => $fallbackId,
                    'message' => 'EPUB manifest fallback chain cycles before reaching an XHTML content document',
                ];

                return [
                    'item' => null,
                    'chain' => $chain,
                    'diagnostics' => $diagnostics,
                    'isFallback' => $isFallback,
                ];
            }

            if (!isset($manifestById[$fallbackId])) {
                $diagnostics[] = [
                    'type' => 'missing-spine-fallback-manifest-item',
                    'id' => (string) $current['id'],
                    'fallback' => $fallbackId,
                    'message' => 'EPUB manifest fallback references an item id that is not in the OPF manifest',
                ];

                return [
                    'item' => null,
                    'chain' => $chain,
                    'diagnostics' => $diagnostics,
                    'isFallback' => $isFallback,
                ];
            }

            $visited[$fallbackId] = true;
            $current = $manifestById[$fallbackId];
            $chain[] = self::fallbackChainItem($current);
            $isFallback = true;
        }
    }

    /**
     * @param array<string, mixed> $item
     *
     * @return array<string, mixed>
     */
    private static function fallbackChainItem(array $item): array
    {
        return [
            'id' => (string) $item['id'],
            'href' => (string) $item['href'],
            'target' => (string) $item['target'],
            'part' => (string) $item['part'],
            'mediaType' => (string) $item['mediaType'],
            'properties' => $item['properties'],
            'exists' => (bool) ($item['exists'] ?? false),
            'encrypted' => self::isEncryptedManifestItem($item),
            'canExposeBytes' => (bool) ($item['canExposeBytes'] ?? true),
        ];
    }

    /**
     * @param array<string, mixed> $item
     *
     * @return array<string, mixed>
     */
    private static function spineFallbackDiagnostic(array $item): array
    {
        $id = (string) $item['id'];
        if (($item['exists'] ?? false) !== true) {
            return [
                'type' => 'missing-spine-fallback-part',
                'id' => $id,
                'part' => (string) $item['part'],
                'message' => 'EPUB manifest fallback item is missing from the package',
            ];
        }

        if (self::isEncryptedManifestItem($item)) {
            return [
                'type' => 'encrypted-spine-fallback-content',
                'id' => $id,
                'part' => (string) $item['part'],
                'message' => 'EPUB manifest fallback item is encrypted and cannot be exposed as XHTML content',
            ];
        }

        return [
            'type' => 'missing-spine-xhtml-fallback',
            'id' => $id,
            'mediaType' => (string) $item['mediaType'],
            'message' => 'EPUB spine item fallback chain did not reach an XHTML content document',
        ];
    }

    /**
     * @param array<string, array<string, mixed>> $manifestById
     *
     * @return array{present:bool, items:list<array<string, mixed>>, diagnostics:list<array<string, mixed>>}
     */
    private function readGuide(
        ZipPackage $package,
        string $opfPart,
        ?\DOMElement $guideElement,
        array $manifestById
    ): array {
        if (!$guideElement instanceof \DOMElement) {
            return [
                'present' => false,
                'items' => [],
                'diagnostics' => [],
            ];
        }

        $manifestByPart = self::manifestByPart($manifestById);
        $items = [];
        $diagnostics = [];
        foreach (self::childElements($guideElement, 'reference', self::OPF_NS) as $index => $referenceElement) {
            $href = trim($referenceElement->getAttribute('href'));
            $reference = $this->packageReference($package, $opfPart, $href, $manifestByPart, 'guide');
            $item = [
                'index' => $index,
                'type' => self::nullableAttribute($referenceElement, 'type'),
                'title' => self::nullableAttribute($referenceElement, 'title'),
                'href' => $href === '' ? null : $href,
                'target' => $reference['target'],
                'part' => $reference['part'],
                'external' => $reference['external'],
                'exists' => $reference['exists'],
                'byteLength' => $reference['byteLength'],
                'crc32' => $reference['crc32'],
                'manifestId' => $reference['manifestId'],
                'mediaType' => $reference['mediaType'],
                'encrypted' => $reference['encrypted'],
                'canExposeBytes' => $reference['canExposeBytes'],
                'diagnostics' => $reference['diagnostics'],
            ];

            foreach ($reference['diagnostics'] as $diagnostic) {
                $diagnostics[] = ['index' => $index] + $diagnostic;
            }
            $items[] = $item;
        }

        return [
            'present' => true,
            'items' => $items,
            'diagnostics' => $diagnostics,
        ];
    }

    /**
     * @param array<string, array<string, mixed>> $manifestById
     *
     * @return list<array<string, mixed>>
     */
    private function readCollections(
        ZipPackage $package,
        string $opfPart,
        \DOMElement $packageElement,
        array $manifestById
    ): array {
        $manifestByPart = self::manifestByPart($manifestById);
        $collections = [];
        foreach (self::childElements($packageElement, 'collection', self::OPF_NS) as $collectionElement) {
            $collections[] = $this->readCollectionElement($package, $opfPart, $collectionElement, $manifestByPart);
        }

        return $collections;
    }

    /**
     * @param array<string, array<string, mixed>> $manifestByPart
     *
     * @return array<string, mixed>
     */
    private function readCollectionElement(
        ZipPackage $package,
        string $opfPart,
        \DOMElement $collectionElement,
        array $manifestByPart
    ): array {
        $links = [];
        foreach (self::childElements($collectionElement, 'link', self::OPF_NS) as $linkElement) {
            $links[] = $this->readCollectionLink($package, $opfPart, $linkElement, $manifestByPart);
        }

        $children = [];
        foreach (self::childElements($collectionElement, 'collection', self::OPF_NS) as $childCollection) {
            $children[] = $this->readCollectionElement($package, $opfPart, $childCollection, $manifestByPart);
        }

        $metadataElement = self::firstChildElement($collectionElement, 'metadata', self::OPF_NS);

        return [
            'id' => self::nullableAttribute($collectionElement, 'id'),
            'role' => self::nullableAttribute($collectionElement, 'role'),
            'language' => self::xmlLang($collectionElement),
            'dir' => self::nullableAttribute($collectionElement, 'dir'),
            'metadata' => $metadataElement instanceof \DOMElement ? $this->readMetadata($metadataElement, '') : [],
            'links' => $links,
            'children' => $children,
        ];
    }

    /**
     * @param array<string, array<string, mixed>> $manifestByPart
     *
     * @return array<string, mixed>
     */
    private function readCollectionLink(
        ZipPackage $package,
        string $opfPart,
        \DOMElement $linkElement,
        array $manifestByPart
    ): array {
        $href = self::nullableAttribute($linkElement, 'href');
        $reference = $this->packageReference($package, $opfPart, $href ?? '', $manifestByPart, 'collection');
        $declaredMediaType = self::nullableAttribute($linkElement, 'media-type');

        return [
            'id' => self::nullableAttribute($linkElement, 'id'),
            'rel' => self::spaceDelimited($linkElement->getAttribute('rel')),
            'href' => $href,
            'target' => $reference['target'],
            'part' => $reference['part'],
            'external' => $reference['external'],
            'exists' => $reference['exists'],
            'byteLength' => $reference['byteLength'],
            'crc32' => $reference['crc32'],
            'mediaType' => $declaredMediaType ?? $reference['mediaType'],
            'manifestId' => $reference['manifestId'],
            'manifestMediaType' => $reference['mediaType'],
            'properties' => self::spaceDelimited($linkElement->getAttribute('properties')),
            'title' => self::nullableAttribute($linkElement, 'title'),
            'refines' => self::nullableAttribute($linkElement, 'refines'),
            'encrypted' => $reference['encrypted'],
            'canExposeBytes' => $reference['canExposeBytes'],
            'diagnostics' => $reference['diagnostics'],
        ];
    }

    /**
     * @param array<string, array<string, mixed>> $manifestByPart
     *
     * @return array{
     *     target:?string,
     *     part:?string,
     *     external:bool,
     *     exists:bool,
     *     byteLength:?int,
     *     crc32:?string,
     *     manifestId:?string,
     *     mediaType:?string,
     *     encrypted:bool,
     *     canExposeBytes:bool,
     *     diagnostics:list<array<string, mixed>>
     * }
     */
    private function packageReference(
        ZipPackage $package,
        string $basePart,
        string $href,
        array $manifestByPart,
        string $context
    ): array {
        $href = trim($href);
        if ($href === '') {
            return [
                'target' => null,
                'part' => null,
                'external' => false,
                'exists' => false,
                'byteLength' => null,
                'crc32' => null,
                'manifestId' => null,
                'mediaType' => null,
                'encrypted' => false,
                'canExposeBytes' => false,
                'diagnostics' => [[
                    'type' => 'missing-' . $context . '-href',
                    'message' => 'EPUB OPF ' . $context . ' reference is missing href',
                ]],
            ];
        }

        if (self::isExternalReference($href)) {
            return [
                'target' => $href,
                'part' => null,
                'external' => true,
                'exists' => false,
                'byteLength' => null,
                'crc32' => null,
                'manifestId' => null,
                'mediaType' => null,
                'encrypted' => false,
                'canExposeBytes' => false,
                'diagnostics' => [[
                    'type' => $context === 'collection' ? 'external-collection-link' : 'external-' . $context . '-reference',
                    'href' => $href,
                    'message' => 'EPUB OPF ' . $context . ' reference points outside the package and was not fetched',
                ]],
            ];
        }

        try {
            $target = OpcPackagePath::resolveInternalTarget($basePart, $href);
        } catch (\InvalidArgumentException $exception) {
            return [
                'target' => null,
                'part' => null,
                'external' => false,
                'exists' => false,
                'byteLength' => null,
                'crc32' => null,
                'manifestId' => null,
                'mediaType' => null,
                'encrypted' => false,
                'canExposeBytes' => false,
                'diagnostics' => [[
                    'type' => 'invalid-' . $context . '-reference',
                    'href' => $href,
                    'message' => $exception->getMessage(),
                ]],
            ];
        }

        $part = OpcPackagePath::stripQueryAndFragment($target);
        $exists = $package->has($part);
        $entry = $exists ? $package->entry($part) : null;
        $manifestItem = $manifestByPart[$part] ?? null;

        $diagnostics = [];
        if (!$exists) {
            $diagnostics[] = [
                'type' => 'missing-' . $context . '-reference',
                'href' => $href,
                'part' => $part,
                'message' => 'EPUB OPF ' . $context . ' reference target is missing from the package',
            ];
        }

        return [
            'target' => $target,
            'part' => $part,
            'external' => false,
            'exists' => $exists,
            'byteLength' => $entry instanceof ZipPackageEntry ? $entry->uncompressedSize : null,
            'crc32' => $entry instanceof ZipPackageEntry ? $entry->crc32Hex() : null,
            'manifestId' => is_array($manifestItem) ? (string) $manifestItem['id'] : null,
            'mediaType' => is_array($manifestItem) ? (string) $manifestItem['mediaType'] : null,
            'encrypted' => is_array($manifestItem) && self::isEncryptedManifestItem($manifestItem),
            'canExposeBytes' => is_array($manifestItem) ? (bool) ($manifestItem['canExposeBytes'] ?? true) : $exists,
            'diagnostics' => $diagnostics,
        ];
    }

    /**
     * @return array{
     *     target:null,
     *     part:null,
     *     external:false,
     *     exists:false,
     *     byteLength:null,
     *     crc32:null,
     *     manifestId:null,
     *     mediaType:null,
     *     encrypted:false,
     *     canExposeBytes:false,
     *     diagnostics:list<array<string, mixed>>
     * }
     */
    private static function emptyPackageReference(): array
    {
        return [
            'target' => null,
            'part' => null,
            'external' => false,
            'exists' => false,
            'byteLength' => null,
            'crc32' => null,
            'manifestId' => null,
            'mediaType' => null,
            'encrypted' => false,
            'canExposeBytes' => false,
            'diagnostics' => [],
        ];
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
            $items = $list instanceof \DOMElement ? $this->readNavList($package, $list, (string) $item['part']) : [];
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
    private function readNavList(ZipPackage $package, \DOMElement $list, string $navPart): array
    {
        $items = [];
        foreach (self::childElements($list, 'li', self::XHTML_NS) as $li) {
            $link = self::firstChildElement($li, 'a', self::XHTML_NS);
            $label = $link instanceof \DOMElement ? $link : self::firstChildElement($li, 'span', self::XHTML_NS);
            $href = $link instanceof \DOMElement ? trim($link->getAttribute('href')) : '';
            $childList = self::firstChildElement($li, 'ol', self::XHTML_NS);
            $types = self::epubTypes($link ?? $label ?? $li);
            $reference = $href === ''
                ? self::emptyPackageReference()
                : $this->packageReference($package, $navPart, $href, [], 'nav');

            $items[] = [
                'title' => $label instanceof \DOMElement ? self::normalizedText($label) : self::normalizedText($li),
                'href' => $href === '' ? null : $href,
                'target' => $reference['target'],
                'part' => $reference['part'],
                'external' => $reference['external'],
                'exists' => $reference['exists'],
                'diagnostics' => $reference['diagnostics'],
                'type' => $types[0] ?? null,
                'types' => $types,
                'children' => $childList instanceof \DOMElement ? $this->readNavList($package, $childList, $navPart) : [],
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
            'items' => $navMap instanceof \DOMElement ? $this->readNcxPoints($package, $navMap, (string) $item['part']) : [],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function readNcxPoints(ZipPackage $package, \DOMElement $parent, string $ncxPart): array
    {
        $items = [];
        foreach (self::childElements($parent, 'navPoint', self::NCX_NS) as $point) {
            $navLabel = self::firstChildElement($point, 'navLabel', self::NCX_NS);
            $label = $navLabel instanceof \DOMElement
                ? self::firstDescendantElement($navLabel, 'text', self::NCX_NS)
                : null;
            $content = self::firstChildElement($point, 'content', self::NCX_NS);
            $src = $content instanceof \DOMElement ? trim($content->getAttribute('src')) : '';
            $reference = $src === ''
                ? self::emptyPackageReference()
                : $this->packageReference($package, $ncxPart, $src, [], 'ncx');

            $items[] = [
                'id' => self::nullableAttribute($point, 'id'),
                'playOrder' => self::nullableAttribute($point, 'playOrder'),
                'title' => $label instanceof \DOMElement ? self::normalizedText($label) : '',
                'href' => $src === '' ? null : $src,
                'target' => $reference['target'],
                'part' => $reference['part'],
                'external' => $reference['external'],
                'exists' => $reference['exists'],
                'diagnostics' => $reference['diagnostics'],
                'children' => $this->readNcxPoints($package, $point, $ncxPart),
            ];
        }

        return $items;
    }

    /**
     * @param array<string, array<string, mixed>> $manifestById
     *
     * @return array<string, array<string, mixed>>
     */
    private function readMediaOverlays(ZipPackage $package, array $manifestById): array
    {
        $references = [];
        foreach ($manifestById as $item) {
            $mediaOverlay = $item['mediaOverlay'] ?? null;
            if (!is_string($mediaOverlay) || $mediaOverlay === '') {
                continue;
            }

            $references[$mediaOverlay][] = (string) $item['id'];
        }

        $overlays = [];
        foreach ($references as $id => $referencedBy) {
            $item = $manifestById[$id] ?? null;
            if (!is_array($item)) {
                $overlays[$id] = [
                    'id' => $id,
                    'href' => null,
                    'target' => null,
                    'part' => null,
                    'mediaType' => null,
                    'exists' => false,
                    'referencedBy' => $referencedBy,
                    'encrypted' => false,
                    'canExposeBytes' => false,
                    'textRef' => null,
                    'textRefTarget' => null,
                    'items' => [],
                    'diagnostics' => [[
                        'type' => 'missing-media-overlay-manifest-item',
                        'id' => $id,
                        'message' => 'EPUB spine item references a media-overlay id that is not in the OPF manifest',
                    ]],
                ];
                continue;
            }

            $overlays[$id] = $this->readMediaOverlayItem($package, $item, $referencedBy);
        }

        return $overlays;
    }

    /**
     * @param array<string, mixed> $item
     * @param list<string> $referencedBy
     *
     * @return array<string, mixed>
     */
    private function readMediaOverlayItem(ZipPackage $package, array $item, array $referencedBy): array
    {
        $diagnostics = [];
        if (($item['mediaType'] ?? null) !== self::SMIL_MEDIA_TYPE) {
            $diagnostics[] = [
                'type' => 'unexpected-media-overlay-type',
                'id' => $item['id'],
                'mediaType' => $item['mediaType'] ?? null,
                'message' => 'EPUB media-overlay manifest item should be application/smil+xml',
            ];
        }

        if (($item['exists'] ?? false) !== true) {
            $diagnostics[] = [
                'type' => 'missing-media-overlay-part',
                'id' => $item['id'],
                'part' => $item['part'],
                'message' => 'EPUB media-overlay SMIL part is missing from the package',
            ];
        }

        if (self::isEncryptedManifestItem($item)) {
            $diagnostics[] = [
                'type' => 'encrypted-media-overlay',
                'id' => $item['id'],
                'part' => $item['part'],
                'message' => 'EPUB media-overlay SMIL part is encrypted and cannot be exposed as reviewer timing data',
            ];
        }

        $textRef = null;
        $textRefTarget = null;
        $textRefReference = null;
        $items = [];
        if (
            ($item['exists'] ?? false) === true
            && !self::isEncryptedManifestItem($item)
            && ($item['mediaType'] ?? null) === self::SMIL_MEDIA_TYPE
        ) {
            $dom = self::loadXml($package->read((string) $item['part']), 'EPUB SMIL media overlay XML');
            $root = $dom->documentElement;
            if (!$root instanceof \DOMElement || $root->localName !== 'smil' || $root->namespaceURI !== self::SMIL_NS) {
                throw new \InvalidArgumentException('EPUB SMIL media overlay must use the SMIL namespace');
            }

            $body = self::firstChildElement($root, 'body', self::SMIL_NS) ?? $root;
            $textRef = self::firstSmilTextRef($body);
            if ($textRef !== null) {
                $textRefReference = $this->smilReference($package, (string) $item['part'], $textRef);
                $textRefTarget = $textRefReference['target'];
            }
            $items = $this->readSmilOverlayItems($package, $body, (string) $item['part'], $textRefTarget);
        }

        return [
            'id' => $item['id'],
            'href' => $item['href'],
            'target' => $item['target'],
            'part' => $item['part'],
            'mediaType' => $item['mediaType'],
            'exists' => $item['exists'],
            'referencedBy' => $referencedBy,
            'encrypted' => self::isEncryptedManifestItem($item),
            'canExposeBytes' => (bool) ($item['canExposeBytes'] ?? true),
            'textRef' => $textRef,
            'textRefTarget' => $textRefTarget,
            'textRefExternal' => $textRefReference['external'] ?? false,
            'textRefDiagnostics' => $textRefReference['diagnostics'] ?? [],
            'items' => $items,
            'diagnostics' => $diagnostics,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function readSmilOverlayItems(
        ZipPackage $package,
        \DOMElement $element,
        string $smilPart,
        ?string $inheritedTextTarget
    ): array {
        $currentTextTarget = $inheritedTextTarget;
        $textRef = self::smilTextRef($element);
        if ($textRef !== null) {
            $currentTextTarget = $this->smilReference($package, $smilPart, $textRef)['target'];
        }

        $items = [];
        if ($element->localName === 'par' && $element->namespaceURI === self::SMIL_NS) {
            $items[] = $this->readSmilPar($package, $element, $smilPart, $currentTextTarget);
        }

        foreach (self::childElements($element) as $child) {
            if ($child->namespaceURI !== self::SMIL_NS || !in_array($child->localName, ['body', 'seq', 'par'], true)) {
                continue;
            }

            array_push($items, ...$this->readSmilOverlayItems($package, $child, $smilPart, $currentTextTarget));
        }

        return $items;
    }

    /**
     * @return array<string, mixed>
     */
    private function readSmilPar(ZipPackage $package, \DOMElement $par, string $smilPart, ?string $textTarget): array
    {
        $text = self::firstChildElement($par, 'text', self::SMIL_NS);
        $audio = self::firstChildElement($par, 'audio', self::SMIL_NS);
        $textSrc = $text instanceof \DOMElement ? self::nullableAttribute($text, 'src') : null;
        $audioSrc = $audio instanceof \DOMElement ? self::nullableAttribute($audio, 'src') : null;
        $textReference = $textSrc === null ? self::emptySmilReference($textTarget) : $this->smilReference($package, $smilPart, $textSrc);
        $audioReference = $this->smilReference($package, $smilPart, $audioSrc);

        return [
            'id' => self::nullableAttribute($par, 'id'),
            'types' => self::epubTypes($par),
            'textSrc' => $textSrc,
            'textTarget' => $textReference['target'],
            'textPart' => $textReference['part'],
            'textExternal' => $textReference['external'],
            'textExists' => $textReference['exists'],
            'audioSrc' => $audioSrc,
            'audioTarget' => $audioReference['target'],
            'audioPart' => $audioReference['part'],
            'audioExternal' => $audioReference['external'],
            'audioExists' => $audioReference['exists'],
            'audioByteLength' => $audioReference['byteLength'],
            'audioCrc32' => $audioReference['crc32'],
            'clipBegin' => $audio instanceof \DOMElement ? self::nullableAttribute($audio, 'clipBegin') : null,
            'clipEnd' => $audio instanceof \DOMElement ? self::nullableAttribute($audio, 'clipEnd') : null,
            'diagnostics' => array_merge($textReference['diagnostics'], $audioReference['diagnostics']),
        ];
    }

    /**
     * @return array{target:?string, part:?string, external:bool, exists:bool, byteLength:?int, crc32:?string, diagnostics:list<array<string, mixed>>}
     */
    private function smilReference(ZipPackage $package, string $basePart, ?string $src): array
    {
        $src = trim((string) $src);
        if ($src === '') {
            return self::emptySmilReference(null);
        }

        if (self::isExternalReference($src)) {
            return [
                'target' => $src,
                'part' => null,
                'external' => true,
                'exists' => false,
                'byteLength' => null,
                'crc32' => null,
                'diagnostics' => [[
                    'type' => 'external-media-overlay-reference',
                    'src' => $src,
                    'message' => 'EPUB media-overlay reference points outside the package and was not fetched',
                ]],
            ];
        }

        try {
            $target = OpcPackagePath::resolveInternalTarget($basePart, $src);
        } catch (\InvalidArgumentException $exception) {
            return [
                'target' => null,
                'part' => null,
                'external' => false,
                'exists' => false,
                'byteLength' => null,
                'crc32' => null,
                'diagnostics' => [[
                    'type' => 'invalid-media-overlay-reference',
                    'src' => $src,
                    'message' => $exception->getMessage(),
                ]],
            ];
        }

        $part = OpcPackagePath::stripQueryAndFragment($target);
        $exists = $package->has($part);
        $entry = $exists ? $package->entry($part) : null;

        return [
            'target' => $target,
            'part' => $part,
            'external' => false,
            'exists' => $exists,
            'byteLength' => $entry instanceof ZipPackageEntry ? $entry->uncompressedSize : null,
            'crc32' => $entry instanceof ZipPackageEntry ? $entry->crc32Hex() : null,
            'diagnostics' => $exists ? [] : [[
                'type' => 'missing-media-overlay-reference',
                'src' => $src,
                'part' => $part,
                'message' => 'EPUB media-overlay reference target is missing from the package',
            ]],
        ];
    }

    /**
     * @return array{target:?string, part:?string, external:bool, exists:bool, byteLength:?int, crc32:?string, diagnostics:list<array<string, mixed>>}
     */
    private static function emptySmilReference(?string $target): array
    {
        $external = is_string($target) && self::isExternalReference($target);

        return [
            'target' => $target,
            'part' => $target === null || $external ? null : OpcPackagePath::stripQueryAndFragment($target),
            'external' => $external,
            'exists' => false,
            'byteLength' => null,
            'crc32' => null,
            'diagnostics' => [],
        ];
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
                'mediaOverlay' => $item['mediaOverlay'],
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
    private function assetReport(ZipPackage $package, string $opfPart, array $manifest, array $metadata): array
    {
        $assets = [];
        $manifestParts = [];
        foreach ($manifest as $item) {
            $manifestParts[(string) $item['part']] = true;
            if ($item['mediaType'] === self::XHTML_MEDIA_TYPE) {
                continue;
            }

            $isCoverImage = self::isCoverImageAsset($item, $metadata);
            $role = self::assetRole($item, $isCoverImage);
            $canExposeBytes = (bool) ($item['canExposeBytes'] ?? true);
            $exportCandidate = self::isExportCandidate($item, $role);
            $byteSha256 = null;
            $diagnostics = [];
            if (($item['exists'] ?? false) === true && $canExposeBytes && $exportCandidate) {
                try {
                    $byteSha256 = hash('sha256', $package->read((string) $item['part']));
                } catch (\Throwable $exception) {
                    $diagnostics[] = [
                        'type' => 'asset-bytes-unavailable',
                        'part' => (string) $item['part'],
                        'message' => $exception->getMessage(),
                    ];
                }
            }

            $assets[] = [
                'id' => $item['id'],
                'href' => $item['href'],
                'target' => $item['target'],
                'part' => $item['part'],
                'mediaType' => $item['mediaType'],
                'properties' => $item['properties'],
                'exists' => $item['exists'],
                'byteLength' => $item['byteLength'],
                'crc32' => $item['crc32'],
                'byteSha256' => $byteSha256,
                'encrypted' => self::isEncryptedManifestItem($item),
                'canExposeBytes' => $canExposeBytes,
                'encryption' => $item['encryption'] ?? null,
                'role' => $role,
                'isCoverImage' => $isCoverImage,
                'coverImageSources' => self::coverImageSources($item, $metadata),
                'exportCandidate' => $exportCandidate,
                'attachmentCandidate' => self::isAttachmentCandidate((string) $item['mediaType'], (string) $item['part'], $isCoverImage)
                    && ($item['exists'] ?? false) === true
                    && $canExposeBytes,
                'attachmentRole' => self::attachmentRole((string) $item['mediaType'], (string) $item['part'], $isCoverImage),
                'diagnostics' => $diagnostics,
            ];
        }

        $coverImage = null;
        foreach ($assets as $asset) {
            if (($asset['isCoverImage'] ?? false) === true) {
                $coverImage = $asset;
                break;
            }
        }

        $attachmentCandidates = array_values(array_filter(
            $assets,
            static fn (array $asset): bool => ($asset['attachmentCandidate'] ?? false) === true,
        ));
        $unmanifestedItems = $this->unmanifestedPackageAssets($package, $manifestParts, $opfPart);
        $diagnostics = [];
        if ($unmanifestedItems !== []) {
            $diagnostics[] = [
                'type' => 'unmanifested-package-assets',
                'message' => 'EPUB package contains non-structural resources that are not listed in the OPF manifest',
                'items' => $unmanifestedItems,
            ];
        }

        return [
            'count' => count($assets),
            'items' => $assets,
            'coverImage' => $coverImage,
            'attachmentCandidateCount' => count($attachmentCandidates),
            'attachmentCandidates' => $attachmentCandidates,
            'unmanifestedCount' => count($unmanifestedItems),
            'unmanifestedItems' => $unmanifestedItems,
            'diagnostics' => $diagnostics,
        ];
    }

    /**
     * @param array<string, bool> $manifestParts
     *
     * @return list<array<string, mixed>>
     */
    private function unmanifestedPackageAssets(ZipPackage $package, array $manifestParts, string $opfPart): array
    {
        $items = [];
        foreach ($package->entries() as $entry) {
            if ($entry->isDirectory()) {
                continue;
            }

            $part = OpcPackagePath::canonicalPartName($entry->name);
            if (isset($manifestParts[$part]) || self::isEpubStructuralPart($part, $opfPart)) {
                continue;
            }

            $mediaType = self::mediaTypeFromPart($part);
            $attachmentCandidate = self::isAttachmentCandidate($mediaType, $part, false);
            $diagnostics = [[
                'type' => 'unmanifested-package-resource',
                'part' => $part,
                'message' => 'EPUB package resource is present in ZIP but absent from the OPF manifest',
            ]];
            $byteSha256 = null;
            try {
                $byteSha256 = hash('sha256', $package->read($part));
            } catch (\Throwable $exception) {
                $diagnostics[] = [
                    'type' => 'unmanifested-package-resource-bytes-unavailable',
                    'part' => $part,
                    'message' => $exception->getMessage(),
                ];
            }

            $items[] = [
                'part' => $part,
                'mediaType' => $mediaType,
                'exists' => true,
                'byteLength' => $entry->uncompressedSize,
                'crc32' => $entry->crc32Hex(),
                'byteSha256' => $byteSha256,
                'attachmentCandidate' => $attachmentCandidate,
                'attachmentRole' => self::attachmentRole($mediaType, $part, false),
                'diagnostics' => $diagnostics,
            ];
        }

        return $items;
    }

    /**
     * @param array<string, mixed> $item
     * @param array<string, mixed> $metadata
     */
    private static function isCoverImageAsset(array $item, array $metadata): bool
    {
        return in_array('cover-image', $item['properties'] ?? [], true)
            || ((string) ($metadata['coverItemId'] ?? '') !== '' && (string) $item['id'] === (string) $metadata['coverItemId']);
    }

    /**
     * @param array<string, mixed> $item
     * @param array<string, mixed> $metadata
     *
     * @return list<string>
     */
    private static function coverImageSources(array $item, array $metadata): array
    {
        $sources = [];
        if (in_array('cover-image', $item['properties'] ?? [], true)) {
            $sources[] = 'manifest-property-cover-image';
        }
        if ((string) ($metadata['coverItemId'] ?? '') !== '' && (string) $item['id'] === (string) $metadata['coverItemId']) {
            $sources[] = 'meta-name-cover';
        }

        return $sources;
    }

    /**
     * @param array<string, mixed> $item
     */
    private static function assetRole(array $item, bool $isCoverImage): string
    {
        if ($isCoverImage) {
            return 'cover-image';
        }

        $mediaType = strtolower((string) ($item['mediaType'] ?? ''));
        if ($mediaType === self::NCX_MEDIA_TYPE) {
            return 'navigation';
        }
        if ($mediaType === self::SMIL_MEDIA_TYPE) {
            return 'media-overlay';
        }
        if ($mediaType === 'text/css') {
            return 'stylesheet';
        }
        if (str_starts_with($mediaType, 'image/')) {
            return 'image';
        }
        if (str_starts_with($mediaType, 'audio/')) {
            return 'audio';
        }
        if (str_starts_with($mediaType, 'video/')) {
            return 'video';
        }
        if (self::isFontResource($mediaType, (string) ($item['part'] ?? ''))) {
            return 'font';
        }

        return 'asset';
    }

    /**
     * @param array<string, mixed> $item
     */
    private static function isExportCandidate(array $item, string $role): bool
    {
        if (($item['exists'] ?? false) !== true || self::isEncryptedManifestItem($item)) {
            return false;
        }

        return !in_array($role, ['navigation', 'media-overlay'], true);
    }

    private static function isAttachmentCandidate(?string $mediaType, string $part, bool $isCoverImage): bool
    {
        if ($isCoverImage) {
            return true;
        }

        $mediaType = strtolower((string) $mediaType);

        return str_starts_with($mediaType, 'image/')
            || str_starts_with($mediaType, 'audio/')
            || str_starts_with($mediaType, 'video/')
            || self::isFontResource($mediaType, $part);
    }

    private static function attachmentRole(?string $mediaType, string $part, bool $isCoverImage): ?string
    {
        if ($isCoverImage) {
            return 'cover-image';
        }

        $mediaType = strtolower((string) $mediaType);
        if (str_starts_with($mediaType, 'image/')) {
            return 'image';
        }
        if (str_starts_with($mediaType, 'audio/')) {
            return 'audio';
        }
        if (str_starts_with($mediaType, 'video/')) {
            return 'video';
        }
        if (self::isFontResource($mediaType, $part)) {
            return 'font';
        }

        return null;
    }

    private static function isEpubStructuralPart(string $part, string $opfPart): bool
    {
        return $part === '/mimetype'
            || $part === $opfPart
            || str_starts_with($part, '/META-INF/')
            || str_ends_with(strtolower($part), '.opf');
    }

    private static function mediaTypeFromPart(string $part): ?string
    {
        return match (strtolower(pathinfo($part, PATHINFO_EXTENSION))) {
            'apng' => 'image/apng',
            'avif' => 'image/avif',
            'css' => 'text/css',
            'gif' => 'image/gif',
            'jpg', 'jpeg' => 'image/jpeg',
            'mp3' => 'audio/mpeg',
            'mp4' => 'video/mp4',
            'ncx' => self::NCX_MEDIA_TYPE,
            'otf' => 'font/otf',
            'png' => 'image/png',
            'smil' => self::SMIL_MEDIA_TYPE,
            'svg' => 'image/svg+xml',
            'ttf' => 'font/ttf',
            'webp' => 'image/webp',
            'woff' => 'font/woff',
            'woff2' => 'font/woff2',
            'xhtml', 'html', 'htm' => self::XHTML_MEDIA_TYPE,
            default => null,
        };
    }

    /**
     * @param array<string, mixed> $metadata
     * @param list<array<string, mixed>> $spine
     * @param list<array<string, mixed>> $xhtmlAssets
     * @param array<string, mixed> $guide
     * @param list<array<string, mixed>> $collections
     * @param array<string, mixed> $renditions
     */
    private function documentNode(
        array $metadata,
        string $opfPart,
        array $spine,
        array $xhtmlAssets,
        array $guide,
        array $collections,
        array $renditions
    ): AstNode {
        $assetsByPart = [];
        foreach ($xhtmlAssets as $asset) {
            $assetsByPart[(string) $asset['part']] = $asset;
        }

        $children = [];
        foreach ($spine as $item) {
            $contentMediaType = $item['contentMediaType'] ?? $item['mediaType'];
            if ($contentMediaType !== self::XHTML_MEDIA_TYPE) {
                continue;
            }

            $contentPart = (string) ($item['contentPart'] ?? $item['part']);
            $asset = $assetsByPart[$contentPart] ?? null;
            if (!is_array($asset)) {
                continue;
            }

            $isFallback = (bool) ($item['contentIsFallback'] ?? false);
            $attributes = [
                'format' => 'html',
                'html' => $asset['html'],
                'part' => $asset['part'],
                'id' => $item['idref'],
                'linear' => $item['linear'],
                'mediaOverlay' => $item['mediaOverlay'],
                'source' => $isFallback ? 'epub3-spine-fallback' : 'epub3-spine',
            ];

            if ($isFallback) {
                $attributes['spinePart'] = $item['part'];
                $attributes['spineMediaType'] = $item['mediaType'];
                $attributes['fallbackOf'] = $item['idref'];
                $attributes['contentId'] = $item['contentId'];
                $attributes['fallbackChain'] = $item['fallbackChain'];
            }

            $children[] = new AstNode('raw_html', $attributes);
        }

        return new AstNode('document', [
            'source' => 'epub3',
            'opfPart' => $opfPart,
            'metadata' => $metadata,
            'guide' => $guide,
            'collections' => $collections,
            'renditions' => $renditions,
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

    private static function smilTextRef(\DOMElement $element): ?string
    {
        $textRef = trim($element->getAttributeNS(self::EPUB_OPS_NS, 'textref'));
        if ($textRef === '') {
            $textRef = trim($element->getAttribute('epub:textref'));
        }
        if ($textRef === '') {
            $textRef = trim($element->getAttribute('textref'));
        }

        return $textRef === '' ? null : $textRef;
    }

    private static function isExternalReference(string $reference): bool
    {
        return preg_match('/^[A-Za-z][A-Za-z0-9+.-]*:/', $reference) === 1
            || str_starts_with($reference, '//');
    }

    private static function firstSmilTextRef(\DOMElement $element): ?string
    {
        $textRef = self::smilTextRef($element);
        if ($textRef !== null) {
            return $textRef;
        }

        foreach (self::childElements($element) as $child) {
            if ($child->namespaceURI !== self::SMIL_NS) {
                continue;
            }

            $textRef = self::firstSmilTextRef($child);
            if ($textRef !== null) {
                return $textRef;
            }
        }

        return null;
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
        if (self::isExternalReference($uri)) {
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

    /**
     * @param array<string, mixed> $item
     */
    private static function canExposeXhtmlContent(array $item): bool
    {
        return ($item['mediaType'] ?? null) === self::XHTML_MEDIA_TYPE
            && ($item['exists'] ?? false) === true
            && !self::isEncryptedManifestItem($item)
            && ($item['canExposeBytes'] ?? true) === true;
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
