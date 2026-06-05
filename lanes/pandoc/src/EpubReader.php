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
     *     accessibility:array<string, mixed>,
     *     container:array<string, mixed>,
     *     opfPart:string,
     *     package:array<string, mixed>,
     *     manifest:list<array<string, mixed>>,
     *     spine:list<array<string, mixed>>,
     *     spineProperties:array<string, mixed>,
     *     nav:?array<string, mixed>,
     *     ncx:?array<string, mixed>,
     *     navigation:array<string, mixed>,
     *     guide:array<string, mixed>,
     *     collections:list<array<string, mixed>>,
     *     renditions:array<string, mixed>,
     *     bindings:array<string, mixed>,
     *     resourceProperties:array<string, mixed>,
     *     remoteResources:array<string, mixed>,
     *     encryption:array<string, mixed>,
     *     mediaOverlays:array<string, array<string, mixed>>,
     *     mediaDurations:array<string, mixed>,
     *     pageBreaks:array<string, mixed>,
     *     xhtmlAssets:list<array<string, mixed>>,
     *     xhtmlResourceReport:array<string, mixed>,
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
            $opf['spineProperties'],
            $opf['xhtmlAssets'],
            $opf['guide'],
            $opf['collections'],
            $opf['bindings'],
            $opf['accessibility'],
            $opf['resourceProperties'],
            $opf['remoteResources'],
            $opf['mediaDurations'],
            $opf['pageBreaks'],
            $opf['navigation'],
            $opf['xhtmlResourceReport'],
            $renditions
        );

        return [
            'document' => $document,
            'metadata' => $opf['metadata'],
            'accessibility' => $opf['accessibility'],
            'container' => $container,
            'opfPart' => $opfPart,
            'package' => $opf['package'],
            'manifest' => $opf['manifest'],
            'spine' => $opf['spine'],
            'spineProperties' => $opf['spineProperties'],
            'nav' => $opf['nav'],
            'ncx' => $opf['ncx'],
            'navigation' => $opf['navigation'],
            'guide' => $opf['guide'],
            'collections' => $opf['collections'],
            'renditions' => $renditions,
            'bindings' => $opf['bindings'],
            'resourceProperties' => $opf['resourceProperties'],
            'remoteResources' => $opf['remoteResources'],
            'encryption' => $opf['encryption'],
            'mediaOverlays' => $opf['mediaOverlays'],
            'mediaDurations' => $opf['mediaDurations'],
            'pageBreaks' => $opf['pageBreaks'],
            'xhtmlAssets' => $opf['xhtmlAssets'],
            'xhtmlResourceReport' => $opf['xhtmlResourceReport'],
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
                        static fn (array $item): bool => ($item['exists'] ?? false) !== true
                            && ($item['external'] ?? false) !== true,
                    )),
                    'externalItems' => array_values(array_filter(
                        $opf['manifest'],
                        static fn (array $item): bool => ($item['external'] ?? false) === true,
                    )),
                ],
                'spine' => [
                    'count' => count($opf['spine']),
                    'items' => $opf['spine'],
                    'properties' => $opf['spineProperties'],
                ],
                'nav' => $opf['nav'],
                'ncx' => $opf['ncx'],
                'navigation' => $opf['navigation'],
                'guide' => $opf['guide'],
                'collections' => $opf['collections'],
                'renditions' => $renditions,
                'bindings' => $opf['bindings'],
                'accessibility' => $opf['accessibility'],
                'resourceProperties' => $opf['resourceProperties'],
                'remoteResources' => $opf['remoteResources'],
                'xhtmlResourceReport' => $opf['xhtmlResourceReport'],
                'encryption' => $opf['encryption'],
                'mediaOverlays' => $opf['mediaOverlays'],
                'mediaDurations' => $opf['mediaDurations'],
                'pageBreaks' => $opf['pageBreaks'],
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

        $entries = $package->localEntries();
        if ($entries === [] || $entries[0]->name !== 'mimetype') {
            throw new \RuntimeException('EPUB mimetype entry must be the first local ZIP entry');
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
     *     spineProperties:array<string, mixed>,
     *     nav:?array<string, mixed>,
     *     ncx:?array<string, mixed>,
     *     navigation:array<string, mixed>,
     *     guide:array<string, mixed>,
     *     collections:list<array<string, mixed>>,
     *     bindings:array<string, mixed>,
     *     accessibility:array<string, mixed>,
     *     resourceProperties:array<string, mixed>,
     *     remoteResources:array<string, mixed>,
     *     encryption:array<string, mixed>,
     *     mediaOverlays:array<string, array<string, mixed>>,
     *     mediaDurations:array<string, mixed>,
     *     pageBreaks:array<string, mixed>,
     *     xhtmlAssets:list<array<string, mixed>>,
     *     xhtmlResourceReport:array<string, mixed>,
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
        $prefixReport = self::packagePrefixReport(trim($root->getAttribute('prefix')));
        $metadata = $this->readMetadata($metadataElement, $uniqueIdentifier);
        $refinementsById = is_array($metadata['refinementsById'] ?? null) ? $metadata['refinementsById'] : [];
        $packageId = self::nullableAttribute($root, 'id');
        $manifestById = $this->readManifest($package, $opfPart, $manifestElement, $refinementsById);
        $encryption = $this->readEncryption($package, $manifestById);
        $manifestById = $this->attachEncryptionToManifest($manifestById, $encryption);
        $metadata = $this->resolveMetadataLinks($package, $opfPart, $metadata, $manifestById);
        $guide = $this->readGuide($package, $opfPart, self::firstChildElement($root, 'guide', self::OPF_NS), $manifestById);
        $collections = $this->readCollections($package, $opfPart, $root, $manifestById);
        $bindings = $this->readBindings($package, self::firstChildElement($root, 'bindings', self::OPF_NS), $manifestById);
        $spineProperties = self::readSpineProperties($spineElement, $refinementsById);
        $spine = $this->readSpine($spineElement, $manifestById, $bindings, $refinementsById);
        $spineProperties = self::spinePropertiesWithItemDiagnostics($spineProperties, $spine);
        $mediaDurations = self::mediaDurationReport($metadata, $manifestById);
        $metadata['mediaDurations'] = $mediaDurations;
        $mediaOverlays = $this->readMediaOverlays($package, $manifestById, $mediaDurations);
        $manifest = array_values($manifestById);
        $resourceProperties = self::resourcePropertyReport($manifest);
        $navItem = $this->firstManifestItemWithProperty($manifest, 'nav');
        $ncxItem = $this->ncxManifestItem($spineElement, $manifestById, $manifest);
        $assetReport = $this->assetReport($package, $opfPart, $manifest, $metadata);
        $nav = $navItem === null ? null : $this->readNavDocument($package, $navItem);
        $ncx = $ncxItem === null ? null : $this->readNcxDocument($package, $ncxItem);
        $navigation = self::navigationReport($nav, $ncx, $spine);
        $pageBreaks = self::pageBreakReport($nav, $spine);
        $xhtmlAssets = $this->xhtmlAssets($package, $manifest, self::manifestByPart($manifestById));
        $xhtmlResourceReport = self::xhtmlResourceReport($xhtmlAssets);
        $remoteResources = self::remoteResourceReport($manifest, $xhtmlAssets, $xhtmlResourceReport);

        return [
            'metadata' => $metadata,
            'package' => [
                'id' => $packageId,
                'version' => trim($root->getAttribute('version')),
                'uniqueIdentifierId' => $uniqueIdentifier === '' ? null : $uniqueIdentifier,
                'uniqueIdentifier' => $metadata['uniqueIdentifier'],
                'opfPart' => $opfPart,
                'language' => self::xmlLang($root),
                'refinements' => self::metadataRefinementsForId($refinementsById, $packageId),
                'prefix' => $prefixReport['raw'],
                'prefixes' => $prefixReport['bindingsByPrefix'],
                'prefixBindings' => $prefixReport['bindings'],
                'prefixDiagnostics' => $prefixReport['diagnostics'],
            ],
            'manifest' => $manifest,
            'spine' => $spine,
            'spineProperties' => $spineProperties,
            'nav' => $nav,
            'ncx' => $ncx,
            'navigation' => $navigation,
            'guide' => $guide,
            'collections' => $collections,
            'bindings' => $bindings,
            'accessibility' => $metadata['accessibility'],
            'resourceProperties' => $resourceProperties,
            'remoteResources' => $remoteResources,
            'encryption' => $encryption,
            'mediaOverlays' => $mediaOverlays,
            'mediaDurations' => $mediaDurations,
            'pageBreaks' => $pageBreaks,
            'xhtmlAssets' => $xhtmlAssets,
            'xhtmlResourceReport' => $xhtmlResourceReport,
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
     * @return array{
     *     raw:string,
     *     bindings:list<array{index:int, prefix:string, iri:string}>,
     *     bindingsByPrefix:array<string, string>,
     *     diagnostics:list<array<string, mixed>>
     * }
     */
    private static function packagePrefixReport(string $raw): array
    {
        $value = trim($raw);
        $bindings = [];
        $bindingsByPrefix = [];
        $diagnostics = [];

        $offset = 0;
        $length = strlen($value);
        while ($offset < $length) {
            $offset += strspn($value, " \t\r\n", $offset);
            if ($offset >= $length) {
                break;
            }

            $segment = substr($value, $offset);
            if (!preg_match('/^([A-Za-z_][A-Za-z0-9._-]*):[ \t\r\n]+([^ \t\r\n]+)/', $segment, $match)) {
                $diagnostics[] = [
                    'type' => 'invalid-package-prefix-declaration',
                    'offset' => $offset,
                    'value' => $segment,
                    'message' => 'EPUB OPF prefix declarations must be prefix: IRI pairs separated by whitespace',
                ];
                break;
            }

            $prefix = $match[1];
            $iri = $match[2];
            if (isset($bindingsByPrefix[$prefix])) {
                $diagnostics[] = [
                    'type' => 'duplicate-package-prefix-declaration',
                    'prefix' => $prefix,
                    'previousIri' => $bindingsByPrefix[$prefix],
                    'iri' => $iri,
                    'message' => 'EPUB OPF prefix declaration repeats a prefix; later binding is retained',
                ];
            }

            $bindingsByPrefix[$prefix] = $iri;
            $bindings[] = [
                'index' => count($bindings),
                'prefix' => $prefix,
                'iri' => $iri,
            ];
            $offset += strlen($match[0]);
        }

        return [
            'raw' => $value,
            'bindings' => $bindings,
            'bindingsByPrefix' => $bindingsByPrefix,
            'diagnostics' => $diagnostics,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function readMetadata(
        \DOMElement $metadataElement,
        string $uniqueIdentifier,
        bool $requireUniqueIdentifier = true
    ): array
    {
        $dc = [];
        $metaProperties = [];
        $metaNames = [];
        $links = [];
        $raw = [];

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
                continue;
            }

            if ($child->namespaceURI !== self::OPF_NS) {
                continue;
            }

            if ($child->localName === 'link') {
                $entry = [
                    'index' => count($links),
                    'id' => self::nullableAttribute($child, 'id'),
                    'rel' => self::spaceDelimited($child->getAttribute('rel')),
                    'href' => self::nullableAttribute($child, 'href'),
                    'mediaType' => self::nullableAttribute($child, 'media-type'),
                    'properties' => self::spaceDelimited($child->getAttribute('properties')),
                    'refines' => self::nullableAttribute($child, 'refines'),
                    'hreflang' => self::nullableAttribute($child, 'hreflang'),
                ];
                $links[] = $entry;
                $raw[] = ['type' => 'link'] + $entry;
                continue;
            }

            if ($child->localName !== 'meta') {
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
                'language' => self::xmlLang($child),
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

        $refinementsById = self::metadataRefinementsById($metaProperties);
        $dc = self::attachMetadataRefinements($dc, $refinementsById);
        $identifiers = array_map(
            static fn (array $entry): string => $entry['text'],
            $dc['identifier'] ?? []
        );
        $uniqueIdentifierReport = self::uniqueIdentifierReport($uniqueIdentifier, $dc, $requireUniqueIdentifier);

        $metadata = [
            'title' => $dc['title'][0]['text'] ?? '',
            'creators' => array_map(static fn (array $entry): string => $entry['text'], $dc['creator'] ?? []),
            'language' => $dc['language'][0]['text'] ?? null,
            'identifier' => $uniqueIdentifierReport['value'],
            'uniqueIdentifier' => $uniqueIdentifierReport,
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
            'refinementsById' => $refinementsById,
            'links' => $links,
            'linksByRel' => self::linksByRel($links),
            'raw' => $raw,
        ];
        $metadata['accessibility'] = self::accessibilityMetadataReport($metadata);

        return $metadata;
    }

    /**
     * @param array<string, list<array<string, mixed>>> $dc
     *
     * @return array<string, mixed>
     */
    private static function uniqueIdentifierReport(string $uniqueIdentifierId, array $dc, bool $required): array
    {
        $id = trim($uniqueIdentifierId);
        $specified = $id !== '';
        $identifierEntries = [];
        foreach ($dc['identifier'] ?? [] as $index => $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $identifierEntries[] = self::identifierReportEntry($entry, (int) $index);
        }

        $matchedEntries = [];
        if ($specified) {
            foreach ($identifierEntries as $entry) {
                if (($entry['id'] ?? null) === $id) {
                    $matchedEntries[] = $entry;
                }
            }
        }

        $value = null;
        $selectedBy = null;
        if ($matchedEntries !== []) {
            $value = (string) $matchedEntries[0]['text'];
            $selectedBy = 'unique-identifier';
        } elseif ($identifierEntries !== []) {
            $value = (string) $identifierEntries[0]['text'];
            $selectedBy = 'first-dc-identifier';
        }

        $diagnostics = [];
        if ($required && !$specified) {
            $diagnostics[] = [
                'type' => 'missing-unique-identifier',
                'message' => 'EPUB OPF package is missing the unique-identifier attribute',
            ];
        }
        if ($specified && $matchedEntries === []) {
            $diagnostics[] = [
                'type' => 'unique-identifier-not-found',
                'id' => $id,
                'message' => 'EPUB OPF unique-identifier does not match any dc:identifier id',
            ];
        }
        if ($required && $identifierEntries === []) {
            $diagnostics[] = [
                'type' => 'missing-dc-identifier',
                'message' => 'EPUB OPF metadata does not contain a dc:identifier entry',
            ];
        }
        if (count($matchedEntries) > 1) {
            $diagnostics[] = [
                'type' => 'duplicate-unique-identifier-id',
                'id' => $id,
                'values' => array_map(
                    static fn (array $entry): string => (string) $entry['text'],
                    $matchedEntries
                ),
                'message' => 'EPUB OPF metadata contains multiple dc:identifier entries with the unique-identifier id',
            ];
        }

        return [
            'specified' => $specified,
            'id' => $specified ? $id : null,
            'present' => $value !== null,
            'matched' => $matchedEntries !== [],
            'value' => $value,
            'selectedBy' => $selectedBy,
            'identifierCount' => count($identifierEntries),
            'matchCount' => count($matchedEntries),
            'duplicateMatchCount' => max(0, count($matchedEntries) - 1),
            'entries' => $identifierEntries,
            'matchedEntries' => $matchedEntries,
            'valid' => $diagnostics === [],
            'diagnostics' => $diagnostics,
        ];
    }

    /**
     * @param array<string, mixed> $entry
     *
     * @return array<string, mixed>
     */
    private static function identifierReportEntry(array $entry, int $index): array
    {
        return [
            'index' => $index,
            'id' => is_string($entry['id'] ?? null) ? $entry['id'] : null,
            'text' => (string) ($entry['text'] ?? ''),
            'scheme' => is_string($entry['scheme'] ?? null) ? $entry['scheme'] : null,
            'language' => is_string($entry['language'] ?? null) ? $entry['language'] : null,
            'refinements' => is_array($entry['refinements'] ?? null) ? $entry['refinements'] : [],
        ];
    }

    /**
     * @param array<string, list<array<string, mixed>>> $metaProperties
     *
     * @return array<string, array<string, list<array<string, mixed>>>>
     */
    private static function metadataRefinementsById(array $metaProperties): array
    {
        $refinements = [];
        foreach ($metaProperties as $property => $entries) {
            if (!is_string($property) || $property === '' || !is_array($entries)) {
                continue;
            }

            foreach ($entries as $entry) {
                if (!is_array($entry)) {
                    continue;
                }

                $subject = self::metadataRefinementSubject($entry['refines'] ?? null);
                if ($subject === null) {
                    continue;
                }

                $refinements[$subject][$property][] = [
                    'property' => $property,
                    'subjectId' => $subject,
                    'refines' => (string) ($entry['refines'] ?? ''),
                    'text' => (string) ($entry['text'] ?? ''),
                    'content' => is_string($entry['content'] ?? null) ? $entry['content'] : null,
                    'scheme' => is_string($entry['scheme'] ?? null) ? $entry['scheme'] : null,
                    'id' => is_string($entry['id'] ?? null) ? $entry['id'] : null,
                    'language' => is_string($entry['language'] ?? null) ? $entry['language'] : null,
                ];
            }
        }

        return $refinements;
    }

    /**
     * @param array<string, array<string, list<array<string, mixed>>>> $refinementsById
     *
     * @return array<string, list<array<string, mixed>>>
     */
    private static function metadataRefinementsForId(array $refinementsById, ?string $id): array
    {
        if ($id === null || $id === '') {
            return [];
        }

        return is_array($refinementsById[$id] ?? null) ? $refinementsById[$id] : [];
    }

    private static function metadataRefinementSubject(mixed $refines): ?string
    {
        if (!is_string($refines)) {
            return null;
        }

        $refines = trim($refines);
        if ($refines === '') {
            return null;
        }

        if (str_starts_with($refines, '#')) {
            $subject = substr($refines, 1);

            return $subject === '' ? null : $subject;
        }

        $fragmentOffset = strpos($refines, '#');
        if ($fragmentOffset === false) {
            return null;
        }

        $subject = substr($refines, $fragmentOffset + 1);

        return $subject === '' ? null : $subject;
    }

    /**
     * @param array<string, list<array<string, mixed>>> $dc
     * @param array<string, array<string, list<array<string, mixed>>>> $refinementsById
     *
     * @return array<string, list<array<string, mixed>>>
     */
    private static function attachMetadataRefinements(array $dc, array $refinementsById): array
    {
        foreach ($dc as $name => $entries) {
            if (!is_array($entries)) {
                continue;
            }

            foreach ($entries as $index => $entry) {
                if (!is_array($entry)) {
                    continue;
                }

                $id = is_string($entry['id'] ?? null) ? $entry['id'] : null;
                $dc[$name][$index]['refinements'] = $id !== null && isset($refinementsById[$id])
                    ? $refinementsById[$id]
                    : [];
            }
        }

        return $dc;
    }

    /**
     * @param array<string, mixed> $metadata
     * @param array<string, array<string, mixed>> $manifestById
     *
     * @return array<string, mixed>
     */
    private function resolveMetadataLinks(
        ZipPackage $package,
        string $opfPart,
        array $metadata,
        array $manifestById
    ): array {
        $manifestByPart = self::manifestByPart($manifestById);
        $links = [];
        foreach (($metadata['links'] ?? []) as $index => $link) {
            if (!is_array($link)) {
                continue;
            }

            $href = $link['href'] ?? null;
            $reference = $this->packageReference($package, $opfPart, is_string($href) ? $href : '', $manifestByPart, 'metadata');
            $diagnostics = $reference['diagnostics'];
            $byteSha256 = null;
            if (
                ($reference['exists'] ?? false) === true
                && ($reference['external'] ?? false) !== true
                && ($reference['canExposeBytes'] ?? false) === true
                && is_string($reference['part'] ?? null)
            ) {
                try {
                    $byteSha256 = hash('sha256', $package->read((string) $reference['part']));
                } catch (\Throwable $exception) {
                    $diagnostics[] = [
                        'type' => 'metadata-link-bytes-unavailable',
                        'part' => (string) $reference['part'],
                        'message' => $exception->getMessage(),
                    ];
                }
            }

            $declaredMediaType = $link['mediaType'] ?? null;
            $links[] = [
                'index' => $index,
                'id' => is_string($link['id'] ?? null) ? $link['id'] : null,
                'rel' => is_array($link['rel'] ?? null) ? array_values($link['rel']) : [],
                'href' => is_string($href) && $href !== '' ? $href : null,
                'target' => $reference['target'],
                'part' => $reference['part'],
                'external' => $reference['external'],
                'exists' => $reference['exists'],
                'byteLength' => $reference['byteLength'],
                'crc32' => $reference['crc32'],
                'byteSha256' => $byteSha256,
                'mediaType' => is_string($declaredMediaType) && $declaredMediaType !== ''
                    ? $declaredMediaType
                    : $reference['mediaType'],
                'manifestId' => $reference['manifestId'],
                'manifestMediaType' => $reference['mediaType'],
                'properties' => is_array($link['properties'] ?? null) ? array_values($link['properties']) : [],
                'refines' => is_string($link['refines'] ?? null) ? $link['refines'] : null,
                'hreflang' => is_string($link['hreflang'] ?? null) ? $link['hreflang'] : null,
                'encrypted' => $reference['encrypted'],
                'canExposeBytes' => $reference['canExposeBytes'],
                'diagnostics' => $diagnostics,
            ];
        }

        $metadata['links'] = $links;
        $metadata['linksByRel'] = self::linksByRel($links);
        $metadata['accessibility'] = self::accessibilityMetadataReport($metadata);

        return $metadata;
    }

    /**
     * @param array<string, mixed> $metadata
     *
     * @return array<string, mixed>
     */
    private static function accessibilityMetadataReport(array $metadata): array
    {
        $entries = [];
        $entriesByProperty = [];
        foreach (($metadata['raw'] ?? []) as $rawEntry) {
            if (!is_array($rawEntry) || ($rawEntry['type'] ?? null) !== 'meta') {
                continue;
            }

            $rawProperty = is_string($rawEntry['property'] ?? null) ? $rawEntry['property'] : null;
            $rawName = is_string($rawEntry['name'] ?? null) ? $rawEntry['name'] : null;
            $property = self::canonicalAccessibilityProperty($rawProperty);
            $source = 'property';
            if ($property === null) {
                $property = self::canonicalAccessibilityProperty($rawName);
                $source = 'name';
            }
            if ($property === null) {
                continue;
            }

            $text = self::metadataEntryValue($rawEntry);
            if ($text === '') {
                continue;
            }

            $entry = [
                'property' => $property,
                'source' => $source,
                'rawProperty' => $rawProperty,
                'rawName' => $rawName,
                'text' => $text,
                'content' => is_string($rawEntry['content'] ?? null) ? $rawEntry['content'] : null,
                'id' => is_string($rawEntry['id'] ?? null) ? $rawEntry['id'] : null,
                'refines' => is_string($rawEntry['refines'] ?? null) ? $rawEntry['refines'] : null,
                'scheme' => is_string($rawEntry['scheme'] ?? null) ? $rawEntry['scheme'] : null,
                'language' => is_string($rawEntry['language'] ?? null) ? $rawEntry['language'] : null,
            ];

            $entries[] = $entry;
            $entriesByProperty[$property][] = $entry;
        }

        $accessModeSufficient = [];
        foreach ($entriesByProperty['accessModeSufficient'] ?? [] as $entry) {
            $accessModeSufficient[] = [
                'text' => $entry['text'],
                'modes' => self::accessModeSufficientModes((string) $entry['text']),
                'source' => $entry['source'],
                'id' => $entry['id'],
                'language' => $entry['language'],
            ];
        }

        $linkedRecords = self::accessibilityLinkedRecords(
            is_array($metadata['links'] ?? null) ? $metadata['links'] : []
        );

        return [
            'present' => $entries !== [] || $linkedRecords !== [],
            'entries' => $entries,
            'entriesByProperty' => $entriesByProperty,
            'accessModes' => self::accessibilityValues($entriesByProperty, 'accessMode'),
            'accessModeSufficient' => $accessModeSufficient,
            'accessibilityFeatures' => self::accessibilityValues($entriesByProperty, 'accessibilityFeature'),
            'accessibilityHazards' => self::accessibilityValues($entriesByProperty, 'accessibilityHazard'),
            'accessibilityControls' => self::accessibilityValues($entriesByProperty, 'accessibilityControl'),
            'accessibilityApis' => self::accessibilityValues($entriesByProperty, 'accessibilityAPI'),
            'accessibilitySummary' => self::firstAccessibilityValue($entriesByProperty, 'accessibilitySummary'),
            'certification' => [
                'certifiedBy' => self::firstAccessibilityValue($entriesByProperty, 'certifiedBy'),
                'certifierCredential' => self::firstAccessibilityValue($entriesByProperty, 'certifierCredential'),
                'certifierReport' => self::firstAccessibilityValue($entriesByProperty, 'certifierReport'),
                'conformsTo' => self::accessibilityValues($entriesByProperty, 'conformsTo'),
            ],
            'linkedRecords' => $linkedRecords,
            'diagnostics' => [],
        ];
    }

    private static function canonicalAccessibilityProperty(?string $property): ?string
    {
        if ($property === null) {
            return null;
        }

        $normalized = strtolower(trim($property));
        if ($normalized === '') {
            return null;
        }

        return [
            'accessmode' => 'accessMode',
            'schema:accessmode' => 'accessMode',
            'accessmodesufficient' => 'accessModeSufficient',
            'schema:accessmodesufficient' => 'accessModeSufficient',
            'accessibilityapi' => 'accessibilityAPI',
            'schema:accessibilityapi' => 'accessibilityAPI',
            'accessibilitycontrol' => 'accessibilityControl',
            'schema:accessibilitycontrol' => 'accessibilityControl',
            'accessibilityfeature' => 'accessibilityFeature',
            'schema:accessibilityfeature' => 'accessibilityFeature',
            'accessibilityhazard' => 'accessibilityHazard',
            'schema:accessibilityhazard' => 'accessibilityHazard',
            'accessibilitysummary' => 'accessibilitySummary',
            'schema:accessibilitysummary' => 'accessibilitySummary',
            'certifiedby' => 'certifiedBy',
            'a11y:certifiedby' => 'certifiedBy',
            'certifiercredential' => 'certifierCredential',
            'a11y:certifiercredential' => 'certifierCredential',
            'certifierreport' => 'certifierReport',
            'a11y:certifierreport' => 'certifierReport',
            'conformsto' => 'conformsTo',
            'dcterms:conformsto' => 'conformsTo',
        ][$normalized] ?? null;
    }

    /**
     * @param array<string, mixed> $entry
     */
    private static function metadataEntryValue(array $entry): string
    {
        $text = trim((string) ($entry['text'] ?? ''));
        if ($text !== '') {
            return $text;
        }

        return trim((string) ($entry['content'] ?? ''));
    }

    /**
     * @param array<string, list<array<string, mixed>>> $entriesByProperty
     *
     * @return list<string>
     */
    private static function accessibilityValues(array $entriesByProperty, string $property): array
    {
        $values = [];
        foreach ($entriesByProperty[$property] ?? [] as $entry) {
            $text = trim((string) ($entry['text'] ?? ''));
            if ($text !== '') {
                $values[$text] = $text;
            }
        }

        return array_values($values);
    }

    /**
     * @param array<string, list<array<string, mixed>>> $entriesByProperty
     */
    private static function firstAccessibilityValue(array $entriesByProperty, string $property): ?string
    {
        foreach ($entriesByProperty[$property] ?? [] as $entry) {
            $text = trim((string) ($entry['text'] ?? ''));
            if ($text !== '') {
                return $text;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private static function accessModeSufficientModes(string $value): array
    {
        $tokens = preg_split('/[\s,]+/', trim($value)) ?: [];
        $modes = [];
        foreach ($tokens as $token) {
            if ($token === '') {
                continue;
            }

            $modes[$token] = $token;
        }

        return array_values($modes);
    }

    /**
     * @param list<array<string, mixed>> $links
     *
     * @return list<array<string, mixed>>
     */
    private static function accessibilityLinkedRecords(array $links): array
    {
        $records = [];
        foreach ($links as $link) {
            if (!is_array($link)) {
                continue;
            }

            $rel = is_array($link['rel'] ?? null) ? array_values($link['rel']) : [];
            $properties = is_array($link['properties'] ?? null) ? array_values($link['properties']) : [];
            $lowerRel = array_map(static fn (mixed $value): string => strtolower((string) $value), $rel);
            $lowerProperties = array_map(static fn (mixed $value): string => strtolower((string) $value), $properties);
            $isAccessibilityRecord = in_array('accessibility', $lowerRel, true)
                || in_array('accessibility-summary', $lowerRel, true)
                || in_array('accessibility-metadata', $lowerProperties, true)
                || in_array('a11y', $lowerProperties, true);

            if (!$isAccessibilityRecord) {
                continue;
            }

            $records[] = [
                'id' => is_string($link['id'] ?? null) ? $link['id'] : null,
                'rel' => $rel,
                'href' => is_string($link['href'] ?? null) ? $link['href'] : null,
                'target' => is_string($link['target'] ?? null) ? $link['target'] : null,
                'part' => is_string($link['part'] ?? null) ? $link['part'] : null,
                'external' => (bool) ($link['external'] ?? false),
                'exists' => (bool) ($link['exists'] ?? false),
                'byteLength' => is_int($link['byteLength'] ?? null) ? $link['byteLength'] : null,
                'byteSha256' => is_string($link['byteSha256'] ?? null) ? $link['byteSha256'] : null,
                'mediaType' => is_string($link['mediaType'] ?? null) ? $link['mediaType'] : null,
                'properties' => $properties,
                'diagnostics' => is_array($link['diagnostics'] ?? null) ? array_values($link['diagnostics']) : [],
            ];
        }

        return $records;
    }

    /**
     * @param list<array<string, mixed>> $links
     *
     * @return array<string, list<array<string, mixed>>>
     */
    private static function linksByRel(array $links): array
    {
        $byRel = [];
        foreach ($links as $link) {
            if (!is_array($link['rel'] ?? null)) {
                continue;
            }

            foreach ($link['rel'] as $rel) {
                if (!is_string($rel) || $rel === '') {
                    continue;
                }

                $byRel[$rel][] = $link;
            }
        }

        return $byRel;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function readManifest(
        ZipPackage $package,
        string $opfPart,
        \DOMElement $manifestElement,
        array $refinementsById
    ): array
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

            $properties = self::spaceDelimited($item->getAttribute('properties'));
            $resourceFlags = self::resourcePropertyFlags($properties);
            $resourceReviewFlags = self::resourceReviewFlags($resourceFlags);

            if (self::isExternalReference($href)) {
                $manifest[$id] = [
                    'id' => $id,
                    'href' => $href,
                    'target' => $href,
                    'part' => null,
                    'external' => true,
                    'mediaType' => $mediaType,
                    'properties' => $properties,
                    'resourceFlags' => $resourceFlags,
                    'resourceReviewFlags' => $resourceReviewFlags,
                    'refinements' => self::metadataRefinementsForId($refinementsById, $id),
                    'fallback' => self::nullableAttribute($item, 'fallback'),
                    'mediaOverlay' => self::nullableAttribute($item, 'media-overlay'),
                    'exists' => false,
                    'byteLength' => null,
                    'crc32' => null,
                    'encrypted' => false,
                    'canExposeBytes' => false,
                    'encryption' => null,
                    'diagnostics' => [[
                        'type' => 'external-manifest-resource',
                        'href' => $href,
                        'message' => 'EPUB OPF manifest item points outside the package and was not fetched',
                    ]],
                ];
                continue;
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
                'external' => false,
                'mediaType' => $mediaType,
                'properties' => $properties,
                'resourceFlags' => $resourceFlags,
                'resourceReviewFlags' => $resourceReviewFlags,
                'refinements' => self::metadataRefinementsForId($refinementsById, $id),
                'fallback' => self::nullableAttribute($item, 'fallback'),
                'mediaOverlay' => self::nullableAttribute($item, 'media-overlay'),
                'exists' => $exists,
                'byteLength' => $entry instanceof ZipPackageEntry ? $entry->uncompressedSize : null,
                'crc32' => $entry instanceof ZipPackageEntry ? $entry->crc32Hex() : null,
                'encrypted' => false,
                'canExposeBytes' => true,
                'encryption' => null,
                'diagnostics' => [],
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

            $part = $item['part'];
            if (!is_string($part) || $part === '') {
                continue;
            }

            $encryptionByPart[$part][] = $item;
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
     * @return array{present:bool, items:list<array<string, mixed>>, diagnostics:list<array<string, mixed>>}
     */
    private function readBindings(ZipPackage $package, ?\DOMElement $bindingsElement, array $manifestById): array
    {
        if (!$bindingsElement instanceof \DOMElement) {
            return [
                'present' => false,
                'items' => [],
                'diagnostics' => [],
            ];
        }

        $items = [];
        $diagnostics = [];
        foreach (self::childElements($bindingsElement, 'mediaType', self::OPF_NS) as $index => $mediaTypeElement) {
            $mediaType = trim($mediaTypeElement->getAttribute('media-type'));
            $handlerId = trim($mediaTypeElement->getAttribute('handler'));
            $handler = $handlerId === '' ? null : ($manifestById[$handlerId] ?? null);
            $itemDiagnostics = [];

            if ($mediaType === '') {
                $itemDiagnostics[] = [
                    'type' => 'missing-binding-media-type',
                    'message' => 'EPUB OPF binding mediaType entry is missing media-type',
                ];
            }

            if ($handlerId === '') {
                $itemDiagnostics[] = [
                    'type' => 'missing-binding-handler',
                    'mediaType' => $mediaType === '' ? null : $mediaType,
                    'message' => 'EPUB OPF binding mediaType entry is missing handler',
                ];
            } elseif (!is_array($handler)) {
                $itemDiagnostics[] = [
                    'type' => 'missing-binding-handler-manifest-item',
                    'mediaType' => $mediaType === '' ? null : $mediaType,
                    'handlerId' => $handlerId,
                    'message' => 'EPUB OPF binding handler does not reference a manifest item',
                ];
            } else {
                if (($handler['exists'] ?? false) !== true) {
                    $itemDiagnostics[] = [
                        'type' => 'missing-binding-handler-part',
                        'mediaType' => $mediaType === '' ? null : $mediaType,
                        'handlerId' => $handlerId,
                        'part' => (string) $handler['part'],
                        'message' => 'EPUB OPF binding handler part is missing from the package',
                    ];
                }

                if (self::isEncryptedManifestItem($handler)) {
                    $itemDiagnostics[] = [
                        'type' => 'encrypted-binding-handler',
                        'mediaType' => $mediaType === '' ? null : $mediaType,
                        'handlerId' => $handlerId,
                        'part' => (string) $handler['part'],
                        'message' => 'EPUB OPF binding handler is encrypted and cannot be exposed for review',
                    ];
                }
            }

            foreach ($itemDiagnostics as $diagnostic) {
                $diagnostics[] = ['index' => $index] + $diagnostic;
            }

            $handlerPart = is_array($handler) ? (string) $handler['part'] : null;
            $entry = $handlerPart !== null && $package->has($handlerPart) ? $package->entry($handlerPart) : null;
            $items[] = [
                'index' => $index,
                'mediaType' => $mediaType === '' ? null : $mediaType,
                'handlerId' => $handlerId === '' ? null : $handlerId,
                'handlerHref' => is_array($handler) ? (string) $handler['href'] : null,
                'handlerTarget' => is_array($handler) ? (string) $handler['target'] : null,
                'handlerPart' => $handlerPart,
                'handlerMediaType' => is_array($handler) ? (string) $handler['mediaType'] : null,
                'handlerProperties' => is_array($handler) ? $handler['properties'] : [],
                'handlerExists' => is_array($handler) && ($handler['exists'] ?? false) === true,
                'handlerEncrypted' => is_array($handler) && self::isEncryptedManifestItem($handler),
                'handlerCanExposeBytes' => is_array($handler)
                    && ($handler['exists'] ?? false) === true
                    && (bool) ($handler['canExposeBytes'] ?? true),
                'handlerByteLength' => $entry instanceof ZipPackageEntry ? $entry->uncompressedSize : null,
                'handlerCrc32' => $entry instanceof ZipPackageEntry ? $entry->crc32Hex() : null,
                'diagnostics' => $itemDiagnostics,
            ];
        }

        return [
            'present' => true,
            'items' => $items,
            'diagnostics' => $diagnostics,
        ];
    }

    /**
     * @param array<string, mixed> $metadata
     * @param array<string, array<string, mixed>> $manifestById
     *
     * @return array{
     *     present:bool,
     *     total:?array<string, mixed>,
     *     totals:list<array<string, mixed>>,
     *     overlaysById:array<string, array<string, mixed>>,
     *     items:list<array<string, mixed>>,
     *     diagnostics:list<array<string, mixed>>
     * }
     */
    private static function mediaDurationReport(array $metadata, array $manifestById): array
    {
        $entries = is_array($metadata['metaProperties']['media:duration'] ?? null)
            ? array_values($metadata['metaProperties']['media:duration'])
            : [];
        $overlayReferences = self::mediaOverlayReferences($manifestById);
        $total = null;
        $totals = [];
        $overlaysById = [];
        $items = [];
        $diagnostics = [];

        foreach ($entries as $index => $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $duration = self::metadataEntryValue($entry);
            $seconds = self::smilClockSeconds($duration);
            $refines = is_string($entry['refines'] ?? null) ? $entry['refines'] : null;
            $subjectId = self::metadataRefinementSubject($refines);
            $itemDiagnostics = [];
            $item = [
                'index' => $index,
                'duration' => $duration,
                'durationSeconds' => $seconds,
                'seconds' => $seconds,
                'validClock' => $seconds !== null,
                'scope' => $subjectId === null ? 'publication' : 'media-overlay',
                'refines' => $refines,
                'subjectId' => $subjectId,
                'metadataId' => is_string($entry['id'] ?? null) ? $entry['id'] : null,
                'language' => is_string($entry['language'] ?? null) ? $entry['language'] : null,
                'manifestId' => null,
                'manifestHref' => null,
                'manifestTarget' => null,
                'manifestPart' => null,
                'manifestMediaType' => null,
                'referencedBy' => [],
                'diagnostics' => [],
            ];

            if ($seconds === null) {
                $itemDiagnostics[] = [
                    'type' => 'invalid-media-duration-clock',
                    'duration' => $duration,
                    'message' => 'EPUB media:duration must be a bounded SMIL clock value',
                ];
            }

            if ($subjectId === null) {
                $totals[] = $item;
                if ($total === null) {
                    $total = $item;
                } else {
                    $itemDiagnostics[] = [
                        'type' => 'duplicate-publication-media-duration',
                        'duration' => $duration,
                        'message' => 'EPUB package contains more than one publication-level media:duration entry',
                    ];
                }
            } else {
                $manifestItem = $manifestById[$subjectId] ?? null;
                $item['manifestId'] = $subjectId;
                if (!is_array($manifestItem)) {
                    $itemDiagnostics[] = [
                        'type' => 'media-duration-refines-missing-manifest-item',
                        'subjectId' => $subjectId,
                        'message' => 'EPUB media:duration refinement does not reference an OPF manifest item',
                    ];
                } else {
                    $item['manifestHref'] = (string) ($manifestItem['href'] ?? '');
                    $item['manifestTarget'] = is_string($manifestItem['target'] ?? null) ? $manifestItem['target'] : null;
                    $item['manifestPart'] = is_string($manifestItem['part'] ?? null) ? $manifestItem['part'] : null;
                    $item['manifestMediaType'] = (string) ($manifestItem['mediaType'] ?? '');
                    $item['referencedBy'] = $overlayReferences[$subjectId] ?? [];

                    if (($manifestItem['mediaType'] ?? null) !== self::SMIL_MEDIA_TYPE) {
                        $itemDiagnostics[] = [
                            'type' => 'media-duration-refines-non-overlay-manifest-item',
                            'subjectId' => $subjectId,
                            'mediaType' => (string) ($manifestItem['mediaType'] ?? ''),
                            'message' => 'EPUB media:duration refinement should reference an OPF media-overlay SMIL item',
                        ];
                    } elseif (!isset($overlaysById[$subjectId])) {
                        $overlaysById[$subjectId] = $item;
                    } else {
                        $itemDiagnostics[] = [
                            'type' => 'duplicate-media-overlay-duration',
                            'subjectId' => $subjectId,
                            'message' => 'EPUB media-overlay manifest item has more than one media:duration entry',
                        ];
                    }
                }
            }

            foreach ($itemDiagnostics as $diagnostic) {
                $diagnostics[] = ['index' => $index] + $diagnostic;
            }
            $item['diagnostics'] = $itemDiagnostics;
            if ($subjectId !== null && isset($overlaysById[$subjectId]) && (int) $overlaysById[$subjectId]['index'] === $index) {
                $overlaysById[$subjectId] = $item;
            }
            if ($subjectId === null) {
                $totals[count($totals) - 1] = $item;
                if ($total !== null && (int) $total['index'] === $index) {
                    $total = $item;
                }
            }
            $items[] = $item;
        }

        return [
            'present' => $entries !== [],
            'total' => $total,
            'totals' => $totals,
            'overlaysById' => $overlaysById,
            'items' => $items,
            'diagnostics' => $diagnostics,
        ];
    }

    /**
     * @param array<string, array<string, mixed>> $manifestById
     *
     * @return array<string, list<string>>
     */
    private static function mediaOverlayReferences(array $manifestById): array
    {
        $references = [];
        foreach ($manifestById as $item) {
            $mediaOverlay = $item['mediaOverlay'] ?? null;
            if (!is_string($mediaOverlay) || $mediaOverlay === '') {
                continue;
            }

            $references[$mediaOverlay][] = (string) ($item['id'] ?? '');
        }

        return $references;
    }

    private static function smilClockSeconds(string $value): ?float
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        if (preg_match('/^([0-9]+):([0-5][0-9]):([0-5][0-9](?:\.[0-9]+)?)$/', $value, $matches) === 1) {
            return ((int) $matches[1] * 3600)
                + ((int) $matches[2] * 60)
                + (float) $matches[3];
        }

        if (preg_match('/^([0-9]+):([0-5][0-9](?:\.[0-9]+)?)$/', $value, $matches) === 1) {
            return ((int) $matches[1] * 60) + (float) $matches[2];
        }

        if (preg_match('/^([0-9]+(?:\.[0-9]+)?)(h|min|s|ms)$/i', $value, $matches) === 1) {
            $amount = (float) $matches[1];

            return match (strtolower($matches[2])) {
                'h' => $amount * 3600,
                'min' => $amount * 60,
                'ms' => $amount / 1000,
                default => $amount,
            };
        }

        return null;
    }

    /**
     * @param array<string, mixed> $bindings
     *
     * @return ?array<string, mixed>
     */
    private static function bindingForMediaType(array $bindings, string $mediaType): ?array
    {
        foreach ($bindings['items'] ?? [] as $binding) {
            if (!is_array($binding)) {
                continue;
            }

            if (($binding['mediaType'] ?? null) === $mediaType) {
                return $binding;
            }
        }

        return null;
    }

    /**
     * @param array<string, array<string, mixed>> $manifestById
     * @param array<string, mixed> $bindings
     *
     * @return list<array<string, mixed>>
     */
    private function readSpine(
        \DOMElement $spineElement,
        array $manifestById,
        array $bindings,
        array $refinementsById
    ): array
    {
        $spine = [];
        foreach (self::childElements($spineElement, 'itemref', self::OPF_NS) as $index => $itemref) {
            $itemrefId = self::nullableAttribute($itemref, 'id');
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
            $binding = self::bindingForMediaType($bindings, (string) $manifestItem['mediaType']);
            $properties = self::spaceDelimited($itemref->getAttribute('properties'));
            $itemProperties = self::spineItemPropertyReport($properties);
            $linearProperties = self::spineItemLinearReport($itemref, $idref);
            $itemProperties['linear'] = $linearProperties;
            $itemDiagnostics = array_merge($itemProperties['diagnostics'], $linearProperties['diagnostics']);
            $itemProperties['diagnostics'] = $itemDiagnostics;
            $spine[] = [
                'index' => $index,
                'id' => $itemrefId,
                'idref' => $idref,
                'target' => $manifestItem['target'],
                'part' => $manifestItem['part'],
                'href' => $manifestItem['href'],
                'mediaType' => $manifestItem['mediaType'],
                'linear' => $linearProperties['linear'],
                'linearRaw' => $linearProperties['raw'],
                'linearSpecified' => $linearProperties['specified'],
                'linearValid' => $linearProperties['valid'],
                'properties' => $properties,
                'refinements' => self::metadataRefinementsForId($refinementsById, $itemrefId),
                'spineItemProperties' => $itemProperties,
                'spineItemDiagnostics' => $itemDiagnostics,
                'pageSpread' => $itemProperties['pageSpread']['placement'],
                'pageSpreadProperties' => $itemProperties['pageSpread']['properties'],
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
                'binding' => $binding,
            ];
        }

        return $spine;
    }

    /**
     * @return array{
     *     toc:?string,
     *     pageProgressionDirection:string,
     *     pageProgressionDirectionRaw:?string,
     *     pageProgressionDirectionSpecified:bool,
     *     pageProgressionDirectionValid:bool,
     *     rightToLeft:bool,
     *     itemDiagnostics:list<array<string, mixed>>,
     *     diagnostics:list<array<string, mixed>>
     * }
     */
    private static function readSpineProperties(\DOMElement $spineElement, array $refinementsById): array
    {
        $spineId = self::nullableAttribute($spineElement, 'id');
        $rawDirection = trim($spineElement->getAttribute('page-progression-direction'));
        $specified = $rawDirection !== '';
        $normalized = strtolower($rawDirection);
        $direction = 'default';
        $valid = true;
        $diagnostics = [];

        if ($specified) {
            if (in_array($normalized, ['ltr', 'rtl', 'default'], true)) {
                $direction = $normalized;
            } else {
                $valid = false;
                $diagnostics[] = [
                    'type' => 'invalid-spine-page-progression-direction',
                    'value' => $rawDirection,
                    'message' => 'EPUB spine page-progression-direction must be ltr, rtl, or default',
                ];
            }
        }

        return [
            'id' => $spineId,
            'toc' => self::nullableAttribute($spineElement, 'toc'),
            'refinements' => self::metadataRefinementsForId($refinementsById, $spineId),
            'pageProgressionDirection' => $direction,
            'pageProgressionDirectionRaw' => $specified ? $rawDirection : null,
            'pageProgressionDirectionSpecified' => $specified,
            'pageProgressionDirectionValid' => $valid,
            'rightToLeft' => $direction === 'rtl',
            'itemDiagnostics' => [],
            'diagnostics' => $diagnostics,
        ];
    }

    /**
     * @param list<array<string, mixed>> $spine
     *
     * @return array<string, mixed>
     */
    private static function spinePropertiesWithItemDiagnostics(array $spineProperties, array $spine): array
    {
        $itemDiagnostics = [];
        $idrefs = [];
        $linearItemCount = 0;
        foreach ($spine as $item) {
            $idref = (string) ($item['idref'] ?? '');
            if ($idref !== '') {
                $idrefs[] = $idref;
            }
            if (($item['linear'] ?? true) === true) {
                ++$linearItemCount;
            }

            foreach (($item['spineItemDiagnostics'] ?? []) as $diagnostic) {
                if (!is_array($diagnostic)) {
                    continue;
                }

                $itemDiagnostics[] = [
                    'index' => (int) ($item['index'] ?? 0),
                    'idref' => $idref,
                ] + $diagnostic;
            }
        }

        $itemCount = count($spine);
        $nonLinearItemCount = $itemCount - $linearItemCount;
        $emptyPrimaryReadingOrder = $itemCount > 0 && $linearItemCount === 0;
        $diagnostics = is_array($spineProperties['diagnostics'] ?? null) ? $spineProperties['diagnostics'] : [];
        if ($emptyPrimaryReadingOrder) {
            $diagnostics[] = [
                'type' => 'spine-has-no-linear-items',
                'itemCount' => $itemCount,
                'idrefs' => $idrefs,
                'message' => 'EPUB spine does not contain any primary reading-order itemrefs; all itemrefs are marked non-linear',
            ];
        }

        $spineProperties['itemCount'] = $itemCount;
        $spineProperties['linearItemCount'] = $linearItemCount;
        $spineProperties['nonLinearItemCount'] = $nonLinearItemCount;
        $spineProperties['hasLinearItems'] = $linearItemCount > 0;
        $spineProperties['primaryReadingOrderEmpty'] = $emptyPrimaryReadingOrder;
        $spineProperties['itemDiagnostics'] = $itemDiagnostics;
        $spineProperties['diagnostics'] = array_merge(
            $diagnostics,
            $itemDiagnostics
        );

        return $spineProperties;
    }

    /**
     * @param list<string> $properties
     *
     * @return array{pageSpread:array<string, mixed>, diagnostics:list<array<string, mixed>>}
     */
    private static function spineItemPropertyReport(array $properties): array
    {
        $matches = [];
        $placements = [];
        foreach ($properties as $property) {
            $placement = match ($property) {
                'page-spread-left', 'rendition:page-spread-left' => 'left',
                'page-spread-right', 'rendition:page-spread-right' => 'right',
                'spread-none', 'rendition:page-spread-center' => 'center',
                default => null,
            };

            if ($placement === null) {
                continue;
            }

            $matches[] = [
                'property' => $property,
                'placement' => $placement,
            ];
            $placements[$placement] = true;
        }

        $spreadProperties = array_map(
            static fn (array $match): string => (string) $match['property'],
            $matches
        );
        $spreadPlacements = array_keys($placements);
        $conflicting = count($spreadPlacements) > 1;
        $diagnostics = [];

        if ($conflicting) {
            $diagnostics[] = [
                'type' => 'conflicting-spine-page-spread-properties',
                'properties' => $spreadProperties,
                'placements' => $spreadPlacements,
                'message' => 'EPUB spine itemref declares more than one page-spread placement',
            ];
        }

        return [
            'pageSpread' => [
                'placement' => $matches[0]['placement'] ?? null,
                'properties' => $spreadProperties,
                'matches' => $matches,
                'conflicting' => $conflicting,
            ],
            'diagnostics' => $diagnostics,
        ];
    }

    /**
     * @return array{raw:?string, specified:bool, linear:bool, valid:bool, idref:string, diagnostics:list<array<string, mixed>>}
     */
    private static function spineItemLinearReport(\DOMElement $itemref, string $idref): array
    {
        $raw = trim($itemref->getAttribute('linear'));
        $specified = $raw !== '';
        $normalized = strtolower($raw);
        $linear = true;
        $valid = true;
        $diagnostics = [];

        if ($specified && $normalized === 'no') {
            $linear = false;
        } elseif ($specified && $normalized !== 'yes') {
            $valid = false;
            $diagnostics[] = [
                'type' => 'invalid-spine-linear-value',
                'idref' => $idref,
                'value' => $raw,
                'message' => 'EPUB spine itemref linear must be yes, no, or omitted; invalid values are treated as yes',
            ];
        }

        return [
            'raw' => $specified ? $raw : null,
            'specified' => $specified,
            'linear' => $linear,
            'valid' => $valid,
            'idref' => $idref,
            'diagnostics' => $diagnostics,
        ];
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
            'metadata' => $metadataElement instanceof \DOMElement ? $this->readMetadata($metadataElement, '', false) : [],
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
     * @param list<array<string, mixed>> $manifest
     *
     * @return array{
     *     summary:array<string, int>,
     *     items:list<array<string, mixed>>,
     *     itemsById:array<string, array<string, mixed>>,
     *     itemsByProperty:array<string, list<array<string, mixed>>>,
     *     reviewItems:list<array<string, mixed>>
     * }
     */
    private static function resourcePropertyReport(array $manifest): array
    {
        $items = [];
        $itemsById = [];
        $itemsByProperty = [
            'nav' => [],
            'cover-image' => [],
            'mathml' => [],
            'svg' => [],
            'remote-resources' => [],
            'scripted' => [],
            'switch' => [],
        ];
        $reviewItems = [];

        foreach ($manifest as $item) {
            $properties = array_values(array_filter(
                is_array($item['properties'] ?? null) ? $item['properties'] : [],
                static fn (mixed $property): bool => is_string($property) && $property !== '',
            ));
            $recognized = array_values(array_filter(
                $properties,
                static fn (string $property): bool => array_key_exists($property, $itemsByProperty),
            ));
            if ($recognized === []) {
                continue;
            }

            $flags = is_array($item['resourceFlags'] ?? null)
                ? $item['resourceFlags']
                : self::resourcePropertyFlags($properties);
            $reviewFlags = is_array($item['resourceReviewFlags'] ?? null)
                ? array_values($item['resourceReviewFlags'])
                : self::resourceReviewFlags($flags);
            $reportItem = [
                'id' => (string) ($item['id'] ?? ''),
                'href' => (string) ($item['href'] ?? ''),
                'target' => is_string($item['target'] ?? null) ? $item['target'] : null,
                'part' => is_string($item['part'] ?? null) ? $item['part'] : null,
                'external' => (bool) ($item['external'] ?? false),
                'mediaType' => (string) ($item['mediaType'] ?? ''),
                'exists' => (bool) ($item['exists'] ?? false),
                'properties' => $recognized,
                'flags' => $flags,
                'reviewFlags' => $reviewFlags,
                'reviewRequired' => $reviewFlags !== [],
            ];

            $items[] = $reportItem;
            if ($reportItem['id'] !== '') {
                $itemsById[$reportItem['id']] = $reportItem;
            }
            foreach ($recognized as $property) {
                $itemsByProperty[$property][] = $reportItem;
            }
            if ($reportItem['reviewRequired']) {
                $reviewItems[] = $reportItem;
            }
        }

        return [
            'summary' => [
                'navCount' => count($itemsByProperty['nav']),
                'coverImageCount' => count($itemsByProperty['cover-image']),
                'mathmlCount' => count($itemsByProperty['mathml']),
                'svgCount' => count($itemsByProperty['svg']),
                'remoteResourcesCount' => count($itemsByProperty['remote-resources']),
                'scriptedCount' => count($itemsByProperty['scripted']),
                'switchCount' => count($itemsByProperty['switch']),
                'reviewRequiredCount' => count($reviewItems),
            ],
            'items' => $items,
            'itemsById' => $itemsById,
            'itemsByProperty' => $itemsByProperty,
            'reviewItems' => $reviewItems,
        ];
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
     * @param ?array<string, mixed> $nav
     * @param list<array<string, mixed>> $spine
     *
     * @return array{
     *     present:bool,
     *     source:string,
     *     count:int,
     *     items:list<array<string, mixed>>,
     *     itemsByPart:array<string, list<array<string, mixed>>>,
     *     diagnostics:list<array<string, mixed>>
     * }
     */
    private static function pageBreakReport(?array $nav, array $spine): array
    {
        $pageList = is_array($nav) && is_array($nav['pageList'] ?? null)
            ? $nav['pageList']
            : [];
        if ($pageList === []) {
            return [
                'present' => false,
                'source' => 'nav-page-list',
                'count' => 0,
                'items' => [],
                'itemsByPart' => [],
                'diagnostics' => [],
            ];
        }

        $spineByContentPart = [];
        foreach ($spine as $spineItem) {
            $contentPart = is_string($spineItem['contentPart'] ?? null)
                ? $spineItem['contentPart']
                : (is_string($spineItem['part'] ?? null) ? $spineItem['part'] : null);
            if ($contentPart === null || $contentPart === '' || isset($spineByContentPart[$contentPart])) {
                continue;
            }

            $spineByContentPart[$contentPart] = $spineItem;
        }

        $items = [];
        $diagnostics = [];
        foreach (self::flattenNavigationItems($pageList) as $pageItem) {
            $navItem = $pageItem['item'];
            $index = count($items);
            $target = is_string($navItem['target'] ?? null) ? $navItem['target'] : null;
            $part = is_string($navItem['part'] ?? null) ? $navItem['part'] : null;
            $spineItem = $part !== null ? ($spineByContentPart[$part] ?? null) : null;
            $itemDiagnostics = [];

            if (($navItem['external'] ?? false) === true) {
                $itemDiagnostics[] = [
                    'type' => 'external-page-list-reference',
                    'target' => $target,
                    'message' => 'EPUB page-list entry points outside the package and was not fetched',
                ];
            } elseif ($target === null) {
                $itemDiagnostics[] = [
                    'type' => 'missing-page-list-target',
                    'message' => 'EPUB page-list entry does not carry a resolvable target',
                ];
            } elseif (($navItem['exists'] ?? false) !== true) {
                $itemDiagnostics[] = [
                    'type' => 'missing-page-list-reference',
                    'part' => $part,
                    'message' => 'EPUB page-list target is missing from the package',
                ];
            } elseif (!is_array($spineItem)) {
                $itemDiagnostics[] = [
                    'type' => 'page-list-target-outside-spine',
                    'part' => $part,
                    'message' => 'EPUB page-list target exists in the package but is not part of the resolved spine handoff',
                ];
            }

            foreach ($itemDiagnostics as $diagnostic) {
                $diagnostics[] = ['index' => $index] + $diagnostic;
            }

            $items[] = [
                'index' => $index,
                'depth' => $pageItem['depth'],
                'label' => is_string($navItem['title'] ?? null) ? $navItem['title'] : '',
                'href' => is_string($navItem['href'] ?? null) ? $navItem['href'] : null,
                'target' => $target,
                'part' => $part,
                'fragment' => self::targetFragment($target),
                'external' => (bool) ($navItem['external'] ?? false),
                'exists' => (bool) ($navItem['exists'] ?? false),
                'type' => is_string($navItem['type'] ?? null) ? $navItem['type'] : null,
                'types' => is_array($navItem['types'] ?? null) ? array_values($navItem['types']) : [],
                'spineIndex' => is_array($spineItem) ? (int) ($spineItem['index'] ?? 0) : null,
                'spineIdref' => is_array($spineItem) ? (string) ($spineItem['idref'] ?? '') : null,
                'spinePart' => is_array($spineItem) ? (string) ($spineItem['part'] ?? '') : null,
                'contentPart' => is_array($spineItem) ? (string) ($spineItem['contentPart'] ?? $spineItem['part'] ?? '') : null,
                'linear' => is_array($spineItem) ? (bool) ($spineItem['linear'] ?? true) : null,
                'pageSpread' => is_array($spineItem) ? ($spineItem['pageSpread'] ?? null) : null,
                'navDiagnostics' => is_array($navItem['diagnostics'] ?? null) ? array_values($navItem['diagnostics']) : [],
                'diagnostics' => $itemDiagnostics,
            ];
        }

        $itemsByPart = [];
        foreach ($items as $item) {
            if (!is_string($item['part'] ?? null) || $item['part'] === '') {
                continue;
            }

            $itemsByPart[$item['part']][] = $item;
        }

        return [
            'present' => $items !== [],
            'source' => 'nav-page-list',
            'count' => count($items),
            'items' => $items,
            'itemsByPart' => $itemsByPart,
            'diagnostics' => $diagnostics,
        ];
    }

    /**
     * @param list<array<string, mixed>> $items
     *
     * @return list<array{item:array<string, mixed>, depth:int}>
     */
    private static function flattenNavigationItems(array $items, int $depth = 0): array
    {
        $flat = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $flat[] = [
                'item' => $item,
                'depth' => $depth,
            ];

            if (is_array($item['children'] ?? null) && $item['children'] !== []) {
                array_push($flat, ...self::flattenNavigationItems($item['children'], $depth + 1));
            }
        }

        return $flat;
    }

    private static function targetFragment(?string $target): ?string
    {
        if ($target === null) {
            return null;
        }

        $offset = strpos($target, '#');
        if ($offset === false) {
            return null;
        }

        $fragment = substr($target, $offset + 1);

        return $fragment === '' ? null : $fragment;
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
     * @param ?array<string, mixed> $nav
     * @param ?array<string, mixed> $ncx
     * @param list<array<string, mixed>> $spine
     *
     * @return array<string, mixed>
     */
    private static function navigationReport(?array $nav, ?array $ncx, array $spine): array
    {
        $spineByContentPart = [];
        $spineCoverage = [];
        foreach ($spine as $spineItem) {
            $contentPart = is_string($spineItem['contentPart'] ?? null)
                ? $spineItem['contentPart']
                : (is_string($spineItem['part'] ?? null) ? $spineItem['part'] : null);
            $spineIndex = (int) ($spineItem['index'] ?? count($spineCoverage));

            if ($contentPart !== null && $contentPart !== '' && !isset($spineByContentPart[$contentPart])) {
                $spineByContentPart[$contentPart] = $spineItem;
            }

            $spineCoverage[$spineIndex] = [
                'index' => $spineIndex,
                'id' => is_string($spineItem['id'] ?? null) ? $spineItem['id'] : null,
                'idref' => (string) ($spineItem['idref'] ?? ''),
                'part' => is_string($spineItem['part'] ?? null) ? $spineItem['part'] : null,
                'contentId' => is_string($spineItem['contentId'] ?? null) ? $spineItem['contentId'] : null,
                'contentPart' => $contentPart,
                'mediaType' => is_string($spineItem['mediaType'] ?? null) ? $spineItem['mediaType'] : null,
                'contentMediaType' => is_string($spineItem['contentMediaType'] ?? null) ? $spineItem['contentMediaType'] : null,
                'linear' => (bool) ($spineItem['linear'] ?? true),
                'pageSpread' => $spineItem['pageSpread'] ?? null,
                'targetCount' => 0,
                'navTocCount' => 0,
                'ncxCount' => 0,
                'presentInNavigation' => false,
                'targets' => [],
                'navTocTargets' => [],
                'ncxTargets' => [],
                'diagnostics' => [],
            ];
        }
        ksort($spineCoverage);

        $items = [];
        $diagnostics = [];
        $targetsBySpineIndex = [];
        $navTocCount = 0;
        $ncxCount = 0;
        $mappedCount = 0;
        $externalCount = 0;
        $missingCount = 0;
        $outsideSpineCount = 0;

        $navItems = is_array($nav) && is_array($nav['items'] ?? null) ? $nav['items'] : [];
        foreach (self::flattenNavigationItems($navItems) as $sourceIndex => $flat) {
            $item = self::navigationTargetItem(
                $flat['item'],
                'nav',
                $sourceIndex,
                (int) $flat['depth'],
                count($items),
                $spineByContentPart
            );
            ++$navTocCount;
            $items[] = $item;
            self::accumulateNavigationTarget(
                $item,
                $diagnostics,
                $targetsBySpineIndex,
                $mappedCount,
                $externalCount,
                $missingCount,
                $outsideSpineCount
            );
        }

        $ncxItems = is_array($ncx) && is_array($ncx['items'] ?? null) ? $ncx['items'] : [];
        foreach (self::flattenNavigationItems($ncxItems) as $sourceIndex => $flat) {
            $item = self::navigationTargetItem(
                $flat['item'],
                'ncx',
                $sourceIndex,
                (int) $flat['depth'],
                count($items),
                $spineByContentPart
            );
            ++$ncxCount;
            $items[] = $item;
            self::accumulateNavigationTarget(
                $item,
                $diagnostics,
                $targetsBySpineIndex,
                $mappedCount,
                $externalCount,
                $missingCount,
                $outsideSpineCount
            );
        }

        $uncoveredLinearSpineItems = [];
        $spineDiagnostics = [];
        foreach ($spineCoverage as $index => $coverage) {
            $targets = $targetsBySpineIndex[$index] ?? [];
            $navTargets = array_values(array_filter(
                $targets,
                static fn (array $target): bool => ($target['source'] ?? null) === 'nav',
            ));
            $ncxTargets = array_values(array_filter(
                $targets,
                static fn (array $target): bool => ($target['source'] ?? null) === 'ncx',
            ));

            $coverage['targets'] = $targets;
            $coverage['navTocTargets'] = $navTargets;
            $coverage['ncxTargets'] = $ncxTargets;
            $coverage['targetCount'] = count($targets);
            $coverage['navTocCount'] = count($navTargets);
            $coverage['ncxCount'] = count($ncxTargets);
            $coverage['presentInNavigation'] = $targets !== [];

            if (($coverage['linear'] ?? true) === true && $targets === []) {
                $diagnostic = [
                    'type' => 'linear-spine-item-missing-navigation',
                    'index' => $coverage['index'],
                    'idref' => $coverage['idref'],
                    'contentPart' => $coverage['contentPart'],
                    'message' => 'EPUB linear spine item is not targeted by the nav TOC or NCX navigation map',
                ];
                $coverage['diagnostics'][] = $diagnostic;
                $spineDiagnostics[] = $diagnostic;
                $uncoveredLinearSpineItems[] = [
                    'index' => $coverage['index'],
                    'idref' => $coverage['idref'],
                    'contentPart' => $coverage['contentPart'],
                    'linear' => true,
                    'diagnostics' => [$diagnostic],
                ];
            }

            $spineCoverage[$index] = $coverage;
        }

        return [
            'present' => $navTocCount > 0 || $ncxCount > 0,
            'source' => 'nav-ncx',
            'navTocCount' => $navTocCount,
            'ncxCount' => $ncxCount,
            'targetCount' => count($items),
            'mappedSpineTargetCount' => $mappedCount,
            'outsideSpineTargetCount' => $outsideSpineCount,
            'missingTargetCount' => $missingCount,
            'externalTargetCount' => $externalCount,
            'uncoveredLinearSpineItemCount' => count($uncoveredLinearSpineItems),
            'items' => $items,
            'spineCoverage' => array_values($spineCoverage),
            'uncoveredLinearSpineItems' => $uncoveredLinearSpineItems,
            'diagnostics' => $diagnostics,
            'spineDiagnostics' => $spineDiagnostics,
        ];
    }

    /**
     * @param array<string, mixed> $item
     * @param array<string, array<string, mixed>> $spineByContentPart
     *
     * @return array<string, mixed>
     */
    private static function navigationTargetItem(
        array $item,
        string $source,
        int $sourceIndex,
        int $depth,
        int $index,
        array $spineByContentPart
    ): array {
        $target = is_string($item['target'] ?? null) ? $item['target'] : null;
        $part = is_string($item['part'] ?? null) ? $item['part'] : null;
        $spineItem = $part !== null ? ($spineByContentPart[$part] ?? null) : null;
        $sourceDiagnostics = is_array($item['diagnostics'] ?? null) ? array_values($item['diagnostics']) : [];
        $diagnostics = [];

        if (($item['external'] ?? false) === true) {
            $diagnostics[] = [
                'type' => 'external-navigation-target',
                'source' => $source,
                'target' => $target,
                'message' => 'EPUB navigation target points outside the package and was not fetched',
            ];
        } elseif ($target === null) {
            $diagnostics[] = [
                'type' => 'missing-navigation-target',
                'source' => $source,
                'message' => 'EPUB navigation item does not carry a resolvable target',
            ];
        } elseif (($item['exists'] ?? true) !== true) {
            $diagnostics[] = [
                'type' => 'missing-navigation-target',
                'source' => $source,
                'part' => $part,
                'message' => 'EPUB navigation target is missing from the package',
            ];
        } elseif (!is_array($spineItem)) {
            $diagnostics[] = [
                'type' => 'navigation-target-outside-spine',
                'source' => $source,
                'part' => $part,
                'message' => 'EPUB navigation target exists in the package but is not part of the resolved spine handoff',
            ];
        }

        return [
            'index' => $index,
            'source' => $source,
            'sourceIndex' => $sourceIndex,
            'depth' => $depth,
            'id' => is_string($item['id'] ?? null) ? $item['id'] : null,
            'playOrder' => is_string($item['playOrder'] ?? null) ? $item['playOrder'] : null,
            'label' => is_string($item['title'] ?? null) ? $item['title'] : '',
            'href' => is_string($item['href'] ?? null) ? $item['href'] : null,
            'target' => $target,
            'part' => $part,
            'fragment' => self::targetFragment($target),
            'external' => (bool) ($item['external'] ?? false),
            'exists' => (bool) ($item['exists'] ?? false),
            'type' => is_string($item['type'] ?? null) ? $item['type'] : null,
            'types' => is_array($item['types'] ?? null) ? array_values($item['types']) : [],
            'spineIndex' => is_array($spineItem) ? (int) ($spineItem['index'] ?? 0) : null,
            'spineIdref' => is_array($spineItem) ? (string) ($spineItem['idref'] ?? '') : null,
            'spineItemId' => is_array($spineItem) && is_string($spineItem['id'] ?? null) ? $spineItem['id'] : null,
            'spinePart' => is_array($spineItem) ? (string) ($spineItem['part'] ?? '') : null,
            'contentPart' => is_array($spineItem) ? (string) ($spineItem['contentPart'] ?? $spineItem['part'] ?? '') : null,
            'linear' => is_array($spineItem) ? (bool) ($spineItem['linear'] ?? true) : null,
            'pageSpread' => is_array($spineItem) ? ($spineItem['pageSpread'] ?? null) : null,
            'sourceDiagnostics' => $sourceDiagnostics,
            'diagnostics' => $diagnostics,
        ];
    }

    /**
     * @param array<string, mixed> $item
     * @param list<array<string, mixed>> $diagnostics
     * @param array<int, list<array<string, mixed>>> $targetsBySpineIndex
     */
    private static function accumulateNavigationTarget(
        array $item,
        array &$diagnostics,
        array &$targetsBySpineIndex,
        int &$mappedCount,
        int &$externalCount,
        int &$missingCount,
        int &$outsideSpineCount
    ): void {
        foreach ($item['diagnostics'] as $diagnostic) {
            if (!is_array($diagnostic)) {
                continue;
            }

            $diagnostics[] = [
                'index' => $item['index'],
                'source' => $item['source'],
                'sourceIndex' => $item['sourceIndex'],
            ] + $diagnostic;
        }

        if (($item['external'] ?? false) === true) {
            ++$externalCount;
        }
        if (($item['exists'] ?? true) !== true && ($item['external'] ?? false) !== true) {
            ++$missingCount;
        }

        $hasOutsideSpineDiagnostic = false;
        foreach ($item['diagnostics'] as $diagnostic) {
            if (($diagnostic['type'] ?? null) === 'navigation-target-outside-spine') {
                $hasOutsideSpineDiagnostic = true;
                break;
            }
        }
        if ($hasOutsideSpineDiagnostic) {
            ++$outsideSpineCount;
        }

        if (is_int($item['spineIndex'] ?? null)) {
            ++$mappedCount;
            $targetsBySpineIndex[$item['spineIndex']][] = $item;
        }
    }

    /**
     * @param array<string, array<string, mixed>> $manifestById
     *
     * @return array<string, array<string, mixed>>
     */
    private function readMediaOverlays(ZipPackage $package, array $manifestById, array $mediaDurations): array
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
                    'duration' => null,
                    'durationSeconds' => null,
                    'durationMetadata' => null,
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

            $durationMetadata = is_array($mediaDurations['overlaysById'][$id] ?? null)
                ? $mediaDurations['overlaysById'][$id]
                : null;
            $overlays[$id] = $this->readMediaOverlayItem($package, $item, $referencedBy, $durationMetadata);
        }

        return $overlays;
    }

    /**
     * @param array<string, mixed> $item
     * @param list<string> $referencedBy
     *
     * @return array<string, mixed>
     */
    private function readMediaOverlayItem(ZipPackage $package, array $item, array $referencedBy, ?array $durationMetadata): array
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
            'duration' => is_array($durationMetadata) ? $durationMetadata['duration'] : null,
            'durationSeconds' => is_array($durationMetadata) ? $durationMetadata['durationSeconds'] : null,
            'durationMetadata' => $durationMetadata,
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
        $clipBegin = $audio instanceof \DOMElement ? self::nullableAttribute($audio, 'clipBegin') : null;
        $clipEnd = $audio instanceof \DOMElement ? self::nullableAttribute($audio, 'clipEnd') : null;
        $clipTiming = self::smilClipTiming($clipBegin, $clipEnd);

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
            'clipBegin' => $clipBegin,
            'clipBeginSeconds' => $clipTiming['clipBeginSeconds'],
            'clipEnd' => $clipEnd,
            'clipEndSeconds' => $clipTiming['clipEndSeconds'],
            'clipDurationSeconds' => $clipTiming['clipDurationSeconds'],
            'clipValid' => $clipTiming['valid'],
            'clipDiagnostics' => $clipTiming['diagnostics'],
            'diagnostics' => array_merge($textReference['diagnostics'], $audioReference['diagnostics'], $clipTiming['diagnostics']),
        ];
    }

    /**
     * @return array{clipBeginSeconds:?float, clipEndSeconds:?float, clipDurationSeconds:?float, valid:bool, diagnostics:list<array<string, mixed>>}
     */
    private static function smilClipTiming(?string $clipBegin, ?string $clipEnd): array
    {
        $beginSeconds = is_string($clipBegin) ? self::smilClockSeconds($clipBegin) : null;
        $endSeconds = is_string($clipEnd) ? self::smilClockSeconds($clipEnd) : null;
        $diagnostics = [];

        if (is_string($clipBegin) && trim($clipBegin) !== '' && $beginSeconds === null) {
            $diagnostics[] = [
                'type' => 'invalid-media-overlay-clip-begin',
                'clipBegin' => $clipBegin,
                'message' => 'EPUB media-overlay clipBegin must be a bounded SMIL clock value',
            ];
        }

        if (is_string($clipEnd) && trim($clipEnd) !== '' && $endSeconds === null) {
            $diagnostics[] = [
                'type' => 'invalid-media-overlay-clip-end',
                'clipEnd' => $clipEnd,
                'message' => 'EPUB media-overlay clipEnd must be a bounded SMIL clock value',
            ];
        }

        $durationSeconds = null;
        if ($beginSeconds !== null && $endSeconds !== null) {
            if ($endSeconds < $beginSeconds) {
                $diagnostics[] = [
                    'type' => 'media-overlay-clip-end-before-begin',
                    'clipBegin' => $clipBegin,
                    'clipEnd' => $clipEnd,
                    'clipBeginSeconds' => $beginSeconds,
                    'clipEndSeconds' => $endSeconds,
                    'message' => 'EPUB media-overlay clipEnd must not be earlier than clipBegin',
                ];
            } else {
                $durationSeconds = $endSeconds - $beginSeconds;
            }
        }

        return [
            'clipBeginSeconds' => $beginSeconds,
            'clipEndSeconds' => $endSeconds,
            'clipDurationSeconds' => $durationSeconds,
            'valid' => $diagnostics === [],
            'diagnostics' => $diagnostics,
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
     * @param array<string, array<string, mixed>> $manifestByPart
     *
     * @return list<array<string, mixed>>
     */
    private function xhtmlAssets(ZipPackage $package, array $manifest, array $manifestByPart): array
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

            $part = (string) $item['part'];
            $html = $package->read($part);
            $contentReport = $this->xhtmlContentResourceReport($package, $part, $html, $manifestByPart);
            $assets[] = [
                'id' => $item['id'],
                'href' => $item['href'],
                'target' => $item['target'],
                'part' => $part,
                'properties' => $item['properties'],
                'resourceFlags' => $item['resourceFlags'] ?? self::resourcePropertyFlags($item['properties'] ?? []),
                'resourceReviewFlags' => $item['resourceReviewFlags'] ?? [],
                'mediaOverlay' => $item['mediaOverlay'],
                'html' => $html,
                'contentResourceReport' => $contentReport,
                'contentResourceFlags' => $contentReport['flags'],
                'contentResourceReviewFlags' => $contentReport['reviewFlags'],
                'contentReferences' => $contentReport['references'],
                'contentDiagnostics' => $contentReport['diagnostics'],
            ];
        }

        return $assets;
    }

    /**
     * @param list<array<string, mixed>> $manifest
     * @param list<array<string, mixed>> $xhtmlAssets
     * @param array<string, mixed> $xhtmlResourceReport
     *
     * @return array<string, mixed>
     */
    private static function remoteResourceReport(array $manifest, array $xhtmlAssets, array $xhtmlResourceReport): array
    {
        $declaredItems = [];
        $declaredByPart = [];
        foreach ($manifest as $item) {
            $flags = is_array($item['resourceFlags'] ?? null)
                ? $item['resourceFlags']
                : self::resourcePropertyFlags(is_array($item['properties'] ?? null) ? $item['properties'] : []);
            if (($flags['remoteResources'] ?? false) !== true) {
                continue;
            }

            $declared = self::remoteResourceManifestItem($item);
            $declaredItems[] = $declared;
            if (is_string($declared['part'] ?? null) && $declared['part'] !== '') {
                $declaredByPart[$declared['part']] = $declared;
            }
        }

        $observedItems = [];
        $observedItemsByPart = [];
        $remoteReferences = [];
        $undeclaredItems = [];
        $declaredButUnobservedItems = [];
        $diagnostics = [];

        foreach ($xhtmlAssets as $asset) {
            $references = self::xhtmlAssetRemoteResourceReferences($asset);
            if ($references === []) {
                continue;
            }

            array_push($remoteReferences, ...$references);
            $part = is_string($asset['part'] ?? null) ? $asset['part'] : '';
            $manifestDeclared = isset($declaredByPart[$part])
                || (($asset['resourceFlags']['remoteResources'] ?? false) === true);
            $observed = [
                'id' => (string) ($asset['id'] ?? ''),
                'href' => (string) ($asset['href'] ?? ''),
                'part' => $part,
                'manifestDeclared' => $manifestDeclared,
                'manifestProperties' => is_array($asset['properties'] ?? null) ? array_values($asset['properties']) : [],
                'remoteReferenceCount' => count($references),
                'remoteReferences' => $references,
                'reviewFlags' => is_array($asset['contentResourceReviewFlags'] ?? null)
                    ? array_values($asset['contentResourceReviewFlags'])
                    : [],
                'diagnostics' => [],
            ];

            if (!$manifestDeclared) {
                $diagnostic = [
                    'type' => 'undeclared-xhtml-remote-resources',
                    'id' => $observed['id'],
                    'part' => $part === '' ? null : $part,
                    'remoteReferenceCount' => count($references),
                    'message' => 'EPUB XHTML content references remote resources but the OPF manifest item does not declare remote-resources',
                ];
                $observed['diagnostics'][] = $diagnostic;
                $undeclaredItems[] = $observed;
                $diagnostics[] = $diagnostic;
            }

            $observedItems[] = $observed;
            if ($part !== '') {
                $observedItemsByPart[$part] = $observed;
            }
        }

        foreach ($declaredItems as $declared) {
            $part = is_string($declared['part'] ?? null) ? $declared['part'] : null;
            if ($part !== null && isset($observedItemsByPart[$part])) {
                continue;
            }

            $mediaType = is_string($declared['mediaType'] ?? null) ? $declared['mediaType'] : null;
            $diagnostic = [
                'type' => $mediaType === self::XHTML_MEDIA_TYPE
                    ? 'declared-remote-resources-not-observed'
                    : 'declared-remote-resources-unscanned-resource',
                'id' => (string) ($declared['id'] ?? ''),
                'part' => $part,
                'mediaType' => $mediaType,
                'message' => $mediaType === self::XHTML_MEDIA_TYPE
                    ? 'EPUB OPF manifest item declares remote-resources but the bounded XHTML scan did not observe resource-loading remote references'
                    : 'EPUB OPF manifest item declares remote-resources on a media type outside the bounded XHTML scanner',
            ];
            $declared['diagnostics'] = [$diagnostic];
            $declaredButUnobservedItems[] = $declared;
            $diagnostics[] = $diagnostic;
        }

        return [
            'present' => $declaredItems !== [] || $remoteReferences !== [],
            'declaredCount' => count($declaredItems),
            'observedAssetCount' => count($observedItems),
            'remoteReferenceCount' => count($remoteReferences),
            'xhtmlExternalReferenceCount' => is_int($xhtmlResourceReport['externalReferenceCount'] ?? null)
                ? $xhtmlResourceReport['externalReferenceCount']
                : 0,
            'undeclaredAssetCount' => count($undeclaredItems),
            'declaredButUnobservedCount' => count($declaredButUnobservedItems),
            'declaredItems' => $declaredItems,
            'declaredItemsByPart' => $declaredByPart,
            'observedItems' => $observedItems,
            'observedItemsByPart' => $observedItemsByPart,
            'undeclaredItems' => $undeclaredItems,
            'declaredButUnobservedItems' => $declaredButUnobservedItems,
            'remoteReferences' => $remoteReferences,
            'diagnostics' => $diagnostics,
        ];
    }

    /**
     * @param array<string, mixed> $item
     *
     * @return array<string, mixed>
     */
    private static function remoteResourceManifestItem(array $item): array
    {
        return [
            'id' => (string) ($item['id'] ?? ''),
            'href' => (string) ($item['href'] ?? ''),
            'target' => is_string($item['target'] ?? null) ? $item['target'] : null,
            'part' => is_string($item['part'] ?? null) ? $item['part'] : null,
            'external' => (bool) ($item['external'] ?? false),
            'mediaType' => is_string($item['mediaType'] ?? null) ? $item['mediaType'] : null,
            'exists' => (bool) ($item['exists'] ?? false),
            'properties' => is_array($item['properties'] ?? null) ? array_values($item['properties']) : [],
            'diagnostics' => [],
        ];
    }

    /**
     * @param array<string, mixed> $asset
     *
     * @return list<array<string, mixed>>
     */
    private static function xhtmlAssetRemoteResourceReferences(array $asset): array
    {
        $references = is_array($asset['contentReferences'] ?? null) ? $asset['contentReferences'] : [];

        return array_values(array_filter(
            $references,
            static fn (array $reference): bool => self::isRemoteResourceReference($reference),
        ));
    }

    /**
     * @param array<string, mixed> $reference
     */
    private static function isRemoteResourceReference(array $reference): bool
    {
        if (($reference['external'] ?? false) !== true) {
            return false;
        }

        $element = strtolower((string) ($reference['element'] ?? ''));
        $attribute = strtolower((string) ($reference['attribute'] ?? ''));

        return !($element === 'a' && $attribute === 'href');
    }

    /**
     * @param list<array<string, mixed>> $xhtmlAssets
     *
     * @return array<string, mixed>
     */
    private static function xhtmlResourceReport(array $xhtmlAssets): array
    {
        $items = [];
        $itemsByPart = [];
        $externalReferences = [];
        $missingReferences = [];
        $encryptedReferences = [];
        $diagnostics = [];
        $mathmlAssetCount = 0;
        $svgAssetCount = 0;
        $scriptedAssetCount = 0;
        $reviewRequiredCount = 0;
        $referenceCount = 0;

        foreach ($xhtmlAssets as $asset) {
            $report = is_array($asset['contentResourceReport'] ?? null) ? $asset['contentResourceReport'] : null;
            if ($report === null) {
                continue;
            }

            $part = (string) ($asset['part'] ?? $report['part'] ?? '');
            $item = [
                'id' => (string) ($asset['id'] ?? ''),
                'part' => $part,
                'href' => (string) ($asset['href'] ?? ''),
                'manifestProperties' => is_array($asset['properties'] ?? null) ? array_values($asset['properties']) : [],
                'flags' => is_array($report['flags'] ?? null) ? $report['flags'] : [],
                'reviewFlags' => is_array($report['reviewFlags'] ?? null) ? array_values($report['reviewFlags']) : [],
                'referenceCount' => count(is_array($report['references'] ?? null) ? $report['references'] : []),
                'references' => is_array($report['references'] ?? null) ? array_values($report['references']) : [],
                'diagnostics' => is_array($report['diagnostics'] ?? null) ? array_values($report['diagnostics']) : [],
            ];

            $referenceCount += $item['referenceCount'];
            if (($item['flags']['mathml'] ?? false) === true) {
                ++$mathmlAssetCount;
            }
            if (($item['flags']['svg'] ?? false) === true) {
                ++$svgAssetCount;
            }
            if (($item['flags']['scripted'] ?? false) === true) {
                ++$scriptedAssetCount;
            }
            if ($item['reviewFlags'] !== []) {
                ++$reviewRequiredCount;
            }

            foreach ($item['references'] as $reference) {
                if (($reference['external'] ?? false) === true) {
                    $externalReferences[] = $reference;
                }
                if (($reference['exists'] ?? true) !== true && ($reference['external'] ?? false) !== true) {
                    $missingReferences[] = $reference;
                }
                if (($reference['encrypted'] ?? false) === true) {
                    $encryptedReferences[] = $reference;
                }
            }

            foreach ($item['diagnostics'] as $diagnostic) {
                $diagnostics[] = [
                    'part' => $part,
                ] + $diagnostic;
            }

            $items[] = $item;
            if ($part !== '') {
                $itemsByPart[$part] = $item;
            }
        }

        return [
            'present' => $items !== [],
            'assetCount' => count($items),
            'referenceCount' => $referenceCount,
            'externalReferenceCount' => count($externalReferences),
            'missingReferenceCount' => count($missingReferences),
            'encryptedReferenceCount' => count($encryptedReferences),
            'mathmlAssetCount' => $mathmlAssetCount,
            'svgAssetCount' => $svgAssetCount,
            'scriptedAssetCount' => $scriptedAssetCount,
            'reviewRequiredCount' => $reviewRequiredCount,
            'items' => $items,
            'itemsByPart' => $itemsByPart,
            'externalReferences' => $externalReferences,
            'missingReferences' => $missingReferences,
            'encryptedReferences' => $encryptedReferences,
            'diagnostics' => $diagnostics,
        ];
    }

    /**
     * @param array<string, array<string, mixed>> $manifestByPart
     *
     * @return array<string, mixed>
     */
    private function xhtmlContentResourceReport(
        ZipPackage $package,
        string $part,
        string $html,
        array $manifestByPart
    ): array {
        $flags = self::emptyXhtmlContentResourceFlags();
        $references = [];
        $diagnostics = [];

        try {
            $dom = self::loadXml($html, 'EPUB XHTML content document');
        } catch (\Throwable $exception) {
            return [
                'part' => $part,
                'flags' => $flags,
                'reviewFlags' => [],
                'references' => [],
                'diagnostics' => [[
                    'type' => 'xhtml-content-resource-scan-failed',
                    'part' => $part,
                    'message' => $exception->getMessage(),
                ]],
            ];
        }

        $root = $dom->documentElement;
        if ($root instanceof \DOMElement) {
            $this->scanXhtmlContentElement($package, $part, $root, $manifestByPart, $flags, $references);
        }

        foreach ($references as $reference) {
            foreach ($reference['diagnostics'] as $diagnostic) {
                $diagnostics[] = [
                    'index' => $reference['index'],
                    'element' => $reference['element'],
                    'attribute' => $reference['attribute'],
                    'href' => $reference['href'],
                ] + $diagnostic;
            }
        }

        return [
            'part' => $part,
            'flags' => $flags,
            'reviewFlags' => self::xhtmlContentReviewFlags($flags),
            'references' => $references,
            'diagnostics' => $diagnostics,
        ];
    }

    /**
     * @param array<string, array<string, mixed>> $manifestByPart
     * @param array<string, bool> $flags
     * @param list<array<string, mixed>> $references
     */
    private function scanXhtmlContentElement(
        ZipPackage $package,
        string $part,
        \DOMElement $element,
        array $manifestByPart,
        array &$flags,
        array &$references
    ): void {
        $namespace = (string) $element->namespaceURI;
        $localName = strtolower($element->localName);
        if ($namespace === 'http://www.w3.org/1998/Math/MathML' || $localName === 'math') {
            $flags['mathml'] = true;
        }
        if ($namespace === 'http://www.w3.org/2000/svg' || $localName === 'svg') {
            $flags['svg'] = true;
        }
        if ($namespace === self::XHTML_NS && $localName === 'script') {
            $flags['scripted'] = true;
        }

        foreach (self::xhtmlEventHandlerAttributes($element) as $attributeName) {
            $flags['scripted'] = true;
            $references[] = [
                'index' => count($references),
                'element' => $element->localName,
                'attribute' => $attributeName,
                'href' => null,
                'target' => null,
                'part' => $part,
                'fragment' => null,
                'external' => false,
                'exists' => true,
                'byteLength' => null,
                'crc32' => null,
                'manifestId' => $manifestByPart[$part]['id'] ?? null,
                'mediaType' => $manifestByPart[$part]['mediaType'] ?? self::XHTML_MEDIA_TYPE,
                'encrypted' => false,
                'canExposeBytes' => true,
                'diagnostics' => [[
                    'type' => 'scripted-xhtml-content-attribute',
                    'attribute' => $attributeName,
                    'message' => 'EPUB XHTML content carries an inline script event handler that requires review',
                ]],
            ];
        }

        foreach (self::xhtmlReferenceAttributes($element) as $attribute) {
            $href = trim($attribute['href']);
            if ($href === '') {
                continue;
            }
            if (preg_match('/^javascript:/i', $href) === 1) {
                $flags['scripted'] = true;
            }

            $references[] = $this->xhtmlContentReference(
                $package,
                $part,
                $element->localName,
                $attribute['attribute'],
                $href,
                $manifestByPart,
                count($references),
                $flags
            );
        }

        foreach (self::childElements($element) as $child) {
            $this->scanXhtmlContentElement($package, $part, $child, $manifestByPart, $flags, $references);
        }
    }

    /**
     * @param array<string, array<string, mixed>> $manifestByPart
     * @param array<string, bool> $flags
     *
     * @return array<string, mixed>
     */
    private function xhtmlContentReference(
        ZipPackage $package,
        string $part,
        string $element,
        string $attribute,
        string $href,
        array $manifestByPart,
        int $index,
        array &$flags
    ): array {
        $reference = $this->packageReference($package, $part, $href, $manifestByPart, 'xhtml-content');
        $diagnostics = $reference['diagnostics'];
        if (($reference['external'] ?? false) === true) {
            $flags['remoteResources'] = true;
        }
        if (($reference['exists'] ?? true) !== true && ($reference['external'] ?? false) !== true) {
            $flags['missingReferences'] = true;
        }
        if (($reference['encrypted'] ?? false) === true) {
            $flags['encryptedReferences'] = true;
            $diagnostics[] = [
                'type' => 'encrypted-xhtml-content-reference',
                'part' => $reference['part'],
                'message' => 'EPUB XHTML content references an encrypted package part that cannot be exposed directly',
            ];
        }

        return [
            'index' => $index,
            'element' => $element,
            'attribute' => $attribute,
            'href' => $href,
            'target' => $reference['target'],
            'part' => $reference['part'],
            'fragment' => self::targetFragment($reference['target']),
            'external' => $reference['external'],
            'exists' => $reference['exists'],
            'byteLength' => $reference['byteLength'],
            'crc32' => $reference['crc32'],
            'manifestId' => $reference['manifestId'],
            'mediaType' => $reference['mediaType'],
            'encrypted' => $reference['encrypted'],
            'canExposeBytes' => $reference['canExposeBytes'],
            'diagnostics' => $diagnostics,
        ];
    }

    /**
     * @return array{mathml:bool, svg:bool, scripted:bool, remoteResources:bool, missingReferences:bool, encryptedReferences:bool}
     */
    private static function emptyXhtmlContentResourceFlags(): array
    {
        return [
            'mathml' => false,
            'svg' => false,
            'scripted' => false,
            'remoteResources' => false,
            'missingReferences' => false,
            'encryptedReferences' => false,
        ];
    }

    /**
     * @param array<string, bool> $flags
     *
     * @return list<string>
     */
    private static function xhtmlContentReviewFlags(array $flags): array
    {
        $reviewFlags = [];
        foreach ([
            'mathml' => 'mathml',
            'svg' => 'svg',
            'scripted' => 'scripted',
            'remoteResources' => 'remote-resources',
            'missingReferences' => 'missing-references',
            'encryptedReferences' => 'encrypted-references',
        ] as $flag => $reviewFlag) {
            if (($flags[$flag] ?? false) === true) {
                $reviewFlags[] = $reviewFlag;
            }
        }

        return $reviewFlags;
    }

    /**
     * @return list<array{attribute:string, href:string}>
     */
    private static function xhtmlReferenceAttributes(\DOMElement $element): array
    {
        $localName = strtolower($element->localName);
        $attributes = [];
        foreach ([
            'a' => ['href'],
            'audio' => ['src'],
            'embed' => ['src'],
            'iframe' => ['src'],
            'image' => ['href', 'xlink:href'],
            'img' => ['src'],
            'link' => ['href'],
            'object' => ['data'],
            'script' => ['src'],
            'source' => ['src'],
            'track' => ['src'],
            'use' => ['href', 'xlink:href'],
            'video' => ['src', 'poster'],
        ][$localName] ?? [] as $attributeName) {
            $value = self::xhtmlAttributeValue($element, $attributeName);
            if ($value === null || trim($value) === '') {
                continue;
            }

            $attributes[] = [
                'attribute' => $attributeName,
                'href' => $value,
            ];
        }

        return $attributes;
    }

    private static function xhtmlAttributeValue(\DOMElement $element, string $attributeName): ?string
    {
        if ($attributeName === 'xlink:href') {
            $value = trim($element->getAttributeNS('http://www.w3.org/1999/xlink', 'href'));
            if ($value !== '') {
                return $value;
            }
        }

        if (!$element->hasAttribute($attributeName)) {
            return null;
        }

        return trim($element->getAttribute($attributeName));
    }

    /**
     * @return list<string>
     */
    private static function xhtmlEventHandlerAttributes(\DOMElement $element): array
    {
        $attributes = [];
        if (!$element->hasAttributes()) {
            return $attributes;
        }

        foreach ($element->attributes as $attribute) {
            if (!$attribute instanceof \DOMAttr) {
                continue;
            }

            $name = strtolower($attribute->name);
            if (str_starts_with($name, 'on')) {
                $attributes[] = $attribute->name;
            }
        }

        return $attributes;
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
            $part = $item['part'] ?? null;
            if (is_string($part) && $part !== '') {
                $manifestParts[$part] = true;
            }

            if ($item['mediaType'] === self::XHTML_MEDIA_TYPE) {
                continue;
            }

            $isCoverImage = self::isCoverImageAsset($item, $metadata);
            $role = self::assetRole($item, $isCoverImage);
            $canExposeBytes = (bool) ($item['canExposeBytes'] ?? true);
            $exportCandidate = self::isExportCandidate($item, $role);
            $byteSha256 = null;
            $diagnostics = is_array($item['diagnostics'] ?? null) ? array_values($item['diagnostics']) : [];
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
                'external' => (bool) ($item['external'] ?? false),
                'mediaType' => $item['mediaType'],
                'properties' => $item['properties'],
                'resourceFlags' => $item['resourceFlags'] ?? self::resourcePropertyFlags($item['properties'] ?? []),
                'resourceReviewFlags' => $item['resourceReviewFlags'] ?? [],
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
        foreach (self::metadataLinkedParts($metadata) as $linkedPart) {
            $manifestParts[$linkedPart] = true;
        }

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
     * @param array<string, mixed> $metadata
     *
     * @return list<string>
     */
    private static function metadataLinkedParts(array $metadata): array
    {
        $parts = [];
        foreach (($metadata['links'] ?? []) as $link) {
            if (!is_array($link)) {
                continue;
            }

            $part = $link['part'] ?? null;
            if (is_string($part) && $part !== '') {
                $parts[$part] = $part;
            }
        }

        return array_values($parts);
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
     * @param array<string, mixed> $spineProperties
     * @param list<array<string, mixed>> $xhtmlAssets
     * @param array<string, mixed> $guide
     * @param list<array<string, mixed>> $collections
     * @param array<string, mixed> $bindings
     * @param array<string, mixed> $accessibility
     * @param array<string, mixed> $resourceProperties
     * @param array<string, mixed> $remoteResources
     * @param array<string, mixed> $mediaDurations
     * @param array<string, mixed> $pageBreaks
     * @param array<string, mixed> $navigation
     * @param array<string, mixed> $xhtmlResourceReport
     * @param array<string, mixed> $renditions
     */
    private function documentNode(
        array $metadata,
        string $opfPart,
        array $spine,
        array $spineProperties,
        array $xhtmlAssets,
        array $guide,
        array $collections,
        array $bindings,
        array $accessibility,
        array $resourceProperties,
        array $remoteResources,
        array $mediaDurations,
        array $pageBreaks,
        array $navigation,
        array $xhtmlResourceReport,
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
                'spineItemId' => $item['id'] ?? null,
                'linear' => $item['linear'],
                'linearRaw' => $item['linearRaw'] ?? null,
                'linearSpecified' => $item['linearSpecified'] ?? false,
                'linearValid' => $item['linearValid'] ?? true,
                'refinements' => $item['refinements'] ?? [],
                'pageProgressionDirection' => $spineProperties['pageProgressionDirection'] ?? 'default',
                'pageSpread' => $item['pageSpread'] ?? null,
                'pageSpreadProperties' => $item['pageSpreadProperties'] ?? [],
                'spineItemProperties' => $item['spineItemProperties'] ?? [],
                'spineItemDiagnostics' => $item['spineItemDiagnostics'] ?? [],
                'mediaOverlay' => $item['mediaOverlay'],
                'pageBreaks' => is_array($pageBreaks['itemsByPart'][$contentPart] ?? null)
                    ? array_values($pageBreaks['itemsByPart'][$contentPart])
                    : [],
                'pageBreakCount' => is_array($pageBreaks['itemsByPart'][$contentPart] ?? null)
                    ? count($pageBreaks['itemsByPart'][$contentPart])
                    : 0,
                'resourceFlags' => $asset['resourceFlags'] ?? [],
                'resourceReviewFlags' => $asset['resourceReviewFlags'] ?? [],
                'contentResourceFlags' => $asset['contentResourceFlags'] ?? [],
                'contentResourceReviewFlags' => $asset['contentResourceReviewFlags'] ?? [],
                'contentReferences' => $asset['contentReferences'] ?? [],
                'contentDiagnostics' => $asset['contentDiagnostics'] ?? [],
                'source' => $isFallback ? 'epub3-spine-fallback' : 'epub3-spine',
            ];

            if ($isFallback) {
                $attributes['spinePart'] = $item['part'];
                $attributes['spineMediaType'] = $item['mediaType'];
                $attributes['fallbackOf'] = $item['idref'];
                $attributes['contentId'] = $item['contentId'];
                $attributes['fallbackChain'] = $item['fallbackChain'];
            }
            if (is_array($item['binding'] ?? null)) {
                $attributes['binding'] = $item['binding'];
            }

            $children[] = new AstNode('raw_html', $attributes);
        }

        return new AstNode('document', [
            'source' => 'epub3',
            'opfPart' => $opfPart,
            'metadata' => $metadata,
            'guide' => $guide,
            'collections' => $collections,
            'bindings' => $bindings,
            'accessibility' => $accessibility,
            'spineProperties' => $spineProperties,
            'resourceProperties' => $resourceProperties,
            'remoteResources' => $remoteResources,
            'navigation' => $navigation,
            'xhtmlResourceReport' => $xhtmlResourceReport,
            'mediaDurations' => $mediaDurations,
            'pageBreaks' => $pageBreaks,
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
     * @param list<string> $properties
     *
     * @return array{nav:bool, coverImage:bool, mathml:bool, svg:bool, remoteResources:bool, scripted:bool, switch:bool}
     */
    private static function resourcePropertyFlags(array $properties): array
    {
        return [
            'nav' => in_array('nav', $properties, true),
            'coverImage' => in_array('cover-image', $properties, true),
            'mathml' => in_array('mathml', $properties, true),
            'svg' => in_array('svg', $properties, true),
            'remoteResources' => in_array('remote-resources', $properties, true),
            'scripted' => in_array('scripted', $properties, true),
            'switch' => in_array('switch', $properties, true),
        ];
    }

    /**
     * @param array<string, bool> $flags
     *
     * @return list<string>
     */
    private static function resourceReviewFlags(array $flags): array
    {
        $reviewFlags = [];
        foreach ([
            'mathml' => 'mathml',
            'svg' => 'svg',
            'remoteResources' => 'remote-resources',
            'scripted' => 'scripted',
            'switch' => 'switch',
        ] as $flag => $property) {
            if (($flags[$flag] ?? false) === true) {
                $reviewFlags[] = $property;
            }
        }

        return $reviewFlags;
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
            $part = $item['part'] ?? null;
            if (!is_string($part) || $part === '') {
                continue;
            }

            $byPart[$part] = $item;
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
