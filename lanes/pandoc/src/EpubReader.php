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
    public const OCF_METADATA_NS = 'http://www.idpf.org/2013/metadata';
    public const ODF_MANIFEST_NS = 'urn:oasis:names:tc:opendocument:xmlns:manifest:1.0';
    public const XMLENC_NS = 'http://www.w3.org/2001/04/xmlenc#';
    public const XMLDSIG_NS = 'http://www.w3.org/2000/09/xmldsig#';
    public const XML_EVENTS_NS = 'http://www.w3.org/2001/xml-events';
    public const SMIL_NS = 'http://www.w3.org/ns/SMIL';
    public const OPF_MEDIA_TYPE = 'application/oebps-package+xml';
    public const XHTML_MEDIA_TYPE = 'application/xhtml+xml';
    public const NCX_MEDIA_TYPE = 'application/x-dtbncx+xml';
    public const SMIL_MEDIA_TYPE = 'application/smil+xml';
    public const IDPF_FONT_OBFUSCATION_ALGORITHM = 'http://www.idpf.org/2008/embedding';
    private const RESERVED_PACKAGE_PREFIXES = [
        'a11y' => 'http://www.idpf.org/epub/vocab/package/a11y/#',
        'dcterms' => 'http://purl.org/dc/terms/',
        'media' => 'http://www.idpf.org/epub/vocab/overlays/#',
        'rendition' => 'http://www.idpf.org/vocab/rendition/#',
        'schema' => 'http://schema.org/',
        'xsd' => 'http://www.w3.org/2001/XMLSchema#',
    ];
    private const CORE_MEDIA_TYPE_KINDS = [
        'application/ecmascript' => 'script',
        'application/font-sfnt' => 'font',
        'application/font-woff' => 'font',
        'application/javascript' => 'script',
        'application/smil+xml' => 'media-overlay',
        'application/vnd.ms-opentype' => 'font',
        'application/x-dtbncx+xml' => 'navigation',
        'application/xhtml+xml' => 'xhtml',
        'audio/mp4' => 'audio',
        'audio/mpeg' => 'audio',
        'font/otf' => 'font',
        'font/ttf' => 'font',
        'font/woff' => 'font',
        'font/woff2' => 'font',
        'image/gif' => 'image',
        'image/jpeg' => 'image',
        'image/png' => 'image',
        'image/svg+xml' => 'svg',
        'image/webp' => 'image',
        'text/css' => 'style',
        'text/javascript' => 'script',
    ];
    private const NCX_NAV_LIST_CLASS_ROLES = [
        'bibliography' => 'bibliography',
        'endnotes' => 'endnotes',
        'footnotes' => 'footnotes',
        'glossary' => 'glossary',
        'index' => 'index',
        'loa' => 'list-of-audio',
        'loi' => 'list-of-illustrations',
        'lot' => 'list-of-tables',
        'lov' => 'list-of-video',
        'notes' => 'notes',
        'page-list' => 'page-list',
        'pages' => 'page-list',
        'list-of-audio' => 'list-of-audio',
        'list-of-figures' => 'list-of-illustrations',
        'list-of-illustrations' => 'list-of-illustrations',
        'list-of-tables' => 'list-of-tables',
        'list-of-video' => 'list-of-video',
    ];
    private const OPF_MANIFEST_STRUCTURAL_ATTRIBUTES = [
        'id' => true,
        'href' => true,
        'media-type' => true,
        'properties' => true,
        'fallback' => true,
        'fallback-style' => true,
        'media-overlay' => true,
        'xml:base' => true,
        'xml:lang' => true,
        'dir' => true,
    ];
    private const OPF_SPINE_ITEMREF_STRUCTURAL_ATTRIBUTES = [
        'id' => true,
        'idref' => true,
        'linear' => true,
        'properties' => true,
        'xml:lang' => true,
        'dir' => true,
    ];
    private const OPF_PACKAGE_STRUCTURAL_ATTRIBUTES = [
        'id' => true,
        'version' => true,
        'unique-identifier' => true,
        'prefix' => true,
        'xml:lang' => true,
        'xml:base' => true,
        'dir' => true,
    ];
    private const OCF_ROOTFILE_STRUCTURAL_ATTRIBUTES = [
        'full-path' => true,
        'media-type' => true,
    ];
    private const OPF_METADATA_STRUCTURAL_ATTRIBUTES = [
        'id' => true,
        'xml:lang' => true,
        'xml:base' => true,
        'dir' => true,
    ];

    /**
     * @return array{
     *     document:AstNode,
     *     mimetypeEntry:array<string, mixed>,
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
     *     navigationOutline:array<string, mixed>,
     *     guide:array<string, mixed>,
     *     collections:list<array<string, mixed>>,
     *     renditions:array<string, mixed>,
     *     bindings:array<string, mixed>,
     *     resourceProperties:array<string, mixed>,
     *     mediaTypes:array<string, mixed>,
     *     remoteResources:array<string, mixed>,
     *     encryption:array<string, mixed>,
     *     ocf:array<string, mixed>,
     *     mediaOverlays:array<string, array<string, mixed>>,
     *     mediaDurations:array<string, mixed>,
     *     mediaOverlayStyles:array<string, mixed>,
     *     pageBreaks:array<string, mixed>,
     *     spineContentProvenance:array<string, mixed>,
     *     xhtmlAssets:list<array<string, mixed>>,
     *     xhtmlResourceReport:array<string, mixed>,
     *     cssResourceReport:array<string, mixed>,
     *     assets:list<array<string, mixed>>,
     *     assetReport:array<string, mixed>,
     *     importReport:array<string, mixed>
     * }
     */
    public function readPackage(ZipPackage $package): array
    {
        $mimetypeEntry = $this->assertEpubMimetype($package);
        $container = $this->readContainer($package);
        $ocf = $this->readOcfSidecars($package);
        $opfPart = (string) $container['opfPart'];

        $opf = $this->readOpf($package, $opfPart);
        $renditions = $this->readRenditions($package, $container, $opfPart, $opf);
        $document = $this->documentNode(
            $opf['metadata'],
            $mimetypeEntry,
            $opfPart,
            $opf['package'],
            $opf['spine'],
            $opf['spineProperties'],
            $opf['xhtmlAssets'],
            $opf['guide'],
            $opf['collections'],
            $opf['bindings'],
            $opf['accessibility'],
            $opf['resourceProperties'],
            $opf['mediaTypes'],
            $opf['remoteResources'],
            $opf['mediaDurations'],
            $opf['mediaOverlayStyles'],
            $opf['pageBreaks'],
            $opf['spineContentProvenance'],
            $opf['navigation'],
            $opf['navigationOutline'],
            $opf['xhtmlResourceReport'],
            $opf['cssResourceReport'],
            $opf['assetReport'],
            $renditions,
            $ocf
        );

        return [
            'document' => $document,
            'mimetypeEntry' => $mimetypeEntry,
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
            'navigationOutline' => $opf['navigationOutline'],
            'guide' => $opf['guide'],
            'collections' => $opf['collections'],
            'renditions' => $renditions,
            'bindings' => $opf['bindings'],
            'resourceProperties' => $opf['resourceProperties'],
            'mediaTypes' => $opf['mediaTypes'],
            'remoteResources' => $opf['remoteResources'],
            'encryption' => $opf['encryption'],
            'ocf' => $ocf,
            'mediaOverlays' => $opf['mediaOverlays'],
            'mediaDurations' => $opf['mediaDurations'],
            'mediaOverlayStyles' => $opf['mediaOverlayStyles'],
            'pageBreaks' => $opf['pageBreaks'],
            'spineContentProvenance' => $opf['spineContentProvenance'],
            'xhtmlAssets' => $opf['xhtmlAssets'],
            'xhtmlResourceReport' => $opf['xhtmlResourceReport'],
            'cssResourceReport' => $opf['cssResourceReport'],
            'assets' => $opf['assets'],
            'assetReport' => $opf['assetReport'],
            'importReport' => [
                'mimetypeEntry' => $mimetypeEntry,
                'container' => $container,
                'metadata' => $opf['metadata'],
                'package' => $opf['package'],
                'manifest' => self::importManifestReport($opf['manifest']),
                'spine' => [
                    'count' => count($opf['spine']),
                    'items' => $opf['spine'],
                    'properties' => $opf['spineProperties'],
                    'contentProvenance' => $opf['spineContentProvenance'],
                ],
                'nav' => $opf['nav'],
                'ncx' => $opf['ncx'],
                'navigation' => $opf['navigation'],
                'navigationOutline' => $opf['navigationOutline'],
                'guide' => $opf['guide'],
                'collections' => $opf['collections'],
                'renditions' => $renditions,
                'bindings' => $opf['bindings'],
                'accessibility' => $opf['accessibility'],
                'resourceProperties' => $opf['resourceProperties'],
                'mediaTypes' => $opf['mediaTypes'],
                'remoteResources' => $opf['remoteResources'],
                'xhtmlResourceReport' => $opf['xhtmlResourceReport'],
                'cssResourceReport' => $opf['cssResourceReport'],
                'encryption' => $opf['encryption'],
                'ocf' => $ocf,
                'mediaOverlays' => $opf['mediaOverlays'],
                'mediaDurations' => $opf['mediaDurations'],
                'mediaOverlayStyles' => $opf['mediaOverlayStyles'],
                'pageBreaks' => $opf['pageBreaks'],
                'assets' => $opf['assetReport'],
            ],
        ];
    }

    public function readDocument(ZipPackage $package): AstNode
    {
        return $this->readPackage($package)['document'];
    }

    /**
     * @return array<string, mixed>
     */
    private function assertEpubMimetype(ZipPackage $package): array
    {
        return $package->assertStoredFirstEntry('mimetype', self::MIMETYPE, 'EPUB mimetype entry');
    }

    /**
     * @return array{
     *     opfPart:string,
     *     selectedRootfileIndex:int,
     *     rootfiles:list<array<string, mixed>>,
     *     linkCount:int,
     *     links:list<array<string, mixed>>,
     *     linksByRel:array<string, list<array<string, mixed>>>,
     *     linkDiagnostics:list<array<string, mixed>>
     * }
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
            $mediaTypeReport = self::rootfileMediaTypeReport($mediaType);
            $exists = $package->has($part);
            $provenance = self::zipEntryProvenance($exists ? $package->entry($part) : null);
            $attributes = self::rootfileElementAttributes($rootfile);
            $customAttributes = self::rootfileCustomAttributes($attributes);
            $rootfiles[] = [
                'index' => $index,
                'fullPath' => $path,
                'path' => $part,
                'mediaType' => $mediaType,
                'normalizedMediaType' => $mediaTypeReport['normalizedMediaType'],
                'baseMediaType' => $mediaTypeReport['baseMediaType'],
                'mediaTypeHasParameters' => $mediaTypeReport['mediaTypeHasParameters'],
                'mediaTypeParameters' => $mediaTypeReport['mediaTypeParameters'],
                'mediaTypeParameterNames' => $mediaTypeReport['mediaTypeParameterNames'],
                'mediaTypeParameterCount' => $mediaTypeReport['mediaTypeParameterCount'],
                'mediaTypeSyntaxValid' => $mediaTypeReport['mediaTypeSyntaxValid'],
                'mediaTypeDiagnostics' => $mediaTypeReport['mediaTypeDiagnostics'],
                'attributes' => $attributes,
                'attributeCount' => count($attributes),
                'customAttributes' => $customAttributes,
                'customAttributeCount' => count($customAttributes),
                'exists' => $exists,
                'byteLength' => $provenance['byteLength'],
                'compressedByteLength' => $provenance['compressedByteLength'],
                'compressionMethod' => $provenance['compressionMethod'],
                'compressionMethodName' => $provenance['compressionMethodName'],
                'compressionSupported' => $provenance['compressionSupported'],
                'crc32' => $provenance['crc32'],
                'canExposeBytes' => $provenance['canExposeBytes'],
                'selected' => false,
            ];
        }

        if ($rootfiles === []) {
            throw new \RuntimeException('EPUB container XML does not list an OPF rootfile');
        }

        $selected = null;
        $selectedIndex = null;
        foreach ($rootfiles as $index => $rootfile) {
            if (self::mediaTypeBaseEquals($rootfile['mediaType'], self::OPF_MEDIA_TYPE)) {
                $selected = $rootfile;
                $selectedIndex = $index;
                break;
            }
        }
        if ($selected === null) {
            $selected = $rootfiles[0];
            $selectedIndex = 0;
        }

        if (!self::mediaTypeBaseEquals($selected['mediaType'], self::OPF_MEDIA_TYPE)) {
            throw new \RuntimeException('EPUB rootfile media-type must be application/oebps-package+xml');
        }

        if ($selected['exists'] !== true) {
            throw new \RuntimeException('EPUB OPF rootfile is missing from the package: ' . $selected['path']);
        }

        foreach ($rootfiles as $index => $rootfile) {
            $rootfiles[$index]['selected'] = $index === $selectedIndex;
        }

        $links = $this->readContainerLinks(
            $package,
            self::firstChildElement($root, 'links', self::OCF_CONTAINER_NS),
        );

        return [
            'opfPart' => $selected['path'],
            'selectedRootfileIndex' => $selectedIndex,
            'rootfiles' => $rootfiles,
            'linkCount' => count($links['items']),
            'links' => $links['items'],
            'linksByRel' => self::linksByRel($links['items']),
            'linkDiagnostics' => $links['diagnostics'],
        ];
    }

    /**
     * @return array{
     *     byteLength:?int,
     *     compressedByteLength:?int,
     *     compressionMethod:?int,
     *     compressionMethodName:?string,
     *     compressionSupported:?bool,
     *     crc32:?string,
     *     canExposeBytes:bool
     * }
     */
    private static function zipEntryProvenance(?ZipPackageEntry $entry): array
    {
        if (!$entry instanceof ZipPackageEntry) {
            return [
                'byteLength' => null,
                'compressedByteLength' => null,
                'compressionMethod' => null,
                'compressionMethodName' => null,
                'compressionSupported' => null,
                'crc32' => null,
                'canExposeBytes' => false,
            ];
        }

        $compressionSupported = self::zipEntryCompressionSupported($entry);

        return [
            'byteLength' => $entry->uncompressedSize,
            'compressedByteLength' => $entry->compressedSize,
            'compressionMethod' => $entry->compressionMethod,
            'compressionMethodName' => self::zipCompressionMethodName($entry->compressionMethod),
            'compressionSupported' => $compressionSupported,
            'crc32' => $entry->crc32Hex(),
            'canExposeBytes' => $compressionSupported,
        ];
    }

    private static function zipEntryCompressionSupported(ZipPackageEntry $entry): bool
    {
        return $entry->compressionMethod === 0 || $entry->compressionMethod === 8;
    }

    private static function zipCompressionMethodName(int $method): string
    {
        return match ($method) {
            0 => 'stored',
            8 => 'deflated',
            default => 'unsupported',
        };
    }

    /**
     * @return array{items:list<array<string, mixed>>, diagnostics:list<array<string, mixed>>}
     */
    private function readContainerLinks(ZipPackage $package, ?\DOMElement $linksElement): array
    {
        if (!$linksElement instanceof \DOMElement) {
            return [
                'items' => [],
                'diagnostics' => [],
            ];
        }

        $items = [];
        $diagnostics = [];
        foreach (self::childElements($linksElement, 'link', self::OCF_CONTAINER_NS) as $index => $link) {
            $href = self::nullableAttribute($link, 'href');
            $reference = $this->containerLinkReference($package, $href ?? '');
            $item = [
                'index' => $index,
                'href' => $href,
                'rel' => self::spaceDelimited($link->getAttribute('rel')),
                'mediaType' => self::nullableAttribute($link, 'media-type'),
                'properties' => self::spaceDelimited($link->getAttribute('properties')),
                'refines' => self::nullableAttribute($link, 'refines'),
                'target' => $reference['target'],
                'part' => $reference['part'],
                'fragment' => $reference['fragment'],
                'fragmentKind' => $reference['fragmentKind'],
                'epubCfi' => $reference['epubCfi'],
                'mediaFragment' => $reference['mediaFragment'],
                'external' => $reference['external'],
                'exists' => $reference['exists'],
                'byteLength' => $reference['byteLength'],
                'crc32' => $reference['crc32'],
                'byteSha256' => $reference['byteSha256'],
                'diagnostics' => $reference['diagnostics'],
            ];

            foreach ($reference['diagnostics'] as $diagnostic) {
                $diagnostics[] = [
                    'index' => $index,
                    'href' => $href,
                ] + $diagnostic;
            }

            $items[] = $item;
        }

        return [
            'items' => $items,
            'diagnostics' => $diagnostics,
        ];
    }

    /**
     * @return array{
     *     target:?string,
     *     part:?string,
     *     fragment:?string,
     *     fragmentKind:?string,
     *     epubCfi:?array<string, mixed>,
     *     mediaFragment:?array<string, mixed>,
     *     external:bool,
     *     exists:bool,
     *     byteLength:?int,
     *     crc32:?string,
     *     byteSha256:?string,
     *     diagnostics:list<array<string, mixed>>
     * }
     */
    private function containerLinkReference(ZipPackage $package, string $href): array
    {
        $href = trim($href);
        $fragmentFields = self::targetFragmentFields(null);
        if ($href === '') {
            return [
                'target' => null,
                'part' => null,
                'fragment' => $fragmentFields['fragment'],
                'fragmentKind' => $fragmentFields['fragmentKind'],
                'epubCfi' => $fragmentFields['epubCfi'],
                'mediaFragment' => $fragmentFields['mediaFragment'],
                'external' => false,
                'exists' => false,
                'byteLength' => null,
                'crc32' => null,
                'byteSha256' => null,
                'diagnostics' => [[
                    'type' => 'missing-container-link-href',
                    'message' => 'EPUB OCF container link is missing href',
                ]],
            ];
        }

        if (self::isExternalReference($href)) {
            $fragmentFields = self::targetFragmentFields($href);

            return [
                'target' => $href,
                'part' => null,
                'fragment' => $fragmentFields['fragment'],
                'fragmentKind' => $fragmentFields['fragmentKind'],
                'epubCfi' => $fragmentFields['epubCfi'],
                'mediaFragment' => $fragmentFields['mediaFragment'],
                'external' => true,
                'exists' => false,
                'byteLength' => null,
                'crc32' => null,
                'byteSha256' => null,
                'diagnostics' => [[
                    'type' => 'external-container-link-reference',
                    'href' => $href,
                    'message' => 'EPUB OCF container link points outside the package and was not fetched',
                ]],
            ];
        }

        try {
            $target = self::ocfPackageReferenceTarget($href, '/META-INF/container.xml');
        } catch (\InvalidArgumentException $exception) {
            return [
                'target' => null,
                'part' => null,
                'fragment' => $fragmentFields['fragment'],
                'fragmentKind' => $fragmentFields['fragmentKind'],
                'epubCfi' => $fragmentFields['epubCfi'],
                'mediaFragment' => $fragmentFields['mediaFragment'],
                'external' => false,
                'exists' => false,
                'byteLength' => null,
                'crc32' => null,
                'byteSha256' => null,
                'diagnostics' => [[
                    'type' => 'invalid-container-link-reference',
                    'href' => $href,
                    'message' => $exception->getMessage(),
                ]],
            ];
        }

        $fragmentFields = self::targetFragmentFields($target);
        $part = OpcPackagePath::stripQueryAndFragment($target);
        $exists = $package->has($part);
        $entry = $exists ? $package->entry($part) : null;
        $diagnostics = $exists ? [] : [[
            'type' => 'missing-container-link-reference',
            'href' => $href,
            'part' => $part,
            'message' => 'EPUB OCF container link target is missing from the package',
        ]];

        return [
            'target' => $target,
            'part' => $part,
            'fragment' => $fragmentFields['fragment'],
            'fragmentKind' => $fragmentFields['fragmentKind'],
            'epubCfi' => $fragmentFields['epubCfi'],
            'mediaFragment' => $fragmentFields['mediaFragment'],
            'external' => false,
            'exists' => $exists,
            'byteLength' => $entry instanceof ZipPackageEntry ? $entry->uncompressedSize : null,
            'crc32' => $entry instanceof ZipPackageEntry ? $entry->crc32Hex() : null,
            'byteSha256' => $exists ? hash('sha256', $package->read($part)) : null,
            'diagnostics' => $diagnostics,
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
     *     navigationOutline:array<string, mixed>,
     *     guide:array<string, mixed>,
     *     collections:list<array<string, mixed>>,
     *     bindings:array<string, mixed>,
     *     accessibility:array<string, mixed>,
     *     resourceProperties:array<string, mixed>,
     *     mediaTypes:array<string, mixed>,
     *     remoteResources:array<string, mixed>,
     *     encryption:array<string, mixed>,
     *     mediaOverlays:array<string, array<string, mixed>>,
     *     mediaDurations:array<string, mixed>,
     *     mediaOverlayStyles:array<string, mixed>,
     *     pageBreaks:array<string, mixed>,
     *     xhtmlAssets:list<array<string, mixed>>,
     *     xhtmlResourceReport:array<string, mixed>,
     *     cssResourceReport:array<string, mixed>,
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
        $packageLanguage = self::xmlLang($root);
        $packageDirection = self::direction($root);
        $packageBase = self::xmlBase($root);
        $packageAttributes = self::packageElementAttributes($root);
        $packageCustomAttributes = self::packageElementCustomAttributes($packageAttributes);
        $packageAuthoring = self::packageAuthoringReport(
            $packageAttributes,
            $packageLanguage,
            $packageDirection,
            $packageBase,
            $packageCustomAttributes
        );
        $prefixReport = self::packagePrefixReport(trim($root->getAttribute('prefix')));
        $metadata = $this->readMetadata(
            $metadataElement,
            $uniqueIdentifier,
            true,
            $prefixReport['bindingsByPrefix'],
            $packageLanguage,
            $packageDirection
        );
        $metadata['packageAuthoring'] = $packageAuthoring;
        $refinementsById = is_array($metadata['refinementsById'] ?? null) ? $metadata['refinementsById'] : [];
        $packageId = self::nullableAttribute($root, 'id');
        $manifestById = $this->readManifest(
            $package,
            $opfPart,
            $manifestElement,
            $refinementsById,
            $prefixReport['bindingsByPrefix']
        );
        $manifestById = self::attachManifestPackagePartDiagnostics($manifestById);
        $encryption = $this->readEncryption($package, $manifestById);
        $manifestById = $this->attachEncryptionToManifest($manifestById, $encryption);
        $metadata = $this->resolveMetadataLinks($package, $opfPart, $metadata, $manifestById);
        $linkedResourcesById = is_array($metadata['linksByRefinedId'] ?? null) ? $metadata['linksByRefinedId'] : [];
        $manifestById = self::attachMetadataLinksToManifest($manifestById, $linkedResourcesById);
        $guide = $this->readGuide($package, $opfPart, self::firstChildElement($root, 'guide', self::OPF_NS), $manifestById);
        $collections = $this->readCollections($package, $opfPart, $root, $manifestById, $prefixReport['bindingsByPrefix']);
        $bindings = $this->readBindings($package, self::firstChildElement($root, 'bindings', self::OPF_NS), $manifestById);
        $manifestById = self::attachManifestMediaTypeReports($manifestById, $bindings);
        $mediaDurations = self::mediaDurationReport($metadata, $manifestById);
        $metadata['mediaDurations'] = $mediaDurations;
        $mediaOverlayStyles = self::mediaOverlayStyleReport($metadata, $manifestById);
        $metadata['mediaOverlayStyles'] = $mediaOverlayStyles;
        $mediaOverlays = $this->readMediaOverlays($package, $manifestById, $mediaDurations, $mediaOverlayStyles);
        $manifestById = self::attachMediaOverlayReferencesToManifest($manifestById, $mediaOverlays);
        $spineProperties = self::readSpineProperties($spineElement, $refinementsById, $linkedResourcesById);
        $spine = $this->readSpine(
            $spineElement,
            $manifestById,
            $bindings,
            $refinementsById,
            $linkedResourcesById,
            is_array($metadata['renditionLayout'] ?? null) ? $metadata['renditionLayout'] : []
        );
        $manifestById = self::attachNonSpineMissingManifestDiagnostics($manifestById, $spine);
        $metadata['refinementSubjectSummary'] = self::metadataRefinementSubjectSummary(
            $metadata,
            $packageId,
            $manifestById,
            $spineProperties,
            $spine,
            $collections,
        );
        $spineProperties = self::spinePropertiesWithItemDiagnostics($spineProperties, $spine);
        $manifest = array_values($manifestById);
        $mediaTypes = self::manifestMediaTypeReport($manifest);
        $navItem = $this->firstManifestItemWithProperty($manifest, 'nav');
        $ncxItem = $this->ncxManifestItem($spineElement, $manifestById, $manifest);
        $assetReport = $this->assetReport($package, $opfPart, $manifest, $manifestById, $metadata, $encryption);
        $manifestByPart = self::manifestByPart($manifestById);
        $nav = $navItem === null ? null : $this->readNavDocument($package, $navItem, $manifestByPart);
        $nav = $nav === null ? null : self::navWithPrimaryNavigationTargetPolicy($nav, $spine);
        $ncx = $ncxItem === null ? null : $this->readNcxDocument($package, $ncxItem, $manifestByPart);
        $navigation = self::navigationReport($nav, $ncx, $spine);
        $navigationOutline = self::navigationOutlineReport($nav, $ncx, $navigation);
        $xhtmlAssets = $this->xhtmlAssets($package, $manifest, $manifestByPart);
        $spineContentProvenance = self::spineContentProvenanceReport($spine, $xhtmlAssets);
        $xhtmlResourceReport = self::xhtmlResourceReport($xhtmlAssets);
        $pageBreaks = self::pageBreakReport($nav, $ncx, $spine, $xhtmlResourceReport);
        $cssResourceReport = $this->cssResourceReport($package, $manifest, $manifestByPart);
        $remoteResources = self::remoteResourceReport($manifest, $xhtmlAssets, $xhtmlResourceReport, $cssResourceReport);
        $resourceProperties = self::resourcePropertyReport($manifest, $xhtmlAssets);
        $packageVersion = trim($root->getAttribute('version'));
        $packageSummary = [
            'id' => $packageId,
            'version' => $packageVersion,
            'uniqueIdentifierId' => $uniqueIdentifier === '' ? null : $uniqueIdentifier,
            'opfPart' => $opfPart,
            'language' => $packageLanguage,
            'direction' => $packageDirection,
            'base' => $packageBase,
            'attributeCount' => count($packageAttributes),
            'customAttributeCount' => count($packageCustomAttributes),
            'authoring' => $packageAuthoring['summary'],
        ];

        return [
            'metadata' => $metadata,
            'package' => [
                'id' => $packageId,
                'version' => $packageVersion,
                'uniqueIdentifierId' => $uniqueIdentifier === '' ? null : $uniqueIdentifier,
                'uniqueIdentifier' => $metadata['uniqueIdentifier'],
                'opfPart' => $opfPart,
                'language' => $packageLanguage,
                'direction' => $packageDirection,
                'base' => $packageBase,
                'attributes' => $packageAttributes,
                'attributeCount' => count($packageAttributes),
                'customAttributes' => $packageCustomAttributes,
                'customAttributeCount' => count($packageCustomAttributes),
                'authoring' => $packageAuthoring,
                'summary' => $packageSummary,
                'refinements' => self::metadataRefinementsForId($refinementsById, $packageId),
                'linkedResources' => self::metadataLinkedResourcesForId($linkedResourcesById, $packageId),
                'prefix' => $prefixReport['raw'],
                'prefixes' => $prefixReport['bindingsByPrefix'],
                'prefixBindings' => $prefixReport['bindings'],
                'prefixDiagnostics' => $prefixReport['diagnostics'],
                'renditionLayout' => $metadata['renditionLayout'],
            ],
            'manifest' => $manifest,
            'spine' => $spine,
            'spineProperties' => $spineProperties,
            'nav' => $nav,
            'ncx' => $ncx,
            'navigation' => $navigation,
            'navigationOutline' => $navigationOutline,
            'guide' => $guide,
            'collections' => $collections,
            'bindings' => $bindings,
            'accessibility' => $metadata['accessibility'],
            'resourceProperties' => $resourceProperties,
            'mediaTypes' => $mediaTypes,
            'remoteResources' => $remoteResources,
            'encryption' => $encryption,
            'mediaOverlays' => $mediaOverlays,
            'mediaDurations' => $mediaDurations,
            'mediaOverlayStyles' => $mediaOverlayStyles,
            'pageBreaks' => $pageBreaks,
            'spineContentProvenance' => $spineContentProvenance,
            'xhtmlAssets' => $xhtmlAssets,
            'xhtmlResourceReport' => $xhtmlResourceReport,
            'cssResourceReport' => $cssResourceReport,
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
            if (!is_array($rootfile) || !self::mediaTypeBaseEquals($rootfile['mediaType'] ?? null, self::OPF_MEDIA_TYPE)) {
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
     * @return array{
     *     normalizedMediaType:string,
     *     baseMediaType:string,
     *     mediaTypeHasParameters:bool,
     *     mediaTypeParameters:array<string, string>,
     *     mediaTypeParameterNames:list<string>,
     *     mediaTypeParameterCount:int,
     *     mediaTypeSyntaxValid:bool,
     *     mediaTypeDiagnostics:list<array<string, mixed>>
     * }
     */
    private static function rootfileMediaTypeReport(string $mediaType): array
    {
        $parts = self::mediaTypeParts($mediaType);
        $parameters = is_array($parts['parameters'] ?? null) ? $parts['parameters'] : [];

        return [
            'normalizedMediaType' => (string) ($parts['normalized'] ?? ''),
            'baseMediaType' => (string) ($parts['base'] ?? ''),
            'mediaTypeHasParameters' => $parameters !== [],
            'mediaTypeParameters' => $parameters,
            'mediaTypeParameterNames' => array_keys($parameters),
            'mediaTypeParameterCount' => count($parameters),
            'mediaTypeSyntaxValid' => (bool) ($parts['valid'] ?? true),
            'mediaTypeDiagnostics' => is_array($parts['diagnostics'] ?? null)
                ? array_values($parts['diagnostics'])
                : [],
        ];
    }

    /**
     * @param array<string, mixed> $rootfile
     *
     * @return array<string, mixed>
     */
    private static function rootfileMediaTypeFields(array $rootfile): array
    {
        $fallback = self::rootfileMediaTypeReport((string) ($rootfile['mediaType'] ?? ''));
        $parameters = is_array($rootfile['mediaTypeParameters'] ?? null)
            ? $rootfile['mediaTypeParameters']
            : $fallback['mediaTypeParameters'];
        $diagnostics = is_array($rootfile['mediaTypeDiagnostics'] ?? null)
            ? array_values($rootfile['mediaTypeDiagnostics'])
            : $fallback['mediaTypeDiagnostics'];
        $parameterNames = is_array($rootfile['mediaTypeParameterNames'] ?? null)
            ? array_values($rootfile['mediaTypeParameterNames'])
            : array_keys($parameters);

        return [
            'normalizedMediaType' => is_string($rootfile['normalizedMediaType'] ?? null)
                ? $rootfile['normalizedMediaType']
                : $fallback['normalizedMediaType'],
            'baseMediaType' => is_string($rootfile['baseMediaType'] ?? null)
                ? $rootfile['baseMediaType']
                : $fallback['baseMediaType'],
            'mediaTypeHasParameters' => (bool) ($rootfile['mediaTypeHasParameters'] ?? ($parameters !== [])),
            'mediaTypeParameters' => $parameters,
            'mediaTypeParameterNames' => $parameterNames,
            'mediaTypeParameterCount' => is_int($rootfile['mediaTypeParameterCount'] ?? null)
                ? $rootfile['mediaTypeParameterCount']
                : count($parameters),
            'mediaTypeSyntaxValid' => (bool) ($rootfile['mediaTypeSyntaxValid'] ?? $fallback['mediaTypeSyntaxValid']),
            'mediaTypeDiagnostics' => $diagnostics,
        ];
    }

    /**
     * @param array<string, mixed> $rootfile
     *
     * @return array<string, mixed>
     */
    private static function rootfileAuthoringFields(array $rootfile): array
    {
        $attributes = is_array($rootfile['attributes'] ?? null) ? $rootfile['attributes'] : [];
        $customAttributes = is_array($rootfile['customAttributes'] ?? null)
            ? $rootfile['customAttributes']
            : self::rootfileCustomAttributes($attributes);

        return [
            'fullPath' => is_string($rootfile['fullPath'] ?? null)
                ? $rootfile['fullPath']
                : ltrim((string) ($rootfile['path'] ?? ''), '/'),
            'attributes' => $attributes,
            'attributeCount' => is_int($rootfile['attributeCount'] ?? null)
                ? $rootfile['attributeCount']
                : count($attributes),
            'customAttributes' => $customAttributes,
            'customAttributeCount' => is_int($rootfile['customAttributeCount'] ?? null)
                ? $rootfile['customAttributeCount']
                : count($customAttributes),
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

        return array_merge([
            'index' => (int) ($rootfile['index'] ?? 0),
            'path' => (string) $rootfile['path'],
            'mediaType' => (string) $rootfile['mediaType'],
        ], self::rootfileMediaTypeFields($rootfile), self::rootfileAuthoringFields($rootfile), [
            'exists' => (bool) ($rootfile['exists'] ?? false),
            'selected' => (bool) ($rootfile['selected'] ?? false),
            'package' => is_array($opf['package'] ?? null) ? $opf['package'] : null,
            'metadata' => self::renditionMetadataSummary($metadata),
            'renditionProperties' => self::renditionProperties($metadata),
            'manifestCount' => is_array($opf['manifest'] ?? null) ? count($opf['manifest']) : null,
            'spineCount' => is_array($opf['spine'] ?? null) ? count($opf['spine']) : null,
            'diagnostics' => [],
            'renditionLayout' => is_array($metadata['renditionLayout'] ?? null)
                ? $metadata['renditionLayout']
                : self::metadataRenditionLayoutReport($metadata),
        ]);
    }

    /**
     * @param array<string, mixed> $rootfile
     *
     * @return array<string, mixed>
     */
    private function readAlternateRenditionSummary(ZipPackage $package, array $rootfile): array
    {
        $summary = array_merge([
            'index' => (int) ($rootfile['index'] ?? 0),
            'path' => (string) $rootfile['path'],
            'mediaType' => (string) $rootfile['mediaType'],
        ], self::rootfileMediaTypeFields($rootfile), self::rootfileAuthoringFields($rootfile), [
            'exists' => (bool) ($rootfile['exists'] ?? false),
            'selected' => false,
            'package' => null,
            'metadata' => self::renditionMetadataSummary([]),
            'renditionProperties' => [],
            'renditionLayout' => self::metadataRenditionLayoutReport([]),
            'manifestCount' => null,
            'spineCount' => null,
            'diagnostics' => [],
        ]);

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
            $alternatePrefixReport = self::packagePrefixReport(trim($root->getAttribute('prefix')));
            $metadata = $this->readMetadata(
                $metadataElement,
                $uniqueIdentifier,
                true,
                $alternatePrefixReport['bindingsByPrefix'],
                self::xmlLang($root),
                self::direction($root)
            );
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
            'direction' => self::direction($root),
            'prefix' => trim($root->getAttribute('prefix')),
        ];
        $summary['metadata'] = self::renditionMetadataSummary($metadata);
        $summary['renditionProperties'] = self::renditionProperties($metadata);
        $summary['renditionLayout'] = is_array($metadata['renditionLayout'] ?? null)
            ? $metadata['renditionLayout']
            : self::metadataRenditionLayoutReport($metadata);
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
        bool $requireUniqueIdentifier = true,
        array $prefixBindings = [],
        ?string $inheritedLanguage = null,
        ?string $inheritedDirection = null
    ): array
    {
        $metadataOwnLanguage = self::xmlLang($metadataElement);
        $metadataOwnDirection = self::direction($metadataElement);
        $metadataBase = self::xmlBase($metadataElement);
        $metadataAttributes = self::opfElementAttributes($metadataElement);
        $metadataCustomAttributes = self::metadataElementCustomAttributes($metadataAttributes);
        $metadataLanguage = $metadataOwnLanguage ?? $inheritedLanguage;
        $metadataDirection = $metadataOwnDirection ?? $inheritedDirection;
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
                    'event' => self::nullableAttribute($child, 'opf:event') ?? self::nullableAttribute($child, 'event'),
                    'language' => self::xmlLang($child) ?? $metadataLanguage,
                    'direction' => self::direction($child) ?? $metadataDirection,
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
                    'title' => self::nullableAttribute($child, 'title'),
                    'hreflang' => self::nullableAttribute($child, 'hreflang'),
                    'language' => self::xmlLang($child) ?? $metadataLanguage,
                    'direction' => self::direction($child) ?? $metadataDirection,
                ];
                $entry['relVocabulary'] = self::metadataLinkTokenReport($entry['rel'], $prefixBindings, 'rel', count($links));
                $entry['propertyVocabulary'] = self::metadataLinkTokenReport($entry['properties'], $prefixBindings, 'properties', count($links));
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
                'propertyVocabulary' => self::metadataPropertyVocabulary($property === '' ? null : $property, $prefixBindings),
                'name' => $name === '' ? null : $name,
                'content' => self::nullableAttribute($child, 'content'),
                'refines' => self::nullableAttribute($child, 'refines'),
                'id' => self::nullableAttribute($child, 'id'),
                'scheme' => self::nullableAttribute($child, 'scheme'),
                'language' => self::xmlLang($child) ?? $metadataLanguage,
                'direction' => self::direction($child) ?? $metadataDirection,
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
        $identifierDetails = self::metadataIdentifierDetails($dc['identifier'] ?? [], $uniqueIdentifierReport);
        $uniqueIdentifierReport = self::uniqueIdentifierReportWithIdentifierDetails($uniqueIdentifierReport, $identifierDetails);
        $dateDetails = self::metadataDateDetails($dc['date'] ?? []);
        $languageDetails = self::metadataLanguageDetails($dc['language'] ?? []);
        $sourceDetails = self::metadataSourceDetails($dc['source'] ?? []);
        $bibliographicDetails = self::metadataBibliographicDetails($dc);
        $titleDetails = self::metadataTitleDetails($dc['title'] ?? []);
        $titlesByType = self::metadataTitlesByType($titleDetails);
        $mainTitle = self::firstMetadataTitleByType($titleDetails, 'main') ?? ($titleDetails[0] ?? null);
        $creatorDetails = self::metadataAgentDetails($dc['creator'] ?? [], 'creator');
        $contributorDetails = self::metadataAgentDetails($dc['contributor'] ?? [], 'contributor');
        $agentDisplayOrder = self::metadataAgentDisplayOrderReport($creatorDetails, $contributorDetails);

        $metadata = [
            'title' => $dc['title'][0]['text'] ?? '',
            'titleDetails' => $titleDetails,
            'titlesByType' => $titlesByType,
            'mainTitle' => $mainTitle,
            'subtitle' => self::firstMetadataTitleByType($titleDetails, 'subtitle'),
            'shortTitle' => self::firstMetadataTitleByType($titleDetails, 'short'),
            'collectionTitle' => self::firstMetadataTitleByType($titleDetails, 'collection'),
            'sortTitle' => is_array($mainTitle) ? $mainTitle['fileAs'] : null,
            'creators' => array_map(static fn (array $entry): string => $entry['text'], $dc['creator'] ?? []),
            'creatorDetails' => $creatorDetails,
            'creatorsByRole' => self::metadataAgentsByRole($creatorDetails),
            'contributors' => array_map(static fn (array $entry): string => $entry['text'], $dc['contributor'] ?? []),
            'contributorDetails' => $contributorDetails,
            'contributorsByRole' => self::metadataAgentsByRole($contributorDetails),
            'untypedContributors' => self::metadataAgentsWithoutRoles($contributorDetails),
            'agentDisplayOrder' => $agentDisplayOrder,
            'language' => $dc['language'][0]['text'] ?? null,
            'languages' => array_map(static fn (array $entry): string => $entry['text'], $dc['language'] ?? []),
            'languageDetails' => $languageDetails,
            'languagesByPrimarySubtag' => self::metadataLanguageDetailsByPrimarySubtag($languageDetails),
            'languageSummary' => self::metadataLanguageSummary($languageDetails),
            'metadataAuthoring' => self::metadataAuthoringReport(
                $metadataAttributes,
                $metadataOwnLanguage,
                $metadataOwnDirection,
                $metadataBase,
                $metadataCustomAttributes,
            ),
            'identifier' => $uniqueIdentifierReport['value'],
            'uniqueIdentifier' => $uniqueIdentifierReport,
            'identifiers' => $identifiers,
            'identifierDetails' => $identifierDetails,
            'identifiersByType' => self::metadataIdentifierDetailsByField($identifierDetails, 'identifierType'),
            'identifiersByScheme' => self::metadataIdentifierDetailsByField($identifierDetails, 'scheme'),
            'identifierSummary' => self::metadataIdentifierSummary($identifierDetails, $uniqueIdentifierReport),
            'subjects' => array_map(static fn (array $entry): string => $entry['text'], $dc['subject'] ?? []),
            'description' => $dc['description'][0]['text'] ?? null,
            'publisher' => $dc['publisher'][0]['text'] ?? null,
            'date' => $dc['date'][0]['text'] ?? null,
            'dateDetails' => $dateDetails,
            'datesByEvent' => self::metadataDateDetailsByEvent($dateDetails),
            'dateSummary' => self::metadataDateSummary($dateDetails),
            'source' => $dc['source'][0]['text'] ?? null,
            'sources' => array_map(static fn (array $entry): string => $entry['text'], $dc['source'] ?? []),
            'sourceDetails' => $sourceDetails,
            'sourcesBySourceOf' => self::metadataSourceDetailsBySourceOf($sourceDetails),
            'sourceSummary' => self::metadataSourceSummary($sourceDetails),
            'bibliographicDetails' => $bibliographicDetails,
            'bibliographicDetailsByKind' => self::metadataBibliographicDetailsByKind($bibliographicDetails),
            'bibliographicSummary' => self::metadataBibliographicSummary($bibliographicDetails),
            'modified' => $metaProperties['dcterms:modified'][0]['text'] ?? null,
            'coverItemId' => $metaNames['cover'][0]['content'] ?? null,
            'dc' => $dc,
            'metaProperties' => $metaProperties,
            'vocabulary' => self::metadataVocabularyReport($metaProperties, $prefixBindings),
            'metaNames' => $metaNames,
            'refinementsById' => $refinementsById,
            'links' => $links,
            'linksByRel' => self::linksByRel($links),
            'raw' => $raw,
        ];
        $metadata['vendorMetadata'] = self::vendorMetadataReport($metadata);
        $metadata['collectionMembership'] = self::metadataCollectionMembershipReport($metadata);
        $metadata['accessibility'] = self::accessibilityMetadataReport($metadata);
        $metadata['renditionLayout'] = self::metadataRenditionLayoutReport($metadata);

        return $metadata;
    }

    /**
     * @param array<string, string> $prefixBindings
     *
     * @return ?array<string, mixed>
     */
    private static function metadataPropertyVocabulary(?string $property, array $prefixBindings): ?array
    {
        $prefixBindings = self::metadataVocabularyPrefixBindings($prefixBindings);
        $raw = is_string($property) ? trim($property) : '';
        if ($raw === '') {
            return null;
        }

        $diagnostics = [];
        if (preg_match('/^([A-Za-z_][A-Za-z0-9._-]*):(.+)$/', $raw, $matches) !== 1) {
            return [
                'raw' => $raw,
                'prefixed' => false,
                'prefix' => null,
                'name' => $raw,
                'localName' => $raw,
                'bindingIri' => null,
                'iri' => null,
                'resolved' => false,
                'diagnostics' => [],
            ];
        }

        $prefix = $matches[1];
        $localName = $matches[2];
        $bindingIri = isset($prefixBindings[$prefix]) ? (string) $prefixBindings[$prefix] : null;
        if ($bindingIri === null || $bindingIri === '') {
            $diagnostics[] = [
                'type' => 'unknown-package-prefix',
                'prefix' => $prefix,
                'property' => $raw,
                'message' => 'EPUB OPF metadata property uses a prefix that is not declared on the package element',
            ];
        }

        return [
            'raw' => $raw,
            'prefixed' => true,
            'prefix' => $prefix,
            'name' => $localName,
            'localName' => $localName,
            'bindingIri' => $bindingIri,
            'iri' => $bindingIri === null || $bindingIri === '' ? null : $bindingIri . $localName,
            'resolved' => $bindingIri !== null && $bindingIri !== '',
            'diagnostics' => $diagnostics,
        ];
    }

    /**
     * @param array<string, string> $prefixBindings
     *
     * @return ?array<string, mixed>
     */
    private static function manifestPropertyVocabulary(?string $property, array $prefixBindings): ?array
    {
        $term = self::metadataPropertyVocabulary($property, $prefixBindings);
        if (!is_array($term)) {
            return null;
        }

        $diagnostics = [];
        foreach ($term['diagnostics'] ?? [] as $diagnostic) {
            if (!is_array($diagnostic)) {
                continue;
            }

            if (($diagnostic['type'] ?? null) === 'unknown-package-prefix') {
                $diagnostic['type'] = 'unknown-manifest-property-prefix';
                $diagnostic['message'] = 'EPUB OPF manifest item property uses a prefix that is not declared on the package element';
            }
            $diagnostics[] = $diagnostic;
        }

        $term['diagnostics'] = $diagnostics;

        return $term;
    }

    /**
     * @param array<string, list<array<string, mixed>>> $metaProperties
     * @param array<string, string> $prefixBindings
     *
     * @return array<string, mixed>
     */
    private static function metadataVocabularyReport(array $metaProperties, array $prefixBindings): array
    {
        $prefixBindings = self::metadataVocabularyPrefixBindings($prefixBindings);
        $resolved = [];
        $unresolved = [];
        $byPrefix = [];
        $diagnostics = [];

        foreach ($metaProperties as $property => $entries) {
            foreach ($entries as $index => $entry) {
                $term = is_array($entry['propertyVocabulary'] ?? null)
                    ? $entry['propertyVocabulary']
                    : self::metadataPropertyVocabulary((string) $property, $prefixBindings);
                if (!is_array($term) || ($term['prefixed'] ?? false) !== true) {
                    continue;
                }

                $summary = [
                    'property' => (string) $property,
                    'entryIndex' => $index,
                    'id' => is_string($entry['id'] ?? null) ? $entry['id'] : null,
                    'refines' => is_string($entry['refines'] ?? null) ? $entry['refines'] : null,
                    'text' => self::metadataEntryValue($entry),
                    'vocabulary' => $term,
                ];

                $prefix = is_string($term['prefix'] ?? null) ? $term['prefix'] : '';
                if ($prefix !== '') {
                    if (!isset($byPrefix[$prefix])) {
                        $byPrefix[$prefix] = [
                            'prefix' => $prefix,
                            'bindingIri' => is_string($term['bindingIri'] ?? null) ? $term['bindingIri'] : null,
                            'entryCount' => 0,
                            'properties' => [],
                            'resolvedCount' => 0,
                            'unresolvedCount' => 0,
                        ];
                    }

                    ++$byPrefix[$prefix]['entryCount'];
                    $byPrefix[$prefix]['properties'][] = (string) $property;
                    if (($term['resolved'] ?? false) === true) {
                        ++$byPrefix[$prefix]['resolvedCount'];
                    } else {
                        ++$byPrefix[$prefix]['unresolvedCount'];
                    }
                }

                if (($term['resolved'] ?? false) === true) {
                    $resolved[] = $summary;
                } else {
                    $unresolved[] = $summary;
                    foreach ($term['diagnostics'] ?? [] as $diagnostic) {
                        if (is_array($diagnostic)) {
                            $diagnostics[] = [
                                'property' => (string) $property,
                                'entryIndex' => $index,
                            ] + $diagnostic;
                        }
                    }
                }
            }
        }

        foreach ($byPrefix as $prefix => $summary) {
            $byPrefix[$prefix]['properties'] = array_values(array_unique($summary['properties']));
        }

        return [
            'prefixes' => $prefixBindings,
            'present' => $resolved !== [] || $unresolved !== [],
            'resolvedPropertyCount' => count($resolved),
            'unresolvedPropertyCount' => count($unresolved),
            'resolvedProperties' => $resolved,
            'unresolvedProperties' => $unresolved,
            'byPrefix' => $byPrefix,
            'diagnostics' => $diagnostics,
        ];
    }

    /**
     * @param array<string, string> $prefixBindings
     *
     * @return array<string, string>
     */
    private static function metadataVocabularyPrefixBindings(array $prefixBindings): array
    {
        return array_replace(self::RESERVED_PACKAGE_PREFIXES, $prefixBindings);
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
            'direction' => is_string($entry['direction'] ?? null) ? $entry['direction'] : null,
            'refinements' => is_array($entry['refinements'] ?? null) ? $entry['refinements'] : [],
        ];
    }

    /**
     * @param list<array<string, mixed>> $entries
     * @param array<string, mixed> $uniqueIdentifier
     *
     * @return list<array<string, mixed>>
     */
    private static function metadataIdentifierDetails(array $entries, array $uniqueIdentifier): array
    {
        $values = [];
        foreach ($entries as $index => $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $text = (string) ($entry['text'] ?? '');
            if ($text === '') {
                continue;
            }

            $values[$text][] = [
                'index' => (int) $index,
                'id' => is_string($entry['id'] ?? null) ? $entry['id'] : null,
            ];
        }

        $uniqueIdentifierId = is_string($uniqueIdentifier['id'] ?? null) ? $uniqueIdentifier['id'] : null;
        $details = [];
        foreach ($entries as $index => $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $refinements = is_array($entry['refinements'] ?? null) ? $entry['refinements'] : [];
            $identifierTypes = self::metadataRefinementEntries($refinements, 'identifier-type');
            $identifierType = $identifierTypes[0] ?? null;
            $text = (string) ($entry['text'] ?? '');
            $duplicateEntries = $text !== '' && count($values[$text] ?? []) > 1 ? $values[$text] : [];

            $details[] = [
                'kind' => 'identifier',
                'index' => (int) $index,
                'text' => $text,
                'id' => is_string($entry['id'] ?? null) ? $entry['id'] : null,
                'scheme' => is_string($entry['scheme'] ?? null) ? $entry['scheme'] : null,
                'language' => is_string($entry['language'] ?? null) ? $entry['language'] : null,
                'direction' => is_string($entry['direction'] ?? null) ? $entry['direction'] : null,
                'identifierTypes' => $identifierTypes,
                'identifierType' => is_array($identifierType) ? (string) $identifierType['value'] : null,
                'identifierTypeScheme' => is_array($identifierType) && is_string($identifierType['scheme'] ?? null)
                    ? $identifierType['scheme']
                    : null,
                'selectedByUniqueIdentifier' => $uniqueIdentifierId !== null
                    && $uniqueIdentifierId !== ''
                    && is_string($entry['id'] ?? null)
                    && $entry['id'] === $uniqueIdentifierId,
                'duplicateValue' => $duplicateEntries !== [],
                'duplicateIds' => array_values(array_filter(
                    array_map(static fn (array $duplicate): ?string => $duplicate['id'], $duplicateEntries),
                    static fn (?string $id): bool => $id !== null && $id !== '',
                )),
                'duplicateIndexes' => array_map(
                    static fn (array $duplicate): int => (int) $duplicate['index'],
                    $duplicateEntries,
                ),
                'linkedResources' => [],
                'refinements' => $refinements,
            ];
        }

        return $details;
    }

    /**
     * @param array<string, mixed> $identifier
     * @param list<array<string, mixed>> $identifierDetails
     *
     * @return array<string, mixed>
     */
    private static function uniqueIdentifierReportWithIdentifierDetails(array $identifier, array $identifierDetails): array
    {
        $detailsById = [];
        $detailsByIndex = [];
        foreach ($identifierDetails as $detail) {
            if (!is_array($detail)) {
                continue;
            }

            $detailsByIndex[(int) ($detail['index'] ?? 0)] = $detail;
            if (is_string($detail['id'] ?? null) && $detail['id'] !== '') {
                $detailsById[$detail['id']] = $detail;
            }
        }

        foreach (['entries', 'matchedEntries'] as $key) {
            $entries = is_array($identifier[$key] ?? null) ? $identifier[$key] : [];
            foreach ($entries as $index => $entry) {
                if (!is_array($entry)) {
                    continue;
                }

                $id = is_string($entry['id'] ?? null) ? $entry['id'] : null;
                $detail = $id !== null && isset($detailsById[$id])
                    ? $detailsById[$id]
                    : ($detailsByIndex[(int) ($entry['index'] ?? $index)] ?? null);
                if (!is_array($detail)) {
                    continue;
                }

                $entries[$index]['identifierTypes'] = is_array($detail['identifierTypes'] ?? null) ? $detail['identifierTypes'] : [];
                $entries[$index]['identifierType'] = is_string($detail['identifierType'] ?? null) ? $detail['identifierType'] : null;
                $entries[$index]['identifierTypeScheme'] = is_string($detail['identifierTypeScheme'] ?? null) ? $detail['identifierTypeScheme'] : null;
                $entries[$index]['duplicateValue'] = (bool) ($detail['duplicateValue'] ?? false);
                $entries[$index]['duplicateIds'] = is_array($detail['duplicateIds'] ?? null) ? $detail['duplicateIds'] : [];
                $entries[$index]['duplicateIndexes'] = is_array($detail['duplicateIndexes'] ?? null) ? $detail['duplicateIndexes'] : [];
            }
            $identifier[$key] = $entries;
        }

        return $identifier;
    }

    /**
     * @param list<array<string, mixed>> $details
     *
     * @return array<string, list<array<string, mixed>>>
     */
    private static function metadataIdentifierDetailsByField(array $details, string $field): array
    {
        $byField = [];
        foreach ($details as $detail) {
            if (!is_array($detail)) {
                continue;
            }

            $value = is_string($detail[$field] ?? null) ? trim($detail[$field]) : '';
            if ($value === '') {
                continue;
            }

            $byField[$value][] = $detail;
        }

        return $byField;
    }

    /**
     * @param list<array<string, mixed>> $details
     * @param array<string, mixed> $uniqueIdentifier
     *
     * @return array<string, mixed>
     */
    private static function metadataIdentifierSummary(array $details, array $uniqueIdentifier): array
    {
        $schemes = [];
        $identifierTypes = [];
        $duplicatesByValue = [];
        $selectedIndex = null;
        $selectedId = null;
        $selectedValue = is_string($uniqueIdentifier['value'] ?? null) ? $uniqueIdentifier['value'] : null;
        $diagnostics = [];

        foreach ($details as $detail) {
            if (!is_array($detail)) {
                continue;
            }

            $scheme = is_string($detail['scheme'] ?? null) ? trim($detail['scheme']) : '';
            if ($scheme !== '') {
                $schemes[$scheme] = $scheme;
            }

            $identifierType = is_string($detail['identifierType'] ?? null) ? trim($detail['identifierType']) : '';
            if ($identifierType !== '') {
                $identifierTypes[$identifierType] = $identifierType;
            }

            if (($detail['selectedByUniqueIdentifier'] ?? false) === true && $selectedIndex === null) {
                $selectedIndex = (int) ($detail['index'] ?? 0);
                $selectedId = is_string($detail['id'] ?? null) ? $detail['id'] : null;
            }

            if (($detail['duplicateValue'] ?? false) !== true) {
                continue;
            }

            $text = (string) ($detail['text'] ?? '');
            if ($text === '' || isset($duplicatesByValue[$text])) {
                continue;
            }

            $duplicateIds = is_array($detail['duplicateIds'] ?? null) ? array_values($detail['duplicateIds']) : [];
            $duplicateIndexes = is_array($detail['duplicateIndexes'] ?? null) ? array_values($detail['duplicateIndexes']) : [];
            $duplicatesByValue[$text] = [
                'value' => $text,
                'count' => count($duplicateIndexes),
                'ids' => $duplicateIds,
                'indexes' => $duplicateIndexes,
            ];
            $diagnostics[] = [
                'type' => 'duplicate-metadata-identifier-value',
                'value' => $text,
                'ids' => $duplicateIds,
                'indexes' => $duplicateIndexes,
                'message' => 'EPUB OPF metadata contains multiple dc:identifier entries with the same value',
            ];
        }

        if ($selectedIndex === null && $selectedValue !== null) {
            foreach ($details as $detail) {
                if ((string) ($detail['text'] ?? '') !== $selectedValue) {
                    continue;
                }

                $selectedIndex = (int) ($detail['index'] ?? 0);
                $selectedId = is_string($detail['id'] ?? null) ? $detail['id'] : null;
                break;
            }
        }

        return [
            'present' => $details !== [],
            'count' => count($details),
            'typedCount' => count(array_filter(
                $details,
                static fn (array $detail): bool => is_string($detail['identifierType'] ?? null)
                    && $detail['identifierType'] !== '',
            )),
            'schemeCount' => count($schemes),
            'schemes' => array_values($schemes),
            'identifierTypes' => array_values($identifierTypes),
            'selectedValue' => $selectedValue,
            'selectedId' => $selectedId,
            'selectedIndex' => $selectedIndex,
            'duplicateValueCount' => count($duplicatesByValue),
            'duplicateValues' => array_keys($duplicatesByValue),
            'duplicatesByValue' => $duplicatesByValue,
            'diagnostics' => $diagnostics,
        ];
    }

    /**
     * @param list<array<string, mixed>> $entries
     *
     * @return list<array<string, mixed>>
     */
    private static function metadataDateDetails(array $entries): array
    {
        $details = [];
        foreach ($entries as $index => $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $refinements = is_array($entry['refinements'] ?? null) ? $entry['refinements'] : [];
            $eventEntries = self::metadataRefinementEntries($refinements, 'event');
            $eventAttribute = is_string($entry['event'] ?? null) && trim($entry['event']) !== ''
                ? trim($entry['event'])
                : null;
            $event = $eventAttribute ?? (is_array($eventEntries[0] ?? null) ? (string) $eventEntries[0]['value'] : null);

            $details[] = [
                'kind' => 'date',
                'index' => (int) $index,
                'text' => (string) ($entry['text'] ?? ''),
                'id' => is_string($entry['id'] ?? null) ? $entry['id'] : null,
                'scheme' => is_string($entry['scheme'] ?? null) ? $entry['scheme'] : null,
                'event' => $event,
                'eventSource' => $eventAttribute !== null ? 'attribute' : ($event !== null ? 'refinement' : null),
                'eventAttribute' => $eventAttribute,
                'eventRefinements' => $eventEntries,
                'displaySeq' => self::firstMetadataRefinementValue($refinements, 'display-seq'),
                'language' => is_string($entry['language'] ?? null) ? $entry['language'] : null,
                'direction' => is_string($entry['direction'] ?? null) ? $entry['direction'] : null,
                'linkedResources' => [],
                'refinements' => $refinements,
            ];
        }

        return $details;
    }

    /**
     * @param list<array<string, mixed>> $details
     *
     * @return array<string, list<array<string, mixed>>>
     */
    private static function metadataDateDetailsByEvent(array $details): array
    {
        $byEvent = [];
        foreach ($details as $detail) {
            if (!is_array($detail)) {
                continue;
            }

            $event = is_string($detail['event'] ?? null) ? trim($detail['event']) : '';
            if ($event === '') {
                continue;
            }

            $byEvent[$event][] = $detail;
        }

        return $byEvent;
    }

    /**
     * @param list<array<string, mixed>> $details
     *
     * @return array{present:bool, count:int, eventCount:int, events:list<string>, diagnostics:list<array<string, mixed>>}
     */
    private static function metadataDateSummary(array $details): array
    {
        $events = [];
        foreach ($details as $detail) {
            $event = is_string($detail['event'] ?? null) ? trim($detail['event']) : '';
            if ($event !== '') {
                $events[$event] = $event;
            }
        }

        return [
            'present' => $details !== [],
            'count' => count($details),
            'eventCount' => count($events),
            'events' => array_values($events),
            'diagnostics' => [],
        ];
    }

    /**
     * @param list<array<string, mixed>> $entries
     *
     * @return list<array<string, mixed>>
     */
    private static function metadataLanguageDetails(array $entries): array
    {
        $details = [];
        $indexesByTag = [];

        foreach ($entries as $index => $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $text = (string) ($entry['text'] ?? '');
            $refinements = is_array($entry['refinements'] ?? null) ? $entry['refinements'] : [];
            $displaySeq = self::firstMetadataRefinementValue($refinements, 'display-seq');
            $tag = self::metadataLanguageTagReport($text);
            $detailIndex = count($details);

            $details[] = [
                'kind' => 'language',
                'index' => (int) $index,
                'text' => $text,
                'tag' => $text,
                'normalizedTag' => $tag['normalizedTag'],
                'primarySubtag' => $tag['primarySubtag'],
                'scriptSubtag' => $tag['scriptSubtag'],
                'regionSubtag' => $tag['regionSubtag'],
                'variantSubtags' => $tag['variantSubtags'],
                'wellFormed' => $tag['wellFormed'],
                'id' => is_string($entry['id'] ?? null) ? $entry['id'] : null,
                'scheme' => is_string($entry['scheme'] ?? null) ? $entry['scheme'] : null,
                'language' => is_string($entry['language'] ?? null) ? $entry['language'] : null,
                'direction' => is_string($entry['direction'] ?? null) ? $entry['direction'] : null,
                'displaySeq' => $displaySeq,
                'displaySeqNumber' => self::metadataDisplaySeqNumber($displaySeq),
                'duplicateTag' => false,
                'duplicateIndexes' => [],
                'linkedResources' => [],
                'refinements' => $refinements,
                'diagnostics' => $tag['diagnostics'],
            ];

            $normalizedTag = $tag['wellFormed'] && is_string($tag['normalizedTag']) ? $tag['normalizedTag'] : '';
            if ($normalizedTag !== '') {
                $indexesByTag[$normalizedTag][] = $detailIndex;
            }
        }

        foreach ($indexesByTag as $normalizedTag => $indexes) {
            if (count($indexes) < 2) {
                continue;
            }

            foreach ($indexes as $detailIndex) {
                $details[$detailIndex]['duplicateTag'] = true;
                $details[$detailIndex]['duplicateIndexes'] = $indexes;
                $details[$detailIndex]['diagnostics'][] = [
                    'type' => 'duplicate-language-tag',
                    'tag' => $normalizedTag,
                    'indexes' => $indexes,
                    'message' => 'EPUB OPF metadata declares the same language tag more than once',
                ];
            }
        }

        return $details;
    }

    /**
     * @return array{
     *     normalizedTag:?string,
     *     primarySubtag:?string,
     *     scriptSubtag:?string,
     *     regionSubtag:?string,
     *     variantSubtags:list<string>,
     *     wellFormed:bool,
     *     diagnostics:list<array<string, mixed>>
     * }
     */
    private static function metadataLanguageTagReport(string $tag): array
    {
        $tag = trim($tag);
        $normalizedTag = $tag === '' ? null : strtolower($tag);

        if ($tag === '' || preg_match('/^[A-Za-z]{2,8}(?:-[A-Za-z0-9]{1,8})*$/', $tag) !== 1) {
            return [
                'normalizedTag' => $normalizedTag,
                'primarySubtag' => null,
                'scriptSubtag' => null,
                'regionSubtag' => null,
                'variantSubtags' => [],
                'wellFormed' => false,
                'diagnostics' => [[
                    'type' => 'invalid-language-tag',
                    'tag' => $tag,
                    'message' => 'EPUB OPF dc:language metadata should use a bounded BCP47-style language tag',
                ]],
            ];
        }

        $parts = explode('-', $tag);
        $primary = strtolower((string) array_shift($parts));
        $script = null;
        $region = null;
        $variants = [];

        foreach ($parts as $part) {
            if ($script === null && preg_match('/^[A-Za-z]{4}$/', $part) === 1) {
                $script = ucfirst(strtolower($part));
                continue;
            }

            if ($region === null && (preg_match('/^[A-Za-z]{2}$/', $part) === 1 || preg_match('/^[0-9]{3}$/', $part) === 1)) {
                $region = strtoupper($part);
                continue;
            }

            $variants[] = strtolower($part);
        }

        return [
            'normalizedTag' => $normalizedTag,
            'primarySubtag' => $primary,
            'scriptSubtag' => $script,
            'regionSubtag' => $region,
            'variantSubtags' => $variants,
            'wellFormed' => true,
            'diagnostics' => [],
        ];
    }

    /**
     * @param list<array<string, mixed>> $details
     *
     * @return array<string, list<array<string, mixed>>>
     */
    private static function metadataLanguageDetailsByPrimarySubtag(array $details): array
    {
        $byPrimarySubtag = [];
        foreach ($details as $detail) {
            if (!is_array($detail) || ($detail['wellFormed'] ?? false) !== true) {
                continue;
            }

            $primarySubtag = is_string($detail['primarySubtag'] ?? null) ? $detail['primarySubtag'] : '';
            if ($primarySubtag === '') {
                continue;
            }

            $byPrimarySubtag[$primarySubtag][] = $detail;
        }

        return $byPrimarySubtag;
    }

    /**
     * @param list<array<string, mixed>> $details
     *
     * @return array<string, mixed>
     */
    private static function metadataLanguageSummary(array $details): array
    {
        $normalizedTags = [];
        $primarySubtags = [];
        $regionSubtags = [];
        $duplicateTags = [];
        $invalidTagCount = 0;
        $diagnostics = [];

        foreach ($details as $detail) {
            if (!is_array($detail)) {
                continue;
            }

            $wellFormed = ($detail['wellFormed'] ?? false) === true;
            $normalizedTag = is_string($detail['normalizedTag'] ?? null) ? $detail['normalizedTag'] : '';
            if ($wellFormed && $normalizedTag !== '') {
                $normalizedTags[$normalizedTag] = $normalizedTag;
            }

            $primarySubtag = is_string($detail['primarySubtag'] ?? null) ? $detail['primarySubtag'] : '';
            if ($wellFormed && $primarySubtag !== '') {
                $primarySubtags[$primarySubtag] = $primarySubtag;
            }

            $regionSubtag = is_string($detail['regionSubtag'] ?? null) ? $detail['regionSubtag'] : '';
            if ($wellFormed && $regionSubtag !== '') {
                $regionSubtags[$regionSubtag] = $regionSubtag;
            }

            if (($detail['duplicateTag'] ?? false) === true && $normalizedTag !== '') {
                $duplicateTags[$normalizedTag] = $normalizedTag;
            }
            if (!$wellFormed) {
                ++$invalidTagCount;
            }

            foreach (($detail['diagnostics'] ?? []) as $diagnostic) {
                if (is_array($diagnostic)) {
                    $diagnostics[] = [
                        'index' => is_int($detail['index'] ?? null) ? $detail['index'] : null,
                        'id' => is_string($detail['id'] ?? null) ? $detail['id'] : null,
                    ] + $diagnostic;
                }
            }
        }

        return [
            'present' => $details !== [],
            'count' => count($details),
            'primaryLanguage' => is_string($details[0]['tag'] ?? null) ? $details[0]['tag'] : null,
            'uniqueTagCount' => count($normalizedTags),
            'normalizedTags' => array_values($normalizedTags),
            'primarySubtagCount' => count($primarySubtags),
            'primarySubtags' => array_values($primarySubtags),
            'regionSubtagCount' => count($regionSubtags),
            'regionSubtags' => array_values($regionSubtags),
            'duplicateTagCount' => count($duplicateTags),
            'duplicateTags' => array_values($duplicateTags),
            'invalidTagCount' => $invalidTagCount,
            'diagnostics' => $diagnostics,
        ];
    }

    /**
     * @param list<array<string, mixed>> $entries
     *
     * @return list<array<string, mixed>>
     */
    private static function metadataSourceDetails(array $entries): array
    {
        $details = [];
        foreach ($entries as $index => $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $refinements = is_array($entry['refinements'] ?? null) ? $entry['refinements'] : [];
            $sourceOfEntries = self::metadataRefinementEntries($refinements, 'source-of');
            $sourceOfValues = array_values(array_filter(
                array_map(static fn (array $sourceOf): string => (string) ($sourceOf['value'] ?? ''), $sourceOfEntries),
                static fn (string $value): bool => $value !== '',
            ));
            $identifierTypes = self::metadataRefinementEntries($refinements, 'identifier-type');
            $identifierType = $identifierTypes[0] ?? null;

            $details[] = [
                'kind' => 'source',
                'index' => (int) $index,
                'text' => (string) ($entry['text'] ?? ''),
                'id' => is_string($entry['id'] ?? null) ? $entry['id'] : null,
                'scheme' => is_string($entry['scheme'] ?? null) ? $entry['scheme'] : null,
                'language' => is_string($entry['language'] ?? null) ? $entry['language'] : null,
                'direction' => is_string($entry['direction'] ?? null) ? $entry['direction'] : null,
                'sourceOf' => $sourceOfValues[0] ?? null,
                'sourceOfValues' => $sourceOfValues,
                'sourceOfEntries' => $sourceOfEntries,
                'displaySeq' => self::firstMetadataRefinementValue($refinements, 'display-seq'),
                'identifierTypes' => $identifierTypes,
                'identifierType' => is_array($identifierType) ? (string) $identifierType['value'] : null,
                'identifierTypeScheme' => is_array($identifierType) && is_string($identifierType['scheme'] ?? null)
                    ? $identifierType['scheme']
                    : null,
                'linkedResources' => [],
                'refinements' => $refinements,
            ];
        }

        return $details;
    }

    /**
     * @param list<array<string, mixed>> $details
     *
     * @return array<string, list<array<string, mixed>>>
     */
    private static function metadataSourceDetailsBySourceOf(array $details): array
    {
        $bySourceOf = [];
        foreach ($details as $detail) {
            if (!is_array($detail)) {
                continue;
            }

            $values = is_array($detail['sourceOfValues'] ?? null) ? $detail['sourceOfValues'] : [];
            foreach ($values as $value) {
                if (!is_string($value) || $value === '') {
                    continue;
                }

                $bySourceOf[$value][] = $detail;
            }
        }

        return $bySourceOf;
    }

    /**
     * @param list<array<string, mixed>> $details
     *
     * @return array{present:bool, count:int, sourceOfCount:int, sourceOfValues:list<string>, identifierTypeCount:int, identifierTypes:list<string>, linkedResourceCount:int, diagnostics:list<array<string, mixed>>}
     */
    private static function metadataSourceSummary(array $details): array
    {
        $sourceOfValues = [];
        $identifierTypes = [];
        $linkedResourceCount = 0;

        foreach ($details as $detail) {
            if (!is_array($detail)) {
                continue;
            }

            foreach ((is_array($detail['sourceOfValues'] ?? null) ? $detail['sourceOfValues'] : []) as $sourceOf) {
                if (is_string($sourceOf) && $sourceOf !== '') {
                    $sourceOfValues[$sourceOf] = $sourceOf;
                }
            }

            $identifierType = is_string($detail['identifierType'] ?? null) ? trim($detail['identifierType']) : '';
            if ($identifierType !== '') {
                $identifierTypes[$identifierType] = $identifierType;
            }

            $linkedResources = is_array($detail['linkedResources'] ?? null) ? $detail['linkedResources'] : [];
            $linkedResourceCount += count($linkedResources);
        }

        return [
            'present' => $details !== [],
            'count' => count($details),
            'sourceOfCount' => count($sourceOfValues),
            'sourceOfValues' => array_values($sourceOfValues),
            'identifierTypeCount' => count($identifierTypes),
            'identifierTypes' => array_values($identifierTypes),
            'linkedResourceCount' => $linkedResourceCount,
            'diagnostics' => [],
        ];
    }

    /**
     * @param array<string, list<array<string, mixed>>> $dc
     *
     * @return list<array<string, mixed>>
     */
    private static function metadataBibliographicDetails(array $dc): array
    {
        $kinds = [
            'description',
            'publisher',
            'rights',
            'type',
            'format',
            'relation',
            'coverage',
        ];
        $details = [];

        foreach ($kinds as $kind) {
            foreach (($dc[$kind] ?? []) as $index => $entry) {
                if (!is_array($entry)) {
                    continue;
                }

                $refinements = is_array($entry['refinements'] ?? null) ? $entry['refinements'] : [];
                $authorityEntries = self::metadataRefinementEntries($refinements, 'authority');
                $termEntries = self::metadataRefinementEntries($refinements, 'term');

                $details[] = [
                    'kind' => $kind,
                    'index' => (int) $index,
                    'text' => (string) ($entry['text'] ?? ''),
                    'id' => is_string($entry['id'] ?? null) ? $entry['id'] : null,
                    'scheme' => is_string($entry['scheme'] ?? null) ? $entry['scheme'] : null,
                    'language' => is_string($entry['language'] ?? null) ? $entry['language'] : null,
                    'direction' => is_string($entry['direction'] ?? null) ? $entry['direction'] : null,
                    'displaySeq' => self::firstMetadataRefinementValue($refinements, 'display-seq'),
                    'fileAs' => self::firstMetadataRefinementValue($refinements, 'file-as'),
                    'alternateScripts' => self::metadataRefinementEntries($refinements, 'alternate-script'),
                    'authority' => is_array($authorityEntries[0] ?? null) ? (string) $authorityEntries[0]['value'] : null,
                    'authorityEntries' => $authorityEntries,
                    'term' => is_array($termEntries[0] ?? null) ? (string) $termEntries[0]['value'] : null,
                    'termEntries' => $termEntries,
                    'linkedResources' => [],
                    'refinements' => $refinements,
                ];
            }
        }

        return $details;
    }

    /**
     * @param list<array<string, mixed>> $details
     *
     * @return array<string, list<array<string, mixed>>>
     */
    private static function metadataBibliographicDetailsByKind(array $details): array
    {
        $byKind = [];
        foreach ($details as $detail) {
            if (!is_array($detail)) {
                continue;
            }

            $kind = is_string($detail['kind'] ?? null) ? $detail['kind'] : '';
            if ($kind === '') {
                continue;
            }

            $byKind[$kind][] = $detail;
        }

        return $byKind;
    }

    /**
     * @param list<array<string, mixed>> $details
     *
     * @return array{present:bool, count:int, kindCount:int, kinds:list<string>, kindCounts:array<string, int>, authorityCount:int, termCount:int, linkedResourceCount:int, diagnostics:list<array<string, mixed>>}
     */
    private static function metadataBibliographicSummary(array $details): array
    {
        $kindCounts = [];
        $authorityCount = 0;
        $termCount = 0;
        $linkedResourceCount = 0;

        foreach ($details as $detail) {
            if (!is_array($detail)) {
                continue;
            }

            $kind = is_string($detail['kind'] ?? null) ? $detail['kind'] : '';
            if ($kind !== '') {
                $kindCounts[$kind] = ($kindCounts[$kind] ?? 0) + 1;
            }

            if (is_string($detail['authority'] ?? null) && $detail['authority'] !== '') {
                ++$authorityCount;
            }
            if (is_string($detail['term'] ?? null) && $detail['term'] !== '') {
                ++$termCount;
            }

            $linkedResources = is_array($detail['linkedResources'] ?? null) ? $detail['linkedResources'] : [];
            $linkedResourceCount += count($linkedResources);
        }

        return [
            'present' => $details !== [],
            'count' => count($details),
            'kindCount' => count($kindCounts),
            'kinds' => array_keys($kindCounts),
            'kindCounts' => $kindCounts,
            'authorityCount' => $authorityCount,
            'termCount' => $termCount,
            'linkedResourceCount' => $linkedResourceCount,
            'diagnostics' => [],
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
                    'propertyVocabulary' => is_array($entry['propertyVocabulary'] ?? null) ? $entry['propertyVocabulary'] : null,
                    'subjectId' => $subject,
                    'refines' => (string) ($entry['refines'] ?? ''),
                    'text' => (string) ($entry['text'] ?? ''),
                    'content' => is_string($entry['content'] ?? null) ? $entry['content'] : null,
                    'scheme' => is_string($entry['scheme'] ?? null) ? $entry['scheme'] : null,
                    'id' => is_string($entry['id'] ?? null) ? $entry['id'] : null,
                    'language' => is_string($entry['language'] ?? null) ? $entry['language'] : null,
                    'direction' => is_string($entry['direction'] ?? null) ? $entry['direction'] : null,
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

    /**
     * @param array<string, mixed> $metadata
     * @param array<string, array<string, mixed>> $manifestById
     * @param array<string, mixed> $spineProperties
     * @param list<array<string, mixed>> $spine
     * @param list<array<string, mixed>> $collections
     *
     * @return array<string, mixed>
     */
    private static function metadataRefinementSubjectSummary(
        array $metadata,
        ?string $packageId,
        array $manifestById,
        array $spineProperties,
        array $spine,
        array $collections
    ): array
    {
        $refinementsById = is_array($metadata['refinementsById'] ?? null) ? $metadata['refinementsById'] : [];
        $knownSubjects = self::knownOpfSubjectIndex($metadata, $packageId, $manifestById, $spineProperties, $spine, $collections);
        $subjectsById = [];
        $subjects = [];
        $diagnostics = [];
        $refinementCount = 0;
        $knownSubjectCount = 0;
        $unknownSubjectCount = 0;

        foreach ($refinementsById as $subjectId => $properties) {
            if (!is_string($subjectId) || $subjectId === '' || !is_array($properties)) {
                continue;
            }

            $subjectRefinementCount = 0;
            $propertyNames = [];
            foreach ($properties as $property => $entries) {
                if (!is_array($entries)) {
                    continue;
                }

                $propertyNames[] = (string) $property;
                $subjectRefinementCount += count($entries);
            }
            $propertyNames = array_values(array_unique($propertyNames));
            $refinementCount += $subjectRefinementCount;
            $known = isset($knownSubjects[$subjectId]);
            $knownSubject = $known ? $knownSubjects[$subjectId] : null;
            $subjectDiagnostics = [];

            if ($known) {
                ++$knownSubjectCount;
            } else {
                ++$unknownSubjectCount;
                $subjectDiagnostics[] = [
                    'type' => 'unknown-metadata-refinement-subject',
                    'subjectId' => $subjectId,
                    'properties' => $propertyNames,
                    'refinementCount' => $subjectRefinementCount,
                    'message' => 'EPUB OPF metadata refinement references an id that is not present in the package, metadata, manifest, spine, or collection subjects',
                ];
            }

            foreach ($subjectDiagnostics as $diagnostic) {
                $diagnostics[] = $diagnostic;
            }

            $subjects[] = $subjectId;
            $subjectsById[$subjectId] = [
                'id' => $subjectId,
                'known' => $known,
                'kind' => is_array($knownSubject) ? $knownSubject['kind'] : null,
                'source' => is_array($knownSubject) ? $knownSubject['source'] : null,
                'properties' => $propertyNames,
                'refinementCount' => $subjectRefinementCount,
                'diagnostics' => $subjectDiagnostics,
            ];
        }

        return [
            'present' => $subjects !== [],
            'subjectCount' => count($subjects),
            'refinementCount' => $refinementCount,
            'knownSubjectCount' => $knownSubjectCount,
            'unknownSubjectCount' => $unknownSubjectCount,
            'subjects' => $subjects,
            'knownSubjects' => array_values(array_filter(
                $subjects,
                static fn (string $subjectId): bool => isset($knownSubjects[$subjectId]),
            )),
            'unknownSubjects' => array_values(array_filter(
                $subjects,
                static fn (string $subjectId): bool => !isset($knownSubjects[$subjectId]),
            )),
            'subjectsById' => $subjectsById,
            'knownSubjectIndex' => $knownSubjects,
            'diagnostics' => $diagnostics,
        ];
    }

    /**
     * @param array<string, mixed> $metadata
     * @param array<string, array<string, mixed>> $manifestById
     * @param array<string, mixed> $spineProperties
     * @param list<array<string, mixed>> $spine
     * @param list<array<string, mixed>> $collections
     *
     * @return array<string, array{id:string, kind:string, source:string}>
     */
    private static function knownOpfSubjectIndex(
        array $metadata,
        ?string $packageId,
        array $manifestById,
        array $spineProperties,
        array $spine,
        array $collections
    ): array
    {
        $subjects = [];
        $add = static function (?string $id, string $kind, string $source) use (&$subjects): void {
            if ($id === null || $id === '') {
                return;
            }

            if (!isset($subjects[$id])) {
                $subjects[$id] = [
                    'id' => $id,
                    'kind' => $kind,
                    'source' => $source,
                ];
            }
        };

        foreach (($metadata['raw'] ?? []) as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $id = is_string($entry['id'] ?? null) ? $entry['id'] : null;
            if ($id === null || $id === '') {
                continue;
            }

            $add($id, 'metadata', 'opf-metadata-' . (string) ($entry['type'] ?? 'item'));
        }

        $add($packageId, 'package', 'opf-package');

        foreach ($manifestById as $id => $_item) {
            $add((string) $id, 'manifest', 'opf-manifest');
        }

        $add(is_string($spineProperties['id'] ?? null) ? $spineProperties['id'] : null, 'spine', 'opf-spine');

        foreach ($spine as $item) {
            if (!is_array($item)) {
                continue;
            }

            $add(is_string($item['id'] ?? null) ? $item['id'] : null, 'spine-item', 'opf-spine-itemref');
        }

        self::addCollectionSubjectIds($subjects, $collections);

        return $subjects;
    }

    /**
     * @param array<string, array{id:string, kind:string, source:string}> $subjects
     * @param list<array<string, mixed>> $collections
     */
    private static function addCollectionSubjectIds(array &$subjects, array $collections): void
    {
        foreach ($collections as $collection) {
            if (!is_array($collection)) {
                continue;
            }

            $id = is_string($collection['id'] ?? null) ? $collection['id'] : null;
            if ($id !== null && $id !== '' && !isset($subjects[$id])) {
                $subjects[$id] = [
                    'id' => $id,
                    'kind' => 'collection',
                    'source' => 'opf-collection',
                ];
            }

            if (is_array($collection['children'] ?? null)) {
                self::addCollectionSubjectIds($subjects, $collection['children']);
            }
        }
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
     * @param list<array<string, mixed>> $entries
     *
     * @return list<array<string, mixed>>
     */
    private static function metadataTitleDetails(array $entries): array
    {
        $details = [];
        foreach ($entries as $index => $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $refinements = is_array($entry['refinements'] ?? null) ? $entry['refinements'] : [];

            $details[] = [
                'kind' => 'title',
                'index' => (int) $index,
                'text' => (string) ($entry['text'] ?? ''),
                'id' => is_string($entry['id'] ?? null) ? $entry['id'] : null,
                'language' => is_string($entry['language'] ?? null) ? $entry['language'] : null,
                'direction' => is_string($entry['direction'] ?? null) ? $entry['direction'] : null,
                'titleType' => self::firstMetadataRefinementValue($refinements, 'title-type'),
                'fileAs' => self::firstMetadataRefinementValue($refinements, 'file-as'),
                'displaySeq' => self::firstMetadataRefinementValue($refinements, 'display-seq'),
                'alternateScripts' => self::metadataRefinementEntries($refinements, 'alternate-script'),
                'refinements' => $refinements,
            ];
        }

        return $details;
    }

    /**
     * @param list<array<string, mixed>> $details
     *
     * @return array<string, list<array<string, mixed>>>
     */
    private static function metadataTitlesByType(array $details): array
    {
        $byType = [];
        foreach ($details as $detail) {
            $titleType = $detail['titleType'] ?? null;
            if (!is_string($titleType) || $titleType === '') {
                continue;
            }

            $byType[$titleType][] = $detail;
        }

        return $byType;
    }

    /**
     * @param list<array<string, mixed>> $details
     *
     * @return array<string, mixed>|null
     */
    private static function firstMetadataTitleByType(array $details, string $type): ?array
    {
        foreach ($details as $detail) {
            if (($detail['titleType'] ?? null) === $type) {
                return $detail;
            }
        }

        return null;
    }

    /**
     * @param list<array<string, mixed>> $entries
     *
     * @return list<array<string, mixed>>
     */
    private static function metadataAgentDetails(array $entries, string $kind): array
    {
        $details = [];
        foreach ($entries as $index => $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $refinements = is_array($entry['refinements'] ?? null) ? $entry['refinements'] : [];
            $roles = self::metadataRefinementEntries($refinements, 'role');
            $roleValues = array_map(static fn (array $role): string => (string) $role['value'], $roles);

            $details[] = [
                'kind' => $kind,
                'index' => (int) $index,
                'text' => (string) ($entry['text'] ?? ''),
                'id' => is_string($entry['id'] ?? null) ? $entry['id'] : null,
                'scheme' => is_string($entry['scheme'] ?? null) ? $entry['scheme'] : null,
                'language' => is_string($entry['language'] ?? null) ? $entry['language'] : null,
                'direction' => is_string($entry['direction'] ?? null) ? $entry['direction'] : null,
                'fileAs' => self::firstMetadataRefinementValue($refinements, 'file-as'),
                'displaySeq' => self::firstMetadataRefinementValue($refinements, 'display-seq'),
                'roles' => $roles,
                'roleValues' => $roleValues,
                'primaryRole' => $roleValues[0] ?? null,
                'alternateScripts' => self::metadataRefinementEntries($refinements, 'alternate-script'),
                'refinements' => $refinements,
            ];
        }

        return $details;
    }

    /**
     * @param array<string, list<array<string, mixed>>> $refinements
     *
     * @return list<array<string, mixed>>
     */
    private static function metadataRefinementEntries(array $refinements, string $property): array
    {
        $items = [];
        foreach ($refinements[$property] ?? [] as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $value = self::metadataRefinementValue($entry);
            if ($value === '') {
                continue;
            }

            $items[] = [
                'property' => is_string($entry['property'] ?? null) ? $entry['property'] : $property,
                'value' => $value,
                'text' => $value,
                'content' => is_string($entry['content'] ?? null) ? $entry['content'] : null,
                'scheme' => is_string($entry['scheme'] ?? null) ? $entry['scheme'] : null,
                'id' => is_string($entry['id'] ?? null) ? $entry['id'] : null,
                'refines' => is_string($entry['refines'] ?? null) ? $entry['refines'] : null,
                'subjectId' => is_string($entry['subjectId'] ?? null) ? $entry['subjectId'] : null,
                'language' => is_string($entry['language'] ?? null) ? $entry['language'] : null,
                'direction' => is_string($entry['direction'] ?? null) ? $entry['direction'] : null,
            ];
        }

        return $items;
    }

    /**
     * @param array<string, list<array<string, mixed>>> $refinements
     */
    private static function firstMetadataRefinementValue(array $refinements, string $property): ?string
    {
        foreach (self::metadataRefinementEntries($refinements, $property) as $entry) {
            return (string) $entry['value'];
        }

        return null;
    }

    /**
     * @param array<string, mixed> $entry
     */
    private static function metadataRefinementValue(array $entry): string
    {
        $text = trim((string) ($entry['text'] ?? ''));
        if ($text !== '') {
            return $text;
        }

        return trim((string) ($entry['content'] ?? ''));
    }

    /**
     * @param list<array<string, mixed>> $details
     *
     * @return array<string, list<array<string, mixed>>>
     */
    private static function metadataAgentsByRole(array $details): array
    {
        $byRole = [];
        foreach ($details as $detail) {
            foreach ($detail['roleValues'] ?? [] as $role) {
                if (!is_string($role) || $role === '') {
                    continue;
                }

                $byRole[$role][] = $detail;
            }
        }

        return $byRole;
    }

    /**
     * @param list<array<string, mixed>> $details
     *
     * @return list<array<string, mixed>>
     */
    private static function metadataAgentsWithoutRoles(array $details): array
    {
        return array_values(array_filter(
            $details,
            static fn (array $detail): bool => ($detail['roleValues'] ?? []) === [],
        ));
    }

    /**
     * @param list<array<string, mixed>> $creatorDetails
     * @param list<array<string, mixed>> $contributorDetails
     *
     * @return array<string, mixed>
     */
    private static function metadataAgentDisplayOrderReport(array $creatorDetails, array $contributorDetails): array
    {
        $items = [];
        $diagnostics = [];
        $roles = [];
        $sequencedCount = 0;
        $unsequencedCount = 0;
        $invalidDisplaySeqCount = 0;

        foreach ([
            'creator' => $creatorDetails,
            'contributor' => $contributorDetails,
        ] as $kind => $details) {
            foreach ($details as $detail) {
                if (!is_array($detail)) {
                    continue;
                }

                $displaySeq = is_string($detail['displaySeq'] ?? null) && trim($detail['displaySeq']) !== ''
                    ? trim($detail['displaySeq'])
                    : null;
                $displaySeqNumber = self::metadataDisplaySeqNumber($displaySeq);
                $displaySeqValid = $displaySeq === null || $displaySeqNumber !== null;
                $itemDiagnostics = [];
                if ($displaySeq !== null && !$displaySeqValid) {
                    $itemDiagnostics[] = [
                        'type' => 'invalid-agent-display-seq',
                        'kind' => $kind,
                        'id' => is_string($detail['id'] ?? null) ? $detail['id'] : null,
                        'text' => (string) ($detail['text'] ?? ''),
                        'displaySeq' => $displaySeq,
                        'message' => 'EPUB OPF creator/contributor display-seq metadata must be a positive integer for ordered handoff',
                    ];
                    ++$invalidDisplaySeqCount;
                }

                if ($displaySeqNumber !== null) {
                    ++$sequencedCount;
                } elseif ($displaySeq === null) {
                    ++$unsequencedCount;
                }

                $roleValues = is_array($detail['roleValues'] ?? null) ? array_values($detail['roleValues']) : [];
                foreach ($roleValues as $role) {
                    if (!is_string($role) || $role === '') {
                        continue;
                    }

                    $roles[$role] = $role;
                }

                $sourceIndex = (int) ($detail['index'] ?? 0);
                $item = [
                    'kind' => $kind,
                    'sourceIndex' => $sourceIndex,
                    'id' => is_string($detail['id'] ?? null) ? $detail['id'] : null,
                    'text' => (string) ($detail['text'] ?? ''),
                    'fileAs' => is_string($detail['fileAs'] ?? null) ? $detail['fileAs'] : null,
                    'displaySeq' => $displaySeq,
                    'displaySeqNumber' => $displaySeqNumber,
                    'displaySeqValid' => $displaySeqValid,
                    'sequenced' => $displaySeqNumber !== null,
                    'unsequenced' => $displaySeq === null,
                    'roles' => is_array($detail['roles'] ?? null) ? array_values($detail['roles']) : [],
                    'roleValues' => $roleValues,
                    'primaryRole' => is_string($detail['primaryRole'] ?? null) ? $detail['primaryRole'] : null,
                    'language' => is_string($detail['language'] ?? null) ? $detail['language'] : null,
                    'direction' => is_string($detail['direction'] ?? null) ? $detail['direction'] : null,
                    'alternateScripts' => is_array($detail['alternateScripts'] ?? null) ? array_values($detail['alternateScripts']) : [],
                    'linkedResources' => is_array($detail['linkedResources'] ?? null) ? array_values($detail['linkedResources']) : [],
                    'refinements' => is_array($detail['refinements'] ?? null) ? $detail['refinements'] : [],
                    'diagnostics' => $itemDiagnostics,
                    '_sortBucket' => $displaySeqNumber !== null ? 0 : ($displaySeq !== null ? 1 : 2),
                    '_sortSeq' => $displaySeqNumber ?? PHP_INT_MAX,
                    '_sortKind' => $kind === 'creator' ? 0 : 1,
                    '_sortIndex' => $sourceIndex,
                ];

                foreach ($itemDiagnostics as $diagnostic) {
                    $diagnostics[] = $diagnostic;
                }

                $items[] = $item;
            }
        }

        usort(
            $items,
            static function (array $left, array $right): int {
                return [$left['_sortBucket'], $left['_sortSeq'], $left['_sortKind'], $left['_sortIndex']]
                    <=> [$right['_sortBucket'], $right['_sortSeq'], $right['_sortKind'], $right['_sortIndex']];
            }
        );

        $byKind = [];
        $byRole = [];
        foreach ($items as $index => $item) {
            unset($item['_sortBucket'], $item['_sortSeq'], $item['_sortKind'], $item['_sortIndex']);
            $item['displayIndex'] = $index;
            $items[$index] = $item;

            $kind = is_string($item['kind'] ?? null) ? $item['kind'] : '';
            if ($kind !== '') {
                $byKind[$kind][] = $item;
            }

            foreach (($item['roleValues'] ?? []) as $role) {
                if (!is_string($role) || $role === '') {
                    continue;
                }

                $byRole[$role][] = $item;
            }
        }

        return [
            'present' => $items !== [],
            'count' => count($items),
            'sequencedCount' => $sequencedCount,
            'unsequencedCount' => $unsequencedCount,
            'invalidDisplaySeqCount' => $invalidDisplaySeqCount,
            'roleCount' => count($roles),
            'roles' => array_values($roles),
            'items' => $items,
            'byKind' => $byKind,
            'byRole' => $byRole,
            'diagnosticCount' => count($diagnostics),
            'diagnostics' => $diagnostics,
        ];
    }

    private static function metadataDisplaySeqNumber(?string $displaySeq): ?int
    {
        if ($displaySeq === null || $displaySeq === '') {
            return null;
        }

        if (preg_match('/^[1-9][0-9]*$/', $displaySeq) !== 1) {
            return null;
        }

        return (int) $displaySeq;
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
            $mediaTypeFields = self::metadataLinkMediaTypeFields(
                is_string($declaredMediaType) ? $declaredMediaType : null,
                is_string($reference['mediaType'] ?? null) ? $reference['mediaType'] : null,
                $index,
                is_string($link['id'] ?? null) ? $link['id'] : null
            );
            $links[] = [
                'index' => $index,
                'id' => is_string($link['id'] ?? null) ? $link['id'] : null,
                'rel' => is_array($link['rel'] ?? null) ? array_values($link['rel']) : [],
                'href' => is_string($href) && $href !== '' ? $href : null,
                'target' => $reference['target'],
                'part' => $reference['part'],
                'fragment' => $reference['fragment'],
                'fragmentKind' => $reference['fragmentKind'],
                'epubCfi' => $reference['epubCfi'],
                'mediaFragment' => $reference['mediaFragment'],
                'external' => $reference['external'],
                'exists' => $reference['exists'],
                'byteLength' => $reference['byteLength'],
                'compressedByteLength' => $reference['compressedByteLength'],
                'compressionMethod' => $reference['compressionMethod'],
                'compressionMethodName' => $reference['compressionMethodName'],
                'compressionSupported' => $reference['compressionSupported'],
                'crc32' => $reference['crc32'],
                'byteSha256' => $byteSha256,
                'mediaType' => is_string($declaredMediaType) && $declaredMediaType !== ''
                    ? $declaredMediaType
                    : $reference['mediaType'],
                'declaredMediaType' => $mediaTypeFields['declaredMediaType'],
                'effectiveMediaType' => $mediaTypeFields['effectiveMediaType'],
                'mediaTypeSource' => $mediaTypeFields['mediaTypeSource'],
                'normalizedMediaType' => $mediaTypeFields['normalizedMediaType'],
                'baseMediaType' => $mediaTypeFields['baseMediaType'],
                'mediaTypeParameters' => $mediaTypeFields['mediaTypeParameters'],
                'mediaTypeParameterNames' => $mediaTypeFields['mediaTypeParameterNames'],
                'mediaTypeParameterCount' => $mediaTypeFields['mediaTypeParameterCount'],
                'mediaTypeSyntaxValid' => $mediaTypeFields['mediaTypeSyntaxValid'],
                'mediaTypeDiagnostics' => $mediaTypeFields['mediaTypeDiagnostics'],
                'manifestId' => $reference['manifestId'],
                'manifestMediaType' => $reference['mediaType'],
                'properties' => is_array($link['properties'] ?? null) ? array_values($link['properties']) : [],
                'relVocabulary' => is_array($link['relVocabulary'] ?? null) ? $link['relVocabulary'] : self::metadataLinkTokenReport(
                    is_array($link['rel'] ?? null) ? array_values($link['rel']) : [],
                    [],
                    'rel',
                    $index
                ),
                'propertyVocabulary' => is_array($link['propertyVocabulary'] ?? null) ? $link['propertyVocabulary'] : self::metadataLinkTokenReport(
                    is_array($link['properties'] ?? null) ? array_values($link['properties']) : [],
                    [],
                    'properties',
                    $index
                ),
                'refines' => is_string($link['refines'] ?? null) ? $link['refines'] : null,
                'subjectId' => self::metadataRefinementSubject($link['refines'] ?? null),
                'title' => is_string($link['title'] ?? null) ? $link['title'] : null,
                'hreflang' => is_string($link['hreflang'] ?? null) ? $link['hreflang'] : null,
                'language' => is_string($link['language'] ?? null) ? $link['language'] : null,
                'direction' => is_string($link['direction'] ?? null) ? $link['direction'] : null,
                'encrypted' => $reference['encrypted'],
                'canExposeBytes' => $reference['canExposeBytes'],
                'diagnostics' => $diagnostics,
            ];
        }

        $metadata['links'] = $links;
        $metadata['linksByRel'] = self::linksByRel($links);
        $metadata['linkVocabulary'] = self::metadataLinkVocabularySummary($links);
        $metadata['linkMediaTypes'] = self::metadataLinkMediaTypeReport($links);
        $metadata['linkTargetReport'] = self::metadataLinkTargetReport($links);
        $metadata['linksByRefinedId'] = self::metadataLinksByRefinedId($links);
        $metadata['linkedResourcesById'] = $metadata['linksByRefinedId'];
        $metadata['linkedResourceSummary'] = self::metadataLinkedResourceSummary($metadata['linksByRefinedId']);
        $metadata = self::attachMetadataLinkedResources($metadata, $metadata['linksByRefinedId']);
        $metadata['collectionMembership'] = self::metadataCollectionMembershipReport($metadata);
        $metadata['accessibility'] = self::accessibilityMetadataReport($metadata);

        return $metadata;
    }

    /**
     * @param list<array<string, mixed>> $links
     *
     * @return array<string, list<array<string, mixed>>>
     */
    private static function metadataLinksByRefinedId(array $links): array
    {
        $byId = [];
        foreach ($links as $link) {
            if (!is_array($link)) {
                continue;
            }

            $subjectId = is_string($link['subjectId'] ?? null)
                ? (string) $link['subjectId']
                : self::metadataRefinementSubject($link['refines'] ?? null);
            if ($subjectId === null) {
                continue;
            }

            $link['subjectId'] = $subjectId;
            $byId[$subjectId][] = $link;
        }

        return $byId;
    }

    /**
     * @param list<string> $tokens
     * @param array<string, string> $prefixBindings
     *
     * @return array{raw:list<string>, kind:string, linkIndex:int, count:int, validCount:int, invalidCount:int, resolvedCount:int, absoluteUrlCount:int, duplicateCount:int, items:list<array<string, mixed>>, diagnostics:list<array<string, mixed>>}
     */
    private static function metadataLinkTokenReport(array $tokens, array $prefixBindings, string $kind, int $linkIndex): array
    {
        $prefixBindings = self::metadataVocabularyPrefixBindings($prefixBindings);
        $items = [];
        $diagnostics = [];
        $seen = [];
        $validCount = 0;
        $resolvedCount = 0;
        $absoluteUrlCount = 0;
        $duplicateCount = 0;

        foreach ($tokens as $index => $token) {
            $value = trim((string) $token);
            if ($value === '') {
                continue;
            }

            $diagnosticsForToken = [];
            $prefix = null;
            $localName = null;
            $iri = null;
            $resolved = false;
            $absoluteUrlWithFragment = self::isAbsoluteUrlWithFragment($value);
            $looksAbsolute = self::isExternalReference($value);
            $tokenKind = 'nmtoken';
            $valid = true;

            if (preg_match('/^([A-Za-z_][A-Za-z0-9_.-]*):([A-Za-z_][A-Za-z0-9_.-]*)$/', $value, $matches) === 1) {
                $tokenKind = 'prefixed-nmtoken';
                $prefix = $matches[1];
                $localName = $matches[2];
                if (isset($prefixBindings[$prefix])) {
                    $resolved = true;
                    $iri = $prefixBindings[$prefix] . $localName;
                    ++$resolvedCount;
                } else {
                    $diagnosticsForToken[] = [
                        'type' => 'unknown-metadata-link-' . $kind . '-prefix',
                        'kind' => $kind,
                        'linkIndex' => $linkIndex,
                        'index' => (int) $index,
                        'value' => $value,
                        'prefix' => $prefix,
                        'message' => 'EPUB OPF metadata link vocabulary token uses a prefix that is not declared on the package element',
                    ];
                }
            } elseif ($absoluteUrlWithFragment) {
                $tokenKind = 'absolute-url-with-fragment';
                $iri = $value;
                ++$absoluteUrlCount;
            } elseif ($looksAbsolute) {
                $tokenKind = 'absolute-url';
                $valid = false;
                $diagnosticsForToken[] = [
                    'type' => 'invalid-metadata-link-' . $kind . '-url-fragment',
                    'kind' => $kind,
                    'linkIndex' => $linkIndex,
                    'index' => (int) $index,
                    'value' => $value,
                    'message' => 'EPUB OPF metadata link vocabulary URLs must include a fragment identifier',
                ];
            } elseif (preg_match('/^[A-Za-z_][A-Za-z0-9_.-]*$/', $value) !== 1) {
                $tokenKind = 'invalid';
                $valid = false;
                $diagnosticsForToken[] = [
                    'type' => 'invalid-metadata-link-' . $kind . '-token',
                    'kind' => $kind,
                    'linkIndex' => $linkIndex,
                    'index' => (int) $index,
                    'value' => $value,
                    'message' => 'EPUB OPF metadata link vocabulary values must be NMTOKENs, prefixed names, or absolute URLs with fragments',
                ];
            }

            if (isset($seen[$value])) {
                ++$duplicateCount;
                $diagnosticsForToken[] = [
                    'type' => 'duplicate-metadata-link-' . $kind . '-token',
                    'kind' => $kind,
                    'linkIndex' => $linkIndex,
                    'index' => (int) $index,
                    'previousIndex' => $seen[$value],
                    'value' => $value,
                    'message' => 'EPUB OPF metadata link vocabulary value is repeated',
                ];
            } else {
                $seen[$value] = (int) $index;
            }

            if ($valid) {
                ++$validCount;
            }

            $item = [
                'index' => (int) $index,
                'value' => $value,
                'kind' => $tokenKind,
                'valid' => $valid,
                'prefix' => $prefix,
                'localName' => $localName,
                'iri' => $iri,
                'resolved' => $resolved,
                'absoluteUrlWithFragment' => $absoluteUrlWithFragment,
                'diagnostics' => $diagnosticsForToken,
            ];

            foreach ($diagnosticsForToken as $diagnostic) {
                $diagnostics[] = $diagnostic;
            }
            $items[] = $item;
        }

        return [
            'raw' => array_values($tokens),
            'kind' => $kind,
            'linkIndex' => $linkIndex,
            'count' => count($items),
            'validCount' => $validCount,
            'invalidCount' => count($items) - $validCount,
            'resolvedCount' => $resolvedCount,
            'absoluteUrlCount' => $absoluteUrlCount,
            'duplicateCount' => $duplicateCount,
            'items' => $items,
            'diagnostics' => $diagnostics,
        ];
    }

    /**
     * @param list<array<string, mixed>> $links
     *
     * @return array{present:bool, linkCount:int, relTokenCount:int, propertyTokenCount:int, resolvedTokenCount:int, absoluteUrlTokenCount:int, duplicateTokenCount:int, diagnosticCount:int, rels:array<string, int>, properties:array<string, int>, diagnostics:list<array<string, mixed>>}
     */
    private static function metadataLinkVocabularySummary(array $links): array
    {
        $rels = [];
        $properties = [];
        $diagnostics = [];
        $relTokenCount = 0;
        $propertyTokenCount = 0;
        $resolvedTokenCount = 0;
        $absoluteUrlTokenCount = 0;
        $duplicateTokenCount = 0;

        foreach ($links as $link) {
            if (!is_array($link)) {
                continue;
            }

            foreach (['rel' => 'relVocabulary', 'properties' => 'propertyVocabulary'] as $tokenField => $reportField) {
                $tokens = is_array($link[$tokenField] ?? null) ? array_values($link[$tokenField]) : [];
                foreach ($tokens as $token) {
                    if (!is_string($token) || $token === '') {
                        continue;
                    }
                    if ($tokenField === 'rel') {
                        $rels[$token] = ($rels[$token] ?? 0) + 1;
                        ++$relTokenCount;
                    } else {
                        $properties[$token] = ($properties[$token] ?? 0) + 1;
                        ++$propertyTokenCount;
                    }
                }

                $report = is_array($link[$reportField] ?? null) ? $link[$reportField] : [];
                $resolvedTokenCount += (int) ($report['resolvedCount'] ?? 0);
                $absoluteUrlTokenCount += (int) ($report['absoluteUrlCount'] ?? 0);
                $duplicateTokenCount += (int) ($report['duplicateCount'] ?? 0);
                foreach (($report['diagnostics'] ?? []) as $diagnostic) {
                    if (is_array($diagnostic)) {
                        $diagnostics[] = $diagnostic;
                    }
                }
            }
        }

        ksort($rels);
        ksort($properties);

        return [
            'present' => $relTokenCount > 0 || $propertyTokenCount > 0,
            'linkCount' => count($links),
            'relTokenCount' => $relTokenCount,
            'propertyTokenCount' => $propertyTokenCount,
            'resolvedTokenCount' => $resolvedTokenCount,
            'absoluteUrlTokenCount' => $absoluteUrlTokenCount,
            'duplicateTokenCount' => $duplicateTokenCount,
            'diagnosticCount' => count($diagnostics),
            'rels' => $rels,
            'properties' => $properties,
            'diagnostics' => $diagnostics,
        ];
    }

    /**
     * @return array{
     *     declaredMediaType:?string,
     *     effectiveMediaType:?string,
     *     mediaTypeSource:string,
     *     normalizedMediaType:?string,
     *     baseMediaType:?string,
     *     mediaTypeParameters:array<string, string>,
     *     mediaTypeParameterNames:list<string>,
     *     mediaTypeParameterCount:int,
     *     mediaTypeSyntaxValid:bool,
     *     mediaTypeDiagnostics:list<array<string, mixed>>
     * }
     */
    private static function metadataLinkMediaTypeFields(
        ?string $declaredMediaType,
        ?string $referenceMediaType,
        int $linkIndex,
        ?string $linkId
    ): array {
        $declared = is_string($declaredMediaType) && trim($declaredMediaType) !== ''
            ? $declaredMediaType
            : null;
        $reference = is_string($referenceMediaType) && trim($referenceMediaType) !== ''
            ? $referenceMediaType
            : null;
        $effective = $declared ?? $reference;
        $source = $declared !== null ? 'declared' : ($reference !== null ? 'manifest' : 'none');

        if ($effective === null) {
            return [
                'declaredMediaType' => $declared,
                'effectiveMediaType' => null,
                'mediaTypeSource' => $source,
                'normalizedMediaType' => null,
                'baseMediaType' => null,
                'mediaTypeParameters' => [],
                'mediaTypeParameterNames' => [],
                'mediaTypeParameterCount' => 0,
                'mediaTypeSyntaxValid' => true,
                'mediaTypeDiagnostics' => [],
            ];
        }

        $parts = self::mediaTypeParts($effective);
        $parameters = is_array($parts['parameters'] ?? null) ? $parts['parameters'] : [];
        $diagnostics = [];
        foreach (is_array($parts['diagnostics'] ?? null) ? $parts['diagnostics'] : [] as $diagnostic) {
            if (!is_array($diagnostic)) {
                continue;
            }

            $diagnostics[] = self::metadataLinkMediaTypeDiagnostic($diagnostic, $linkIndex, $linkId, $source);
        }

        return [
            'declaredMediaType' => $declared,
            'effectiveMediaType' => $effective,
            'mediaTypeSource' => $source,
            'normalizedMediaType' => (string) ($parts['normalized'] ?? ''),
            'baseMediaType' => (string) ($parts['base'] ?? ''),
            'mediaTypeParameters' => $parameters,
            'mediaTypeParameterNames' => array_keys($parameters),
            'mediaTypeParameterCount' => count($parameters),
            'mediaTypeSyntaxValid' => $diagnostics === [],
            'mediaTypeDiagnostics' => $diagnostics,
        ];
    }

    /**
     * @param array<string, mixed> $diagnostic
     *
     * @return array<string, mixed>
     */
    private static function metadataLinkMediaTypeDiagnostic(array $diagnostic, int $linkIndex, ?string $linkId, string $source): array
    {
        $item = $diagnostic;
        $type = is_string($item['type'] ?? null)
            ? str_replace('manifest-media-type', 'metadata-link-media-type', $item['type'])
            : 'invalid-metadata-link-media-type';
        $message = is_string($item['message'] ?? null)
            ? str_replace('EPUB OPF manifest media-type', 'EPUB OPF metadata link media-type', $item['message'])
            : 'EPUB OPF metadata link media-type requires review';

        $item['type'] = $type;
        $item['index'] = $linkIndex;
        $item['id'] = $linkId;
        $item['source'] = $source;
        $item['message'] = $message;

        return $item;
    }

    /**
     * @param list<array<string, mixed>> $links
     *
     * @return array<string, mixed>
     */
    private static function metadataLinkMediaTypeReport(array $links): array
    {
        $items = [];
        $diagnostics = [];
        $baseMediaTypes = [];
        $parameterNames = [];
        $declaredLinkCount = 0;
        $manifestInheritedLinkCount = 0;
        $parameterizedLinkCount = 0;
        $parameterCount = 0;
        $invalidMediaTypeCount = 0;

        foreach ($links as $index => $link) {
            if (!is_array($link)) {
                continue;
            }

            $baseMediaType = is_string($link['baseMediaType'] ?? null) && $link['baseMediaType'] !== ''
                ? $link['baseMediaType']
                : null;
            $parameters = is_array($link['mediaTypeParameters'] ?? null) ? $link['mediaTypeParameters'] : [];
            $linkDiagnostics = is_array($link['mediaTypeDiagnostics'] ?? null)
                ? array_values($link['mediaTypeDiagnostics'])
                : [];
            $source = is_string($link['mediaTypeSource'] ?? null) ? $link['mediaTypeSource'] : 'none';

            if ($source === 'declared') {
                ++$declaredLinkCount;
            } elseif ($source === 'manifest') {
                ++$manifestInheritedLinkCount;
            }

            if ($parameters !== []) {
                ++$parameterizedLinkCount;
                $parameterCount += count($parameters);
                foreach (array_keys($parameters) as $name) {
                    $parameterNames[$name] = true;
                }
            }

            if ($baseMediaType !== null) {
                $baseMediaTypes[$baseMediaType] = ($baseMediaTypes[$baseMediaType] ?? 0) + 1;
            }

            if (($link['mediaTypeSyntaxValid'] ?? true) !== true) {
                ++$invalidMediaTypeCount;
            }

            foreach ($linkDiagnostics as $diagnostic) {
                if (is_array($diagnostic)) {
                    $diagnostics[] = $diagnostic;
                }
            }

            $items[] = [
                'index' => (int) ($link['index'] ?? $index),
                'id' => is_string($link['id'] ?? null) ? $link['id'] : null,
                'href' => is_string($link['href'] ?? null) ? $link['href'] : null,
                'mediaType' => is_string($link['mediaType'] ?? null) ? $link['mediaType'] : null,
                'declaredMediaType' => is_string($link['declaredMediaType'] ?? null) ? $link['declaredMediaType'] : null,
                'effectiveMediaType' => is_string($link['effectiveMediaType'] ?? null) ? $link['effectiveMediaType'] : null,
                'mediaTypeSource' => $source,
                'normalizedMediaType' => is_string($link['normalizedMediaType'] ?? null) ? $link['normalizedMediaType'] : null,
                'baseMediaType' => $baseMediaType,
                'mediaTypeParameters' => $parameters,
                'mediaTypeParameterNames' => array_keys($parameters),
                'mediaTypeParameterCount' => count($parameters),
                'mediaTypeSyntaxValid' => (bool) ($link['mediaTypeSyntaxValid'] ?? true),
                'diagnostics' => $linkDiagnostics,
            ];
        }

        ksort($baseMediaTypes, SORT_STRING);
        $parameterNames = array_keys($parameterNames);
        sort($parameterNames, SORT_STRING);

        return [
            'present' => $items !== [],
            'linkCount' => count($items),
            'declaredLinkCount' => $declaredLinkCount,
            'manifestInheritedLinkCount' => $manifestInheritedLinkCount,
            'parameterizedLinkCount' => $parameterizedLinkCount,
            'parameterCount' => $parameterCount,
            'parameterNames' => $parameterNames,
            'invalidMediaTypeCount' => $invalidMediaTypeCount,
            'baseMediaTypes' => $baseMediaTypes,
            'items' => $items,
            'diagnosticCount' => count($diagnostics),
            'diagnostics' => $diagnostics,
        ];
    }

    /**
     * @param list<array<string, mixed>> $links
     *
     * @return array<string, mixed>
     */
    private static function metadataLinkTargetReport(array $links): array
    {
        $items = [];
        $publicationItems = [];
        $refinedItems = [];
        $itemsByRel = [];
        $rels = [];
        $diagnostics = [];
        $localLinkCount = 0;
        $existingLocalLinkCount = 0;
        $manifestLinkCount = 0;
        $unmanifestedLocalLinkCount = 0;
        $externalLinkCount = 0;
        $missingLinkCount = 0;
        $encryptedLinkCount = 0;
        $byteExposedLinkCount = 0;

        foreach ($links as $index => $link) {
            if (!is_array($link)) {
                continue;
            }

            $item = self::metadataLinkTargetReportItem($link, (int) $index);
            $items[] = $item;
            if ($item['scope'] === 'publication') {
                $publicationItems[] = $item;
            } else {
                $refinedItems[] = $item;
            }

            foreach ($item['rel'] as $rel) {
                if (!is_string($rel) || $rel === '') {
                    continue;
                }
                $rels[$rel] = ($rels[$rel] ?? 0) + 1;
                $itemsByRel[$rel][] = $item;
            }

            $isLocal = ($item['external'] ?? false) !== true && is_string($item['part'] ?? null) && $item['part'] !== '';
            if ($isLocal) {
                ++$localLinkCount;
            }
            if ($isLocal && ($item['exists'] ?? false) === true) {
                ++$existingLocalLinkCount;
            }
            if (is_string($item['manifestId'] ?? null) && $item['manifestId'] !== '') {
                ++$manifestLinkCount;
            }
            if ($isLocal && ($item['exists'] ?? false) === true && !is_string($item['manifestId'] ?? null)) {
                ++$unmanifestedLocalLinkCount;
            }
            if (($item['external'] ?? false) === true) {
                ++$externalLinkCount;
            }
            if (($item['external'] ?? false) !== true && ($item['exists'] ?? false) !== true) {
                ++$missingLinkCount;
            }
            if (($item['encrypted'] ?? false) === true) {
                ++$encryptedLinkCount;
            }
            if (is_string($item['byteSha256'] ?? null) && $item['byteSha256'] !== '') {
                ++$byteExposedLinkCount;
            }

            foreach ($item['diagnostics'] as $diagnostic) {
                if (is_array($diagnostic)) {
                    $diagnostics[] = $diagnostic;
                }
            }
        }

        ksort($rels, SORT_STRING);
        ksort($itemsByRel, SORT_STRING);

        return [
            'present' => $items !== [],
            'linkCount' => count($items),
            'publicationLinkCount' => count($publicationItems),
            'refinedLinkCount' => count($refinedItems),
            'localLinkCount' => $localLinkCount,
            'existingLocalLinkCount' => $existingLocalLinkCount,
            'manifestLinkCount' => $manifestLinkCount,
            'unmanifestedLocalLinkCount' => $unmanifestedLocalLinkCount,
            'externalLinkCount' => $externalLinkCount,
            'missingLinkCount' => $missingLinkCount,
            'encryptedLinkCount' => $encryptedLinkCount,
            'byteExposedLinkCount' => $byteExposedLinkCount,
            'rels' => $rels,
            'items' => $items,
            'publicationItems' => $publicationItems,
            'refinedItems' => $refinedItems,
            'itemsByRel' => $itemsByRel,
            'diagnosticCount' => count($diagnostics),
            'diagnostics' => $diagnostics,
        ];
    }

    /**
     * @param array<string, mixed> $link
     *
     * @return array<string, mixed>
     */
    private static function metadataLinkTargetReportItem(array $link, int $index): array
    {
        $subjectId = is_string($link['subjectId'] ?? null) && $link['subjectId'] !== ''
            ? (string) $link['subjectId']
            : null;
        $scope = $subjectId === null ? 'publication' : 'refined-subject';
        $item = [
            'index' => $index,
            'id' => is_string($link['id'] ?? null) ? $link['id'] : null,
            'scope' => $scope,
            'subjectId' => $subjectId,
            'rel' => is_array($link['rel'] ?? null) ? array_values($link['rel']) : [],
            'href' => is_string($link['href'] ?? null) ? $link['href'] : null,
            'target' => is_string($link['target'] ?? null) ? $link['target'] : null,
            'part' => is_string($link['part'] ?? null) ? $link['part'] : null,
            'fragment' => is_string($link['fragment'] ?? null) ? $link['fragment'] : null,
            'fragmentKind' => is_string($link['fragmentKind'] ?? null) ? $link['fragmentKind'] : null,
            'mediaType' => is_string($link['mediaType'] ?? null) ? $link['mediaType'] : null,
            'declaredMediaType' => is_string($link['declaredMediaType'] ?? null) ? $link['declaredMediaType'] : null,
            'effectiveMediaType' => is_string($link['effectiveMediaType'] ?? null) ? $link['effectiveMediaType'] : null,
            'mediaTypeSource' => is_string($link['mediaTypeSource'] ?? null) ? $link['mediaTypeSource'] : 'none',
            'normalizedMediaType' => is_string($link['normalizedMediaType'] ?? null) ? $link['normalizedMediaType'] : null,
            'baseMediaType' => is_string($link['baseMediaType'] ?? null) ? $link['baseMediaType'] : null,
            'mediaTypeParameters' => is_array($link['mediaTypeParameters'] ?? null) ? $link['mediaTypeParameters'] : [],
            'mediaTypeParameterNames' => is_array($link['mediaTypeParameterNames'] ?? null) ? array_values($link['mediaTypeParameterNames']) : [],
            'mediaTypeParameterCount' => is_int($link['mediaTypeParameterCount'] ?? null) ? $link['mediaTypeParameterCount'] : 0,
            'mediaTypeSyntaxValid' => (bool) ($link['mediaTypeSyntaxValid'] ?? true),
            'mediaTypeDiagnostics' => is_array($link['mediaTypeDiagnostics'] ?? null) ? array_values($link['mediaTypeDiagnostics']) : [],
            'manifestId' => is_string($link['manifestId'] ?? null) ? $link['manifestId'] : null,
            'manifestMediaType' => is_string($link['manifestMediaType'] ?? null) ? $link['manifestMediaType'] : null,
            'title' => is_string($link['title'] ?? null) ? $link['title'] : null,
            'external' => (bool) ($link['external'] ?? false),
            'exists' => (bool) ($link['exists'] ?? false),
            'encrypted' => (bool) ($link['encrypted'] ?? false),
            'canExposeBytes' => (bool) ($link['canExposeBytes'] ?? false),
            'byteLength' => is_int($link['byteLength'] ?? null) ? $link['byteLength'] : null,
            'compressedByteLength' => is_int($link['compressedByteLength'] ?? null) ? $link['compressedByteLength'] : null,
            'compressionMethod' => is_int($link['compressionMethod'] ?? null) ? $link['compressionMethod'] : null,
            'compressionMethodName' => is_string($link['compressionMethodName'] ?? null) ? $link['compressionMethodName'] : null,
            'compressionSupported' => is_bool($link['compressionSupported'] ?? null) ? $link['compressionSupported'] : null,
            'crc32' => is_string($link['crc32'] ?? null) ? $link['crc32'] : null,
            'byteSha256' => is_string($link['byteSha256'] ?? null) ? $link['byteSha256'] : null,
            'sourceDiagnostics' => is_array($link['diagnostics'] ?? null) ? array_values($link['diagnostics']) : [],
            'diagnostics' => [],
        ];

        $scopePrefix = $scope === 'publication' ? 'publication' : 'refined';
        $isLocal = ($item['external'] ?? false) !== true && is_string($item['part'] ?? null) && $item['part'] !== '';

        if (($item['external'] ?? false) === true) {
            $item['diagnostics'][] = self::metadataLinkTargetDiagnostic('external', $scopePrefix, $item);
        } elseif (($item['exists'] ?? false) !== true) {
            $item['diagnostics'][] = self::metadataLinkTargetDiagnostic('missing', $scopePrefix, $item);
        } elseif ($isLocal && !is_string($item['manifestId'] ?? null)) {
            $item['diagnostics'][] = self::metadataLinkTargetDiagnostic('unmanifested', $scopePrefix, $item);
        }

        if (($item['encrypted'] ?? false) === true) {
            $item['diagnostics'][] = self::metadataLinkTargetDiagnostic('encrypted', $scopePrefix, $item);
        }

        return $item;
    }

    /**
     * @param array<string, mixed> $item
     *
     * @return array<string, mixed>
     */
    private static function metadataLinkTargetDiagnostic(string $kind, string $scopePrefix, array $item): array
    {
        $message = match ($kind) {
            'external' => 'EPUB OPF metadata link points outside the package and was not fetched',
            'missing' => 'EPUB OPF metadata link target is missing from the package',
            'unmanifested' => 'EPUB OPF metadata link resolves to package bytes that are not declared in the OPF manifest',
            'encrypted' => 'EPUB OPF metadata link target is encrypted and cannot expose package bytes',
            default => 'EPUB OPF metadata link target requires package review',
        };

        return [
            'type' => $kind . '-' . $scopePrefix . '-metadata-link',
            'index' => (int) ($item['index'] ?? 0),
            'id' => is_string($item['id'] ?? null) ? $item['id'] : null,
            'scope' => is_string($item['scope'] ?? null) ? $item['scope'] : null,
            'subjectId' => is_string($item['subjectId'] ?? null) ? $item['subjectId'] : null,
            'rel' => is_array($item['rel'] ?? null) ? array_values($item['rel']) : [],
            'href' => is_string($item['href'] ?? null) ? $item['href'] : null,
            'target' => is_string($item['target'] ?? null) ? $item['target'] : null,
            'part' => is_string($item['part'] ?? null) ? $item['part'] : null,
            'manifestId' => is_string($item['manifestId'] ?? null) ? $item['manifestId'] : null,
            'message' => $message,
        ];
    }

    /**
     * @param array<string, list<array<string, mixed>>> $linksByRefinedId
     *
     * @return array{present:bool, subjectCount:int, linkCount:int, subjects:list<string>, diagnostics:list<array<string, mixed>>}
     */
    private static function metadataLinkedResourceSummary(array $linksByRefinedId): array
    {
        $linkCount = 0;
        foreach ($linksByRefinedId as $links) {
            $linkCount += is_array($links) ? count($links) : 0;
        }

        return [
            'present' => $linkCount > 0,
            'subjectCount' => count($linksByRefinedId),
            'linkCount' => $linkCount,
            'subjects' => array_keys($linksByRefinedId),
            'diagnostics' => [],
        ];
    }

    /**
     * @param array<string, mixed> $metadata
     * @param array<string, list<array<string, mixed>>> $linksByRefinedId
     *
     * @return array<string, mixed>
     */
    private static function attachMetadataLinkedResources(array $metadata, array $linksByRefinedId): array
    {
        $dc = is_array($metadata['dc'] ?? null) ? $metadata['dc'] : [];
        foreach ($dc as $name => $entries) {
            if (!is_array($entries)) {
                continue;
            }

            foreach ($entries as $index => $entry) {
                if (!is_array($entry)) {
                    continue;
                }

                $id = is_string($entry['id'] ?? null) ? $entry['id'] : null;
                $dc[$name][$index]['linkedResources'] = self::metadataLinkedResourcesForId($linksByRefinedId, $id);
            }
        }
        $metadata['dc'] = $dc;

        $metadata['titleDetails'] = self::metadataDetailsWithLinkedResources(
            is_array($metadata['titleDetails'] ?? null) ? $metadata['titleDetails'] : [],
            $linksByRefinedId
        );
        $metadata['titlesByType'] = self::metadataTitlesByType($metadata['titleDetails']);
        $metadata['mainTitle'] = self::firstMetadataTitleByType($metadata['titleDetails'], 'main') ?? ($metadata['titleDetails'][0] ?? null);
        $metadata['subtitle'] = self::firstMetadataTitleByType($metadata['titleDetails'], 'subtitle');
        $metadata['shortTitle'] = self::firstMetadataTitleByType($metadata['titleDetails'], 'short');
        $metadata['collectionTitle'] = self::firstMetadataTitleByType($metadata['titleDetails'], 'collection');
        $metadata['sortTitle'] = is_array($metadata['mainTitle'] ?? null) ? $metadata['mainTitle']['fileAs'] : null;

        $metadata['creatorDetails'] = self::metadataDetailsWithLinkedResources(
            is_array($metadata['creatorDetails'] ?? null) ? $metadata['creatorDetails'] : [],
            $linksByRefinedId
        );
        $metadata['creatorsByRole'] = self::metadataAgentsByRole($metadata['creatorDetails']);
        $metadata['contributorDetails'] = self::metadataDetailsWithLinkedResources(
            is_array($metadata['contributorDetails'] ?? null) ? $metadata['contributorDetails'] : [],
            $linksByRefinedId
        );
        $metadata['contributorsByRole'] = self::metadataAgentsByRole($metadata['contributorDetails']);
        $metadata['untypedContributors'] = self::metadataAgentsWithoutRoles($metadata['contributorDetails']);
        $metadata['agentDisplayOrder'] = self::metadataAgentDisplayOrderReport(
            $metadata['creatorDetails'],
            $metadata['contributorDetails']
        );

        if (is_array($metadata['uniqueIdentifier'] ?? null)) {
            $metadata['uniqueIdentifier'] = self::uniqueIdentifierWithLinkedResources(
                $metadata['uniqueIdentifier'],
                $linksByRefinedId
            );
        }
        $metadata['identifierDetails'] = self::metadataDetailsWithLinkedResources(
            is_array($metadata['identifierDetails'] ?? null) ? $metadata['identifierDetails'] : [],
            $linksByRefinedId
        );
        $metadata['identifiersByType'] = self::metadataIdentifierDetailsByField($metadata['identifierDetails'], 'identifierType');
        $metadata['identifiersByScheme'] = self::metadataIdentifierDetailsByField($metadata['identifierDetails'], 'scheme');
        $metadata['identifierSummary'] = self::metadataIdentifierSummary(
            $metadata['identifierDetails'],
            is_array($metadata['uniqueIdentifier'] ?? null) ? $metadata['uniqueIdentifier'] : []
        );
        $metadata['dateDetails'] = self::metadataDetailsWithLinkedResources(
            is_array($metadata['dateDetails'] ?? null) ? $metadata['dateDetails'] : [],
            $linksByRefinedId
        );
        $metadata['datesByEvent'] = self::metadataDateDetailsByEvent($metadata['dateDetails']);
        $metadata['dateSummary'] = self::metadataDateSummary($metadata['dateDetails']);
        $metadata['sourceDetails'] = self::metadataDetailsWithLinkedResources(
            is_array($metadata['sourceDetails'] ?? null) ? $metadata['sourceDetails'] : [],
            $linksByRefinedId
        );
        $metadata['sourcesBySourceOf'] = self::metadataSourceDetailsBySourceOf($metadata['sourceDetails']);
        $metadata['sourceSummary'] = self::metadataSourceSummary($metadata['sourceDetails']);
        $metadata['bibliographicDetails'] = self::metadataDetailsWithLinkedResources(
            is_array($metadata['bibliographicDetails'] ?? null) ? $metadata['bibliographicDetails'] : [],
            $linksByRefinedId
        );
        $metadata['bibliographicDetailsByKind'] = self::metadataBibliographicDetailsByKind($metadata['bibliographicDetails']);
        $metadata['bibliographicSummary'] = self::metadataBibliographicSummary($metadata['bibliographicDetails']);

        return $metadata;
    }

    /**
     * @param list<array<string, mixed>> $details
     * @param array<string, list<array<string, mixed>>> $linksByRefinedId
     *
     * @return list<array<string, mixed>>
     */
    private static function metadataDetailsWithLinkedResources(array $details, array $linksByRefinedId): array
    {
        foreach ($details as $index => $detail) {
            if (!is_array($detail)) {
                continue;
            }

            $id = is_string($detail['id'] ?? null) ? $detail['id'] : null;
            $details[$index]['linkedResources'] = self::metadataLinkedResourcesForId($linksByRefinedId, $id);
        }

        return $details;
    }

    /**
     * @param array<string, mixed> $identifier
     * @param array<string, list<array<string, mixed>>> $linksByRefinedId
     *
     * @return array<string, mixed>
     */
    private static function uniqueIdentifierWithLinkedResources(array $identifier, array $linksByRefinedId): array
    {
        foreach (['entries', 'matchedEntries'] as $key) {
            $entries = is_array($identifier[$key] ?? null) ? $identifier[$key] : [];
            foreach ($entries as $index => $entry) {
                if (!is_array($entry)) {
                    continue;
                }

                $id = is_string($entry['id'] ?? null) ? $entry['id'] : null;
                $entries[$index]['linkedResources'] = self::metadataLinkedResourcesForId($linksByRefinedId, $id);
            }
            $identifier[$key] = $entries;
        }

        return $identifier;
    }

    /**
     * @param array<string, list<array<string, mixed>>> $linksByRefinedId
     *
     * @return list<array<string, mixed>>
     */
    private static function metadataLinkedResourcesForId(array $linksByRefinedId, ?string $id): array
    {
        if ($id === null || $id === '') {
            return [];
        }

        return is_array($linksByRefinedId[$id] ?? null) ? array_values($linksByRefinedId[$id]) : [];
    }

    /**
     * @param array<string, array<string, mixed>> $manifestById
     * @param array<string, list<array<string, mixed>>> $linksByRefinedId
     *
     * @return array<string, array<string, mixed>>
     */
    private static function attachMetadataLinksToManifest(array $manifestById, array $linksByRefinedId): array
    {
        foreach ($manifestById as $id => $item) {
            $manifestById[$id]['linkedResources'] = self::metadataLinkedResourcesForId($linksByRefinedId, (string) $id);
        }

        return $manifestById;
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
     * @param array<string, mixed> $metadata
     *
     * @return array<string, mixed>
     */
    private static function metadataRenditionLayoutReport(array $metadata): array
    {
        $metaProperties = is_array($metadata['metaProperties'] ?? null) ? $metadata['metaProperties'] : [];
        $layout = self::renditionMetadataScalarReport($metaProperties, 'layout', ['reflowable', 'pre-paginated']);
        $orientation = self::renditionMetadataScalarReport($metaProperties, 'orientation', ['auto', 'landscape', 'portrait']);
        $spread = self::renditionMetadataScalarReport($metaProperties, 'spread', ['auto', 'none', 'both', 'landscape', 'portrait']);
        $viewportEntries = is_array($metaProperties['rendition:viewport'] ?? null) ? $metaProperties['rendition:viewport'] : [];
        $viewports = [];
        $diagnostics = array_merge($layout['diagnostics'], $orientation['diagnostics'], $spread['diagnostics']);
        foreach ($viewportEntries as $index => $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $viewport = self::renditionViewportReport($entry, (int) $index);
            foreach ($viewport['diagnostics'] as $diagnostic) {
                $diagnostics[] = $diagnostic;
            }
            $viewports[] = $viewport;
        }

        $validViewports = array_values(array_filter(
            $viewports,
            static fn (array $viewport): bool => ($viewport['valid'] ?? false) === true,
        ));
        $invalidViewports = array_values(array_filter(
            $viewports,
            static fn (array $viewport): bool => ($viewport['valid'] ?? true) !== true,
        ));
        $selectedViewport = $validViewports[0] ?? ($viewports[0] ?? self::emptyRenditionViewportReport());
        $present = $layout['present']
            || $orientation['present']
            || $spread['present']
            || $viewports !== [];

        return [
            'present' => $present,
            'fixedLayout' => ($layout['value'] ?? null) === 'pre-paginated',
            'layout' => $layout['value'],
            'layoutRaw' => $layout['raw'],
            'layoutProperty' => $layout,
            'orientation' => $orientation['value'],
            'orientationRaw' => $orientation['raw'],
            'orientationProperty' => $orientation,
            'spread' => $spread['value'],
            'spreadRaw' => $spread['raw'],
            'spreadProperty' => $spread,
            'viewport' => $selectedViewport,
            'viewports' => $viewports,
            'viewportCount' => count($viewports),
            'validViewportCount' => count($validViewports),
            'invalidViewportCount' => count($invalidViewports),
            'viewportRaw' => $selectedViewport['raw'],
            'viewportWidth' => $selectedViewport['width'],
            'viewportHeight' => $selectedViewport['height'],
            'diagnostics' => $diagnostics,
        ];
    }

    /**
     * @param array<string, list<array<string, mixed>>> $metaProperties
     * @param list<string> $allowedValues
     *
     * @return array<string, mixed>
     */
    private static function renditionMetadataScalarReport(array $metaProperties, string $name, array $allowedValues): array
    {
        $property = 'rendition:' . $name;
        $entries = is_array($metaProperties[$property] ?? null) ? $metaProperties[$property] : [];
        $items = [];
        $diagnostics = [];
        $validValues = [];
        $selected = null;

        foreach ($entries as $index => $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $raw = self::metadataEntryValue($entry);
            $value = strtolower(trim($raw));
            $valid = in_array($value, $allowedValues, true);
            $itemDiagnostics = [];
            if ($raw === '' || !$valid) {
                $itemDiagnostics[] = [
                    'type' => 'invalid-rendition-' . $name . '-value',
                    'property' => $property,
                    'index' => (int) $index,
                    'value' => $raw,
                    'allowedValues' => $allowedValues,
                    'message' => 'EPUB OPF rendition metadata value is not recognized for bounded package review',
                ];
            }

            $item = [
                'index' => (int) $index,
                'property' => $property,
                'id' => is_string($entry['id'] ?? null) ? $entry['id'] : null,
                'raw' => $raw,
                'value' => $valid ? $value : null,
                'normalized' => $value,
                'valid' => $valid,
                'language' => is_string($entry['language'] ?? null) ? $entry['language'] : null,
                'direction' => is_string($entry['direction'] ?? null) ? $entry['direction'] : null,
                'propertyVocabulary' => is_array($entry['propertyVocabulary'] ?? null) ? $entry['propertyVocabulary'] : null,
                'diagnostics' => $itemDiagnostics,
            ];

            if ($valid) {
                $validValues[$value] = $value;
                if ($selected === null) {
                    $selected = $item;
                }
            }

            foreach ($itemDiagnostics as $diagnostic) {
                $diagnostics[] = $diagnostic;
            }
            $items[] = $item;
        }

        if (count($validValues) > 1) {
            $diagnostics[] = [
                'type' => 'conflicting-rendition-' . $name . '-values',
                'property' => $property,
                'values' => array_values($validValues),
                'message' => 'EPUB OPF rendition metadata declares more than one valid value for the same package property',
            ];
        }

        return [
            'present' => $items !== [],
            'property' => $property,
            'value' => is_array($selected) ? $selected['value'] : null,
            'raw' => is_array($selected) ? $selected['raw'] : ($items[0]['raw'] ?? null),
            'selected' => $selected,
            'entries' => $items,
            'count' => count($items),
            'validCount' => count(array_filter(
                $items,
                static fn (array $item): bool => ($item['valid'] ?? false) === true,
            )),
            'invalidCount' => count(array_filter(
                $items,
                static fn (array $item): bool => ($item['valid'] ?? true) !== true,
            )),
            'valid' => $items === [] || $diagnostics === [],
            'diagnostics' => $diagnostics,
        ];
    }

    /**
     * @param array<string, mixed> $entry
     *
     * @return array<string, mixed>
     */
    private static function renditionViewportReport(array $entry, int $index): array
    {
        $raw = self::metadataEntryValue($entry);
        $parameters = [];
        $diagnostics = [];
        $unknownParameterDiagnostics = [];
        $segments = $raw === '' ? [] : preg_split('/\s*,\s*/', $raw);
        foreach ($segments ?: [] as $segment) {
            $segment = trim((string) $segment);
            if ($segment === '') {
                continue;
            }

            if (preg_match('/^([A-Za-z][A-Za-z0-9_-]*)\s*=\s*(.+)$/', $segment, $matches) !== 1) {
                $diagnostics[] = [
                    'type' => 'invalid-rendition-viewport-parameter',
                    'property' => 'rendition:viewport',
                    'index' => $index,
                    'segment' => $segment,
                    'message' => 'EPUB OPF rendition viewport parameters must be key=value pairs',
                ];
                continue;
            }

            $key = strtolower($matches[1]);
            $value = trim($matches[2]);
            if (isset($parameters[$key])) {
                $diagnostics[] = [
                    'type' => 'duplicate-rendition-viewport-parameter',
                    'property' => 'rendition:viewport',
                    'index' => $index,
                    'parameter' => $key,
                    'message' => 'EPUB OPF rendition viewport repeats a parameter; first value is retained',
                ];
                continue;
            }

            $parameters[$key] = $value;
            if (!in_array($key, ['width', 'height'], true)) {
                $unknownParameterDiagnostics[] = [
                    'type' => 'unknown-rendition-viewport-parameter',
                    'property' => 'rendition:viewport',
                    'index' => $index,
                    'parameter' => $key,
                    'value' => $value,
                    'message' => 'EPUB OPF rendition viewport parameter is preserved but not used by the bounded package review parser',
                ];
            }
        }

        if ($raw === '') {
            $diagnostics[] = [
                'type' => 'empty-rendition-viewport',
                'property' => 'rendition:viewport',
                'index' => $index,
                'message' => 'EPUB OPF rendition viewport metadata is empty',
            ];
        }

        foreach (['width', 'height'] as $dimension) {
            $value = $parameters[$dimension] ?? null;
            if ($value === null || preg_match('/^[1-9][0-9]*$/', $value) !== 1) {
                $diagnostics[] = [
                    'type' => 'invalid-rendition-viewport-' . $dimension,
                    'property' => 'rendition:viewport',
                    'index' => $index,
                    'parameter' => $dimension,
                    'value' => $value,
                    'message' => 'EPUB OPF rendition viewport width and height must be positive integer CSS pixels',
                ];
            }
        }
        $diagnostics = array_merge($diagnostics, $unknownParameterDiagnostics);

        return [
            'present' => true,
            'index' => $index,
            'property' => 'rendition:viewport',
            'id' => is_string($entry['id'] ?? null) ? $entry['id'] : null,
            'raw' => $raw,
            'parameters' => $parameters,
            'widthRaw' => $parameters['width'] ?? null,
            'heightRaw' => $parameters['height'] ?? null,
            'width' => isset($parameters['width']) && preg_match('/^[1-9][0-9]*$/', $parameters['width']) === 1
                ? (int) $parameters['width']
                : null,
            'height' => isset($parameters['height']) && preg_match('/^[1-9][0-9]*$/', $parameters['height']) === 1
                ? (int) $parameters['height']
                : null,
            'language' => is_string($entry['language'] ?? null) ? $entry['language'] : null,
            'direction' => is_string($entry['direction'] ?? null) ? $entry['direction'] : null,
            'propertyVocabulary' => is_array($entry['propertyVocabulary'] ?? null) ? $entry['propertyVocabulary'] : null,
            'valid' => $diagnostics === [],
            'diagnostics' => $diagnostics,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function emptyRenditionViewportReport(): array
    {
        return [
            'present' => false,
            'index' => null,
            'property' => 'rendition:viewport',
            'id' => null,
            'raw' => null,
            'parameters' => [],
            'widthRaw' => null,
            'heightRaw' => null,
            'width' => null,
            'height' => null,
            'language' => null,
            'direction' => null,
            'propertyVocabulary' => null,
            'valid' => false,
            'diagnostics' => [],
        ];
    }

    /**
     * @param array<string, mixed> $metadata
     *
     * @return array<string, mixed>
     */
    private static function metadataCollectionMembershipReport(array $metadata): array
    {
        $metaProperties = is_array($metadata['metaProperties'] ?? null) ? $metadata['metaProperties'] : [];
        $refinementsById = is_array($metadata['refinementsById'] ?? null) ? $metadata['refinementsById'] : [];
        $linksByRefinedId = is_array($metadata['linksByRefinedId'] ?? null) ? $metadata['linksByRefinedId'] : [];
        $entries = is_array($metaProperties['belongs-to-collection'] ?? null)
            ? $metaProperties['belongs-to-collection']
            : [];

        $items = [];
        $byType = [];
        $types = [];
        $diagnostics = [];
        $typedCount = 0;
        $positionedCount = 0;
        $invalidGroupPositionCount = 0;

        foreach ($entries as $entryIndex => $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $id = is_string($entry['id'] ?? null) ? $entry['id'] : null;
            $refinements = $id !== null && is_array($refinementsById[$id] ?? null) ? $refinementsById[$id] : [];
            $collectionTypes = self::metadataRefinementEntries($refinements, 'collection-type');
            $groupPositions = self::metadataRefinementEntries($refinements, 'group-position');
            $collectionType = is_array($collectionTypes[0] ?? null) ? (string) $collectionTypes[0]['value'] : null;
            $groupPosition = is_array($groupPositions[0] ?? null) ? (string) $groupPositions[0]['value'] : null;
            $groupPositionNumber = self::metadataNumericValue($groupPosition);
            $itemDiagnostics = [];

            if (self::metadataEntryValue($entry) === '') {
                $itemDiagnostics[] = [
                    'type' => 'empty-belongs-to-collection',
                    'id' => $id,
                    'index' => (int) $entryIndex,
                    'message' => 'EPUB OPF belongs-to-collection metadata is empty',
                ];
            }
            if ($groupPosition !== null && $groupPosition !== '' && $groupPositionNumber === null) {
                $itemDiagnostics[] = [
                    'type' => 'invalid-collection-group-position',
                    'id' => $id,
                    'index' => (int) $entryIndex,
                    'value' => $groupPosition,
                    'message' => 'EPUB OPF collection group-position metadata should be numeric',
                ];
            }

            $item = [
                'index' => (int) $entryIndex,
                'id' => $id,
                'title' => self::metadataEntryValue($entry),
                'value' => self::metadataEntryValue($entry),
                'text' => is_string($entry['text'] ?? null) ? $entry['text'] : '',
                'content' => is_string($entry['content'] ?? null) ? $entry['content'] : null,
                'collectionType' => $collectionType,
                'collectionTypes' => $collectionTypes,
                'groupPosition' => $groupPosition,
                'groupPositionNumber' => $groupPositionNumber,
                'groupPositions' => $groupPositions,
                'displaySeq' => self::firstMetadataRefinementValue($refinements, 'display-seq'),
                'fileAs' => self::firstMetadataRefinementValue($refinements, 'file-as'),
                'language' => is_string($entry['language'] ?? null) ? $entry['language'] : null,
                'direction' => is_string($entry['direction'] ?? null) ? $entry['direction'] : null,
                'propertyVocabulary' => is_array($entry['propertyVocabulary'] ?? null) ? $entry['propertyVocabulary'] : null,
                'linkedResources' => self::metadataLinkedResourcesForId($linksByRefinedId, $id),
                'refinements' => $refinements,
                'diagnostics' => $itemDiagnostics,
            ];

            if ($collectionType !== null && $collectionType !== '') {
                ++$typedCount;
                $types[$collectionType] = $collectionType;
                $byType[$collectionType][] = $item;
            }
            if ($groupPositionNumber !== null) {
                ++$positionedCount;
            }

            foreach ($itemDiagnostics as $diagnostic) {
                $diagnostics[] = $diagnostic;
                if (($diagnostic['type'] ?? null) === 'invalid-collection-group-position') {
                    ++$invalidGroupPositionCount;
                }
            }

            $items[] = $item;
        }

        return [
            'present' => $items !== [],
            'count' => count($items),
            'typedCount' => $typedCount,
            'positionedCount' => $positionedCount,
            'invalidGroupPositionCount' => $invalidGroupPositionCount,
            'types' => array_values($types),
            'items' => $items,
            'byType' => $byType,
            'diagnostics' => $diagnostics,
        ];
    }

    private static function metadataNumericValue(?string $value): ?float
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);
        if ($trimmed === '' || preg_match('/^[+-]?(?:\d+(?:\.\d+)?|\.\d+)$/', $trimmed) !== 1) {
            return null;
        }

        return (float) $trimmed;
    }

    /**
     * @param array<string, mixed> $metadata
     *
     * @return array<string, mixed>
     */
    private static function vendorMetadataReport(array $metadata): array
    {
        $metaProperties = is_array($metadata['metaProperties'] ?? null) ? $metadata['metaProperties'] : [];
        $items = [];
        $itemsByVendor = [
            'ibooks' => [],
            'calibre' => [],
        ];
        $diagnostics = [];

        foreach ($metaProperties as $property => $entries) {
            if (!is_string($property) || !is_array($entries)) {
                continue;
            }

            $vendor = null;
            $field = null;
            if (str_starts_with($property, 'ibooks:')) {
                $vendor = 'ibooks';
                $field = substr($property, strlen('ibooks:'));
            } elseif (str_starts_with($property, 'calibre:')) {
                $vendor = 'calibre';
                $field = substr($property, strlen('calibre:'));
            }

            if ($vendor === null || $field === null) {
                continue;
            }

            foreach ($entries as $entryIndex => $entry) {
                if (!is_array($entry)) {
                    continue;
                }

                $value = self::metadataEntryValue($entry);
                $entryDiagnostics = [];
                if ($field === '') {
                    $entryDiagnostics[] = [
                        'type' => 'empty-vendor-metadata-field',
                        'vendor' => $vendor,
                        'property' => $property,
                        'entryIndex' => (int) $entryIndex,
                    ];
                }
                if ($value === '') {
                    $entryDiagnostics[] = [
                        'type' => 'empty-vendor-metadata-value',
                        'vendor' => $vendor,
                        'property' => $property,
                        'entryIndex' => (int) $entryIndex,
                    ];
                }

                $item = [
                    'vendor' => $vendor,
                    'field' => $field,
                    'property' => $property,
                    'entryIndex' => (int) $entryIndex,
                    'id' => is_string($entry['id'] ?? null) ? $entry['id'] : null,
                    'refines' => is_string($entry['refines'] ?? null) ? $entry['refines'] : null,
                    'value' => $value,
                    'text' => is_string($entry['text'] ?? null) ? $entry['text'] : '',
                    'content' => is_string($entry['content'] ?? null) ? $entry['content'] : null,
                    'language' => is_string($entry['language'] ?? null) ? $entry['language'] : null,
                    'direction' => is_string($entry['direction'] ?? null) ? $entry['direction'] : null,
                    'propertyVocabulary' => is_array($entry['propertyVocabulary'] ?? null) ? $entry['propertyVocabulary'] : null,
                    'diagnostics' => $entryDiagnostics,
                ];

                $items[] = $item;
                $itemsByVendor[$vendor][] = $item;
                foreach ($entryDiagnostics as $diagnostic) {
                    $diagnostics[] = $diagnostic;
                }
            }
        }

        return [
            'present' => $items !== [],
            'itemCount' => count($items),
            'ibooksCount' => count($itemsByVendor['ibooks']),
            'calibreCount' => count($itemsByVendor['calibre']),
            'items' => $items,
            'itemsByVendor' => $itemsByVendor,
            'ibooks' => self::vendorMetadataFields($itemsByVendor['ibooks']),
            'calibre' => self::vendorMetadataFields($itemsByVendor['calibre']),
            'diagnostics' => $diagnostics,
        ];
    }

    /**
     * @param list<array<string, mixed>> $items
     *
     * @return array<string, list<array<string, mixed>>>
     */
    private static function vendorMetadataFields(array $items): array
    {
        $fields = [];
        foreach ($items as $item) {
            $field = is_string($item['field'] ?? null) ? $item['field'] : '';
            if ($field === '') {
                continue;
            }

            $fields[$field][] = $item;
        }

        return $fields;
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
        array $refinementsById,
        array $prefixBindings = []
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
            $propertyVocabulary = self::manifestItemPropertyVocabularyReport($properties, $prefixBindings, $id);
            $resourceFlags = self::resourcePropertyFlags($properties);
            $resourceReviewFlags = self::resourceReviewFlags($resourceFlags);
            $attributes = self::manifestItemAttributes($item);
            $customAttributes = self::manifestItemCustomAttributes($attributes);
            $language = self::xmlLang($item);
            $direction = self::direction($item);
            $base = self::xmlBase($item);
            $baseResolution = self::manifestItemBaseResolution($base);

            if (self::isExternalReference($href)) {
                $fragmentFields = self::targetFragmentFields($href);
                $manifest[$id] = [
                    'id' => $id,
                    'href' => $href,
                    'target' => $href,
                    'part' => null,
                    'fragment' => $fragmentFields['fragment'],
                    'fragmentKind' => $fragmentFields['fragmentKind'],
                    'epubCfi' => $fragmentFields['epubCfi'],
                    'mediaFragment' => $fragmentFields['mediaFragment'],
                    'external' => true,
                    'mediaType' => $mediaType,
                    'properties' => $properties,
                    'propertyVocabulary' => $propertyVocabulary,
                    'language' => $language,
                    'direction' => $direction,
                    'base' => $base,
                    'baseResolutionPolicy' => $baseResolution['policy'],
                    'baseResolution' => $baseResolution,
                    'attributes' => $attributes,
                    'customAttributes' => $customAttributes,
                    'resourceFlags' => $resourceFlags,
                    'resourceReviewFlags' => $resourceReviewFlags,
                    'refinements' => self::metadataRefinementsForId($refinementsById, $id),
                    'fallback' => self::nullableAttribute($item, 'fallback'),
                    'fallbackStyle' => self::nullableAttribute($item, 'fallback-style'),
                    'mediaOverlay' => self::nullableAttribute($item, 'media-overlay'),
                    'exists' => false,
                    'byteLength' => null,
                    'crc32' => null,
                    'byteSha256' => null,
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
            $fragmentFields = self::targetFragmentFields($target);
            $exists = $package->has($part);
            $entry = $exists ? $package->entry($part) : null;
            $byteSha256 = null;
            $diagnostics = [];
            if ($exists) {
                try {
                    $byteSha256 = hash('sha256', $package->read($part));
                } catch (\Throwable $exception) {
                    $diagnostics[] = [
                        'type' => 'manifest-resource-bytes-unavailable',
                        'part' => $part,
                        'message' => $exception->getMessage(),
                    ];
                }
            }

            $manifest[$id] = [
                'id' => $id,
                'href' => $href,
                'target' => $target,
                'part' => $part,
                'fragment' => $fragmentFields['fragment'],
                'fragmentKind' => $fragmentFields['fragmentKind'],
                'epubCfi' => $fragmentFields['epubCfi'],
                'mediaFragment' => $fragmentFields['mediaFragment'],
                'external' => false,
                'mediaType' => $mediaType,
                'properties' => $properties,
                'propertyVocabulary' => $propertyVocabulary,
                'language' => $language,
                'direction' => $direction,
                'base' => $base,
                'baseResolutionPolicy' => $baseResolution['policy'],
                'baseResolution' => $baseResolution,
                'attributes' => $attributes,
                'customAttributes' => $customAttributes,
                'resourceFlags' => $resourceFlags,
                'resourceReviewFlags' => $resourceReviewFlags,
                'refinements' => self::metadataRefinementsForId($refinementsById, $id),
                'fallback' => self::nullableAttribute($item, 'fallback'),
                'fallbackStyle' => self::nullableAttribute($item, 'fallback-style'),
                'mediaOverlay' => self::nullableAttribute($item, 'media-overlay'),
                'exists' => $exists,
                'byteLength' => $entry instanceof ZipPackageEntry ? $entry->uncompressedSize : null,
                'crc32' => $entry instanceof ZipPackageEntry ? $entry->crc32Hex() : null,
                'byteSha256' => $byteSha256,
                'encrypted' => false,
                'canExposeBytes' => true,
                'encryption' => null,
                'diagnostics' => $diagnostics,
            ];
        }

        return $manifest;
    }

    /**
     * @param array<string, string> $attributes
     *
     * @return array<string, string>
     */
    private static function manifestItemCustomAttributes(array $attributes): array
    {
        $custom = [];
        foreach ($attributes as $name => $value) {
            if (!is_string($name) || !is_string($value)) {
                continue;
            }
            if (isset(self::OPF_MANIFEST_STRUCTURAL_ATTRIBUTES[$name])) {
                continue;
            }

            $custom[$name] = $value;
        }

        return $custom;
    }

    /**
     * @param array<string, string> $attributes
     *
     * @return array<string, string>
     */
    private static function rootfileCustomAttributes(array $attributes): array
    {
        $custom = [];
        foreach ($attributes as $name => $value) {
            if (!is_string($name) || !is_string($value)) {
                continue;
            }
            if (isset(self::OCF_ROOTFILE_STRUCTURAL_ATTRIBUTES[$name])) {
                continue;
            }
            if ($name === 'xmlns' || str_starts_with($name, 'xmlns:')) {
                continue;
            }

            $custom[$name] = $value;
        }

        return $custom;
    }

    /**
     * @param array<string, string> $attributes
     *
     * @return array<string, string>
     */
    private static function spineItemrefCustomAttributes(array $attributes): array
    {
        $custom = [];
        foreach ($attributes as $name => $value) {
            if (!is_string($name) || !is_string($value)) {
                continue;
            }
            if (isset(self::OPF_SPINE_ITEMREF_STRUCTURAL_ATTRIBUTES[$name])) {
                continue;
            }

            $custom[$name] = $value;
        }

        return $custom;
    }

    /**
     * @param array<string, string> $attributes
     *
     * @return array<string, string>
     */
    private static function packageElementCustomAttributes(array $attributes): array
    {
        $custom = [];
        foreach ($attributes as $name => $value) {
            if (!is_string($name) || !is_string($value)) {
                continue;
            }
            if (isset(self::OPF_PACKAGE_STRUCTURAL_ATTRIBUTES[$name])) {
                continue;
            }
            if ($name === 'xmlns' || str_starts_with($name, 'xmlns:')) {
                continue;
            }

            $custom[$name] = $value;
        }

        return $custom;
    }

    /**
     * @param array<string, string> $attributes
     *
     * @return array<string, string>
     */
    private static function metadataElementCustomAttributes(array $attributes): array
    {
        $custom = [];
        foreach ($attributes as $name => $value) {
            if (!is_string($name) || !is_string($value)) {
                continue;
            }
            if (isset(self::OPF_METADATA_STRUCTURAL_ATTRIBUTES[$name])) {
                continue;
            }
            if ($name === 'xmlns' || str_starts_with($name, 'xmlns:')) {
                continue;
            }

            $custom[$name] = $value;
        }

        return $custom;
    }

    /**
     * @return array<string, string>
     */
    private static function manifestItemAttributes(\DOMElement $element): array
    {
        return self::opfElementAttributes($element);
    }

    /**
     * @return array<string, string>
     */
    private static function rootfileElementAttributes(\DOMElement $element): array
    {
        return self::opfElementAttributes($element);
    }

    /**
     * @return array<string, string>
     */
    private static function packageElementAttributes(\DOMElement $element): array
    {
        return self::opfElementAttributes($element);
    }

    /**
     * @return array<string, string>
     */
    private static function spineItemrefAttributes(\DOMElement $element): array
    {
        return self::opfElementAttributes($element);
    }

    /**
     * @return array<string, string>
     */
    private static function opfElementAttributes(\DOMElement $element): array
    {
        $attributes = [];
        if (!$element->hasAttributes()) {
            return $attributes;
        }

        foreach ($element->attributes as $attribute) {
            if (!$attribute instanceof \DOMAttr) {
                continue;
            }

            $name = $attribute->prefix !== ''
                ? $attribute->prefix . ':' . $attribute->localName
                : $attribute->name;
            $attributes[$name] = $attribute->value;
        }

        ksort($attributes);

        return $attributes;
    }

    /**
     * @param array<string, string> $attributes
     * @param array<string, string> $customAttributes
     *
     * @return array<string, mixed>
     */
    private static function packageAuthoringReport(
        array $attributes,
        ?string $language,
        ?string $direction,
        ?string $base,
        array $customAttributes
    ): array {
        $structuralAttributes = [];
        foreach ($attributes as $name => $value) {
            if (!is_string($name) || !is_string($value)) {
                continue;
            }
            if (!isset(self::OPF_PACKAGE_STRUCTURAL_ATTRIBUTES[$name])) {
                continue;
            }

            $structuralAttributes[$name] = $value;
        }
        $baseSources = self::packageAuthoringFieldSources($attributes, $customAttributes, 'base', $base);
        $languageSources = self::packageAuthoringFieldSources($attributes, $customAttributes, 'language', $language);
        $directionSources = self::packageAuthoringFieldSources($attributes, $customAttributes, 'direction', $direction);
        $sourceReports = [
            'base' => $baseSources,
            'language' => $languageSources,
            'direction' => $directionSources,
        ];
        $diagnostics = self::packageAuthoringDiagnostics($sourceReports, [
            'base' => $base,
            'language' => $language,
            'direction' => $direction,
        ]);
        $conflicts = array_values(array_filter(
            $diagnostics,
            static fn (array $diagnostic): bool => str_starts_with((string) $diagnostic['type'], 'conflicting-')
        ));
        $duplicateFields = [];
        foreach ($sourceReports as $field => $sources) {
            if (count($sources) > 1) {
                $duplicateFields[] = $field;
            }
        }
        $customConflictCount = count(array_filter(
            $conflicts,
            static fn (array $diagnostic): bool => (int) ($diagnostic['customAttributeCount'] ?? 0) > 0
        ));
        $baseResolutionPolicy = $base === null ? null : 'reported-not-applied-to-package-paths';
        $summary = [
            'language' => $language,
            'direction' => $direction,
            'base' => $base,
            'attributeCount' => count($attributes),
            'structuralAttributeCount' => count($structuralAttributes),
            'customAttributeCount' => count($customAttributes),
            'hasCustomAttributes' => $customAttributes !== [],
            'hasBase' => $base !== null,
            'baseResolutionPolicy' => $baseResolutionPolicy,
            'baseResolutionMetadataOnly' => $base !== null,
            'baseResolutionAppliesToPackagePaths' => false,
            'baseSourceCount' => count($baseSources),
            'languageSourceCount' => count($languageSources),
            'directionSourceCount' => count($directionSources),
            'duplicateAuthoringFields' => $duplicateFields,
            'duplicateAuthoringFieldCount' => count($duplicateFields),
            'conflictCount' => count($conflicts),
            'customConflictCount' => $customConflictCount,
            'hasConflicts' => $conflicts !== [],
            'diagnosticCount' => count($diagnostics),
            'diagnosticTypes' => array_map(
                static fn (array $diagnostic): string => (string) ($diagnostic['type'] ?? ''),
                $diagnostics
            ),
        ];

        return [
            'present' => $attributes !== [],
            'language' => $language,
            'direction' => $direction,
            'base' => $base,
            'attributes' => $attributes,
            'attributeCount' => count($attributes),
            'structuralAttributes' => $structuralAttributes,
            'structuralAttributeCount' => count($structuralAttributes),
            'customAttributes' => $customAttributes,
            'customAttributeCount' => count($customAttributes),
            'hasCustomAttributes' => $customAttributes !== [],
            'hasBase' => $base !== null,
            'baseResolutionPolicy' => $baseResolutionPolicy,
            'baseResolution' => [
                'metadataOnly' => $base !== null,
                'appliesToPackagePaths' => false,
                'policy' => $baseResolutionPolicy,
            ],
            'baseSources' => $baseSources,
            'baseSourceCount' => count($baseSources),
            'languageSources' => $languageSources,
            'languageSourceCount' => count($languageSources),
            'directionSources' => $directionSources,
            'directionSourceCount' => count($directionSources),
            'duplicateAuthoringFields' => $duplicateFields,
            'duplicateAuthoringFieldCount' => count($duplicateFields),
            'conflicts' => $conflicts,
            'conflictCount' => count($conflicts),
            'customConflictCount' => $customConflictCount,
            'hasConflicts' => $conflicts !== [],
            'diagnostics' => $diagnostics,
            'summary' => $summary,
        ];
    }

    /**
     * @param array<string, string> $attributes
     * @param array<string, string> $customAttributes
     *
     * @return array<string, mixed>
     */
    private static function metadataAuthoringReport(
        array $attributes,
        ?string $language,
        ?string $direction,
        ?string $base,
        array $customAttributes
    ): array {
        $structuralAttributes = [];
        foreach ($attributes as $name => $value) {
            if (!is_string($name) || !is_string($value)) {
                continue;
            }
            if (!isset(self::OPF_METADATA_STRUCTURAL_ATTRIBUTES[$name])) {
                continue;
            }

            $structuralAttributes[$name] = $value;
        }

        return [
            'present' => $attributes !== [],
            'language' => $language,
            'direction' => $direction,
            'base' => $base,
            'attributes' => $attributes,
            'attributeCount' => count($attributes),
            'structuralAttributes' => $structuralAttributes,
            'structuralAttributeCount' => count($structuralAttributes),
            'customAttributes' => $customAttributes,
            'customAttributeCount' => count($customAttributes),
            'hasCustomAttributes' => $customAttributes !== [],
            'hasLanguage' => $language !== null,
            'hasDirection' => $direction !== null,
            'hasBase' => $base !== null,
            'baseResolutionPolicy' => $base === null ? null : 'reported-not-applied-to-package-paths',
            'baseResolution' => [
                'metadataOnly' => $base !== null,
                'appliesToPackagePaths' => false,
                'policy' => $base === null ? null : 'reported-not-applied-to-package-paths',
            ],
        ];
    }

    /**
     * @param array<string, string> $attributes
     * @param array<string, string> $customAttributes
     *
     * @return list<array<string, mixed>>
     */
    private static function packageAuthoringFieldSources(
        array $attributes,
        array $customAttributes,
        string $field,
        ?string $selectedValue
    ): array {
        $sources = [];
        foreach ($attributes as $name => $value) {
            if (!is_string($name) || !is_string($value) || !self::packageAuthoringAttributeMatchesField($name, $field)) {
                continue;
            }

            $structural = isset(self::OPF_PACKAGE_STRUCTURAL_ATTRIBUTES[$name]);
            $sources[] = [
                'attribute' => $name,
                'value' => $value,
                'structural' => $structural,
                'custom' => isset($customAttributes[$name]),
                'selected' => self::packageAuthoringSelectedAttribute($field) === $name && $selectedValue === $value,
            ];
        }

        return $sources;
    }

    private static function packageAuthoringAttributeMatchesField(string $attribute, string $field): bool
    {
        $localName = str_contains($attribute, ':') ? substr($attribute, (int) strrpos($attribute, ':') + 1) : $attribute;
        $normalizedLocalName = strtolower($localName);

        return match ($field) {
            'base' => $attribute === 'xml:base' || $normalizedLocalName === 'base',
            'language' => $attribute === 'xml:lang' || in_array($normalizedLocalName, ['lang', 'language'], true),
            'direction' => $attribute === 'dir' || in_array($normalizedLocalName, ['dir', 'direction'], true),
            default => false,
        };
    }

    private static function packageAuthoringSelectedAttribute(string $field): ?string
    {
        return [
            'base' => 'xml:base',
            'language' => 'xml:lang',
            'direction' => 'dir',
        ][$field] ?? null;
    }

    /**
     * @param array<string, list<array<string, mixed>>> $sourceReports
     * @param array<string, ?string> $selectedValues
     *
     * @return list<array<string, mixed>>
     */
    private static function packageAuthoringDiagnostics(array $sourceReports, array $selectedValues): array
    {
        $diagnostics = [];
        foreach ($sourceReports as $field => $sources) {
            if (count($sources) < 2) {
                continue;
            }

            $values = [];
            foreach ($sources as $source) {
                $value = (string) ($source['value'] ?? '');
                $values[self::packageAuthoringComparableValue($field, $value)] = $value;
            }

            $conflicting = count($values) > 1;
            $diagnostics[] = [
                'type' => ($conflicting ? 'conflicting' : 'duplicate') . '-opf-package-' . $field . '-authoring',
                'field' => $field,
                'sourceCount' => count($sources),
                'customAttributeCount' => count(array_filter(
                    $sources,
                    static fn (array $source): bool => (bool) ($source['custom'] ?? false)
                )),
                'selectedValue' => $selectedValues[$field] ?? null,
                'attributes' => array_map(static fn (array $source): string => (string) $source['attribute'], $sources),
                'values' => array_values($values),
                'message' => $conflicting
                    ? 'EPUB OPF package root has conflicting authoring attributes for ' . $field . '; the OPF structural value is retained for package summaries.'
                    : 'EPUB OPF package root repeats authoring attributes for ' . $field . '; duplicate provenance is retained for review.',
            ];
        }

        return $diagnostics;
    }

    private static function packageAuthoringComparableValue(string $field, string $value): string
    {
        $value = trim($value);
        if ($field === 'language' || $field === 'direction') {
            return strtolower($value);
        }

        return $value;
    }

    /**
     * @param list<string> $properties
     * @param array<string, string> $prefixBindings
     *
     * @return array<string, mixed>
     */
    private static function manifestItemPropertyVocabularyReport(array $properties, array $prefixBindings, string $manifestId): array
    {
        $items = [];
        $diagnostics = [];
        $prefixedCount = 0;
        $resolvedCount = 0;
        $unresolvedCount = 0;

        foreach ($properties as $index => $property) {
            if (!is_string($property) || $property === '') {
                continue;
            }

            $vocabulary = self::manifestPropertyVocabulary($property, $prefixBindings);
            if (!is_array($vocabulary)) {
                continue;
            }

            if (($vocabulary['prefixed'] ?? false) === true) {
                ++$prefixedCount;
                if (($vocabulary['resolved'] ?? false) === true) {
                    ++$resolvedCount;
                } else {
                    ++$unresolvedCount;
                }
            }

            $item = [
                'index' => (int) $index,
                'property' => $property,
                'vocabulary' => $vocabulary,
            ];

            foreach ($vocabulary['diagnostics'] ?? [] as $diagnostic) {
                if (is_array($diagnostic)) {
                    $diagnostics[] = [
                        'manifestId' => $manifestId,
                        'index' => (int) $index,
                        'property' => $property,
                    ] + $diagnostic;
                }
            }

            $items[] = $item;
        }

        return [
            'manifestId' => $manifestId,
            'present' => $items !== [],
            'count' => count($items),
            'prefixedCount' => $prefixedCount,
            'resolvedCount' => $resolvedCount,
            'unresolvedCount' => $unresolvedCount,
            'items' => $items,
            'diagnostics' => $diagnostics,
            'diagnosticCount' => count($diagnostics),
        ];
    }

    /**
     * @param array<string, array<string, mixed>> $manifestById
     *
     * @return array<string, array<string, mixed>>
     */
    private static function attachManifestPackagePartDiagnostics(array $manifestById): array
    {
        foreach ($manifestById as $id => $item) {
            $manifestById[$id]['duplicatePackagePart'] = false;
            $manifestById[$id]['duplicatePackagePartIds'] = [];
            $manifestById[$id]['duplicatePackagePartHrefs'] = [];
            $manifestById[$id]['duplicatePackagePartTargets'] = [];
        }

        foreach (self::duplicateManifestPackagePartGroups($manifestById) as $group) {
            $diagnostic = self::duplicateManifestPackagePartDiagnostic($group);
            foreach ($group['ids'] as $id) {
                if (!is_string($id) || !isset($manifestById[$id])) {
                    continue;
                }

                $manifestById[$id]['duplicatePackagePart'] = true;
                $manifestById[$id]['duplicatePackagePartIds'] = $group['ids'];
                $manifestById[$id]['duplicatePackagePartHrefs'] = $group['hrefs'];
                $manifestById[$id]['duplicatePackagePartTargets'] = $group['targets'];
                $manifestById[$id]['diagnostics'][] = $diagnostic;
            }
        }

        return $manifestById;
    }

    /**
     * @param list<array<string, mixed>> $manifest
     *
     * @return array<string, mixed>
     */
    private static function importManifestReport(array $manifest): array
    {
        $missingItems = [];
        $externalItems = [];
        $itemsByPart = [];
        $itemDiagnostics = [];
        foreach ($manifest as $item) {
            if (($item['external'] ?? false) === true) {
                $externalItems[] = $item;
            } elseif (($item['exists'] ?? false) !== true) {
                $missingItems[] = $item;
            }

            $part = $item['part'] ?? null;
            if (is_string($part) && $part !== '') {
                $itemsByPart[$part][] = $item;
            }

            foreach (is_array($item['diagnostics'] ?? null) ? $item['diagnostics'] : [] as $diagnostic) {
                if (!is_array($diagnostic)) {
                    continue;
                }

                $itemDiagnostics[] = [
                    'id' => (string) ($item['id'] ?? ''),
                    'href' => (string) ($item['href'] ?? ''),
                    'target' => is_string($item['target'] ?? null) ? $item['target'] : null,
                    'part' => is_string($item['part'] ?? null) ? $item['part'] : null,
                    'mediaType' => is_string($item['mediaType'] ?? null) ? $item['mediaType'] : null,
                ] + $diagnostic;
            }
        }

        ksort($itemsByPart, SORT_STRING);

        $duplicateGroups = self::duplicateManifestPackagePartGroups($manifest);
        $duplicateItemCount = 0;
        $diagnostics = [];
        foreach ($duplicateGroups as $group) {
            $duplicateItemCount += $group['itemCount'];
            $diagnostics[] = self::duplicateManifestPackagePartDiagnostic($group);
        }
        $diagnostics = array_merge($diagnostics, $itemDiagnostics);

        return [
            'count' => count($manifest),
            'items' => $manifest,
            'authoring' => self::manifestItemAuthoringReport($manifest),
            'itemsByPart' => $itemsByPart,
            'missingItemCount' => count($missingItems),
            'missingItems' => $missingItems,
            'externalItemCount' => count($externalItems),
            'externalItems' => $externalItems,
            'byteProvenance' => self::manifestByteProvenanceReport($manifest),
            'duplicatePackagePartCount' => count($duplicateGroups),
            'duplicatePackageItemCount' => $duplicateItemCount,
            'duplicatePackageParts' => array_values(array_map(
                static fn (array $group): string => (string) $group['part'],
                $duplicateGroups,
            )),
            'duplicatePackagePartItems' => $duplicateGroups,
            'itemDiagnosticCount' => count($itemDiagnostics),
            'itemDiagnostics' => $itemDiagnostics,
            'diagnostics' => $diagnostics,
            'diagnosticCount' => count($diagnostics),
        ];
    }

    /**
     * @param list<array<string, mixed>> $manifest
     *
     * @return array<string, mixed>
     */
    private static function manifestItemAuthoringReport(array $manifest): array
    {
        $items = [];
        $itemsById = [];
        $languageItems = [];
        $directionItems = [];
        $baseItems = [];
        $customAttributeItems = [];

        foreach ($manifest as $item) {
            $attributes = is_array($item['attributes'] ?? null) ? $item['attributes'] : [];
            $customAttributes = is_array($item['customAttributes'] ?? null)
                ? $item['customAttributes']
                : self::manifestItemCustomAttributes($attributes);
            $base = is_string($item['base'] ?? null) && $item['base'] !== ''
                ? $item['base']
                : (is_string($attributes['xml:base'] ?? null) && $attributes['xml:base'] !== ''
                    ? $attributes['xml:base']
                    : null);
            $baseResolution = self::manifestItemBaseResolution($base);
            $summary = [
                'id' => (string) ($item['id'] ?? ''),
                'href' => (string) ($item['href'] ?? ''),
                'target' => is_string($item['target'] ?? null) ? $item['target'] : null,
                'part' => is_string($item['part'] ?? null) ? $item['part'] : null,
                'mediaType' => is_string($item['mediaType'] ?? null) ? $item['mediaType'] : null,
                'language' => is_string($item['language'] ?? null) ? $item['language'] : null,
                'direction' => is_string($item['direction'] ?? null) ? $item['direction'] : null,
                'base' => $base,
                'baseResolutionPolicy' => $baseResolution['policy'],
                'baseResolution' => $baseResolution,
                'attributes' => $attributes,
                'attributeCount' => count($attributes),
                'customAttributes' => $customAttributes,
                'customAttributeCount' => count($customAttributes),
                'hasBase' => $base !== null,
            ];

            $items[] = $summary;
            if ($summary['id'] !== '') {
                $itemsById[$summary['id']] = $summary;
            }
            if ($summary['language'] !== null) {
                $languageItems[] = $summary;
            }
            if ($summary['direction'] !== null) {
                $directionItems[] = $summary;
            }
            if ($base !== null) {
                $baseItems[] = $summary;
            }
            if ($customAttributes !== []) {
                $customAttributeItems[] = $summary;
            }
        }

        ksort($itemsById, SORT_STRING);

        return [
            'present' => $items !== [],
            'itemCount' => count($items),
            'items' => $items,
            'itemsById' => $itemsById,
            'languageItemCount' => count($languageItems),
            'languageItems' => $languageItems,
            'directionItemCount' => count($directionItems),
            'directionItems' => $directionItems,
            'baseItemCount' => count($baseItems),
            'baseItems' => $baseItems,
            'customAttributeItemCount' => count($customAttributeItems),
            'customAttributeItems' => $customAttributeItems,
        ];
    }

    /**
     * @return array{metadataOnly:bool, appliesToManifestHrefs:bool, policy:?string}
     */
    private static function manifestItemBaseResolution(?string $base): array
    {
        return [
            'metadataOnly' => $base !== null,
            'appliesToManifestHrefs' => false,
            'policy' => $base === null ? null : 'reported-not-applied-to-manifest-hrefs',
        ];
    }

    /**
     * @param array<string, array<string, mixed>> $manifestById
     * @param list<array<string, mixed>> $spine
     *
     * @return array<string, array<string, mixed>>
     */
    private static function attachNonSpineMissingManifestDiagnostics(array $manifestById, array $spine): array
    {
        $spineIds = [];
        foreach ($spine as $item) {
            $idref = $item['idref'] ?? null;
            if (is_string($idref) && $idref !== '') {
                $spineIds[$idref] = true;
            }
        }

        foreach ($manifestById as $id => $item) {
            if (isset($spineIds[$id]) || ($item['external'] ?? false) === true || ($item['exists'] ?? false) === true) {
                continue;
            }

            $diagnostics = is_array($item['diagnostics'] ?? null) ? array_values($item['diagnostics']) : [];
            $diagnostics[] = self::missingNonSpineManifestResourceDiagnostic($item);
            $manifestById[$id]['diagnostics'] = $diagnostics;
        }

        return $manifestById;
    }

    /**
     * @param array<string, mixed> $item
     *
     * @return array<string, mixed>
     */
    private static function missingNonSpineManifestResourceDiagnostic(array $item): array
    {
        return [
            'type' => 'missing-non-spine-manifest-resource',
            'id' => (string) ($item['id'] ?? ''),
            'href' => (string) ($item['href'] ?? ''),
            'target' => is_string($item['target'] ?? null) ? $item['target'] : null,
            'part' => is_string($item['part'] ?? null) ? $item['part'] : null,
            'mediaType' => is_string($item['mediaType'] ?? null) ? $item['mediaType'] : null,
            'message' => 'EPUB OPF manifest item outside the spine references a package part that is missing from the ZIP package',
        ];
    }

    /**
     * @param list<array<string, mixed>> $manifest
     *
     * @return array<string, mixed>
     */
    private static function manifestByteProvenanceReport(array $manifest): array
    {
        $items = [];
        $itemsById = [];
        $itemsByPart = [];
        $hashedItems = [];
        $encryptedItems = [];
        $missingItems = [];
        $externalItems = [];

        foreach ($manifest as $item) {
            $id = (string) ($item['id'] ?? '');
            $part = is_string($item['part'] ?? null) ? $item['part'] : null;
            $byteSha256 = is_string($item['byteSha256'] ?? null) ? $item['byteSha256'] : null;
            $summary = [
                'id' => $id,
                'href' => (string) ($item['href'] ?? ''),
                'target' => is_string($item['target'] ?? null) ? $item['target'] : null,
                'part' => $part,
                'external' => ($item['external'] ?? false) === true,
                'exists' => ($item['exists'] ?? false) === true,
                'mediaType' => is_string($item['mediaType'] ?? null) ? $item['mediaType'] : null,
                'byteLength' => is_int($item['byteLength'] ?? null) ? $item['byteLength'] : null,
                'crc32' => is_string($item['crc32'] ?? null) ? $item['crc32'] : null,
                'byteSha256' => $byteSha256,
                'encrypted' => self::isEncryptedManifestItem($item),
                'canExposeBytes' => ($item['canExposeBytes'] ?? false) === true,
                'diagnosticCount' => count(is_array($item['diagnostics'] ?? null) ? $item['diagnostics'] : []),
            ];

            $items[] = $summary;
            if ($id !== '') {
                $itemsById[$id] = $summary;
            }
            if ($part !== null && $part !== '' && !isset($itemsByPart[$part])) {
                $itemsByPart[$part] = $summary;
            }
            if ($byteSha256 !== null) {
                $hashedItems[] = $summary;
            }
            if ($summary['encrypted']) {
                $encryptedItems[] = $summary;
            }
            if (!$summary['external'] && !$summary['exists']) {
                $missingItems[] = $summary;
            }
            if ($summary['external']) {
                $externalItems[] = $summary;
            }
        }

        ksort($itemsById, SORT_STRING);
        ksort($itemsByPart, SORT_STRING);

        return [
            'present' => $items !== [],
            'itemCount' => count($items),
            'hashedItemCount' => count($hashedItems),
            'encryptedItemCount' => count($encryptedItems),
            'missingItemCount' => count($missingItems),
            'externalItemCount' => count($externalItems),
            'items' => $items,
            'itemsById' => $itemsById,
            'itemsByPart' => $itemsByPart,
            'hashedItems' => $hashedItems,
            'encryptedItems' => $encryptedItems,
            'missingItems' => $missingItems,
            'externalItems' => $externalItems,
        ];
    }

    /**
     * @param iterable<array<string, mixed>> $manifest
     *
     * @return list<array{
     *     part:string,
     *     itemCount:int,
     *     ids:list<string>,
     *     hrefs:list<string>,
     *     targets:list<string>,
     *     items:list<array<string, mixed>>
     * }>
     */
    private static function duplicateManifestPackagePartGroups(iterable $manifest): array
    {
        $itemsByPart = [];
        foreach ($manifest as $item) {
            $part = $item['part'] ?? null;
            if (!is_string($part) || $part === '') {
                continue;
            }

            $itemsByPart[$part][] = $item;
        }

        ksort($itemsByPart, SORT_STRING);

        $groups = [];
        foreach ($itemsByPart as $part => $items) {
            if (count($items) < 2) {
                continue;
            }

            $ids = [];
            $hrefs = [];
            $targets = [];
            $summaries = [];
            foreach ($items as $item) {
                $id = (string) ($item['id'] ?? '');
                $href = (string) ($item['href'] ?? '');
                $target = (string) ($item['target'] ?? '');
                $ids[] = $id;
                $hrefs[] = $href;
                $targets[] = $target;
                $summaries[] = [
                    'id' => $id,
                    'href' => $href,
                    'target' => $target,
                    'part' => $part,
                    'mediaType' => (string) ($item['mediaType'] ?? ''),
                    'exists' => ($item['exists'] ?? false) === true,
                ];
            }

            $groups[] = [
                'part' => $part,
                'itemCount' => count($items),
                'ids' => $ids,
                'hrefs' => $hrefs,
                'targets' => $targets,
                'items' => $summaries,
            ];
        }

        return $groups;
    }

    /**
     * @param array{
     *     part:string,
     *     itemCount:int,
     *     ids:list<string>,
     *     hrefs:list<string>,
     *     targets:list<string>,
     *     items:list<array<string, mixed>>
     * } $group
     *
     * @return array<string, mixed>
     */
    private static function duplicateManifestPackagePartDiagnostic(array $group): array
    {
        return [
            'type' => 'duplicate-manifest-package-part',
            'part' => $group['part'],
            'itemCount' => $group['itemCount'],
            'ids' => $group['ids'],
            'hrefs' => $group['hrefs'],
            'targets' => $group['targets'],
            'message' => 'EPUB OPF manifest contains multiple item ids for the same package part',
        ];
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
     *     exposure:array<string, mixed>,
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
                'exposure' => self::encryptionExposureReport([]),
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
            $mediaType = is_array($manifestItem) ? (string) $manifestItem['mediaType'] : self::mediaTypeFromPart($part);
            $properties = is_array($manifestItem) && is_array($manifestItem['properties'] ?? null)
                ? array_values($manifestItem['properties'])
                : [];
            $algorithm = $method instanceof \DOMElement ? self::nullableAttribute($method, 'Algorithm') : null;
            $obfuscatedFont = self::isObfuscatedFont($algorithm, $mediaType, $part);
            $role = self::encryptedResourceRole($mediaType, $part, $properties);
            $isCoverImage = in_array('cover-image', $properties, true);
            $attachmentCandidateBlocked = self::isAttachmentCandidate($mediaType, $part, $isCoverImage);
            $item = [
                'index' => $index,
                'uri' => $uri,
                'part' => $part,
                'algorithm' => $algorithm,
                'manifestId' => is_array($manifestItem) ? (string) $manifestItem['id'] : null,
                'mediaType' => $mediaType,
                'role' => $role,
                'exists' => $package->has($part),
                'obfuscatedFont' => $obfuscatedFont,
                'canExposeBytes' => false,
                'reviewPolicy' => $obfuscatedFont ? 'obfuscated-font-review' : 'encrypted-resource-review',
                'byteExposurePolicy' => $obfuscatedFont ? 'obfuscated-font-bytes-blocked' : 'encrypted-resource-bytes-blocked',
                'attachmentCandidateBlocked' => $attachmentCandidateBlocked,
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
            'exposure' => self::encryptionExposureReport($items),
            'diagnostics' => $diagnostics,
        ];
    }

    /**
     * @param list<array<string, mixed>> $items
     *
     * @return array<string, mixed>
     */
    private static function encryptionExposureReport(array $items): array
    {
        $reportItems = [];
        $roleCounts = [];
        $obfuscatedFontParts = [];
        $nonObfuscatedEncryptedParts = [];
        $blockedByteExposureCount = 0;
        $attachmentCandidateBlockedCount = 0;

        foreach ($items as $item) {
            $part = is_string($item['part'] ?? null) ? $item['part'] : null;
            $role = is_string($item['role'] ?? null) ? $item['role'] : 'asset';
            $obfuscatedFont = ($item['obfuscatedFont'] ?? false) === true;
            $canExposeBytes = ($item['canExposeBytes'] ?? false) === true;
            $attachmentCandidateBlocked = ($item['attachmentCandidateBlocked'] ?? false) === true;

            $roleCounts[$role] = ($roleCounts[$role] ?? 0) + 1;
            if (!$canExposeBytes) {
                ++$blockedByteExposureCount;
            }
            if ($attachmentCandidateBlocked) {
                ++$attachmentCandidateBlockedCount;
            }
            if ($part !== null && $part !== '') {
                if ($obfuscatedFont) {
                    $obfuscatedFontParts[] = $part;
                } else {
                    $nonObfuscatedEncryptedParts[] = $part;
                }
            }

            $reportItems[] = [
                'index' => (int) ($item['index'] ?? 0),
                'uri' => is_string($item['uri'] ?? null) ? $item['uri'] : null,
                'part' => $part,
                'manifestId' => is_string($item['manifestId'] ?? null) ? $item['manifestId'] : null,
                'mediaType' => is_string($item['mediaType'] ?? null) ? $item['mediaType'] : null,
                'role' => $role,
                'algorithm' => is_string($item['algorithm'] ?? null) ? $item['algorithm'] : null,
                'exists' => ($item['exists'] ?? false) === true,
                'obfuscatedFont' => $obfuscatedFont,
                'canExposeBytes' => $canExposeBytes,
                'reviewPolicy' => is_string($item['reviewPolicy'] ?? null)
                    ? $item['reviewPolicy']
                    : ($obfuscatedFont ? 'obfuscated-font-review' : 'encrypted-resource-review'),
                'byteExposurePolicy' => is_string($item['byteExposurePolicy'] ?? null)
                    ? $item['byteExposurePolicy']
                    : ($obfuscatedFont ? 'obfuscated-font-bytes-blocked' : 'encrypted-resource-bytes-blocked'),
                'attachmentCandidateBlocked' => $attachmentCandidateBlocked,
            ];
        }

        ksort($roleCounts);
        $obfuscatedFontParts = array_values(array_unique($obfuscatedFontParts));
        $nonObfuscatedEncryptedParts = array_values(array_unique($nonObfuscatedEncryptedParts));
        sort($obfuscatedFontParts, SORT_STRING);
        sort($nonObfuscatedEncryptedParts, SORT_STRING);

        return [
            'present' => $items !== [],
            'itemCount' => count($items),
            'blockedByteExposureCount' => $blockedByteExposureCount,
            'obfuscatedFontCount' => count($obfuscatedFontParts),
            'nonObfuscatedEncryptedCount' => count($nonObfuscatedEncryptedParts),
            'attachmentCandidateBlockedCount' => $attachmentCandidateBlockedCount,
            'roles' => array_keys($roleCounts),
            'roleCounts' => $roleCounts,
            'items' => $reportItems,
            'obfuscatedFontParts' => $obfuscatedFontParts,
            'nonObfuscatedEncryptedParts' => $nonObfuscatedEncryptedParts,
            'diagnostics' => [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function readOcfSidecars(ZipPackage $package): array
    {
        $manifest = $this->readOcfManifest($package);
        $metadata = $this->readOcfMetadata($package);
        $rights = $this->readOcfRights($package);
        $signatures = $this->readOcfSignatures($package);
        $supplemental = $this->readOcfSupplementalSidecars($package);
        $diagnostics = array_merge(
            is_array($manifest['diagnostics'] ?? null) ? $manifest['diagnostics'] : [],
            is_array($metadata['diagnostics'] ?? null) ? $metadata['diagnostics'] : [],
            is_array($rights['diagnostics'] ?? null) ? $rights['diagnostics'] : [],
            is_array($signatures['diagnostics'] ?? null) ? $signatures['diagnostics'] : [],
            is_array($supplemental['diagnostics'] ?? null) ? $supplemental['diagnostics'] : []
        );

        return [
            'present' => ($manifest['present'] ?? false) === true
                || ($metadata['present'] ?? false) === true
                || ($rights['present'] ?? false) === true
                || ($signatures['present'] ?? false) === true
                || ($supplemental['present'] ?? false) === true,
            'sidecarCount' => (($manifest['present'] ?? false) === true ? 1 : 0)
                + (($metadata['present'] ?? false) === true ? 1 : 0)
                + (($rights['present'] ?? false) === true ? 1 : 0)
                + (($signatures['present'] ?? false) === true ? 1 : 0)
                + (int) ($supplemental['sidecarCount'] ?? 0),
            'referenceCount' => (int) ($manifest['referenceCount'] ?? 0) + (int) ($metadata['referenceCount'] ?? 0) + (int) ($rights['referenceCount'] ?? 0) + (int) ($signatures['referenceCount'] ?? 0),
            'localReferenceCount' => (int) ($manifest['localReferenceCount'] ?? 0) + (int) ($metadata['localReferenceCount'] ?? 0) + (int) ($rights['localReferenceCount'] ?? 0) + (int) ($signatures['localReferenceCount'] ?? 0),
            'externalReferenceCount' => (int) ($manifest['externalReferenceCount'] ?? 0) + (int) ($metadata['externalReferenceCount'] ?? 0) + (int) ($rights['externalReferenceCount'] ?? 0) + (int) ($signatures['externalReferenceCount'] ?? 0),
            'missingReferenceCount' => (int) ($manifest['missingReferenceCount'] ?? 0) + (int) ($metadata['missingReferenceCount'] ?? 0) + (int) ($rights['missingReferenceCount'] ?? 0) + (int) ($signatures['missingReferenceCount'] ?? 0),
            'manifest' => $manifest,
            'metadata' => $metadata,
            'rights' => $rights,
            'signatures' => $signatures,
            'supplemental' => $supplemental,
            'diagnostics' => $diagnostics,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function readOcfManifest(ZipPackage $package): array
    {
        $part = '/META-INF/manifest.xml';
        if (!$package->has($part)) {
            return self::emptyOcfSidecarReport($part) + [
                'format' => null,
                'odfCompatible' => null,
                'version' => null,
                'itemCount' => 0,
                'items' => [],
                'itemsByPart' => [],
                'declaredPartCount' => 0,
                'missingItemCount' => 0,
                'sizeMismatchCount' => 0,
            ];
        }

        $report = self::ocfSidecarMetadata($package, $part) + [
            'present' => true,
            'valid' => true,
            'rootName' => null,
            'rootNamespace' => null,
            'format' => 'xml',
            'odfCompatible' => false,
            'version' => null,
            'itemCount' => 0,
            'items' => [],
            'itemsByPart' => [],
            'declaredPartCount' => 0,
            'missingItemCount' => 0,
            'sizeMismatchCount' => 0,
            'referenceCount' => 0,
            'localReferenceCount' => 0,
            'externalReferenceCount' => 0,
            'missingReferenceCount' => 0,
            'diagnostics' => [],
        ];

        try {
            $dom = self::loadXml($package->read($part), 'EPUB OCF manifest XML');
        } catch (\Throwable $exception) {
            $report['valid'] = false;
            $report['diagnostics'][] = [
                'type' => 'invalid-ocf-manifest-xml',
                'part' => $part,
                'message' => $exception->getMessage(),
            ];

            return $report;
        }

        $root = $dom->documentElement;
        $report['rootName'] = $root instanceof \DOMElement ? $root->localName : null;
        $report['rootNamespace'] = $root instanceof \DOMElement ? $root->namespaceURI : null;
        $odfCompatible = $root instanceof \DOMElement
            && $root->localName === 'manifest'
            && $root->namespaceURI === self::ODF_MANIFEST_NS;
        $report['format'] = $odfCompatible ? 'odf-manifest' : 'xml';
        $report['odfCompatible'] = $odfCompatible;
        $report['version'] = $root instanceof \DOMElement
            ? self::nullableNamespacedAttribute($root, self::ODF_MANIFEST_NS, 'version', 'manifest:version')
            : null;

        if (!$root instanceof \DOMElement) {
            $report['valid'] = false;

            return $report;
        }

        if (!$odfCompatible) {
            $report['diagnostics'][] = [
                'type' => 'nonstandard-ocf-manifest-root',
                'part' => $part,
                'rootName' => $report['rootName'],
                'rootNamespace' => $report['rootNamespace'],
                'message' => 'EPUB OCF manifest.xml does not use the ODF manifest root; it is reported but not used for rendition processing',
            ];

            return $report;
        }

        $items = [];
        $itemsByPart = [];
        foreach (self::childElements($root, 'file-entry', self::ODF_MANIFEST_NS) as $index => $entryElement) {
            $item = $this->readOcfManifestFileEntry($package, $entryElement, $index);
            foreach ($item['diagnostics'] as $diagnostic) {
                $report['diagnostics'][] = ['index' => $index] + $diagnostic;
            }

            $items[] = $item;
            if (is_string($item['part'] ?? null) && $item['part'] !== '') {
                $itemsByPart[$item['part']] = $item;
            }
        }

        $report['items'] = $items;
        $report['itemsByPart'] = $itemsByPart;
        $report['itemCount'] = count($items);
        $report['declaredPartCount'] = count(array_filter(
            $items,
            static fn (array $item): bool => is_string($item['part'] ?? null) && $item['part'] !== '',
        ));
        $report['missingItemCount'] = count(array_filter(
            $items,
            static fn (array $item): bool => ($item['exists'] ?? true) !== true,
        ));
        $report['sizeMismatchCount'] = count(array_filter(
            $items,
            static fn (array $item): bool => ($item['sizeMatches'] ?? true) === false,
        ));

        return self::ocfReportWithReferenceCounts($report, self::ocfItemReferences($items));
    }

    /**
     * @return array<string, mixed>
     */
    private function readOcfManifestFileEntry(ZipPackage $package, \DOMElement $entryElement, int $index): array
    {
        $fullPath = self::nullableNamespacedAttribute($entryElement, self::ODF_MANIFEST_NS, 'full-path', 'manifest:full-path');
        $mediaType = self::nullableNamespacedAttribute($entryElement, self::ODF_MANIFEST_NS, 'media-type', 'manifest:media-type');
        $version = self::nullableNamespacedAttribute($entryElement, self::ODF_MANIFEST_NS, 'version', 'manifest:version');
        $size = self::nullableNamespacedAttribute($entryElement, self::ODF_MANIFEST_NS, 'size', 'manifest:size');
        $encrypted = self::firstChildElement($entryElement, 'encryption-data', self::ODF_MANIFEST_NS) instanceof \DOMElement;
        $reference = null;
        $diagnostics = [];

        if ($fullPath === null) {
            $diagnostics[] = [
                'type' => 'missing-ocf-manifest-full-path',
                'message' => 'EPUB OCF manifest file-entry is missing manifest:full-path',
            ];
        } else {
            $reference = $this->ocfManifestEntryReference($package, $fullPath);
            foreach ($reference['diagnostics'] as $diagnostic) {
                $diagnostics[] = $diagnostic;
            }
        }

        $declaredSize = null;
        if ($size !== null) {
            if (preg_match('/^\d+$/', $size) === 1) {
                $declaredSize = (int) $size;
            } else {
                $diagnostics[] = [
                    'type' => 'invalid-ocf-manifest-size',
                    'size' => $size,
                    'message' => 'EPUB OCF manifest file-entry size must be a non-negative integer',
                ];
            }
        }

        $byteLength = is_array($reference) && is_int($reference['byteLength'] ?? null) ? $reference['byteLength'] : null;
        $sizeMatches = $declaredSize === null || $byteLength === null || $declaredSize === $byteLength;
        if (!$sizeMatches) {
            $diagnostics[] = [
                'type' => 'ocf-manifest-size-mismatch',
                'fullPath' => $fullPath,
                'declaredSize' => $declaredSize,
                'byteLength' => $byteLength,
                'message' => 'EPUB OCF manifest file-entry size does not match the ZIP entry byte length',
            ];
        }

        $part = is_array($reference) && is_string($reference['part'] ?? null) ? $reference['part'] : null;
        $directory = $fullPath === '/' || (is_string($fullPath) && str_ends_with($fullPath, '/'));
        $byteSha256 = null;
        $canExposeBytes = is_array($reference)
            && ($reference['exists'] ?? false) === true
            && $part !== null
            && !$directory
            && !$encrypted;
        if ($canExposeBytes) {
            try {
                $byteSha256 = hash('sha256', $package->read($part));
            } catch (\Throwable $exception) {
                $diagnostics[] = [
                    'type' => 'ocf-manifest-entry-bytes-unavailable',
                    'fullPath' => $fullPath,
                    'part' => $part,
                    'message' => $exception->getMessage(),
                ];
            }
        }

        return [
            'index' => $index,
            'fullPath' => $fullPath,
            'target' => is_array($reference) ? $reference['target'] : null,
            'part' => $part,
            'root' => $fullPath === '/',
            'directory' => $directory,
            'mediaType' => $mediaType,
            'version' => $version,
            'declaredSize' => $declaredSize,
            'sizeRaw' => $size,
            'sizeMatches' => $sizeMatches,
            'exists' => is_array($reference) ? (bool) ($reference['exists'] ?? false) : false,
            'byteLength' => $byteLength,
            'crc32' => is_array($reference) && is_string($reference['crc32'] ?? null) ? $reference['crc32'] : null,
            'byteSha256' => $byteSha256,
            'encrypted' => $encrypted,
            'canExposeBytes' => $canExposeBytes,
            'attributes' => self::elementAttributes($entryElement),
            'reference' => $reference,
            'diagnostics' => $diagnostics,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function ocfManifestEntryReference(ZipPackage $package, string $fullPath): array
    {
        $fullPath = trim($fullPath);
        if ($fullPath === '') {
            return self::missingOcfSidecarReference('manifest');
        }

        if (self::isExternalReference($fullPath)) {
            return [
                'target' => $fullPath,
                'part' => null,
                'external' => true,
                'exists' => false,
                'byteLength' => null,
                'crc32' => null,
                'diagnostics' => [[
                    'type' => 'ocf-manifest-external-reference',
                    'fullPath' => $fullPath,
                    'message' => 'EPUB OCF manifest file-entry points outside the package and was not fetched',
                ]],
            ];
        }

        $directory = $fullPath === '/' || str_ends_with($fullPath, '/');
        $path = $directory && $fullPath !== '/' ? rtrim($fullPath, '/') : $fullPath;
        try {
            $part = OpcPackagePath::canonicalPartNameFromUri($path, true);
        } catch (\InvalidArgumentException $exception) {
            return [
                'target' => null,
                'part' => null,
                'external' => false,
                'exists' => false,
                'byteLength' => null,
                'crc32' => null,
                'diagnostics' => [[
                    'type' => 'ocf-manifest-invalid-reference',
                    'fullPath' => $fullPath,
                    'message' => $exception->getMessage(),
                ]],
            ];
        }

        if ($part === '/') {
            return [
                'target' => '/',
                'part' => null,
                'external' => false,
                'exists' => true,
                'byteLength' => null,
                'crc32' => null,
                'diagnostics' => [],
            ];
        }

        if ($directory) {
            $part .= '/';
        }

        $exists = $package->has($part);
        $entry = $exists ? $package->entry($part) : null;
        $diagnostics = $exists ? [] : [[
            'type' => 'ocf-manifest-missing-reference',
            'fullPath' => $fullPath,
            'part' => $part,
            'message' => 'EPUB OCF manifest file-entry target is missing from the package',
        ]];

        return [
            'target' => $part,
            'part' => $part,
            'external' => false,
            'exists' => $exists,
            'byteLength' => $entry instanceof ZipPackageEntry ? $entry->uncompressedSize : null,
            'crc32' => $entry instanceof ZipPackageEntry ? $entry->crc32Hex() : null,
            'diagnostics' => $diagnostics,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function readOcfMetadata(ZipPackage $package): array
    {
        $part = '/META-INF/metadata.xml';
        if (!$package->has($part)) {
            return self::emptyOcfSidecarReport($part) + [
                'recommendedRoot' => null,
                'language' => null,
                'itemCount' => 0,
                'items' => [],
            ];
        }

        $report = self::ocfSidecarMetadata($package, $part) + [
            'present' => true,
            'valid' => true,
            'rootName' => null,
            'rootNamespace' => null,
            'recommendedRoot' => null,
            'language' => null,
            'itemCount' => 0,
            'items' => [],
            'referenceCount' => 0,
            'localReferenceCount' => 0,
            'externalReferenceCount' => 0,
            'missingReferenceCount' => 0,
            'diagnostics' => [],
        ];

        try {
            $dom = self::loadXml($package->read($part), 'EPUB OCF metadata XML');
        } catch (\Throwable $exception) {
            $report['valid'] = false;
            $report['diagnostics'][] = [
                'type' => 'invalid-ocf-metadata-xml',
                'part' => $part,
                'message' => $exception->getMessage(),
            ];

            return $report;
        }

        $root = $dom->documentElement;
        $report['rootName'] = $root instanceof \DOMElement ? $root->localName : null;
        $report['rootNamespace'] = $root instanceof \DOMElement ? $root->namespaceURI : null;
        $report['language'] = $root instanceof \DOMElement ? self::xmlLang($root) : null;
        $recommendedRoot = $root instanceof \DOMElement
            && $root->localName === 'metadata'
            && $root->namespaceURI === self::OCF_METADATA_NS;
        $report['recommendedRoot'] = $recommendedRoot;

        if (!$root instanceof \DOMElement) {
            $report['valid'] = false;

            return $report;
        }

        if (!$recommendedRoot) {
            $report['diagnostics'][] = [
                'type' => 'nonstandard-ocf-metadata-root',
                'part' => $part,
                'rootName' => $report['rootName'],
                'rootNamespace' => $report['rootNamespace'],
                'message' => 'EPUB OCF metadata.xml does not use the recommended metadata root in the IDPF container metadata namespace',
            ];
        }

        $items = [];
        foreach (self::childElements($root) as $index => $child) {
            $href = self::nullableAttribute($child, 'href')
                ?? self::nullableAttribute($child, 'src')
                ?? self::nullableAttribute($child, 'URI');
            $reference = $href === null
                ? null
                : $this->ocfSidecarReference($package, $href, 'metadata');
            $diagnostics = $reference === null ? [] : $reference['diagnostics'];
            $namespaceQualified = is_string($child->namespaceURI) && $child->namespaceURI !== '';

            if (!$namespaceQualified) {
                $diagnostics[] = [
                    'type' => 'unqualified-ocf-metadata-element',
                    'name' => $child->localName,
                    'message' => 'EPUB OCF metadata.xml should use namespace-qualified metadata elements',
                ];
            }

            foreach ($diagnostics as $diagnostic) {
                $report['diagnostics'][] = ['index' => $index] + $diagnostic;
            }

            $items[] = [
                'index' => $index,
                'name' => $child->localName,
                'namespace' => $child->namespaceURI,
                'namespaceQualified' => $namespaceQualified,
                'id' => self::nullableAttribute($child, 'id'),
                'href' => $href,
                'mediaType' => self::nullableAttribute($child, 'media-type'),
                'text' => self::normalizedText($child),
                'attributes' => self::elementAttributes($child),
                'reference' => $reference,
                'diagnostics' => $diagnostics,
            ];
        }

        $report['items'] = $items;
        $report['itemCount'] = count($items);

        return self::ocfReportWithReferenceCounts($report, self::ocfItemReferences($items));
    }

    /**
     * @return array<string, mixed>
     */
    private function readOcfRights(ZipPackage $package): array
    {
        $part = '/META-INF/rights.xml';
        if (!$package->has($part)) {
            return self::emptyOcfSidecarReport($part);
        }

        $report = self::ocfSidecarMetadata($package, $part) + [
            'present' => true,
            'valid' => true,
            'rootName' => null,
            'rootNamespace' => null,
            'language' => null,
            'itemCount' => 0,
            'items' => [],
            'referenceCount' => 0,
            'localReferenceCount' => 0,
            'externalReferenceCount' => 0,
            'missingReferenceCount' => 0,
            'diagnostics' => [],
        ];

        try {
            $dom = self::loadXml($package->read($part), 'EPUB OCF rights XML');
        } catch (\Throwable $exception) {
            $report['valid'] = false;
            $report['diagnostics'][] = [
                'type' => 'invalid-ocf-rights-xml',
                'part' => $part,
                'message' => $exception->getMessage(),
            ];

            return $report;
        }

        $root = $dom->documentElement;
        $report['rootName'] = $root instanceof \DOMElement ? $root->localName : null;
        $report['rootNamespace'] = $root instanceof \DOMElement ? $root->namespaceURI : null;
        $report['language'] = $root instanceof \DOMElement ? self::xmlLang($root) : null;

        if (!$root instanceof \DOMElement || $root->localName !== 'rights' || $root->namespaceURI !== self::OCF_CONTAINER_NS) {
            $report['valid'] = false;
            $report['diagnostics'][] = [
                'type' => 'invalid-ocf-rights-root',
                'part' => $part,
                'rootName' => $report['rootName'],
                'rootNamespace' => $report['rootNamespace'],
                'message' => 'EPUB OCF rights XML should use the OCF rights root element',
            ];
        }

        if (!$root instanceof \DOMElement) {
            return $report;
        }

        $items = [];
        foreach (self::childElements($root) as $index => $child) {
            $href = self::nullableAttribute($child, 'href')
                ?? self::nullableAttribute($child, 'src')
                ?? self::nullableAttribute($child, 'URI');
            $reference = $href === null
                ? null
                : $this->ocfSidecarReference($package, $href, 'rights');
            $diagnostics = $reference === null ? [] : $reference['diagnostics'];
            foreach ($diagnostics as $diagnostic) {
                $report['diagnostics'][] = ['index' => $index] + $diagnostic;
            }

            $items[] = [
                'index' => $index,
                'name' => $child->localName,
                'namespace' => $child->namespaceURI,
                'id' => self::nullableAttribute($child, 'id'),
                'href' => $href,
                'mediaType' => self::nullableAttribute($child, 'media-type'),
                'text' => self::normalizedText($child),
                'attributes' => self::elementAttributes($child),
                'reference' => $reference,
                'diagnostics' => $diagnostics,
            ];
        }

        $report['items'] = $items;
        $report['itemCount'] = count($items);
        $report = self::ocfReportWithReferenceCounts($report, self::ocfItemReferences($items));
        $report['valid'] = $report['valid'] && $report['diagnostics'] === [];

        return $report;
    }

    /**
     * @return array<string, mixed>
     */
    private function readOcfSignatures(ZipPackage $package): array
    {
        $part = '/META-INF/signatures.xml';
        if (!$package->has($part)) {
            return self::emptyOcfSidecarReport($part) + [
                'signatureCount' => 0,
                'items' => [],
                'references' => [],
            ];
        }

        $report = self::ocfSidecarMetadata($package, $part) + [
            'present' => true,
            'valid' => true,
            'rootName' => null,
            'rootNamespace' => null,
            'signatureCount' => 0,
            'items' => [],
            'references' => [],
            'referenceCount' => 0,
            'localReferenceCount' => 0,
            'externalReferenceCount' => 0,
            'missingReferenceCount' => 0,
            'diagnostics' => [],
        ];

        try {
            $dom = self::loadXml($package->read($part), 'EPUB OCF signatures XML');
        } catch (\Throwable $exception) {
            $report['valid'] = false;
            $report['diagnostics'][] = [
                'type' => 'invalid-ocf-signatures-xml',
                'part' => $part,
                'message' => $exception->getMessage(),
            ];

            return $report;
        }

        $root = $dom->documentElement;
        $report['rootName'] = $root instanceof \DOMElement ? $root->localName : null;
        $report['rootNamespace'] = $root instanceof \DOMElement ? $root->namespaceURI : null;

        if (!$root instanceof \DOMElement || $root->localName !== 'signatures' || $root->namespaceURI !== self::OCF_CONTAINER_NS) {
            $report['valid'] = false;
            $report['diagnostics'][] = [
                'type' => 'invalid-ocf-signatures-root',
                'part' => $part,
                'rootName' => $report['rootName'],
                'rootNamespace' => $report['rootNamespace'],
                'message' => 'EPUB OCF signatures XML should use the OCF signatures root element',
            ];
        }

        $items = [];
        $references = [];
        foreach ($dom->getElementsByTagNameNS(self::XMLDSIG_NS, 'Signature') as $signatureIndex => $signatureElement) {
            if (!$signatureElement instanceof \DOMElement) {
                continue;
            }

            $signature = $this->readOcfSignature($package, $signatureElement, $signatureIndex);
            foreach ($signature['diagnostics'] as $diagnostic) {
                $report['diagnostics'][] = [
                    'signatureIndex' => $signatureIndex,
                    'signatureId' => $signature['id'],
                ] + $diagnostic;
            }
            array_push($references, ...$signature['references']);
            $items[] = $signature;
        }

        $report['items'] = $items;
        $report['signatureCount'] = count($items);
        $report['references'] = $references;
        $report = self::ocfReportWithReferenceCounts($report, $references);
        $report['valid'] = $report['valid'] && $report['diagnostics'] === [];

        return $report;
    }

    /**
     * @return array<string, mixed>
     */
    private function readOcfSupplementalSidecars(ZipPackage $package): array
    {
        $items = [];
        $itemsByPart = [];
        $diagnostics = [];
        $totalByteLength = 0;
        $totalCompressedByteLength = 0;
        $knownParts = [
            '/META-INF/container.xml' => true,
            '/META-INF/encryption.xml' => true,
            '/META-INF/manifest.xml' => true,
            '/META-INF/metadata.xml' => true,
            '/META-INF/rights.xml' => true,
            '/META-INF/signatures.xml' => true,
        ];

        foreach ($package->entries() as $entry) {
            if ($entry->isDirectory()) {
                continue;
            }

            $part = OpcPackagePath::canonicalPartName($entry->name);
            if (!str_starts_with($part, '/META-INF/') || isset($knownParts[$part])) {
                continue;
            }

            $relative = substr($part, strlen('/META-INF/'));
            if ($relative === '' || str_contains($relative, '/')) {
                continue;
            }

            $item = $this->readOcfSupplementalSidecar($package, $entry, $part, count($items));
            foreach ($item['diagnostics'] as $diagnostic) {
                $diagnostics[] = [
                    'index' => $item['index'],
                    'part' => $part,
                ] + $diagnostic;
            }

            $items[] = $item;
            $itemsByPart[$part] = $item;
            $totalByteLength += (int) ($item['byteLength'] ?? 0);
            $totalCompressedByteLength += (int) ($item['compressedByteLength'] ?? 0);
        }

        $xmlItems = array_values(array_filter(
            $items,
            static fn (array $item): bool => ($item['xmlSidecar'] ?? false) === true,
        ));
        $invalidXmlItems = array_values(array_filter(
            $xmlItems,
            static fn (array $item): bool => ($item['xmlValid'] ?? true) === false,
        ));
        $vendorItems = array_values(array_filter(
            $items,
            static fn (array $item): bool => is_string($item['vendor'] ?? null) && $item['vendor'] !== '',
        ));
        $vendors = [];
        foreach ($vendorItems as $item) {
            $vendor = (string) ($item['vendor'] ?? '');
            if ($vendor !== '') {
                $vendors[$vendor] = $vendor;
            }
        }

        return [
            'present' => $items !== [],
            'sidecarCount' => count($items),
            'itemCount' => count($items),
            'xmlSidecarCount' => count($xmlItems),
            'invalidXmlSidecarCount' => count($invalidXmlItems),
            'vendorSidecarCount' => count($vendorItems),
            'vendors' => array_values($vendors),
            'totalByteLength' => $totalByteLength,
            'totalCompressedByteLength' => $totalCompressedByteLength,
            'items' => $items,
            'itemsByPart' => $itemsByPart,
            'diagnosticCount' => count($diagnostics),
            'diagnostics' => $diagnostics,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function readOcfSupplementalSidecar(
        ZipPackage $package,
        ZipPackageEntry $entry,
        string $part,
        int $index
    ): array {
        $provenance = self::zipEntryProvenance($entry);
        $extension = strtolower((string) pathinfo($part, PATHINFO_EXTENSION));
        $mediaType = self::ocfSupplementalMediaType($part);
        $classification = self::ocfSupplementalSidecarClassification($part);
        $diagnostics = [];
        $bytes = null;
        $byteSha256 = null;

        if (($provenance['canExposeBytes'] ?? false) === true) {
            try {
                $bytes = $package->read($part);
                $byteSha256 = hash('sha256', $bytes);
            } catch (\Throwable $exception) {
                $diagnostics[] = [
                    'type' => 'supplemental-ocf-sidecar-bytes-unavailable',
                    'message' => $exception->getMessage(),
                ];
            }
        } else {
            $diagnostics[] = [
                'type' => 'unsupported-supplemental-ocf-sidecar-compression',
                'compressionMethod' => $provenance['compressionMethod'],
                'compressionMethodName' => $provenance['compressionMethodName'],
                'message' => 'EPUB supplemental OCF sidecar uses unsupported ZIP compression and remains metadata-only',
            ];
        }

        $xmlSidecar = $extension === 'xml';
        $xmlValid = null;
        $rootName = null;
        $rootNamespace = null;
        $rootLanguage = null;
        $rootAttributes = [];
        $childElementCount = 0;

        if ($xmlSidecar && $bytes !== null) {
            try {
                $dom = self::loadXml($bytes, 'EPUB supplemental OCF sidecar XML');
                $root = $dom->documentElement;
                $xmlValid = $root instanceof \DOMElement;
                if ($root instanceof \DOMElement) {
                    $rootName = $root->localName;
                    $rootNamespace = $root->namespaceURI;
                    $rootLanguage = self::xmlLang($root);
                    $rootAttributes = self::elementAttributes($root);
                    $childElementCount = count(self::childElements($root));
                }
            } catch (\Throwable $exception) {
                $xmlValid = false;
                $diagnostics[] = [
                    'type' => 'invalid-supplemental-ocf-sidecar-xml',
                    'message' => $exception->getMessage(),
                ];
            }
        }

        return [
            'index' => $index,
            'part' => $part,
            'packagePath' => ltrim($part, '/'),
            'fileName' => basename($part),
            'kind' => $classification['kind'],
            'vendor' => $classification['vendor'],
            'mediaType' => $mediaType,
            'byteLength' => $provenance['byteLength'],
            'compressedByteLength' => $provenance['compressedByteLength'],
            'compressionMethod' => $provenance['compressionMethod'],
            'compressionMethodName' => $provenance['compressionMethodName'],
            'compressionSupported' => $provenance['compressionSupported'],
            'crc32' => $provenance['crc32'],
            'byteSha256' => $byteSha256,
            'canExposeBytes' => false,
            'canExposeAsDocumentMedia' => false,
            'metadataOnly' => true,
            'xmlSidecar' => $xmlSidecar,
            'xmlValid' => $xmlValid,
            'rootName' => $rootName,
            'rootNamespace' => $rootNamespace,
            'rootLanguage' => $rootLanguage,
            'rootAttributes' => $rootAttributes,
            'childElementCount' => $childElementCount,
            'diagnostics' => $diagnostics,
        ];
    }

    /**
     * @return array{kind:string, vendor:?string}
     */
    private static function ocfSupplementalSidecarClassification(string $part): array
    {
        return match (strtolower(basename($part))) {
            'com.apple.ibooks.display-options.xml' => [
                'kind' => 'ibooks-display-options',
                'vendor' => 'ibooks',
            ],
            'calibre_bookmarks.txt' => [
                'kind' => 'calibre-bookmarks',
                'vendor' => 'calibre',
            ],
            default => [
                'kind' => 'supplemental-meta-inf-sidecar',
                'vendor' => null,
            ],
        };
    }

    private static function ocfSupplementalMediaType(string $part): ?string
    {
        $mediaType = self::mediaTypeFromPart($part);
        if ($mediaType !== null) {
            return $mediaType;
        }

        return match (strtolower((string) pathinfo($part, PATHINFO_EXTENSION))) {
            'json' => 'application/json',
            'opf' => self::OPF_MEDIA_TYPE,
            'txt' => 'text/plain',
            'xml' => 'application/xml',
            default => null,
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function readOcfSignature(ZipPackage $package, \DOMElement $signature, int $signatureIndex): array
    {
        $signedInfo = self::firstChildElement($signature, 'SignedInfo', self::XMLDSIG_NS);
        $canonicalization = $signedInfo instanceof \DOMElement
            ? self::firstChildElement($signedInfo, 'CanonicalizationMethod', self::XMLDSIG_NS)
            : null;
        $signatureMethod = $signedInfo instanceof \DOMElement
            ? self::firstChildElement($signedInfo, 'SignatureMethod', self::XMLDSIG_NS)
            : null;
        $signatureValue = self::firstChildElement($signature, 'SignatureValue', self::XMLDSIG_NS);
        $references = [];
        $diagnostics = [];

        if (!$signedInfo instanceof \DOMElement) {
            $diagnostics[] = [
                'type' => 'ocf-signature-missing-signed-info',
                'message' => 'EPUB OCF signature is missing ds:SignedInfo',
            ];
        } else {
            foreach (self::childElements($signedInfo, 'Reference', self::XMLDSIG_NS) as $referenceIndex => $referenceElement) {
                $reference = $this->readOcfSignatureReference($package, $referenceElement, $referenceIndex);
                foreach ($reference['diagnostics'] as $diagnostic) {
                    $diagnostics[] = [
                        'referenceIndex' => $referenceIndex,
                        'uri' => $reference['uri'],
                    ] + $diagnostic;
                }
                $references[] = $reference;
            }
        }

        return [
            'index' => $signatureIndex,
            'id' => self::nullableAttribute($signature, 'Id') ?? self::nullableAttribute($signature, 'ID'),
            'canonicalizationMethod' => $canonicalization instanceof \DOMElement
                ? self::nullableAttribute($canonicalization, 'Algorithm')
                : null,
            'signatureMethod' => $signatureMethod instanceof \DOMElement
                ? self::nullableAttribute($signatureMethod, 'Algorithm')
                : null,
            'signatureValuePresent' => $signatureValue instanceof \DOMElement && trim($signatureValue->textContent) !== '',
            'referenceCount' => count($references),
            'references' => $references,
            'diagnostics' => $diagnostics,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function readOcfSignatureReference(ZipPackage $package, \DOMElement $referenceElement, int $referenceIndex): array
    {
        $uri = self::nullableAttribute($referenceElement, 'URI');
        $reference = $uri === null
            ? self::missingOcfSidecarReference('signature')
            : $this->ocfSidecarReference($package, $uri, 'signature');
        $digestMethod = self::firstChildElement($referenceElement, 'DigestMethod', self::XMLDSIG_NS);
        $digestValue = self::firstChildElement($referenceElement, 'DigestValue', self::XMLDSIG_NS);

        return [
            'index' => $referenceIndex,
            'uri' => $uri,
            'target' => $reference['target'],
            'part' => $reference['part'],
            'fragment' => $reference['fragment'],
            'fragmentKind' => $reference['fragmentKind'],
            'epubCfi' => $reference['epubCfi'],
            'mediaFragment' => $reference['mediaFragment'],
            'external' => $reference['external'],
            'exists' => $reference['exists'],
            'byteLength' => $reference['byteLength'],
            'compressedByteLength' => $reference['compressedByteLength'],
            'compressionMethod' => $reference['compressionMethod'],
            'compressionMethodName' => $reference['compressionMethodName'],
            'compressionSupported' => $reference['compressionSupported'],
            'crc32' => $reference['crc32'],
            'byteSha256' => $reference['byteSha256'],
            'canExposeBytes' => $reference['canExposeBytes'],
            'digestMethod' => $digestMethod instanceof \DOMElement
                ? self::nullableAttribute($digestMethod, 'Algorithm')
                : null,
            'digestValue' => $digestValue instanceof \DOMElement ? trim($digestValue->textContent) : null,
            'diagnostics' => $reference['diagnostics'],
        ];
    }

    /**
     * @return array{
     *     target:?string,
     *     part:?string,
     *     fragment:?string,
     *     fragmentKind:?string,
     *     epubCfi:?array<string, mixed>,
     *     mediaFragment:?array<string, mixed>,
     *     external:bool,
     *     exists:bool,
     *     byteLength:?int,
     *     compressedByteLength:?int,
     *     compressionMethod:?int,
     *     compressionMethodName:?string,
     *     compressionSupported:?bool,
     *     crc32:?string,
     *     byteSha256:?string,
     *     canExposeBytes:bool,
     *     diagnostics:list<array<string, mixed>>
     * }
     */
    private function ocfSidecarReference(ZipPackage $package, string $uri, string $context): array
    {
        $uri = trim($uri);
        $fragmentFields = self::targetFragmentFields(null);
        if ($uri === '') {
            return self::missingOcfSidecarReference($context);
        }

        if (self::isExternalReference($uri)) {
            $fragmentFields = self::targetFragmentFields($uri);

            return [
                'target' => $uri,
                'part' => null,
                'fragment' => $fragmentFields['fragment'],
                'fragmentKind' => $fragmentFields['fragmentKind'],
                'epubCfi' => $fragmentFields['epubCfi'],
                'mediaFragment' => $fragmentFields['mediaFragment'],
                'external' => true,
                'exists' => false,
                'byteLength' => null,
                'compressedByteLength' => null,
                'compressionMethod' => null,
                'compressionMethodName' => null,
                'compressionSupported' => null,
                'crc32' => null,
                'byteSha256' => null,
                'canExposeBytes' => false,
                'diagnostics' => [[
                    'type' => 'ocf-' . $context . '-remote-reference',
                    'uri' => $uri,
                    'message' => 'EPUB OCF ' . $context . ' reference points outside the package and was not fetched',
                ]],
            ];
        }

        try {
            $target = self::ocfPackageReferenceTarget($uri, match ($context) {
                'manifest' => '/META-INF/manifest.xml',
                'metadata' => '/META-INF/metadata.xml',
                'signature' => '/META-INF/signatures.xml',
                default => '/META-INF/rights.xml',
            });
        } catch (\InvalidArgumentException $exception) {
            return [
                'target' => null,
                'part' => null,
                'fragment' => $fragmentFields['fragment'],
                'fragmentKind' => $fragmentFields['fragmentKind'],
                'epubCfi' => $fragmentFields['epubCfi'],
                'mediaFragment' => $fragmentFields['mediaFragment'],
                'external' => false,
                'exists' => false,
                'byteLength' => null,
                'compressedByteLength' => null,
                'compressionMethod' => null,
                'compressionMethodName' => null,
                'compressionSupported' => null,
                'crc32' => null,
                'byteSha256' => null,
                'canExposeBytes' => false,
                'diagnostics' => [[
                    'type' => 'ocf-' . $context . '-invalid-reference',
                    'uri' => $uri,
                    'message' => $exception->getMessage(),
                ]],
            ];
        }

        $part = OpcPackagePath::stripQueryAndFragment($target);
        $fragmentFields = self::targetFragmentFields($target);
        $exists = $package->has($part);
        $entry = $exists ? $package->entry($part) : null;
        $provenance = self::zipEntryProvenance($entry);
        $canExposeBytes = ($provenance['canExposeBytes'] ?? false) === true;
        $byteSha256 = null;
        $diagnostics = $exists ? [] : [[
            'type' => 'ocf-' . $context . '-missing-reference',
            'uri' => $uri,
            'part' => $part,
            'message' => 'EPUB OCF ' . $context . ' reference target is missing from the package',
        ]];
        if ($exists && $canExposeBytes) {
            try {
                $byteSha256 = hash('sha256', $package->read($part));
            } catch (\Throwable $exception) {
                $canExposeBytes = false;
                $diagnostics[] = [
                    'type' => 'ocf-' . $context . '-reference-bytes-unavailable',
                    'uri' => $uri,
                    'part' => $part,
                    'message' => $exception->getMessage(),
                ];
            }
        }

        return [
            'target' => $target,
            'part' => $part,
            'fragment' => $fragmentFields['fragment'],
            'fragmentKind' => $fragmentFields['fragmentKind'],
            'epubCfi' => $fragmentFields['epubCfi'],
            'mediaFragment' => $fragmentFields['mediaFragment'],
            'external' => false,
            'exists' => $exists,
            'byteLength' => $provenance['byteLength'],
            'compressedByteLength' => $provenance['compressedByteLength'],
            'compressionMethod' => $provenance['compressionMethod'],
            'compressionMethodName' => $provenance['compressionMethodName'],
            'compressionSupported' => $provenance['compressionSupported'],
            'crc32' => $provenance['crc32'],
            'byteSha256' => $byteSha256,
            'canExposeBytes' => $canExposeBytes,
            'diagnostics' => $diagnostics,
        ];
    }

    private static function ocfPackageReferenceTarget(string $uri, string $sidecarPart): string
    {
        if (str_contains($uri, "\0") || str_contains($uri, '\\')) {
            throw new \InvalidArgumentException('EPUB OCF sidecar references must use slash-separated package paths');
        }

        if (str_starts_with($uri, '#')) {
            return $sidecarPart . $uri;
        }

        $split = strcspn($uri, '?#');
        $path = substr($uri, 0, $split);
        $suffix = substr($uri, $split);
        if ($path === '') {
            throw new \InvalidArgumentException('EPUB OCF sidecar reference path must not be empty');
        }

        return OpcPackagePath::canonicalPartNameFromUri($path) . $suffix;
    }

    /**
     * @return array<string, mixed>
     */
    private static function emptyOcfSidecarReport(string $part): array
    {
        return [
            'present' => false,
            'part' => null,
            'expectedPart' => $part,
            'byteLength' => null,
            'crc32' => null,
            'byteSha256' => null,
            'valid' => null,
            'rootName' => null,
            'rootNamespace' => null,
            'referenceCount' => 0,
            'localReferenceCount' => 0,
            'externalReferenceCount' => 0,
            'missingReferenceCount' => 0,
            'diagnostics' => [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function ocfSidecarMetadata(ZipPackage $package, string $part): array
    {
        $entry = $package->entry($part);
        $bytes = $package->read($part);

        return [
            'part' => $part,
            'expectedPart' => $part,
            'byteLength' => $entry->uncompressedSize,
            'crc32' => $entry->crc32Hex(),
            'byteSha256' => hash('sha256', $bytes),
        ];
    }

    /**
     * @param list<array<string, mixed>> $items
     *
     * @return list<array<string, mixed>>
     */
    private static function ocfItemReferences(array $items): array
    {
        $references = [];
        foreach ($items as $item) {
            if (is_array($item['reference'] ?? null)) {
                $references[] = $item['reference'];
            }
        }

        return $references;
    }

    /**
     * @param array<string, mixed> $report
     * @param list<array<string, mixed>> $references
     *
     * @return array<string, mixed>
     */
    private static function ocfReportWithReferenceCounts(array $report, array $references): array
    {
        $report['referenceCount'] = count($references);
        $report['localReferenceCount'] = count(array_filter(
            $references,
            static fn (array $reference): bool => ($reference['external'] ?? false) !== true
                && ($reference['exists'] ?? false) === true,
        ));
        $report['externalReferenceCount'] = count(array_filter(
            $references,
            static fn (array $reference): bool => ($reference['external'] ?? false) === true,
        ));
        $report['missingReferenceCount'] = count(array_filter(
            $references,
            static fn (array $reference): bool => ($reference['external'] ?? false) !== true
                && ($reference['exists'] ?? true) !== true,
        ));

        return $report;
    }

    /**
     * @return array{
     *     target:null,
     *     part:null,
     *     fragment:null,
     *     fragmentKind:null,
     *     epubCfi:null,
     *     mediaFragment:null,
     *     external:false,
     *     exists:false,
     *     byteLength:null,
     *     compressedByteLength:null,
     *     compressionMethod:null,
     *     compressionMethodName:null,
     *     compressionSupported:null,
     *     crc32:null,
     *     byteSha256:null,
     *     canExposeBytes:false,
     *     diagnostics:list<array<string, mixed>>
     * }
     */
    private static function missingOcfSidecarReference(string $context): array
    {
        return [
            'target' => null,
            'part' => null,
            'fragment' => null,
            'fragmentKind' => null,
            'epubCfi' => null,
            'mediaFragment' => null,
            'external' => false,
            'exists' => false,
            'byteLength' => null,
            'compressedByteLength' => null,
            'compressionMethod' => null,
            'compressionMethodName' => null,
            'compressionSupported' => null,
            'crc32' => null,
            'byteSha256' => null,
            'canExposeBytes' => false,
            'diagnostics' => [[
                'type' => 'ocf-' . $context . '-missing-reference',
                'message' => 'EPUB OCF ' . $context . ' reference is missing a URI',
            ]],
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
        $encryptionByPart = self::encryptionItemsByPart($encryption);

        foreach ($manifestById as $id => $item) {
            $entries = $encryptionByPart[(string) $item['part']] ?? [];
            if ($entries === []) {
                continue;
            }

            $manifestById[$id]['encrypted'] = true;
            $manifestById[$id]['canExposeBytes'] = false;
            $manifestById[$id]['byteSha256'] = null;
            $manifestById[$id]['encryption'] = [
                'items' => $entries,
                'algorithm' => $entries[0]['algorithm'] ?? null,
                'role' => $entries[0]['role'] ?? self::encryptedResourceRole(
                    is_string($item['mediaType'] ?? null) ? $item['mediaType'] : null,
                    (string) $item['part'],
                    is_array($item['properties'] ?? null) ? array_values($item['properties']) : [],
                ),
                'obfuscatedFont' => self::containsObfuscatedFont($entries),
                'canExposeBytes' => false,
                'reviewPolicy' => self::containsObfuscatedFont($entries) ? 'obfuscated-font-review' : 'encrypted-resource-review',
                'byteExposurePolicy' => self::containsObfuscatedFont($entries) ? 'obfuscated-font-bytes-blocked' : 'encrypted-resource-bytes-blocked',
                'attachmentCandidateBlocked' => count(array_filter(
                    $entries,
                    static fn (array $entry): bool => ($entry['attachmentCandidateBlocked'] ?? false) === true,
                )) > 0,
            ];
        }

        return $manifestById;
    }

    /**
     * @param array<string, mixed> $encryption
     *
     * @return array<string, list<array<string, mixed>>>
     */
    private static function encryptionItemsByPart(array $encryption): array
    {
        $itemsByPart = [];
        foreach ($encryption['items'] ?? [] as $item) {
            if (!is_array($item) || !isset($item['part'])) {
                continue;
            }

            $part = $item['part'];
            if (!is_string($part) || $part === '') {
                continue;
            }

            $itemsByPart[$part][] = $item;
        }

        return $itemsByPart;
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
            $handlerCanExposeBytes = is_array($handler)
                && ($handler['exists'] ?? false) === true
                && (bool) ($handler['canExposeBytes'] ?? true);
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
                'handlerCanExposeBytes' => $handlerCanExposeBytes,
                'handlerByteLength' => $entry instanceof ZipPackageEntry ? $entry->uncompressedSize : null,
                'handlerByteSha256' => $handlerCanExposeBytes && $handlerPart !== null
                    ? hash('sha256', $package->read($handlerPart))
                    : null,
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

                    if (!self::mediaTypeBaseEquals($manifestItem['mediaType'] ?? null, self::SMIL_MEDIA_TYPE)) {
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
     * @param array<string, mixed> $metadata
     * @param array<string, array<string, mixed>> $manifestById
     *
     * @return array<string, mixed>
     */
    private static function mediaOverlayStyleReport(array $metadata, array $manifestById): array
    {
        $styleProperties = [
            'media:active-class' => 'active-class',
            'media:playback-active-class' => 'playback-active-class',
        ];
        $entries = [];
        foreach ((is_array($metadata['raw'] ?? null) ? $metadata['raw'] : []) as $rawIndex => $entry) {
            if (!is_array($entry) || ($entry['type'] ?? null) !== 'meta') {
                continue;
            }

            $property = is_string($entry['property'] ?? null) ? $entry['property'] : null;
            if ($property === null || !isset($styleProperties[$property])) {
                continue;
            }

            $entries[] = [
                'index' => $rawIndex,
                'property' => $property,
                'kind' => $styleProperties[$property],
                'entry' => $entry,
            ];
        }

        $overlayReferences = self::mediaOverlayReferences($manifestById);
        $publication = self::emptyMediaOverlayStyleSummary(null);
        $overlaysById = [];
        $items = [];
        $diagnostics = [];

        foreach ($entries as $entryRecord) {
            $index = (int) $entryRecord['index'];
            $property = (string) $entryRecord['property'];
            $kind = (string) $entryRecord['kind'];
            $entry = is_array($entryRecord['entry']) ? $entryRecord['entry'] : [];
            $class = self::metadataEntryValue($entry);
            $classTokens = self::spaceDelimited($class);
            $refines = is_string($entry['refines'] ?? null) ? $entry['refines'] : null;
            $subjectId = self::metadataRefinementSubject($refines);
            $classField = $kind === 'active-class' ? 'activeClass' : 'playbackActiveClass';
            $tokensField = $kind === 'active-class' ? 'activeClassTokens' : 'playbackActiveClassTokens';
            $metadataField = $kind === 'active-class' ? 'activeClassMetadata' : 'playbackActiveClassMetadata';
            $itemDiagnostics = [];
            $item = [
                'index' => $index,
                'property' => $property,
                'kind' => $kind,
                'class' => $class,
                'classTokens' => $classTokens,
                'validClass' => $class !== '',
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

            if ($class === '') {
                $itemDiagnostics[] = [
                    'type' => 'empty-media-overlay-style-class',
                    'property' => $property,
                    'message' => 'EPUB media-overlay style class metadata must not be empty',
                ];
            }

            $manifestItem = null;
            $canAttachToOverlay = false;
            if ($subjectId !== null) {
                $manifestItem = $manifestById[$subjectId] ?? null;
                $item['manifestId'] = $subjectId;
                if (!is_array($manifestItem)) {
                    $itemDiagnostics[] = [
                        'type' => 'media-overlay-style-refines-missing-manifest-item',
                        'subjectId' => $subjectId,
                        'property' => $property,
                        'message' => 'EPUB media-overlay style class refinement does not reference an OPF manifest item',
                    ];
                } else {
                    $item['manifestHref'] = (string) ($manifestItem['href'] ?? '');
                    $item['manifestTarget'] = is_string($manifestItem['target'] ?? null) ? $manifestItem['target'] : null;
                    $item['manifestPart'] = is_string($manifestItem['part'] ?? null) ? $manifestItem['part'] : null;
                    $item['manifestMediaType'] = (string) ($manifestItem['mediaType'] ?? '');
                    $item['referencedBy'] = $overlayReferences[$subjectId] ?? [];

                    if (!self::mediaTypeBaseEquals($manifestItem['mediaType'] ?? null, self::SMIL_MEDIA_TYPE)) {
                        $itemDiagnostics[] = [
                            'type' => 'media-overlay-style-refines-non-overlay-manifest-item',
                            'subjectId' => $subjectId,
                            'mediaType' => (string) ($manifestItem['mediaType'] ?? ''),
                            'property' => $property,
                            'message' => 'EPUB media-overlay style class refinement should reference an OPF media-overlay SMIL item',
                        ];
                    } else {
                        $canAttachToOverlay = true;
                    }
                }
            }

            if ($subjectId === null) {
                if ($class !== '' && is_array($publication[$metadataField])) {
                    $itemDiagnostics[] = [
                        'type' => 'duplicate-publication-media-overlay-style-class',
                        'property' => $property,
                        'message' => 'EPUB package contains more than one publication-level media-overlay style class entry for this property',
                    ];
                }
            } elseif ($canAttachToOverlay) {
                if (!isset($overlaysById[$subjectId])) {
                    $overlaysById[$subjectId] = self::emptyMediaOverlayStyleSummary($subjectId);
                    $overlaysById[$subjectId]['manifestId'] = $subjectId;
                    $overlaysById[$subjectId]['manifestHref'] = $item['manifestHref'];
                    $overlaysById[$subjectId]['manifestTarget'] = $item['manifestTarget'];
                    $overlaysById[$subjectId]['manifestPart'] = $item['manifestPart'];
                    $overlaysById[$subjectId]['manifestMediaType'] = $item['manifestMediaType'];
                    $overlaysById[$subjectId]['referencedBy'] = $item['referencedBy'];
                }

                if ($class !== '' && is_array($overlaysById[$subjectId][$metadataField])) {
                    $itemDiagnostics[] = [
                        'type' => 'duplicate-media-overlay-style-class',
                        'subjectId' => $subjectId,
                        'property' => $property,
                        'message' => 'EPUB media-overlay manifest item has more than one style class entry for this property',
                    ];
                }
            }

            foreach ($itemDiagnostics as $diagnostic) {
                $diagnostics[] = ['index' => $index] + $diagnostic;
            }
            $item['diagnostics'] = $itemDiagnostics;

            if ($subjectId === null) {
                if ($class !== '' && !is_array($publication[$metadataField])) {
                    $publication[$classField] = $class;
                    $publication[$tokensField] = $classTokens;
                    $publication[$metadataField] = $item;
                }
                $publication['items'][] = $item;
                array_push($publication['diagnostics'], ...$itemDiagnostics);
            } elseif ($canAttachToOverlay) {
                if ($class !== '' && !is_array($overlaysById[$subjectId][$metadataField])) {
                    $overlaysById[$subjectId][$classField] = $class;
                    $overlaysById[$subjectId][$tokensField] = $classTokens;
                    $overlaysById[$subjectId][$metadataField] = $item;
                }
                $overlaysById[$subjectId]['items'][] = $item;
                array_push($overlaysById[$subjectId]['diagnostics'], ...$itemDiagnostics);
            }

            $items[] = $item;
        }

        return [
            'present' => $entries !== [],
            'publication' => $publication,
            'activeClass' => $publication['activeClass'],
            'activeClassTokens' => $publication['activeClassTokens'],
            'activeClassMetadata' => $publication['activeClassMetadata'],
            'playbackActiveClass' => $publication['playbackActiveClass'],
            'playbackActiveClassTokens' => $publication['playbackActiveClassTokens'],
            'playbackActiveClassMetadata' => $publication['playbackActiveClassMetadata'],
            'overlaysById' => $overlaysById,
            'items' => $items,
            'diagnostics' => $diagnostics,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function emptyMediaOverlayStyleSummary(?string $id): array
    {
        return [
            'id' => $id,
            'activeClass' => null,
            'activeClassTokens' => [],
            'activeClassMetadata' => null,
            'playbackActiveClass' => null,
            'playbackActiveClassTokens' => [],
            'playbackActiveClassMetadata' => null,
            'manifestId' => $id,
            'manifestHref' => null,
            'manifestTarget' => null,
            'manifestPart' => null,
            'manifestMediaType' => null,
            'referencedBy' => [],
            'items' => [],
            'diagnostics' => [],
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

    /**
     * @param array<string, array<string, mixed>> $manifestById
     * @param array<string, array<string, mixed>> $mediaOverlays
     *
     * @return array<string, array<string, mixed>>
     */
    private static function attachMediaOverlayReferencesToManifest(array $manifestById, array $mediaOverlays): array
    {
        foreach ($manifestById as $id => $item) {
            $reference = self::mediaOverlayReferenceReport($item, $mediaOverlays);
            $item['mediaOverlayReference'] = $reference;
            $item['mediaOverlayDiagnostics'] = is_array($reference)
                ? array_values($reference['diagnostics'] ?? [])
                : [];

            if ($item['mediaOverlayDiagnostics'] !== []) {
                $diagnostics = is_array($item['diagnostics'] ?? null) ? array_values($item['diagnostics']) : [];
                array_push($diagnostics, ...$item['mediaOverlayDiagnostics']);
                $item['diagnostics'] = $diagnostics;
            }

            $manifestById[$id] = $item;
        }

        return $manifestById;
    }

    /**
     * @param array<string, mixed> $item
     * @param array<string, array<string, mixed>> $mediaOverlays
     *
     * @return ?array<string, mixed>
     */
    private static function mediaOverlayReferenceReport(array $item, array $mediaOverlays): ?array
    {
        $mediaOverlay = self::nullableManifestId($item['mediaOverlay'] ?? null);
        if ($mediaOverlay === null) {
            return null;
        }

        $overlay = $mediaOverlays[$mediaOverlay] ?? null;
        if (!is_array($overlay)) {
            return [
                'id' => $mediaOverlay,
                'href' => null,
                'target' => null,
                'part' => null,
                'mediaType' => null,
                'exists' => false,
                'referencedBy' => [(string) ($item['id'] ?? '')],
                'encrypted' => false,
                'canExposeBytes' => false,
                'duration' => null,
                'durationSeconds' => null,
                'durationMetadata' => null,
                'activeClass' => null,
                'activeClassTokens' => [],
                'activeClassMetadata' => null,
                'playbackActiveClass' => null,
                'playbackActiveClassTokens' => [],
                'playbackActiveClassMetadata' => null,
                'styleMetadata' => null,
                'textRef' => null,
                'textRefTarget' => null,
                'textRefPart' => null,
                'textRefFragment' => null,
                'textRefFragmentKind' => null,
                'textRefEpubCfi' => null,
                'textRefMediaFragment' => null,
                'textRefExternal' => false,
                'textRefExists' => false,
                'textRefByteLength' => null,
                'textRefCrc32' => null,
                'textRefByteSha256' => null,
                'textRefManifestId' => null,
                'textRefMediaType' => null,
                'textRefEncrypted' => false,
                'textRefCanExposeBytes' => false,
                'textRefDiagnostics' => [],
                'sequenceCount' => 0,
                'sequenceDiagnostics' => [],
                'itemCount' => 0,
                'diagnostics' => [[
                    'type' => 'missing-media-overlay-manifest-item',
                    'id' => $mediaOverlay,
                    'message' => 'EPUB OPF manifest item references a media-overlay id that is not in the OPF manifest',
                ]],
            ];
        }

        return [
            'id' => (string) ($overlay['id'] ?? $mediaOverlay),
            'href' => is_string($overlay['href'] ?? null) ? $overlay['href'] : null,
            'target' => is_string($overlay['target'] ?? null) ? $overlay['target'] : null,
            'part' => is_string($overlay['part'] ?? null) ? $overlay['part'] : null,
            'mediaType' => is_string($overlay['mediaType'] ?? null) ? $overlay['mediaType'] : null,
            'exists' => (bool) ($overlay['exists'] ?? false),
            'referencedBy' => is_array($overlay['referencedBy'] ?? null) ? array_values($overlay['referencedBy']) : [],
            'encrypted' => (bool) ($overlay['encrypted'] ?? false),
            'canExposeBytes' => (bool) ($overlay['canExposeBytes'] ?? false),
            'duration' => is_string($overlay['duration'] ?? null) ? $overlay['duration'] : null,
            'durationSeconds' => is_float($overlay['durationSeconds'] ?? null) || is_int($overlay['durationSeconds'] ?? null)
                ? (float) $overlay['durationSeconds']
                : null,
            'durationMetadata' => is_array($overlay['durationMetadata'] ?? null) ? $overlay['durationMetadata'] : null,
            'activeClass' => is_string($overlay['activeClass'] ?? null) ? $overlay['activeClass'] : null,
            'activeClassTokens' => is_array($overlay['activeClassTokens'] ?? null) ? array_values($overlay['activeClassTokens']) : [],
            'activeClassMetadata' => is_array($overlay['activeClassMetadata'] ?? null) ? $overlay['activeClassMetadata'] : null,
            'playbackActiveClass' => is_string($overlay['playbackActiveClass'] ?? null) ? $overlay['playbackActiveClass'] : null,
            'playbackActiveClassTokens' => is_array($overlay['playbackActiveClassTokens'] ?? null) ? array_values($overlay['playbackActiveClassTokens']) : [],
            'playbackActiveClassMetadata' => is_array($overlay['playbackActiveClassMetadata'] ?? null) ? $overlay['playbackActiveClassMetadata'] : null,
            'styleMetadata' => is_array($overlay['styleMetadata'] ?? null) ? $overlay['styleMetadata'] : null,
            'textRef' => is_string($overlay['textRef'] ?? null) ? $overlay['textRef'] : null,
            'textRefTarget' => is_string($overlay['textRefTarget'] ?? null) ? $overlay['textRefTarget'] : null,
            'textRefPart' => is_string($overlay['textRefPart'] ?? null) ? $overlay['textRefPart'] : null,
            'textRefFragment' => is_string($overlay['textRefFragment'] ?? null) ? $overlay['textRefFragment'] : null,
            'textRefFragmentKind' => is_string($overlay['textRefFragmentKind'] ?? null) ? $overlay['textRefFragmentKind'] : null,
            'textRefEpubCfi' => is_array($overlay['textRefEpubCfi'] ?? null) ? $overlay['textRefEpubCfi'] : null,
            'textRefMediaFragment' => is_array($overlay['textRefMediaFragment'] ?? null) ? $overlay['textRefMediaFragment'] : null,
            'textRefExternal' => (bool) ($overlay['textRefExternal'] ?? false),
            'textRefExists' => (bool) ($overlay['textRefExists'] ?? false),
            'textRefByteLength' => is_int($overlay['textRefByteLength'] ?? null) ? $overlay['textRefByteLength'] : null,
            'textRefCrc32' => is_string($overlay['textRefCrc32'] ?? null) ? $overlay['textRefCrc32'] : null,
            'textRefByteSha256' => is_string($overlay['textRefByteSha256'] ?? null) ? $overlay['textRefByteSha256'] : null,
            'textRefManifestId' => is_string($overlay['textRefManifestId'] ?? null) ? $overlay['textRefManifestId'] : null,
            'textRefMediaType' => is_string($overlay['textRefMediaType'] ?? null) ? $overlay['textRefMediaType'] : null,
            'textRefEncrypted' => (bool) ($overlay['textRefEncrypted'] ?? false),
            'textRefCanExposeBytes' => (bool) ($overlay['textRefCanExposeBytes'] ?? false),
            'textRefDiagnostics' => is_array($overlay['textRefDiagnostics'] ?? null) ? array_values($overlay['textRefDiagnostics']) : [],
            'sequenceCount' => is_array($overlay['sequences'] ?? null) ? count($overlay['sequences']) : (int) ($overlay['sequenceCount'] ?? 0),
            'sequenceDiagnostics' => is_array($overlay['sequenceDiagnostics'] ?? null) ? array_values($overlay['sequenceDiagnostics']) : [],
            'itemCount' => is_array($overlay['items'] ?? null) ? count($overlay['items']) : 0,
            'diagnostics' => is_array($overlay['diagnostics'] ?? null) ? array_values($overlay['diagnostics']) : [],
        ];
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
        $baseMediaType = self::baseMediaType($mediaType);
        foreach ($bindings['items'] ?? [] as $binding) {
            if (!is_array($binding)) {
                continue;
            }

            if (self::baseMediaType($binding['mediaType'] ?? null) === $baseMediaType) {
                return $binding;
            }
        }

        return null;
    }

    /**
     * @param array<string, array<string, mixed>> $manifestById
     * @param array<string, mixed> $bindings
     *
     * @return array<string, array<string, mixed>>
     */
    private static function attachManifestMediaTypeReports(array $manifestById, array $bindings): array
    {
        foreach ($manifestById as $id => $item) {
            $binding = self::bindingForMediaType($bindings, (string) ($item['mediaType'] ?? ''));
            $report = self::manifestItemMediaTypeReport($item, $binding, $manifestById);

            $item['mediaTypeReport'] = $report;
            $item['coreMediaType'] = $report['coreMediaType'];
            $item['coreMediaTypeKind'] = $report['coreMediaTypeKind'];
            $item['epubContentDocument'] = $report['epubContentDocument'];
            $item['foreignResource'] = $report['foreignResource'];
            $item['exemptResource'] = $report['exemptResource'];
            $item['mediaTypeReviewFlags'] = $report['reviewFlags'];
            $item['mediaTypeDiagnostics'] = $report['diagnostics'];
            $manifestById[$id] = $item;
        }

        return $manifestById;
    }

    /**
     * @param list<array<string, mixed>> $manifest
     *
     * @return array<string, mixed>
     */
    private static function manifestMediaTypeReport(array $manifest): array
    {
        $items = [];
        $itemsById = [];
        $itemsByMediaType = [];
        $reviewItems = [];
        $diagnostics = [];

        foreach ($manifest as $item) {
            $report = is_array($item['mediaTypeReport'] ?? null)
                ? $item['mediaTypeReport']
                : self::manifestItemMediaTypeReport($item, null);
            $items[] = $report;

            $id = (string) ($report['id'] ?? '');
            if ($id !== '') {
                $itemsById[$id] = $report;
            }

            $normalized = (string) ($report['normalizedMediaType'] ?? '');
            if ($normalized !== '') {
                $itemsByMediaType[$normalized][] = $report;
            }

            if (($report['reviewRequired'] ?? false) === true) {
                $reviewItems[] = $report;
            }

            foreach (($report['diagnostics'] ?? []) as $diagnostic) {
                if (!is_array($diagnostic)) {
                    continue;
                }

                $diagnostics[] = [
                    'id' => $id,
                    'mediaType' => (string) ($report['mediaType'] ?? ''),
                ] + $diagnostic;
            }
        }

        $countWhere = static function (callable $predicate) use ($items): int {
            return count(array_filter($items, $predicate));
        };

        return [
            'manifestItemCount' => count($items),
            'coreMediaTypeCount' => $countWhere(static fn (array $item): bool => ($item['coreMediaType'] ?? false) === true),
            'foreignResourceCount' => $countWhere(static fn (array $item): bool => ($item['foreignResource'] ?? false) === true),
            'exemptResourceCount' => $countWhere(static fn (array $item): bool => ($item['exemptResource'] ?? false) === true),
            'epubContentDocumentCount' => $countWhere(static fn (array $item): bool => ($item['epubContentDocument'] ?? false) === true),
            'invalidMediaTypeCount' => $countWhere(static fn (array $item): bool => ($item['mediaTypeSyntaxValid'] ?? true) !== true),
            'requiresSpineFallbackWhenDirectCount' => $countWhere(static fn (array $item): bool => ($item['requiresSpineFallbackWhenDirect'] ?? false) === true),
            'manifestFallbackCount' => $countWhere(static fn (array $item): bool => ($item['hasManifestFallback'] ?? false) === true),
            'bindingHandledCount' => $countWhere(static fn (array $item): bool => ($item['bindingHandled'] ?? false) === true),
            'foreignResourceWithoutFallbackCount' => $countWhere(static fn (array $item): bool => in_array('foreign-resource-without-fallback', $item['reviewFlags'] ?? [], true)),
            'reviewRequiredCount' => count($reviewItems),
            'items' => $items,
            'itemsById' => $itemsById,
            'itemsByMediaType' => $itemsByMediaType,
            'reviewItems' => $reviewItems,
            'diagnostics' => $diagnostics,
        ];
    }

    /**
     * @param array<string, mixed> $item
     * @param ?array<string, mixed> $binding
     * @param ?array<string, array<string, mixed>> $manifestById
     *
     * @return array<string, mixed>
     */
    private static function manifestItemMediaTypeReport(array $item, ?array $binding, ?array $manifestById = null): array
    {
        $id = (string) ($item['id'] ?? '');
        $mediaType = (string) ($item['mediaType'] ?? '');
        $part = is_string($item['part'] ?? null) ? (string) $item['part'] : null;
        $parts = self::mediaTypeParts($mediaType);
        $coreKind = self::coreMediaTypeKind($mediaType);
        $core = $coreKind !== null;
        $epubContentDocument = in_array($parts['base'], [self::XHTML_MEDIA_TYPE, 'image/svg+xml'], true);
        $exemptReason = $core ? null : self::exemptMediaTypeReason($mediaType, $part ?? '');
        $exempt = $exemptReason !== null;
        $foreignResource = !$core && !$exempt;
        $fallback = self::manifestFallbackCoverageReport($item, $manifestById ?? []);
        $fallbackId = $fallback['fallbackId'];
        $hasManifestFallback = (bool) $fallback['hasManifestFallback'];
        $manifestFallbackUsable = (bool) $fallback['usable'];
        $fallbackStyleId = self::nullableManifestId($item['fallbackStyle'] ?? null);
        $hasFallbackStyle = $fallbackStyleId !== null;
        $bindingHandlerId = is_array($binding) && is_string($binding['handlerId'] ?? null) ? $binding['handlerId'] : null;
        $bindingHandled = is_array($binding)
            && ($binding['handlerExists'] ?? false) === true
            && ($binding['handlerCanExposeBytes'] ?? false) === true
            && self::mediaTypeBaseEquals($binding['handlerMediaType'] ?? null, self::XHTML_MEDIA_TYPE);

        $reviewFlags = [];
        $diagnostics = is_array($parts['diagnostics'] ?? null) ? array_values($parts['diagnostics']) : [];
        if (($parts['valid'] ?? true) !== true) {
            $reviewFlags[] = 'invalid-media-type';
        }
        foreach ($fallback['diagnostics'] as $diagnostic) {
            $diagnostics[] = $diagnostic;
            $flag = match ($diagnostic['type'] ?? null) {
                'missing-manifest-fallback-item' => 'unresolved-manifest-fallback',
                'cyclic-manifest-fallback-chain' => 'cyclic-manifest-fallback',
                'unsupported-manifest-fallback-terminal' => 'unsupported-manifest-fallback',
                default => 'invalid-manifest-fallback',
            };
            if (!in_array($flag, $reviewFlags, true)) {
                $reviewFlags[] = $flag;
            }
        }
        if ($foreignResource && !$manifestFallbackUsable && !$bindingHandled) {
            if (!in_array('foreign-resource-without-fallback', $reviewFlags, true)) {
                $reviewFlags[] = 'foreign-resource-without-fallback';
            }
            $diagnostics[] = [
                'type' => 'foreign-resource-without-fallback',
                'fallbackRequired' => true,
                'fallbackId' => $fallbackId,
                'bindingHandlerId' => $bindingHandlerId,
                'message' => 'EPUB OPF manifest item uses a non-core media type without a usable manifest fallback or OPF binding handler',
            ];
        }

        $fallbackCoverage = match (true) {
            $hasManifestFallback && $manifestFallbackUsable => 'manifest-fallback',
            $hasManifestFallback => 'invalid-manifest-fallback',
            $bindingHandled => 'binding-handler',
            $core => 'core-media-type',
            $exempt => 'exempt-resource',
            default => 'missing',
        };

        return [
            'id' => $id,
            'href' => (string) ($item['href'] ?? ''),
            'target' => is_string($item['target'] ?? null) ? $item['target'] : null,
            'part' => $part,
            'external' => (bool) ($item['external'] ?? false),
            'exists' => (bool) ($item['exists'] ?? false),
            'mediaType' => $mediaType,
            'normalizedMediaType' => $parts['normalized'],
            'baseMediaType' => $parts['base'],
            'mediaTypeParameters' => $parts['parameters'],
            'mediaTypeParameterCount' => count($parts['parameters']),
            'mediaTypeSyntaxValid' => (bool) ($parts['valid'] ?? true),
            'properties' => is_array($item['properties'] ?? null) ? array_values($item['properties']) : [],
            'coreMediaType' => $core,
            'coreMediaTypeKind' => $coreKind,
            'epubContentDocument' => $epubContentDocument,
            'requiresSpineFallbackWhenDirect' => !$epubContentDocument,
            'foreignResource' => $foreignResource,
            'exemptResource' => $exempt,
            'exemptReason' => $exemptReason,
            'fallbackId' => $fallbackId,
            'hasManifestFallback' => $hasManifestFallback,
            'fallbackResolved' => (bool) $fallback['resolved'],
            'fallbackUsable' => $manifestFallbackUsable,
            'fallbackChain' => $fallback['chain'],
            'fallbackTerminalId' => $fallback['terminalId'],
            'fallbackTerminalMediaType' => $fallback['terminalMediaType'],
            'fallbackTerminalCoreMediaType' => $fallback['terminalCoreMediaType'],
            'fallbackTerminalEpubContentDocument' => $fallback['terminalEpubContentDocument'],
            'fallbackTerminalExemptResource' => $fallback['terminalExemptResource'],
            'fallbackStyleId' => $fallbackStyleId,
            'hasFallbackStyle' => $hasFallbackStyle,
            'bindingHandlerId' => $bindingHandlerId,
            'bindingHandled' => $bindingHandled,
            'fallbackCoverage' => $fallbackCoverage,
            'reviewRequired' => $reviewFlags !== [],
            'reviewFlags' => $reviewFlags,
            'diagnostics' => $diagnostics,
        ];
    }

    /**
     * @param array<string, mixed> $item
     * @param array<string, array<string, mixed>> $manifestById
     *
     * @return array{
     *     fallbackId:?string,
     *     hasManifestFallback:bool,
     *     resolved:bool,
     *     usable:bool,
     *     chain:list<array<string, mixed>>,
     *     terminalId:?string,
     *     terminalMediaType:?string,
     *     terminalCoreMediaType:?bool,
     *     terminalEpubContentDocument:?bool,
     *     terminalExemptResource:?bool,
     *     diagnostics:list<array<string, mixed>>
     * }
     */
    private static function manifestFallbackCoverageReport(array $item, array $manifestById): array
    {
        $fallbackId = self::nullableManifestId($item['fallback'] ?? null);
        if ($fallbackId === null) {
            return [
                'fallbackId' => null,
                'hasManifestFallback' => false,
                'resolved' => false,
                'usable' => false,
                'chain' => [],
                'terminalId' => null,
                'terminalMediaType' => null,
                'terminalCoreMediaType' => null,
                'terminalEpubContentDocument' => null,
                'terminalExemptResource' => null,
                'diagnostics' => [],
            ];
        }

        $chain = [];
        $diagnostics = [];
        $visited = [];
        $sourceId = (string) ($item['id'] ?? '');
        if ($sourceId !== '') {
            $visited[$sourceId] = true;
        }
        $current = $item;
        $next = $fallbackId;

        while ($next !== null) {
            if (isset($visited[$next])) {
                $diagnostics[] = [
                    'type' => 'cyclic-manifest-fallback-chain',
                    'id' => (string) ($current['id'] ?? ''),
                    'fallback' => $next,
                    'chainIds' => array_map(
                        static fn (array $chainItem): string => (string) $chainItem['id'],
                        $chain
                    ),
                    'message' => 'EPUB OPF manifest fallback chain cycles before reaching a core media type',
                ];
                break;
            }

            if (!isset($manifestById[$next])) {
                $diagnostics[] = [
                    'type' => 'missing-manifest-fallback-item',
                    'id' => (string) ($current['id'] ?? ''),
                    'fallback' => $next,
                    'message' => 'EPUB OPF manifest fallback references an item id that is not in the OPF manifest',
                ];
                break;
            }

            $visited[$next] = true;
            $current = $manifestById[$next];
            $mediaType = (string) ($current['mediaType'] ?? '');
            $part = is_string($current['part'] ?? null) ? (string) $current['part'] : '';
            $coreKind = self::coreMediaTypeKind($mediaType);
            $exemptReason = $coreKind === null ? self::exemptMediaTypeReason($mediaType, $part) : null;
            $parts = self::mediaTypeParts($mediaType);
            $epubContentDocument = in_array($parts['base'], [self::XHTML_MEDIA_TYPE, 'image/svg+xml'], true);
            $chain[] = [
                'id' => (string) ($current['id'] ?? ''),
                'href' => (string) ($current['href'] ?? ''),
                'target' => is_string($current['target'] ?? null) ? $current['target'] : null,
                'part' => is_string($current['part'] ?? null) ? $current['part'] : null,
                'external' => (bool) ($current['external'] ?? false),
                'exists' => (bool) ($current['exists'] ?? false),
                'mediaType' => $mediaType,
                'baseMediaType' => $parts['base'],
                'coreMediaType' => $coreKind !== null,
                'coreMediaTypeKind' => $coreKind,
                'epubContentDocument' => $epubContentDocument,
                'exemptResource' => $exemptReason !== null,
                'exemptReason' => $exemptReason,
                'foreignResource' => $coreKind === null && $exemptReason === null,
                'fallbackId' => self::nullableManifestId($current['fallback'] ?? null),
            ];

            $next = self::nullableManifestId($current['fallback'] ?? null);
        }

        $terminal = $chain === [] ? null : $chain[count($chain) - 1];
        $terminalForeign = is_array($terminal) && ($terminal['foreignResource'] ?? false) === true;
        if ($diagnostics === [] && $terminalForeign) {
            $diagnostics[] = [
                'type' => 'unsupported-manifest-fallback-terminal',
                'id' => (string) ($item['id'] ?? ''),
                'fallback' => $fallbackId,
                'terminalId' => (string) ($terminal['id'] ?? ''),
                'terminalMediaType' => (string) ($terminal['mediaType'] ?? ''),
                'message' => 'EPUB OPF manifest fallback chain terminates at another non-core media type',
            ];
        }

        return [
            'fallbackId' => $fallbackId,
            'hasManifestFallback' => true,
            'resolved' => $diagnostics === [] && $chain !== [],
            'usable' => $diagnostics === [] && $chain !== [] && !$terminalForeign,
            'chain' => $chain,
            'terminalId' => is_array($terminal) ? (string) $terminal['id'] : null,
            'terminalMediaType' => is_array($terminal) ? (string) $terminal['mediaType'] : null,
            'terminalCoreMediaType' => is_array($terminal) ? (bool) $terminal['coreMediaType'] : null,
            'terminalEpubContentDocument' => is_array($terminal) ? (bool) $terminal['epubContentDocument'] : null,
            'terminalExemptResource' => is_array($terminal) ? (bool) $terminal['exemptResource'] : null,
            'diagnostics' => $diagnostics,
        ];
    }

    /**
     * @return array{normalized:string, base:string, parameters:array<string, string>, valid:bool, diagnostics:list<array<string, mixed>>}
     */
    private static function mediaTypeParts(string $mediaType): array
    {
        $raw = trim($mediaType);
        $tokens = array_map('trim', explode(';', $raw));
        $base = strtolower(array_shift($tokens) ?? '');
        $parameters = [];
        $diagnostics = [];
        if ($base === '' || preg_match('/^[A-Za-z0-9!#$%&\'*+.^_`{|}~-]+\/[A-Za-z0-9!#$%&\'*+.^_`{|}~-]+$/', $base) !== 1) {
            $diagnostics[] = [
                'type' => 'invalid-manifest-media-type',
                'mediaType' => $raw,
                'baseMediaType' => $base,
                'message' => 'EPUB OPF manifest media-type must be a MIME type in type/subtype form',
            ];
        }

        foreach ($tokens as $index => $token) {
            if ($token === '') {
                continue;
            }

            if (!str_contains($token, '=')) {
                $diagnostics[] = [
                    'type' => 'invalid-manifest-media-type-parameter',
                    'mediaType' => $raw,
                    'parameter' => $token,
                    'parameterIndex' => $index,
                    'message' => 'EPUB OPF manifest media-type parameters must use name=value syntax',
                ];
                continue;
            }

            [$name, $value] = array_pad(explode('=', $token, 2), 2, '');
            $name = strtolower(trim($name));
            if ($name === '' || preg_match('/^[A-Za-z0-9!#$%&\'*+.^_`{|}~-]+$/', $name) !== 1) {
                $diagnostics[] = [
                    'type' => 'invalid-manifest-media-type-parameter-name',
                    'mediaType' => $raw,
                    'parameter' => $token,
                    'parameterIndex' => $index,
                    'name' => $name,
                    'message' => 'EPUB OPF manifest media-type parameter names must be MIME tokens',
                ];
                continue;
            }

            $parameterValue = trim($value, " \t\n\r\0\x0B\"'");
            if (isset($parameters[$name])) {
                $diagnostics[] = [
                    'type' => 'duplicate-manifest-media-type-parameter',
                    'mediaType' => $raw,
                    'parameter' => $name,
                    'parameterIndex' => $index,
                    'previousValue' => $parameters[$name],
                    'value' => $parameterValue,
                    'message' => 'EPUB OPF manifest media-type parameter repeats a name; later value is retained for package review',
                ];
            }

            $parameters[$name] = $parameterValue;
        }

        $normalized = $base;
        foreach ($parameters as $name => $value) {
            $normalized .= '; ' . $name . '=' . strtolower($value);
        }

        return [
            'normalized' => $normalized,
            'base' => $base,
            'parameters' => $parameters,
            'valid' => $diagnostics === [],
            'diagnostics' => $diagnostics,
        ];
    }

    private static function baseMediaType(mixed $mediaType): string
    {
        return self::mediaTypeParts(is_string($mediaType) ? $mediaType : '')['base'];
    }

    private static function mediaTypeBaseEquals(mixed $mediaType, string $expectedBase): bool
    {
        return self::baseMediaType($mediaType) === strtolower($expectedBase);
    }

    private static function coreMediaTypeKind(string $mediaType): ?string
    {
        $parts = self::mediaTypeParts($mediaType);
        if ($parts['base'] === 'audio/ogg') {
            return strtolower($parts['parameters']['codecs'] ?? '') === 'opus' ? 'audio' : null;
        }

        return self::CORE_MEDIA_TYPE_KINDS[$parts['base']] ?? null;
    }

    private static function exemptMediaTypeReason(string $mediaType, string $part): ?string
    {
        $parts = self::mediaTypeParts($mediaType);
        if (str_starts_with($parts['base'], 'video/')) {
            return 'video';
        }

        if (self::isFontResource($mediaType, $part)) {
            return 'font';
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
        array $refinementsById,
        array $linksByRefinedId = [],
        array $packageRenditionLayout = []
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

            $binding = self::bindingForMediaType($bindings, (string) $manifestItem['mediaType']);
            $content = $this->resolveSpineContentItem($manifestItem, $manifestById, $binding);
            $contentItem = $content['item'];
            $properties = self::spaceDelimited($itemref->getAttribute('properties'));
            $language = self::xmlLang($itemref);
            $direction = self::direction($itemref);
            $attributes = self::spineItemrefAttributes($itemref);
            $customAttributes = self::spineItemrefCustomAttributes($attributes);
            $itemProperties = self::spineItemPropertyReport($properties);
            $linearProperties = self::spineItemLinearReport($itemref, $idref);
            $itemProperties['linear'] = $linearProperties;
            $itemDiagnostics = array_merge($itemProperties['diagnostics'], $linearProperties['diagnostics']);
            $itemProperties['diagnostics'] = $itemDiagnostics;
            $refinements = self::metadataRefinementsForId($refinementsById, $itemrefId);
            $linkedResources = self::metadataLinkedResourcesForId($linksByRefinedId, $itemrefId);
            $effectiveRendition = self::spineItemEffectiveRenditionReport(
                $itemProperties,
                $refinements,
                $packageRenditionLayout
            );
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
                'language' => $language,
                'direction' => $direction,
                'attributes' => $attributes,
                'customAttributes' => $customAttributes,
                'refinements' => $refinements,
                'linkedResources' => $linkedResources,
                'spineItemProperties' => $itemProperties,
                'spineItemDiagnostics' => $itemDiagnostics,
                'effectiveRendition' => $effectiveRendition,
                'pageSpread' => $itemProperties['pageSpread']['placement'],
                'pageSpreadProperties' => $itemProperties['pageSpread']['properties'],
                'flow' => $itemProperties['flow']['value'],
                'flowProperties' => $itemProperties['flow']['properties'],
                'alignX' => $itemProperties['alignX']['value'],
                'alignXProperties' => $itemProperties['alignX']['properties'],
                'layout' => $itemProperties['layout']['value'],
                'layoutProperties' => $itemProperties['layout']['properties'],
                'orientation' => $itemProperties['orientation']['value'],
                'orientationProperties' => $itemProperties['orientation']['properties'],
                'spread' => $itemProperties['spread']['value'],
                'spreadProperties' => $itemProperties['spread']['properties'],
                'mediaOverlay' => $manifestItem['mediaOverlay'],
                'mediaOverlayReference' => $manifestItem['mediaOverlayReference'] ?? null,
                'mediaOverlayDiagnostics' => $manifestItem['mediaOverlayDiagnostics'] ?? [],
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
    private static function readSpineProperties(
        \DOMElement $spineElement,
        array $refinementsById,
        array $linksByRefinedId = []
    ): array
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
            'linkedResources' => self::metadataLinkedResourcesForId($linksByRefinedId, $spineId),
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
        $itemIds = [];
        $linearItemCount = 0;
        foreach ($spine as $item) {
            $idref = (string) ($item['idref'] ?? '');
            if ($idref !== '') {
                $idrefs[] = $idref;
            }
            $itemId = is_string($item['id'] ?? null) ? (string) $item['id'] : '';
            if ($itemId !== '') {
                $itemIds[$itemId][] = [
                    'index' => (int) ($item['index'] ?? 0),
                    'idref' => $idref,
                ];
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

        ksort($itemIds, SORT_STRING);
        $duplicateItemIds = [];
        $duplicateItemIdItemCount = 0;
        foreach ($itemIds as $itemId => $items) {
            if (count($items) < 2) {
                continue;
            }

            $indexes = array_map(static fn (array $item): int => (int) $item['index'], $items);
            $duplicateIdrefs = array_map(static fn (array $item): string => (string) $item['idref'], $items);
            $duplicateItemIds[] = $itemId;
            $duplicateItemIdItemCount += count($items);
            foreach ($items as $item) {
                $itemDiagnostics[] = [
                    'type' => 'duplicate-spine-itemref-id',
                    'index' => (int) $item['index'],
                    'id' => $itemId,
                    'idref' => (string) $item['idref'],
                    'indexes' => $indexes,
                    'idrefs' => $duplicateIdrefs,
                    'message' => 'EPUB spine contains multiple itemref elements with the same id; metadata refinements for that id are ambiguous',
                ];
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
        $spineProperties['duplicateItemIdCount'] = count($duplicateItemIds);
        $spineProperties['duplicateItemIds'] = $duplicateItemIds;
        $spineProperties['duplicateItemIdItemCount'] = $duplicateItemIdItemCount;
        $spineProperties['authoring'] = self::spineItemrefAuthoringReport($spine);
        $spineProperties['itemDiagnostics'] = $itemDiagnostics;
        $spineProperties['diagnostics'] = array_merge(
            $diagnostics,
            $itemDiagnostics
        );

        return $spineProperties;
    }

    /**
     * @param list<array<string, mixed>> $spine
     *
     * @return array<string, mixed>
     */
    private static function spineItemrefAuthoringReport(array $spine): array
    {
        $items = [];
        $itemsByIndex = [];
        $languageItems = [];
        $directionItems = [];
        $customAttributeItems = [];

        foreach ($spine as $item) {
            $index = (int) ($item['index'] ?? count($items));
            $attributes = is_array($item['attributes'] ?? null) ? $item['attributes'] : [];
            $customAttributes = is_array($item['customAttributes'] ?? null)
                ? $item['customAttributes']
                : self::spineItemrefCustomAttributes($attributes);
            $summary = [
                'index' => $index,
                'id' => is_string($item['id'] ?? null) ? $item['id'] : null,
                'idref' => (string) ($item['idref'] ?? ''),
                'target' => is_string($item['target'] ?? null) ? $item['target'] : null,
                'part' => is_string($item['part'] ?? null) ? $item['part'] : null,
                'href' => is_string($item['href'] ?? null) ? $item['href'] : null,
                'linear' => (bool) ($item['linear'] ?? true),
                'language' => is_string($item['language'] ?? null) ? $item['language'] : null,
                'direction' => is_string($item['direction'] ?? null) ? $item['direction'] : null,
                'attributes' => $attributes,
                'attributeCount' => count($attributes),
                'customAttributes' => $customAttributes,
                'customAttributeCount' => count($customAttributes),
            ];

            $items[] = $summary;
            $itemsByIndex[$index] = $summary;
            if ($summary['language'] !== null) {
                $languageItems[] = $summary;
            }
            if ($summary['direction'] !== null) {
                $directionItems[] = $summary;
            }
            if ($customAttributes !== []) {
                $customAttributeItems[] = $summary;
            }
        }

        ksort($itemsByIndex, SORT_NUMERIC);

        return [
            'present' => $items !== [],
            'itemCount' => count($items),
            'items' => $items,
            'itemsByIndex' => array_values($itemsByIndex),
            'languageItemCount' => count($languageItems),
            'languageItems' => $languageItems,
            'directionItemCount' => count($directionItems),
            'directionItems' => $directionItems,
            'customAttributeItemCount' => count($customAttributeItems),
            'customAttributeItems' => $customAttributeItems,
        ];
    }

    /**
     * @param list<string> $properties
     *
     * @return array{pageSpread:array<string, mixed>, flow:array<string, mixed>, alignX:array<string, mixed>, layout:array<string, mixed>, orientation:array<string, mixed>, spread:array<string, mixed>, diagnostics:list<array<string, mixed>>}
     */
    private static function spineItemPropertyReport(array $properties): array
    {
        $matches = [];
        $placements = [];
        $flowMatches = [];
        $flowValues = [];
        $alignXMatches = [];
        $alignXValues = [];
        $layoutMatches = [];
        $layoutValues = [];
        $orientationMatches = [];
        $orientationValues = [];
        $spreadMatches = [];
        $spreadValues = [];
        foreach ($properties as $property) {
            $placement = match ($property) {
                'page-spread-left', 'rendition:page-spread-left' => 'left',
                'page-spread-right', 'rendition:page-spread-right' => 'right',
                'spread-none', 'rendition:page-spread-center' => 'center',
                default => null,
            };

            if ($placement !== null) {
                $matches[] = [
                    'property' => $property,
                    'placement' => $placement,
                ];
                $placements[$placement] = true;
            }

            $flow = match ($property) {
                'rendition:flow-auto' => 'auto',
                'rendition:flow-paginated' => 'paginated',
                'rendition:flow-scrolled-continuous' => 'scrolled-continuous',
                'rendition:flow-scrolled-doc' => 'scrolled-doc',
                default => null,
            };

            if ($flow !== null) {
                $flowMatches[] = [
                    'property' => $property,
                    'value' => $flow,
                ];
                $flowValues[$flow] = true;
            }

            $alignX = match ($property) {
                'rendition:align-x-center' => 'center',
                default => null,
            };

            if ($alignX !== null) {
                $alignXMatches[] = [
                    'property' => $property,
                    'value' => $alignX,
                ];
                $alignXValues[$alignX] = true;
            }

            $layout = match ($property) {
                'rendition:layout-reflowable' => 'reflowable',
                'rendition:layout-pre-paginated', 'rendition:layout-prepaginated' => 'pre-paginated',
                default => null,
            };

            if ($layout !== null) {
                $layoutMatches[] = [
                    'property' => $property,
                    'value' => $layout,
                ];
                $layoutValues[$layout] = true;
            }

            $orientation = match ($property) {
                'rendition:orientation-auto' => 'auto',
                'rendition:orientation-landscape' => 'landscape',
                'rendition:orientation-portrait' => 'portrait',
                default => null,
            };

            if ($orientation !== null) {
                $orientationMatches[] = [
                    'property' => $property,
                    'value' => $orientation,
                ];
                $orientationValues[$orientation] = true;
            }

            $spread = match ($property) {
                'rendition:spread-auto' => 'auto',
                'rendition:spread-none' => 'none',
                'rendition:spread-both' => 'both',
                'rendition:spread-landscape' => 'landscape',
                'rendition:spread-portrait' => 'portrait',
                default => null,
            };

            if ($spread !== null) {
                $spreadMatches[] = [
                    'property' => $property,
                    'value' => $spread,
                ];
                $spreadValues[$spread] = true;
            }
        }

        $spreadProperties = array_map(
            static fn (array $match): string => (string) $match['property'],
            $matches
        );
        $spreadPlacements = array_keys($placements);
        $conflicting = count($spreadPlacements) > 1;
        $flowProperties = array_map(
            static fn (array $match): string => (string) $match['property'],
            $flowMatches
        );
        $flowValuesList = array_keys($flowValues);
        $flowConflicting = count($flowValuesList) > 1;
        $alignXProperties = array_map(
            static fn (array $match): string => (string) $match['property'],
            $alignXMatches
        );
        $alignXValuesList = array_keys($alignXValues);
        $alignXConflicting = count($alignXValuesList) > 1;
        $layoutProperties = array_map(
            static fn (array $match): string => (string) $match['property'],
            $layoutMatches
        );
        $layoutValuesList = array_keys($layoutValues);
        $layoutConflicting = count($layoutValuesList) > 1;
        $orientationProperties = array_map(
            static fn (array $match): string => (string) $match['property'],
            $orientationMatches
        );
        $orientationValuesList = array_keys($orientationValues);
        $orientationConflicting = count($orientationValuesList) > 1;
        $spreadOverrideProperties = array_map(
            static fn (array $match): string => (string) $match['property'],
            $spreadMatches
        );
        $spreadOverrideValuesList = array_keys($spreadValues);
        $spreadOverrideConflicting = count($spreadOverrideValuesList) > 1;
        $diagnostics = [];

        if ($conflicting) {
            $diagnostics[] = [
                'type' => 'conflicting-spine-page-spread-properties',
                'properties' => $spreadProperties,
                'placements' => $spreadPlacements,
                'message' => 'EPUB spine itemref declares more than one page-spread placement',
            ];
        }

        if ($flowConflicting) {
            $diagnostics[] = [
                'type' => 'conflicting-spine-flow-properties',
                'properties' => $flowProperties,
                'values' => $flowValuesList,
                'message' => 'EPUB spine itemref declares more than one rendition flow value',
            ];
        }

        if ($alignXConflicting) {
            $diagnostics[] = [
                'type' => 'conflicting-spine-align-x-properties',
                'properties' => $alignXProperties,
                'values' => $alignXValuesList,
                'message' => 'EPUB spine itemref declares more than one rendition horizontal alignment value',
            ];
        }

        if ($layoutConflicting) {
            $diagnostics[] = [
                'type' => 'conflicting-spine-layout-properties',
                'properties' => $layoutProperties,
                'values' => $layoutValuesList,
                'message' => 'EPUB spine itemref declares more than one rendition layout override value',
            ];
        }

        if ($orientationConflicting) {
            $diagnostics[] = [
                'type' => 'conflicting-spine-orientation-properties',
                'properties' => $orientationProperties,
                'values' => $orientationValuesList,
                'message' => 'EPUB spine itemref declares more than one rendition orientation override value',
            ];
        }

        if ($spreadOverrideConflicting) {
            $diagnostics[] = [
                'type' => 'conflicting-spine-spread-properties',
                'properties' => $spreadOverrideProperties,
                'values' => $spreadOverrideValuesList,
                'message' => 'EPUB spine itemref declares more than one rendition spread override value',
            ];
        }

        return [
            'pageSpread' => [
                'placement' => $matches[0]['placement'] ?? null,
                'properties' => $spreadProperties,
                'matches' => $matches,
                'conflicting' => $conflicting,
            ],
            'flow' => [
                'value' => $flowMatches[0]['value'] ?? null,
                'properties' => $flowProperties,
                'matches' => $flowMatches,
                'values' => $flowValuesList,
                'conflicting' => $flowConflicting,
            ],
            'alignX' => [
                'value' => $alignXMatches[0]['value'] ?? null,
                'properties' => $alignXProperties,
                'matches' => $alignXMatches,
                'values' => $alignXValuesList,
                'conflicting' => $alignXConflicting,
            ],
            'layout' => [
                'value' => $layoutMatches[0]['value'] ?? null,
                'properties' => $layoutProperties,
                'matches' => $layoutMatches,
                'values' => $layoutValuesList,
                'conflicting' => $layoutConflicting,
                'fixedLayout' => ($layoutMatches[0]['value'] ?? null) === 'pre-paginated',
            ],
            'orientation' => [
                'value' => $orientationMatches[0]['value'] ?? null,
                'properties' => $orientationProperties,
                'matches' => $orientationMatches,
                'values' => $orientationValuesList,
                'conflicting' => $orientationConflicting,
            ],
            'spread' => [
                'value' => $spreadMatches[0]['value'] ?? null,
                'properties' => $spreadOverrideProperties,
                'matches' => $spreadMatches,
                'values' => $spreadOverrideValuesList,
                'conflicting' => $spreadOverrideConflicting,
            ],
            'diagnostics' => $diagnostics,
        ];
    }

    /**
     * @param array<string, mixed> $itemProperties
     * @param array<string, list<array<string, mixed>>> $refinements
     * @param array<string, mixed> $packageRenditionLayout
     *
     * @return array<string, mixed>
     */
    private static function spineItemEffectiveRenditionReport(
        array $itemProperties,
        array $refinements,
        array $packageRenditionLayout
    ): array {
        $layout = self::effectiveRenditionScalar('layout', $itemProperties, $packageRenditionLayout);
        $orientation = self::effectiveRenditionScalar('orientation', $itemProperties, $packageRenditionLayout);
        $spread = self::effectiveRenditionScalar('spread', $itemProperties, $packageRenditionLayout);
        $flow = self::effectiveRenditionItemrefScalar('flow', $itemProperties);
        $alignX = self::effectiveRenditionItemrefScalar('alignX', $itemProperties);
        $itemrefViewportReport = self::spineItemRenditionViewportRefinementReport($refinements);
        $packageViewport = is_array($packageRenditionLayout['viewport'] ?? null)
            ? $packageRenditionLayout['viewport']
            : self::emptyRenditionViewportReport();
        $itemrefViewport = is_array($itemrefViewportReport['selected'] ?? null)
            ? $itemrefViewportReport['selected']
            : null;
        $itemrefViewportValid = is_array($itemrefViewport) && ($itemrefViewport['valid'] ?? false) === true;
        $packageViewportPresent = ($packageViewport['present'] ?? false) === true;

        if ($itemrefViewportValid) {
            $viewport = $itemrefViewport;
            $viewportSource = 'itemref-refinement';
        } elseif ($packageViewportPresent) {
            $viewport = $packageViewport;
            $viewportSource = 'package';
        } elseif (is_array($itemrefViewport)) {
            $viewport = $itemrefViewport;
            $viewportSource = 'itemref-refinement';
        } else {
            $viewport = self::emptyRenditionViewportReport();
            $viewportSource = null;
        }

        $packageDiagnostics = is_array($packageRenditionLayout['diagnostics'] ?? null)
            ? $packageRenditionLayout['diagnostics']
            : [];
        $itemrefViewportDiagnostics = is_array($itemrefViewportReport['diagnostics'] ?? null)
            ? $itemrefViewportReport['diagnostics']
            : [];
        $diagnostics = array_values(array_merge($packageDiagnostics, $itemrefViewportDiagnostics));
        $present = $layout['value'] !== null
            || $orientation['value'] !== null
            || $spread['value'] !== null
            || $flow['value'] !== null
            || $alignX['value'] !== null
            || ($viewport['present'] ?? false) === true;

        return [
            'present' => $present,
            'fixedLayout' => $layout['value'] === 'pre-paginated',
            'layout' => $layout['value'],
            'layoutSource' => $layout['source'],
            'layoutItemref' => $layout['itemref'],
            'layoutPackage' => $layout['package'],
            'orientation' => $orientation['value'],
            'orientationSource' => $orientation['source'],
            'orientationItemref' => $orientation['itemref'],
            'orientationPackage' => $orientation['package'],
            'spread' => $spread['value'],
            'spreadSource' => $spread['source'],
            'spreadItemref' => $spread['itemref'],
            'spreadPackage' => $spread['package'],
            'flow' => $flow['value'],
            'flowSource' => $flow['source'],
            'alignX' => $alignX['value'],
            'alignXSource' => $alignX['source'],
            'viewport' => $viewport,
            'viewportSource' => $viewportSource,
            'viewportRaw' => is_string($viewport['raw'] ?? null) ? $viewport['raw'] : null,
            'viewportWidth' => is_int($viewport['width'] ?? null) ? $viewport['width'] : null,
            'viewportHeight' => is_int($viewport['height'] ?? null) ? $viewport['height'] : null,
            'itemrefViewports' => is_array($itemrefViewportReport['viewports'] ?? null)
                ? $itemrefViewportReport['viewports']
                : [],
            'itemrefViewportCount' => is_int($itemrefViewportReport['count'] ?? null)
                ? $itemrefViewportReport['count']
                : 0,
            'validItemrefViewportCount' => is_int($itemrefViewportReport['validCount'] ?? null)
                ? $itemrefViewportReport['validCount']
                : 0,
            'invalidItemrefViewportCount' => is_int($itemrefViewportReport['invalidCount'] ?? null)
                ? $itemrefViewportReport['invalidCount']
                : 0,
            'packageViewport' => $packageViewport,
            'diagnostics' => $diagnostics,
            'diagnosticCount' => count($diagnostics),
        ];
    }

    /**
     * @param array<string, mixed> $itemProperties
     * @param array<string, mixed> $packageRenditionLayout
     *
     * @return array{value:?string, source:?string, itemref:?string, package:?string}
     */
    private static function effectiveRenditionScalar(
        string $name,
        array $itemProperties,
        array $packageRenditionLayout
    ): array {
        $itemref = is_string($itemProperties[$name]['value'] ?? null) ? $itemProperties[$name]['value'] : null;
        $package = is_string($packageRenditionLayout[$name] ?? null) ? $packageRenditionLayout[$name] : null;
        $value = $itemref ?? $package;

        return [
            'value' => $value,
            'source' => $itemref !== null ? 'itemref' : ($package !== null ? 'package' : null),
            'itemref' => $itemref,
            'package' => $package,
        ];
    }

    /**
     * @param array<string, mixed> $itemProperties
     *
     * @return array{value:?string, source:?string}
     */
    private static function effectiveRenditionItemrefScalar(string $name, array $itemProperties): array
    {
        $value = is_string($itemProperties[$name]['value'] ?? null) ? $itemProperties[$name]['value'] : null;

        return [
            'value' => $value,
            'source' => $value !== null ? 'itemref' : null,
        ];
    }

    /**
     * @param array<string, list<array<string, mixed>>> $refinements
     *
     * @return array<string, mixed>
     */
    private static function spineItemRenditionViewportRefinementReport(array $refinements): array
    {
        $entries = is_array($refinements['rendition:viewport'] ?? null) ? $refinements['rendition:viewport'] : [];
        $viewports = [];
        $diagnostics = [];
        foreach ($entries as $index => $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $viewport = self::renditionViewportReport($entry, count($viewports));
            $viewport['source'] = 'itemref-refinement';
            $viewport['subjectId'] = is_string($entry['subjectId'] ?? null) ? $entry['subjectId'] : null;
            $viewport['refines'] = is_string($entry['refines'] ?? null) ? $entry['refines'] : null;
            $viewport['entryIndex'] = (int) $index;
            foreach ($viewport['diagnostics'] as $diagnostic) {
                $diagnostics[] = $diagnostic;
            }
            $viewports[] = $viewport;
        }

        $validViewports = array_values(array_filter(
            $viewports,
            static fn (array $viewport): bool => ($viewport['valid'] ?? false) === true,
        ));
        $invalidViewports = array_values(array_filter(
            $viewports,
            static fn (array $viewport): bool => ($viewport['valid'] ?? true) !== true,
        ));

        return [
            'present' => $viewports !== [],
            'count' => count($viewports),
            'validCount' => count($validViewports),
            'invalidCount' => count($invalidViewports),
            'viewports' => $viewports,
            'selected' => $validViewports[0] ?? ($viewports[0] ?? null),
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
     * @param ?array<string, mixed> $binding
     *
     * @return array{
     *     item:?array<string, mixed>,
     *     chain:list<array<string, mixed>>,
     *     diagnostics:list<array<string, mixed>>,
     *     isFallback:bool
     * }
     */
    private function resolveSpineContentItem(array $manifestItem, array $manifestById, ?array $binding = null): array
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
                $bindingContent = (string) ($current['id'] ?? '') === (string) ($manifestItem['id'] ?? '')
                    ? self::spineBindingFallbackContentItem($binding, $manifestById, $visited)
                    : null;
                if ($bindingContent !== null) {
                    $chain[] = self::bindingFallbackChainItem($bindingContent, $binding);

                    return [
                        'item' => $bindingContent,
                        'chain' => $chain,
                        'diagnostics' => $diagnostics,
                        'isFallback' => true,
                    ];
                }

                if ((string) ($current['id'] ?? '') === (string) ($manifestItem['id'] ?? '')) {
                    array_push(
                        $diagnostics,
                        ...self::spineBindingFallbackDiagnostics($binding, $manifestById, $visited)
                    );
                }
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
     * @param ?array<string, mixed> $binding
     * @param array<string, array<string, mixed>> $manifestById
     * @param array<string, bool> $visited
     *
     * @return ?array<string, mixed>
     */
    private static function spineBindingFallbackContentItem(?array $binding, array $manifestById, array $visited): ?array
    {
        if (!is_array($binding)) {
            return null;
        }

        $handlerId = self::nullableManifestId($binding['handlerId'] ?? null);
        if ($handlerId === null || isset($visited[$handlerId]) || !isset($manifestById[$handlerId])) {
            return null;
        }

        $handler = $manifestById[$handlerId];

        return self::canExposeXhtmlContent($handler) ? $handler : null;
    }

    /**
     * @param array<string, mixed> $item
     * @param ?array<string, mixed> $binding
     *
     * @return array<string, mixed>
     */
    private static function bindingFallbackChainItem(array $item, ?array $binding): array
    {
        return self::fallbackChainItem($item) + [
            'source' => 'binding-handler',
            'bindingMediaType' => is_string($binding['mediaType'] ?? null) ? $binding['mediaType'] : null,
            'bindingHandlerId' => is_string($binding['handlerId'] ?? null) ? $binding['handlerId'] : null,
        ];
    }

    /**
     * @param ?array<string, mixed> $binding
     * @param array<string, array<string, mixed>> $manifestById
     * @param array<string, bool> $visited
     *
     * @return list<array<string, mixed>>
     */
    private static function spineBindingFallbackDiagnostics(?array $binding, array $manifestById, array $visited): array
    {
        if (!is_array($binding)) {
            return [];
        }

        $mediaType = is_string($binding['mediaType'] ?? null) ? $binding['mediaType'] : null;
        $handlerId = self::nullableManifestId($binding['handlerId'] ?? null);
        if ($handlerId === null) {
            return [[
                'type' => 'missing-spine-binding-handler',
                'mediaType' => $mediaType,
                'message' => 'EPUB OPF binding for a spine media type does not name a handler manifest item',
            ]];
        }

        if (isset($visited[$handlerId])) {
            return [[
                'type' => 'cyclic-spine-binding-handler',
                'mediaType' => $mediaType,
                'handlerId' => $handlerId,
                'message' => 'EPUB OPF binding handler points back into the current spine fallback chain',
            ]];
        }

        $handler = $manifestById[$handlerId] ?? null;
        if (!is_array($handler)) {
            return [[
                'type' => 'missing-spine-binding-handler-manifest-item',
                'mediaType' => $mediaType,
                'handlerId' => $handlerId,
                'message' => 'EPUB OPF binding handler does not reference a manifest item available for spine fallback',
            ]];
        }

        if (($handler['exists'] ?? false) !== true) {
            return [[
                'type' => 'missing-spine-binding-handler-part',
                'mediaType' => $mediaType,
                'handlerId' => $handlerId,
                'part' => (string) ($handler['part'] ?? ''),
                'message' => 'EPUB OPF binding handler part is missing from the package',
            ]];
        }

        if (self::isEncryptedManifestItem($handler)) {
            return [[
                'type' => 'encrypted-spine-binding-handler',
                'mediaType' => $mediaType,
                'handlerId' => $handlerId,
                'part' => (string) ($handler['part'] ?? ''),
                'message' => 'EPUB OPF binding handler is encrypted and cannot be exposed as XHTML content',
            ]];
        }

        if (!self::mediaTypeBaseEquals($handler['mediaType'] ?? null, self::XHTML_MEDIA_TYPE)) {
            return [[
                'type' => 'non-xhtml-spine-binding-handler',
                'mediaType' => $mediaType,
                'handlerId' => $handlerId,
                'handlerMediaType' => (string) ($handler['mediaType'] ?? ''),
                'message' => 'EPUB OPF binding handler must resolve to an XHTML content document before it can be used as a spine fallback',
            ]];
        }

        return [];
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
     * @return array{
     *     present:bool,
     *     itemCount:int,
     *     typedItemCount:int,
     *     missingTypeCount:int,
     *     types:list<string>,
     *     typeCounts:array<string, int>,
     *     items:list<array<string, mixed>>,
     *     itemsByType:array<string, list<array<string, mixed>>>,
     *     diagnosticCount:int,
     *     diagnostics:list<array<string, mixed>>
     * }
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
                'itemCount' => 0,
                'typedItemCount' => 0,
                'missingTypeCount' => 0,
                'types' => [],
                'typeCounts' => [],
                'items' => [],
                'itemsByType' => [],
                'diagnosticCount' => 0,
                'diagnostics' => [],
            ];
        }

        $manifestByPart = self::manifestByPart($manifestById);
        $items = [];
        $itemsByType = [];
        $types = [];
        $typedItemCount = 0;
        $missingTypeCount = 0;
        $diagnostics = [];
        foreach (self::childElements($guideElement, 'reference', self::OPF_NS) as $index => $referenceElement) {
            $href = trim($referenceElement->getAttribute('href'));
            $reference = $this->packageReference($package, $opfPart, $href, $manifestByPart, 'guide');
            $typeRaw = self::nullableAttribute($referenceElement, 'type');
            $typeTokens = self::spaceDelimited($typeRaw ?? '');
            $itemDiagnostics = $reference['diagnostics'];
            if ($typeTokens === []) {
                ++$missingTypeCount;
                $missingTypeDiagnostic = [
                    'type' => 'missing-guide-reference-type',
                    'href' => $href === '' ? null : $href,
                    'message' => 'EPUB OPF guide reference is missing type metadata for import handoff classification',
                ];
                $itemDiagnostics[] = $missingTypeDiagnostic;
                $diagnostics[] = ['index' => $index] + $missingTypeDiagnostic;
            } else {
                ++$typedItemCount;
            }

            $item = [
                'index' => $index,
                'type' => $typeTokens[0] ?? null,
                'typeRaw' => $typeRaw,
                'types' => $typeTokens,
                'title' => self::nullableAttribute($referenceElement, 'title'),
                'href' => $href === '' ? null : $href,
                'target' => $reference['target'],
                'part' => $reference['part'],
                'fragment' => $reference['fragment'],
                'fragmentKind' => $reference['fragmentKind'],
                'epubCfi' => $reference['epubCfi'],
                'mediaFragment' => $reference['mediaFragment'],
                'external' => $reference['external'],
                'exists' => $reference['exists'],
                'byteLength' => $reference['byteLength'],
                'crc32' => $reference['crc32'],
                'manifestId' => $reference['manifestId'],
                'mediaType' => $reference['mediaType'],
                'encrypted' => $reference['encrypted'],
                'canExposeBytes' => $reference['canExposeBytes'],
                'diagnostics' => $itemDiagnostics,
            ];

            foreach ($reference['diagnostics'] as $diagnostic) {
                $diagnostics[] = ['index' => $index] + $diagnostic;
            }
            foreach ($typeTokens as $type) {
                if (!isset($types[$type])) {
                    $types[$type] = 0;
                }
                ++$types[$type];
                $itemsByType[$type][] = $item;
            }
            $items[] = $item;
        }

        return [
            'present' => true,
            'itemCount' => count($items),
            'typedItemCount' => $typedItemCount,
            'missingTypeCount' => $missingTypeCount,
            'types' => array_keys($types),
            'typeCounts' => $types,
            'items' => $items,
            'itemsByType' => $itemsByType,
            'diagnosticCount' => count($diagnostics),
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
        array $manifestById,
        array $prefixBindings
    ): array {
        $manifestByPart = self::manifestByPart($manifestById);
        $collections = [];
        foreach (self::childElements($packageElement, 'collection', self::OPF_NS) as $collectionElement) {
            $collections[] = $this->readCollectionElement($package, $opfPart, $collectionElement, $manifestByPart, $prefixBindings);
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
        array $manifestByPart,
        array $prefixBindings
    ): array {
        $links = [];
        foreach (self::childElements($collectionElement, 'link', self::OPF_NS) as $linkElement) {
            $links[] = $this->readCollectionLink($package, $opfPart, $linkElement, $manifestByPart);
        }

        $children = [];
        foreach (self::childElements($collectionElement, 'collection', self::OPF_NS) as $childCollection) {
            $children[] = $this->readCollectionElement($package, $opfPart, $childCollection, $manifestByPart, $prefixBindings);
        }

        $metadataElement = self::firstChildElement($collectionElement, 'metadata', self::OPF_NS);
        $collectionLanguage = self::xmlLang($collectionElement);
        $collectionDirection = self::direction($collectionElement);
        $role = self::nullableAttribute($collectionElement, 'role');
        $roleReport = self::collectionRoleReport($role, $prefixBindings);
        $linkReport = self::collectionLinkReport($links);

        return [
            'id' => self::nullableAttribute($collectionElement, 'id'),
            'role' => $role,
            'roleReport' => $roleReport,
            'roleTokens' => $roleReport['values'],
            'primaryRole' => $roleReport['primaryRole'],
            'language' => $collectionLanguage,
            'dir' => $collectionDirection,
            'metadata' => $metadataElement instanceof \DOMElement
                ? $this->readMetadata($metadataElement, '', false, $prefixBindings, $collectionLanguage, $collectionDirection)
                : [],
            'links' => $links,
            'linkReport' => $linkReport,
            'linkCount' => $linkReport['count'],
            'localLinkCount' => $linkReport['localCount'],
            'externalLinkCount' => $linkReport['externalCount'],
            'missingLinkCount' => $linkReport['missingCount'],
            'encryptedLinkCount' => $linkReport['encryptedCount'],
            'collectionLinkRelTokens' => $linkReport['relTokens'],
            'collectionLinkRelCounts' => $linkReport['relCounts'],
            'collectionLinksByRel' => $linkReport['linksByRel'],
            'collectionLinkPropertyTokens' => $linkReport['propertyTokens'],
            'collectionLinkPropertyCounts' => $linkReport['propertyCounts'],
            'collectionLinkDiagnostics' => $linkReport['diagnostics'],
            'children' => $children,
        ];
    }

    /**
     * @param list<array<string, mixed>> $links
     *
     * @return array<string, mixed>
     */
    private static function collectionLinkReport(array $links): array
    {
        $relCounts = [];
        $linksByRel = [];
        $propertyCounts = [];
        $diagnostics = [];
        $localCount = 0;
        $externalCount = 0;
        $missingCount = 0;
        $encryptedCount = 0;
        $recordLinkCount = 0;
        $reviewRequiredCount = 0;

        foreach ($links as $index => $link) {
            $external = ($link['external'] ?? false) === true;
            $exists = ($link['exists'] ?? false) === true;
            $encrypted = ($link['encrypted'] ?? false) === true;
            $part = is_string($link['part'] ?? null) ? $link['part'] : null;
            $linkDiagnostics = is_array($link['diagnostics'] ?? null) ? $link['diagnostics'] : [];

            if ($external) {
                ++$externalCount;
            } elseif ($part !== null && $part !== '') {
                ++$localCount;
            }
            if (!$external && !$exists) {
                ++$missingCount;
            }
            if ($encrypted) {
                ++$encryptedCount;
            }

            $rels = is_array($link['rel'] ?? null) ? array_values($link['rel']) : [];
            if ($external || (!$exists && !$external) || $encrypted || $linkDiagnostics !== [] || $rels === []) {
                ++$reviewRequiredCount;
            }
            if ($rels === []) {
                $diagnostics[] = [
                    'type' => 'missing-collection-link-rel',
                    'index' => $index,
                    'id' => is_string($link['id'] ?? null) ? $link['id'] : null,
                    'href' => is_string($link['href'] ?? null) ? $link['href'] : null,
                    'message' => 'EPUB OPF collection link is missing rel tokens for review handoff classification',
                ];
            }
            foreach ($rels as $rel) {
                $rel = (string) $rel;
                $relCounts[$rel] = ($relCounts[$rel] ?? 0) + 1;
                $linksByRel[$rel][] = $link;
                if ($rel === 'record') {
                    ++$recordLinkCount;
                }
            }

            foreach (is_array($link['properties'] ?? null) ? $link['properties'] : [] as $property) {
                $property = (string) $property;
                $propertyCounts[$property] = ($propertyCounts[$property] ?? 0) + 1;
            }

            foreach ($linkDiagnostics as $diagnostic) {
                if (!is_array($diagnostic)) {
                    continue;
                }

                $diagnostics[] = [
                    'index' => $index,
                    'id' => is_string($link['id'] ?? null) ? $link['id'] : null,
                    'href' => is_string($link['href'] ?? null) ? $link['href'] : null,
                ] + $diagnostic;
            }
        }

        return [
            'present' => $links !== [],
            'count' => count($links),
            'localCount' => $localCount,
            'externalCount' => $externalCount,
            'missingCount' => $missingCount,
            'encryptedCount' => $encryptedCount,
            'recordLinkCount' => $recordLinkCount,
            'reviewRequiredCount' => $reviewRequiredCount,
            'relTokens' => array_keys($relCounts),
            'relCounts' => $relCounts,
            'linksByRel' => $linksByRel,
            'propertyTokens' => array_keys($propertyCounts),
            'propertyCounts' => $propertyCounts,
            'diagnosticCount' => count($diagnostics),
            'diagnostics' => $diagnostics,
        ];
    }

    /**
     * @param array<string, string> $prefixBindings
     *
     * @return array<string, mixed>
     */
    private static function collectionRoleReport(?string $role, array $prefixBindings): array
    {
        $raw = trim((string) $role);
        $values = $raw === '' ? [] : preg_split('/\s+/', $raw);
        $values = array_values(array_filter(
            array_map(static fn (mixed $value): string => trim((string) $value), $values ?: []),
            static fn (string $value): bool => $value !== '',
        ));
        $prefixBindings = self::metadataVocabularyPrefixBindings($prefixBindings);
        $items = [];
        $diagnostics = [];
        $seen = [];

        if ($raw === '') {
            $diagnostics[] = [
                'type' => 'missing-collection-role',
                'message' => 'EPUB OPF collection elements should identify their purpose with a role value',
            ];
        }

        foreach ($values as $index => $value) {
            $isAbsolute = self::isAbsoluteUrlWithFragment($value);
            $looksAbsolute = preg_match('/^[A-Za-z][A-Za-z0-9+.-]*:/', $value) === 1
                && !preg_match('/^[A-Za-z_][A-Za-z0-9._-]*:[A-Za-z0-9._-]+$/', $value);
            $isNmtoken = preg_match('/^[A-Za-z0-9._:-]+$/', $value) === 1;
            $itemDiagnostics = [];

            if (!$isNmtoken && !$isAbsolute) {
                $itemDiagnostics[] = [
                    'type' => $looksAbsolute ? 'invalid-collection-role-url-fragment' : 'invalid-collection-role-token',
                    'role' => $value,
                    'index' => $index,
                    'message' => $looksAbsolute
                        ? 'EPUB OPF absolute collection role URLs must include a fragment identifier'
                        : 'EPUB OPF collection role values must be NMTOKENs or absolute URLs with fragments',
                ];
            }

            if (isset($seen[$value])) {
                $itemDiagnostics[] = [
                    'type' => 'duplicate-collection-role-token',
                    'role' => $value,
                    'index' => $index,
                    'previousIndex' => $seen[$value],
                    'message' => 'EPUB OPF collection role value is repeated',
                ];
            }
            $seen[$value] = $index;

            $prefix = null;
            $localName = null;
            $bindingIri = null;
            $iri = $isAbsolute ? $value : null;
            $resolved = $isAbsolute;
            if (!$isAbsolute && preg_match('/^([A-Za-z_][A-Za-z0-9._-]*):([A-Za-z0-9._-]+)$/', $value, $matches) === 1) {
                $prefix = $matches[1];
                $localName = $matches[2];
                $bindingIri = $prefixBindings[$prefix] ?? null;
                if ($bindingIri === null || $bindingIri === '') {
                    $itemDiagnostics[] = [
                        'type' => 'unknown-collection-role-prefix',
                        'role' => $value,
                        'prefix' => $prefix,
                        'index' => $index,
                        'message' => 'EPUB OPF collection role uses a prefixed token without a package prefix declaration',
                    ];
                } else {
                    $iri = $bindingIri . $localName;
                    $resolved = true;
                }
            }

            $item = [
                'index' => $index,
                'value' => $value,
                'raw' => $value,
                'kind' => $isAbsolute ? 'absolute-url-with-fragment' : ($prefix === null ? 'nmtoken' : 'prefixed-nmtoken'),
                'nmtoken' => $isNmtoken,
                'absoluteUrlWithFragment' => $isAbsolute,
                'valid' => $itemDiagnostics === [] || array_reduce(
                    $itemDiagnostics,
                    static fn (bool $valid, array $diagnostic): bool => $valid
                        && !in_array($diagnostic['type'] ?? '', ['invalid-collection-role-token', 'invalid-collection-role-url-fragment'], true),
                    true
                ),
                'prefix' => $prefix,
                'localName' => $localName,
                'bindingIri' => $bindingIri,
                'iri' => $iri,
                'resolved' => $resolved,
                'diagnostics' => $itemDiagnostics,
            ];
            foreach ($itemDiagnostics as $diagnostic) {
                $diagnostics[] = $diagnostic;
            }
            $items[] = $item;
        }

        $validItems = array_values(array_filter(
            $items,
            static fn (array $item): bool => ($item['valid'] ?? false) === true,
        ));
        $resolvedItems = array_values(array_filter(
            $items,
            static fn (array $item): bool => ($item['resolved'] ?? false) === true,
        ));
        $absoluteItems = array_values(array_filter(
            $items,
            static fn (array $item): bool => ($item['absoluteUrlWithFragment'] ?? false) === true,
        ));

        return [
            'present' => $raw !== '',
            'raw' => $raw,
            'values' => $values,
            'primaryRole' => is_array($validItems[0] ?? null) ? (string) $validItems[0]['value'] : null,
            'items' => $items,
            'count' => count($items),
            'validCount' => count($validItems),
            'invalidCount' => count($items) - count($validItems),
            'resolvedCount' => count($resolvedItems),
            'absoluteUrlCount' => count($absoluteItems),
            'diagnostics' => $diagnostics,
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
            'fragment' => $reference['fragment'],
            'fragmentKind' => $reference['fragmentKind'],
            'epubCfi' => $reference['epubCfi'],
            'mediaFragment' => $reference['mediaFragment'],
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
     *     fragment:?string,
     *     fragmentKind:?string,
     *     epubCfi:?array<string, mixed>,
     *     mediaFragment:?array<string, mixed>,
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
            $fragmentFields = self::targetFragmentFields(null);

            return [
                'target' => null,
                'part' => null,
                'fragment' => $fragmentFields['fragment'],
                'fragmentKind' => $fragmentFields['fragmentKind'],
                'epubCfi' => $fragmentFields['epubCfi'],
                'mediaFragment' => $fragmentFields['mediaFragment'],
                'external' => false,
                'exists' => false,
                'byteLength' => null,
                'compressedByteLength' => null,
                'compressionMethod' => null,
                'compressionMethodName' => null,
                'compressionSupported' => null,
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
            $fragmentFields = self::targetFragmentFields($href);

            return [
                'target' => $href,
                'part' => null,
                'fragment' => $fragmentFields['fragment'],
                'fragmentKind' => $fragmentFields['fragmentKind'],
                'epubCfi' => $fragmentFields['epubCfi'],
                'mediaFragment' => $fragmentFields['mediaFragment'],
                'external' => true,
                'exists' => false,
                'byteLength' => null,
                'compressedByteLength' => null,
                'compressionMethod' => null,
                'compressionMethodName' => null,
                'compressionSupported' => null,
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
            $fragmentFields = self::targetFragmentFields(null);

            return [
                'target' => null,
                'part' => null,
                'fragment' => $fragmentFields['fragment'],
                'fragmentKind' => $fragmentFields['fragmentKind'],
                'epubCfi' => $fragmentFields['epubCfi'],
                'mediaFragment' => $fragmentFields['mediaFragment'],
                'external' => false,
                'exists' => false,
                'byteLength' => null,
                'compressedByteLength' => null,
                'compressionMethod' => null,
                'compressionMethodName' => null,
                'compressionSupported' => null,
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
        $fragmentFields = self::targetFragmentFields($target);
        $exists = $package->has($part);
        $entry = $exists ? $package->entry($part) : null;
        $provenance = self::zipEntryProvenance($entry);
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
            'fragment' => $fragmentFields['fragment'],
            'fragmentKind' => $fragmentFields['fragmentKind'],
            'epubCfi' => $fragmentFields['epubCfi'],
            'mediaFragment' => $fragmentFields['mediaFragment'],
            'external' => false,
            'exists' => $exists,
            'byteLength' => $provenance['byteLength'],
            'compressedByteLength' => $provenance['compressedByteLength'],
            'compressionMethod' => $provenance['compressionMethod'],
            'compressionMethodName' => $provenance['compressionMethodName'],
            'compressionSupported' => $provenance['compressionSupported'],
            'crc32' => $provenance['crc32'],
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
     *     fragment:null,
     *     fragmentKind:null,
     *     epubCfi:null,
     *     mediaFragment:null,
     *     external:false,
     *     exists:false,
     *     byteLength:null,
     *     compressedByteLength:null,
     *     compressionMethod:null,
     *     compressionMethodName:null,
     *     compressionSupported:null,
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
            'fragment' => null,
            'fragmentKind' => null,
            'epubCfi' => null,
            'mediaFragment' => null,
            'external' => false,
            'exists' => false,
            'byteLength' => null,
            'compressedByteLength' => null,
            'compressionMethod' => null,
            'compressionMethodName' => null,
            'compressionSupported' => null,
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
     * @param list<array<string, mixed>> $xhtmlAssets
     *
     * @return array{
     *     summary:array<string, int>,
     *     items:list<array<string, mixed>>,
     *     itemsById:array<string, array<string, mixed>>,
     *     itemsByProperty:array<string, list<array<string, mixed>>>,
     *     reviewItems:list<array<string, mixed>>,
     *     contentFeatureReconciliation:array<string, mixed>
     * }
     */
    private static function resourcePropertyReport(array $manifest, array $xhtmlAssets = []): array
    {
        $propertyVocabulary = self::manifestPropertyVocabularySummary($manifest);
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
                'propertyVocabulary' => is_array($item['propertyVocabulary'] ?? null) ? $item['propertyVocabulary'] : null,
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
            'propertyVocabulary' => $propertyVocabulary,
            'contentFeatureReconciliation' => self::contentFeatureReconciliationReport($xhtmlAssets),
        ];
    }

    /**
     * @param list<array<string, mixed>> $xhtmlAssets
     *
     * @return array<string, mixed>
     */
    private static function contentFeatureReconciliationReport(array $xhtmlAssets): array
    {
        $featureProperties = [
            'mathml' => 'mathml',
            'svg' => 'svg',
            'scripted' => 'scripted',
            'switch' => 'switch',
        ];
        $featureNames = array_values($featureProperties);
        $items = [];
        $itemsById = [];
        $itemsByPart = [];
        $undeclaredItems = [];
        $declaredButUnobservedItems = [];
        $diagnostics = [];
        $declaredFeatureCount = 0;
        $observedFeatureCount = 0;
        $matchedFeatureCount = 0;
        $undeclaredFeatureCount = 0;
        $declaredButUnobservedFeatureCount = 0;

        foreach ($xhtmlAssets as $asset) {
            $resourceFlags = is_array($asset['resourceFlags'] ?? null) ? $asset['resourceFlags'] : [];
            $contentResourceFlags = is_array($asset['contentResourceFlags'] ?? null) ? $asset['contentResourceFlags'] : [];
            $declaredFeatures = [];
            $observedFeatures = [];
            $matchedFeatures = [];
            $undeclaredFeatures = [];
            $declaredButUnobservedFeatures = [];

            foreach ($featureProperties as $flag => $property) {
                $declared = ($resourceFlags[$flag] ?? false) === true;
                $observed = ($contentResourceFlags[$flag] ?? false) === true;

                if ($declared) {
                    $declaredFeatures[] = $property;
                }
                if ($observed) {
                    $observedFeatures[] = $property;
                }
                if ($declared && $observed) {
                    $matchedFeatures[] = $property;
                } elseif ($observed) {
                    $undeclaredFeatures[] = $property;
                } elseif ($declared) {
                    $declaredButUnobservedFeatures[] = $property;
                }
            }

            if ($declaredFeatures === [] && $observedFeatures === []) {
                continue;
            }

            $itemDiagnostics = [];
            $id = (string) ($asset['id'] ?? '');
            $part = is_string($asset['part'] ?? null) ? $asset['part'] : '';
            if ($undeclaredFeatures !== []) {
                $diagnostic = [
                    'type' => 'undeclared-xhtml-content-feature-properties',
                    'id' => $id,
                    'part' => $part === '' ? null : $part,
                    'features' => $undeclaredFeatures,
                    'message' => 'EPUB XHTML content uses review-significant content features not declared in the OPF manifest item properties',
                ];
                $itemDiagnostics[] = $diagnostic;
                $diagnostics[] = $diagnostic;
            }
            if ($declaredButUnobservedFeatures !== []) {
                $diagnostic = [
                    'type' => 'declared-xhtml-content-feature-properties-not-observed',
                    'id' => $id,
                    'part' => $part === '' ? null : $part,
                    'features' => $declaredButUnobservedFeatures,
                    'message' => 'EPUB OPF manifest item declares content feature properties that were not observed by the bounded XHTML scan',
                ];
                $itemDiagnostics[] = $diagnostic;
                $diagnostics[] = $diagnostic;
            }

            $item = [
                'id' => $id,
                'href' => (string) ($asset['href'] ?? ''),
                'target' => is_string($asset['target'] ?? null) ? $asset['target'] : null,
                'part' => $part === '' ? null : $part,
                'mediaType' => is_string($asset['mediaType'] ?? null) ? $asset['mediaType'] : self::XHTML_MEDIA_TYPE,
                'manifestProperties' => is_array($asset['properties'] ?? null) ? array_values($asset['properties']) : [],
                'declaredFeatures' => $declaredFeatures,
                'observedFeatures' => $observedFeatures,
                'matchedFeatures' => $matchedFeatures,
                'undeclaredFeatures' => $undeclaredFeatures,
                'declaredButUnobservedFeatures' => $declaredButUnobservedFeatures,
                'manifestReviewFlags' => is_array($asset['resourceReviewFlags'] ?? null)
                    ? array_values($asset['resourceReviewFlags'])
                    : self::resourceReviewFlags($resourceFlags),
                'contentReviewFlags' => is_array($asset['contentResourceReviewFlags'] ?? null)
                    ? array_values($asset['contentResourceReviewFlags'])
                    : self::xhtmlContentReviewFlags($contentResourceFlags),
                'diagnostics' => $itemDiagnostics,
            ];

            $items[] = $item;
            if ($id !== '') {
                $itemsById[$id] = $item;
            }
            if ($part !== '') {
                $itemsByPart[$part] = $item;
            }
            if ($undeclaredFeatures !== []) {
                $undeclaredItems[] = $item;
            }
            if ($declaredButUnobservedFeatures !== []) {
                $declaredButUnobservedItems[] = $item;
            }

            $declaredFeatureCount += count($declaredFeatures);
            $observedFeatureCount += count($observedFeatures);
            $matchedFeatureCount += count($matchedFeatures);
            $undeclaredFeatureCount += count($undeclaredFeatures);
            $declaredButUnobservedFeatureCount += count($declaredButUnobservedFeatures);
        }

        return [
            'present' => $items !== [],
            'features' => $featureNames,
            'itemCount' => count($items),
            'declaredFeatureCount' => $declaredFeatureCount,
            'observedFeatureCount' => $observedFeatureCount,
            'matchedFeatureCount' => $matchedFeatureCount,
            'undeclaredFeatureCount' => $undeclaredFeatureCount,
            'declaredButUnobservedFeatureCount' => $declaredButUnobservedFeatureCount,
            'undeclaredItemCount' => count($undeclaredItems),
            'declaredButUnobservedItemCount' => count($declaredButUnobservedItems),
            'items' => $items,
            'itemsById' => $itemsById,
            'itemsByPart' => $itemsByPart,
            'undeclaredItems' => $undeclaredItems,
            'declaredButUnobservedItems' => $declaredButUnobservedItems,
            'diagnosticCount' => count($diagnostics),
            'diagnostics' => $diagnostics,
        ];
    }

    /**
     * @param list<array<string, mixed>> $manifest
     *
     * @return array<string, mixed>
     */
    private static function manifestPropertyVocabularySummary(array $manifest): array
    {
        $items = [];
        $itemsById = [];
        $byPrefix = [];
        $diagnostics = [];
        $propertyTokenCount = 0;
        $prefixedPropertyCount = 0;
        $resolvedPropertyCount = 0;
        $unresolvedPropertyCount = 0;

        foreach ($manifest as $item) {
            $report = is_array($item['propertyVocabulary'] ?? null) ? $item['propertyVocabulary'] : null;
            if (!is_array($report) || ($report['count'] ?? 0) === 0) {
                continue;
            }

            $manifestId = (string) ($item['id'] ?? '');
            $summaryItem = [
                'id' => $manifestId,
                'href' => (string) ($item['href'] ?? ''),
                'target' => is_string($item['target'] ?? null) ? $item['target'] : null,
                'part' => is_string($item['part'] ?? null) ? $item['part'] : null,
                'external' => (bool) ($item['external'] ?? false),
                'mediaType' => (string) ($item['mediaType'] ?? ''),
                'properties' => is_array($item['properties'] ?? null) ? array_values($item['properties']) : [],
                'propertyVocabulary' => $report,
            ];
            $items[] = $summaryItem;
            if ($manifestId !== '') {
                $itemsById[$manifestId] = $summaryItem;
            }

            foreach ($report['items'] ?? [] as $propertyItem) {
                if (!is_array($propertyItem)) {
                    continue;
                }

                ++$propertyTokenCount;
                $vocabulary = is_array($propertyItem['vocabulary'] ?? null) ? $propertyItem['vocabulary'] : null;
                if (!is_array($vocabulary) || ($vocabulary['prefixed'] ?? false) !== true) {
                    continue;
                }

                ++$prefixedPropertyCount;
                $prefix = is_string($vocabulary['prefix'] ?? null) ? $vocabulary['prefix'] : '';
                if ($prefix !== '') {
                    if (!isset($byPrefix[$prefix])) {
                        $byPrefix[$prefix] = [
                            'prefix' => $prefix,
                            'bindingIri' => is_string($vocabulary['bindingIri'] ?? null) ? $vocabulary['bindingIri'] : null,
                            'propertyTokenCount' => 0,
                            'resolvedCount' => 0,
                            'unresolvedCount' => 0,
                            'properties' => [],
                            'manifestIds' => [],
                        ];
                    }

                    ++$byPrefix[$prefix]['propertyTokenCount'];
                    $byPrefix[$prefix]['properties'][] = (string) ($propertyItem['property'] ?? '');
                    $byPrefix[$prefix]['manifestIds'][] = $manifestId;
                }

                if (($vocabulary['resolved'] ?? false) === true) {
                    ++$resolvedPropertyCount;
                    if ($prefix !== '') {
                        ++$byPrefix[$prefix]['resolvedCount'];
                    }
                } else {
                    ++$unresolvedPropertyCount;
                    if ($prefix !== '') {
                        ++$byPrefix[$prefix]['unresolvedCount'];
                    }
                    foreach ($vocabulary['diagnostics'] ?? [] as $diagnostic) {
                        if (is_array($diagnostic)) {
                            $diagnostics[] = [
                                'manifestId' => $manifestId,
                                'href' => (string) ($item['href'] ?? ''),
                                'index' => (int) ($propertyItem['index'] ?? 0),
                                'property' => (string) ($propertyItem['property'] ?? ''),
                            ] + $diagnostic;
                        }
                    }
                }
            }
        }

        foreach ($byPrefix as $prefix => $summary) {
            $byPrefix[$prefix]['properties'] = array_values(array_unique(array_filter(
                $summary['properties'],
                static fn (string $property): bool => $property !== '',
            )));
            $byPrefix[$prefix]['manifestIds'] = array_values(array_unique(array_filter(
                $summary['manifestIds'],
                static fn (string $manifestId): bool => $manifestId !== '',
            )));
        }

        return [
            'present' => $propertyTokenCount > 0,
            'itemCount' => count($items),
            'propertyTokenCount' => $propertyTokenCount,
            'prefixedPropertyCount' => $prefixedPropertyCount,
            'resolvedPropertyCount' => $resolvedPropertyCount,
            'unresolvedPropertyCount' => $unresolvedPropertyCount,
            'items' => $items,
            'itemsById' => $itemsById,
            'byPrefix' => $byPrefix,
            'diagnostics' => $diagnostics,
            'diagnosticCount' => count($diagnostics),
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
            if (self::mediaTypeBaseEquals($item['mediaType'] ?? null, self::NCX_MEDIA_TYPE)) {
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
     *     pageList:list<array<string, mixed>>,
     *     auxiliaryNavigation:array<string, mixed>,
     *     auxiliarySections:list<array<string, mixed>>,
     *     auxiliaryItems:list<array<string, mixed>>,
     *     documentDiagnostics:array<string, mixed>,
     *     documentDiagnosticCount:int
     * }
     */
    private function readNavDocument(ZipPackage $package, array $item, array $manifestByPart): array
    {
        $part = (string) $item['part'];
        if (self::isEncryptedManifestItem($item)) {
            $documentDiagnostics = self::navDocumentDiagnosticReport([], $part, true);

            return [
                'part' => $part,
                'items' => [],
                'sections' => [],
                'sectionsByType' => [],
                'sectionCount' => 0,
                'hiddenSectionCount' => 0,
                'hiddenItemCount' => 0,
                'landmarks' => [],
                'pageList' => [],
                'auxiliaryNavigation' => self::auxiliaryNavReport([]),
                'auxiliarySections' => [],
                'auxiliaryItems' => [],
                'encrypted' => true,
                'encryption' => $item['encryption'] ?? null,
                'documentDiagnostics' => $documentDiagnostics,
                'documentDiagnosticCount' => $documentDiagnostics['diagnosticCount'],
            ];
        }

        $dom = self::loadXml($package->read($part), 'EPUB navigation XHTML');
        $sections = [];
        $sectionsByType = [];
        $hiddenSectionCount = 0;
        $hiddenItemCount = 0;
        $tocItems = null;
        $fallbackItems = null;
        foreach (self::navigationElements($dom) as $nav) {
            $types = self::epubTypes($nav);
            $list = self::firstChildElement($nav, 'ol', self::XHTML_NS);
            $items = $list instanceof \DOMElement ? $this->readNavList($package, $list, $part, $manifestByPart) : [];
            $itemDiagnosticSummary = self::navItemDocumentDiagnosticSummary($items);
            $section = [
                'id' => self::nullableAttribute($nav, 'id'),
                'class' => self::nullableAttribute($nav, 'class'),
                'classes' => self::spaceDelimited($nav->getAttribute('class')),
                'language' => self::xmlLang($nav),
                'direction' => self::direction($nav),
                'hidden' => self::elementHidden($nav),
                'attributes' => self::elementAttributes($nav),
                'type' => $types[0] ?? null,
                'types' => $types,
                'title' => self::navHeading($nav),
                'hasOrderedList' => $list instanceof \DOMElement,
                'itemCount' => count(self::flattenNavigationItems($items)),
                'hiddenItemCount' => $itemDiagnosticSummary['hiddenCount'],
                'missingItemLabelCount' => $itemDiagnosticSummary['missingLabelCount'],
                'emptyItemLabelCount' => $itemDiagnosticSummary['emptyLabelCount'],
                'missingItemHrefCount' => $itemDiagnosticSummary['missingHrefCount'],
                'missingItemLinkCount' => $itemDiagnosticSummary['missingLinkCount'],
                'unlabeledParentItemCount' => $itemDiagnosticSummary['unlabeledParentCount'],
                'itemDiagnosticCount' => $itemDiagnosticSummary['diagnosticCount'],
                'items' => $items,
            ];

            $sections[] = $section;
            foreach ($types as $type) {
                $sectionsByType[$type][] = $section;
            }
            if ($section['hidden']) {
                ++$hiddenSectionCount;
            }
            foreach (self::flattenNavigationItems($items) as $flat) {
                if (($flat['item']['hidden'] ?? false) === true) {
                    ++$hiddenItemCount;
                }
            }
            $fallbackItems ??= $items;
            if (in_array('toc', $types, true) && $tocItems === null) {
                $tocItems = $items;
            }
        }

        if ($sections === []) {
            $documentDiagnostics = self::navDocumentDiagnosticReport([], $part);

            return [
                'part' => $part,
                'items' => [],
                'sections' => [],
                'sectionsByType' => [],
                'sectionCount' => 0,
                'hiddenSectionCount' => 0,
                'hiddenItemCount' => 0,
                'landmarks' => [],
                'pageList' => [],
                'auxiliaryNavigation' => self::auxiliaryNavReport([]),
                'auxiliarySections' => [],
                'auxiliaryItems' => [],
                'documentDiagnostics' => $documentDiagnostics,
                'documentDiagnosticCount' => $documentDiagnostics['diagnosticCount'],
            ];
        }

        $auxiliaryNavigation = self::auxiliaryNavReport($sections);
        $documentDiagnostics = self::navDocumentDiagnosticReport($sections, $part);

        return [
            'part' => $part,
            'items' => $tocItems ?? $fallbackItems ?? [],
            'sections' => $sections,
            'sectionsByType' => $sectionsByType,
            'sectionCount' => count($sections),
            'hiddenSectionCount' => $hiddenSectionCount,
            'hiddenItemCount' => $hiddenItemCount,
            'landmarks' => self::navItemsForType($sections, 'landmarks'),
            'pageList' => self::navItemsForType($sections, 'page-list'),
            'auxiliaryNavigation' => $auxiliaryNavigation,
            'auxiliarySections' => $auxiliaryNavigation['sections'],
            'auxiliaryItems' => $auxiliaryNavigation['items'],
            'documentDiagnostics' => $documentDiagnostics,
            'documentDiagnosticCount' => $documentDiagnostics['diagnosticCount'],
        ];
    }

    /**
     * @param list<array<string, mixed>> $sections
     *
     * @return array<string, mixed>
     */
    private static function navDocumentDiagnosticReport(array $sections, string $part, bool $encrypted = false): array
    {
        $primaryTypes = [
            'toc' => true,
            'landmarks' => true,
            'page-list' => true,
        ];
        $typeSections = [
            'toc' => [],
            'landmarks' => [],
            'page-list' => [],
        ];
        $diagnostics = [];
        $emptySectionCount = 0;
        $hiddenPrimarySectionCount = 0;
        $missingHeadingSectionCount = 0;
        $missingPrimaryItemLabelCount = 0;
        $missingOrderedListSectionCount = 0;
        $missingEntryLabelCount = 0;
        $multiPrimarySectionCount = 0;
        $untypedSectionCount = 0;
        $missingItemLabelCount = 0;
        $emptyItemLabelCount = 0;
        $missingItemHrefCount = 0;
        $missingItemLinkCount = 0;
        $unlabeledParentItemCount = 0;
        $hiddenItemCount = 0;
        $itemDiagnosticCount = 0;
        $itemDiagnostics = [];
        $sourceDiagnosticCount = 0;
        $sourceDiagnostics = [];
        $invalidFragmentTargetCount = 0;
        $invalidFragmentDiagnostics = [];
        $navItemCount = 0;
        $targetedNavItemCount = 0;
        $targetsBySection = [];
        $itemIds = [];
        $labelIds = [];

        if ($encrypted) {
            $diagnostics[] = [
                'type' => 'encrypted-nav-document',
                'part' => $part,
                'message' => 'EPUB navigation document is encrypted and cannot be inspected for nav structure',
            ];
        } elseif ($sections === []) {
            $diagnostics[] = [
                'type' => 'missing-nav-document-section',
                'part' => $part,
                'message' => 'EPUB navigation document does not contain any XHTML nav sections',
            ];
        }

        foreach ($sections as $sectionIndex => $section) {
            $sectionTypes = array_values(array_filter(
                is_array($section['types'] ?? null) ? $section['types'] : [],
                static fn (mixed $type): bool => is_string($type) && $type !== '',
            ));
            $sectionId = is_string($section['id'] ?? null) ? $section['id'] : null;
            $sectionTitle = is_string($section['title'] ?? null) ? $section['title'] : '';
            $sectionItems = is_array($section['items'] ?? null) ? $section['items'] : [];
            $flatItems = self::flattenNavigationItems($sectionItems);
            $itemCount = is_int($section['itemCount'] ?? null)
                ? $section['itemCount']
                : count($flatItems);
            $navItemCount += count($flatItems);

            foreach ($flatItems as $flatIndex => $flat) {
                $item = is_array($flat['item'] ?? null) ? $flat['item'] : [];
                $itemId = is_string($item['itemId'] ?? null) ? $item['itemId'] : null;
                $labelId = is_string($item['labelId'] ?? null) ? $item['labelId'] : null;
                $itemContext = [
                    'sectionIndex' => $sectionIndex,
                    'sectionId' => $sectionId,
                    'sectionTypes' => $sectionTypes,
                    'itemIndex' => $flatIndex,
                    'depth' => (int) ($flat['depth'] ?? 0),
                    'itemId' => $itemId,
                    'labelId' => $labelId,
                    'title' => is_string($item['title'] ?? null) ? $item['title'] : '',
                    'href' => is_string($item['href'] ?? null) ? $item['href'] : null,
                    'target' => is_string($item['target'] ?? null) ? $item['target'] : null,
                    'part' => is_string($item['part'] ?? null) ? $item['part'] : null,
                    'fragmentKind' => is_string($item['fragmentKind'] ?? null) ? $item['fragmentKind'] : null,
                ];
                if ($itemId !== null && $itemId !== '') {
                    $itemIds[$itemId][] = $itemContext;
                }
                if ($labelId !== null && $labelId !== '') {
                    $labelIds[$labelId][] = $itemContext;
                }

                foreach (is_array($item['diagnostics'] ?? null) ? $item['diagnostics'] : [] as $sourceDiagnostic) {
                    if (!is_array($sourceDiagnostic)) {
                        continue;
                    }

                    ++$sourceDiagnosticCount;
                    $normalizedSourceDiagnostic = $itemContext + $sourceDiagnostic;
                    $sourceDiagnostics[] = $normalizedSourceDiagnostic;
                    $diagnostics[] = $normalizedSourceDiagnostic;
                }

                foreach (['epubCfi' => 'epub-cfi', 'mediaFragment' => 'media-fragment'] as $field => $fragmentKind) {
                    $fragment = is_array($item[$field] ?? null) ? $item[$field] : null;
                    if ($fragment === null || ($fragment['valid'] ?? true) === true) {
                        continue;
                    }

                    ++$invalidFragmentTargetCount;
                    foreach (is_array($fragment['diagnostics'] ?? null) ? $fragment['diagnostics'] : [] as $fragmentDiagnostic) {
                        if (!is_array($fragmentDiagnostic)) {
                            continue;
                        }

                        $normalizedFragmentDiagnostic = $itemContext + [
                            'fragmentKind' => $fragmentKind,
                            'fragmentReport' => $fragment,
                        ] + $fragmentDiagnostic;
                        $invalidFragmentDiagnostics[] = $normalizedFragmentDiagnostic;
                        $diagnostics[] = $normalizedFragmentDiagnostic;
                    }
                }

                $target = is_string($item['target'] ?? null) ? trim($item['target']) : '';
                if ($target === '') {
                    continue;
                }

                ++$targetedNavItemCount;
                $targetsBySection[$sectionIndex . "\0" . $target][] = [
                    'sectionIndex' => $sectionIndex,
                    'sectionId' => $sectionId,
                    'sectionTypes' => $sectionTypes,
                    'itemIndex' => $flatIndex,
                    'depth' => (int) ($flat['depth'] ?? 0),
                    'itemId' => $itemId,
                    'labelId' => $labelId,
                    'title' => $itemContext['title'],
                    'href' => is_string($item['href'] ?? null) ? $item['href'] : null,
                    'target' => $target,
                ];
            }

            if ($sectionTypes === []) {
                ++$untypedSectionCount;
                $diagnostics[] = [
                    'type' => 'missing-nav-section-type',
                    'part' => $part,
                    'sectionIndex' => $sectionIndex,
                    'sectionId' => $sectionId,
                    'title' => $sectionTitle,
                    'message' => 'EPUB navigation section is missing an epub:type value',
                ];
            }

            foreach ($sectionTypes as $sectionType) {
                if (isset($primaryTypes[$sectionType])) {
                    $typeSections[$sectionType][] = [
                        'sectionIndex' => $sectionIndex,
                        'sectionId' => $sectionId,
                        'title' => $sectionTitle,
                    ];
                }
            }

            $primarySectionTypes = array_values(array_filter(
                $sectionTypes,
                static fn (string $type): bool => isset($primaryTypes[$type]),
            ));
            if (count($primarySectionTypes) > 1) {
                ++$multiPrimarySectionCount;
                $diagnostics[] = [
                    'type' => 'multi-primary-nav-section-types',
                    'part' => $part,
                    'sectionIndex' => $sectionIndex,
                    'sectionId' => $sectionId,
                    'sectionTypes' => $sectionTypes,
                    'primarySectionTypes' => $primarySectionTypes,
                    'message' => 'EPUB navigation section declares multiple primary nav types; import review should choose a single role',
                ];
            }
            if ($primarySectionTypes !== [] && ($section['hidden'] ?? false) === true) {
                ++$hiddenPrimarySectionCount;
                $diagnostics[] = [
                    'type' => 'hidden-primary-nav-section',
                    'part' => $part,
                    'sectionIndex' => $sectionIndex,
                    'sectionId' => $sectionId,
                    'sectionTypes' => $primarySectionTypes,
                    'message' => 'EPUB primary navigation section is hidden and may not be visible to readers',
                ];
            }

            if ($primarySectionTypes !== [] && $sectionTitle === '') {
                ++$missingHeadingSectionCount;
                $diagnostics[] = [
                    'type' => 'missing-primary-nav-section-heading',
                    'part' => $part,
                    'sectionIndex' => $sectionIndex,
                    'sectionId' => $sectionId,
                    'sectionTypes' => $primarySectionTypes,
                    'message' => 'EPUB primary navigation section has no heading label for review handoff',
                ];
            }

            if ($primarySectionTypes !== []) {
                foreach ($flatItems as $itemIndex => $flat) {
                    $item = is_array($flat['item'] ?? null) ? $flat['item'] : [];
                    $label = is_string($item['title'] ?? null) ? trim($item['title']) : '';
                    if ($label !== '') {
                        continue;
                    }

                    ++$missingPrimaryItemLabelCount;
                    ++$missingEntryLabelCount;
                    $diagnostics[] = [
                        'type' => 'missing-primary-nav-item-label',
                        'part' => $part,
                        'sectionIndex' => $sectionIndex,
                        'sectionId' => $sectionId,
                        'sectionType' => $primarySectionTypes[0] ?? null,
                        'sectionTypes' => $primarySectionTypes,
                        'itemIndex' => $itemIndex,
                        'itemId' => is_string($item['itemId'] ?? null) ? $item['itemId'] : null,
                        'labelId' => is_string($item['labelId'] ?? null) ? $item['labelId'] : null,
                        'labelElement' => is_string($item['labelElement'] ?? null) ? $item['labelElement'] : null,
                        'href' => is_string($item['href'] ?? null) ? $item['href'] : null,
                        'target' => is_string($item['target'] ?? null) ? $item['target'] : null,
                        'depth' => (int) ($flat['depth'] ?? 0),
                        'message' => 'EPUB primary navigation item has no text label for review handoff',
                    ];
                }
            } else {
                foreach ($flatItems as $flatIndex => $flat) {
                    $entry = is_array($flat['item'] ?? null) ? $flat['item'] : [];
                    $label = is_string($entry['title'] ?? null) ? trim($entry['title']) : '';
                    $href = is_string($entry['href'] ?? null) ? trim($entry['href']) : '';
                    $target = is_string($entry['target'] ?? null) ? trim($entry['target']) : '';
                    if ($label !== '' || ($href === '' && $target === '')) {
                        continue;
                    }

                    ++$missingEntryLabelCount;
                    $diagnostics[] = [
                        'type' => 'missing-nav-entry-label',
                        'part' => $part,
                        'sectionIndex' => $sectionIndex,
                        'sectionId' => $sectionId,
                        'sectionTypes' => $sectionTypes,
                        'entryIndex' => $flatIndex,
                        'href' => $href === '' ? null : $href,
                        'target' => $target === '' ? null : $target,
                        'depth' => is_int($flat['depth'] ?? null) ? $flat['depth'] : null,
                        'message' => 'EPUB navigation entry has a target but no visible label for review handoff',
                    ];
                }
            }

            if (($section['hasOrderedList'] ?? false) !== true) {
                ++$missingOrderedListSectionCount;
                $diagnostics[] = [
                    'type' => 'missing-nav-section-ordered-list',
                    'part' => $part,
                    'sectionIndex' => $sectionIndex,
                    'sectionId' => $sectionId,
                    'sectionTypes' => $sectionTypes,
                    'message' => 'EPUB navigation section does not contain a direct ordered list',
                ];
            }

            if ($itemCount === 0) {
                ++$emptySectionCount;
                $diagnostics[] = [
                    'type' => 'empty-nav-section',
                    'part' => $part,
                    'sectionIndex' => $sectionIndex,
                    'sectionId' => $sectionId,
                    'sectionTypes' => $sectionTypes,
                    'message' => 'EPUB navigation section has no resolved navigation items',
                ];
            }

            foreach (self::flattenNavigationItems(is_array($section['items'] ?? null) ? $section['items'] : []) as $flatIndex => $flat) {
                $item = is_array($flat['item'] ?? null) ? $flat['item'] : [];
                foreach (is_array($item['documentDiagnostics'] ?? null) ? $item['documentDiagnostics'] : [] as $itemDiagnostic) {
                    if (!is_array($itemDiagnostic)) {
                        continue;
                    }

                    if (($itemDiagnostic['type'] ?? null) === 'missing-nav-item-label') {
                        ++$missingItemLabelCount;
                    } elseif (($itemDiagnostic['type'] ?? null) === 'empty-nav-item-label') {
                        ++$emptyItemLabelCount;
                    } elseif (($itemDiagnostic['type'] ?? null) === 'missing-nav-item-href') {
                        ++$missingItemHrefCount;
                    } elseif (($itemDiagnostic['type'] ?? null) === 'missing-nav-item-link') {
                        ++$missingItemLinkCount;
                    } elseif (($itemDiagnostic['type'] ?? null) === 'nav-item-child-list-without-label') {
                        ++$unlabeledParentItemCount;
                    } elseif (($itemDiagnostic['type'] ?? null) === 'hidden-nav-item') {
                        ++$hiddenItemCount;
                    }

                    ++$itemDiagnosticCount;
                    $normalizedItemDiagnostic = [
                        'part' => $part,
                        'sectionIndex' => $sectionIndex,
                        'sectionId' => $sectionId,
                        'sectionTypes' => $sectionTypes,
                        'itemIndex' => $flatIndex,
                        'depth' => (int) ($flat['depth'] ?? 0),
                    ] + $itemDiagnostic;
                    $itemDiagnostics[] = $normalizedItemDiagnostic;
                    $diagnostics[] = $normalizedItemDiagnostic;
                }
            }
        }

        $duplicateTargetDiagnostics = [];
        $duplicateTargetItemCount = 0;
        foreach ($targetsBySection as $matches) {
            if (count($matches) <= 1) {
                continue;
            }

            $duplicateTargetItemCount += count($matches);
            $diagnostic = [
                'type' => 'duplicate-nav-item-target',
                'part' => $part,
                'sectionIndex' => $matches[0]['sectionIndex'],
                'sectionId' => $matches[0]['sectionId'],
                'sectionTypes' => $matches[0]['sectionTypes'],
                'target' => $matches[0]['target'],
                'itemCount' => count($matches),
                'itemIndexes' => array_column($matches, 'itemIndex'),
                'itemIds' => array_values(array_filter(
                    array_column($matches, 'itemId'),
                    static fn (mixed $id): bool => is_string($id) && $id !== '',
                )),
                'labelIds' => array_values(array_filter(
                    array_column($matches, 'labelId'),
                    static fn (mixed $id): bool => is_string($id) && $id !== '',
                )),
                'titles' => array_values(array_filter(
                    array_column($matches, 'title'),
                    static fn (mixed $title): bool => is_string($title) && $title !== '',
                )),
                'message' => 'EPUB navigation section contains multiple items targeting the same package location',
            ];
            $duplicateTargetDiagnostics[] = $diagnostic;
            $diagnostics[] = $diagnostic;
        }

        $duplicateItemIdDiagnostics = [];
        foreach ($itemIds as $id => $matches) {
            if (count($matches) <= 1) {
                continue;
            }

            $diagnostic = [
                'type' => 'duplicate-nav-item-id',
                'part' => $part,
                'itemId' => $id,
                'itemCount' => count($matches),
                'sectionIndexes' => array_values(array_unique(array_column($matches, 'sectionIndex'))),
                'sectionIds' => array_values(array_filter(
                    array_unique(array_column($matches, 'sectionId')),
                    static fn (mixed $sectionId): bool => is_string($sectionId) && $sectionId !== '',
                )),
                'itemIndexes' => array_column($matches, 'itemIndex'),
                'labelIds' => array_values(array_filter(
                    array_column($matches, 'labelId'),
                    static fn (mixed $id): bool => is_string($id) && $id !== '',
                )),
                'titles' => array_values(array_filter(
                    array_column($matches, 'title'),
                    static fn (mixed $title): bool => is_string($title) && $title !== '',
                )),
                'message' => 'EPUB navigation document reuses the same list item id for more than one nav item',
            ];
            $duplicateItemIdDiagnostics[] = $diagnostic;
            $diagnostics[] = $diagnostic;
        }

        $duplicateLabelIdDiagnostics = [];
        foreach ($labelIds as $id => $matches) {
            if (count($matches) <= 1) {
                continue;
            }

            $diagnostic = [
                'type' => 'duplicate-nav-label-id',
                'part' => $part,
                'labelId' => $id,
                'itemCount' => count($matches),
                'sectionIndexes' => array_values(array_unique(array_column($matches, 'sectionIndex'))),
                'sectionIds' => array_values(array_filter(
                    array_unique(array_column($matches, 'sectionId')),
                    static fn (mixed $sectionId): bool => is_string($sectionId) && $sectionId !== '',
                )),
                'itemIndexes' => array_column($matches, 'itemIndex'),
                'itemIds' => array_values(array_filter(
                    array_column($matches, 'itemId'),
                    static fn (mixed $id): bool => is_string($id) && $id !== '',
                )),
                'titles' => array_values(array_filter(
                    array_column($matches, 'title'),
                    static fn (mixed $title): bool => is_string($title) && $title !== '',
                )),
                'message' => 'EPUB navigation document reuses the same label id for more than one nav item',
            ];
            $duplicateLabelIdDiagnostics[] = $diagnostic;
            $diagnostics[] = $diagnostic;
        }

        $duplicatePrimaryTypeCount = 0;
        foreach ($typeSections as $type => $matches) {
            if (count($matches) <= 1) {
                continue;
            }

            ++$duplicatePrimaryTypeCount;
            $diagnostics[] = [
                'type' => 'duplicate-primary-nav-section',
                'part' => $part,
                'sectionType' => $type,
                'sectionCount' => count($matches),
                'sectionIndexes' => array_column($matches, 'sectionIndex'),
                'sectionIds' => array_values(array_filter(
                    array_column($matches, 'sectionId'),
                    static fn (mixed $id): bool => is_string($id) && $id !== '',
                )),
                'message' => 'EPUB navigation document declares more than one primary section for the same nav type',
            ];
        }

        if (!$encrypted && $sections !== [] && $typeSections['toc'] === []) {
            $diagnostics[] = [
                'type' => 'missing-nav-toc-section',
                'part' => $part,
                'message' => 'EPUB navigation document is missing a toc nav section',
            ];
        }

        $diagnosticTypes = [];
        foreach ($diagnostics as $diagnostic) {
            if (!is_array($diagnostic)) {
                continue;
            }

            $type = is_string($diagnostic['type'] ?? null) ? $diagnostic['type'] : '';
            if ($type === '') {
                continue;
            }

            $diagnosticTypes[$type] = ($diagnosticTypes[$type] ?? 0) + 1;
        }

        return [
            'present' => $sections !== [],
            'part' => $part,
            'sectionCount' => count($sections),
            'primarySectionCount' => count($typeSections['toc']) + count($typeSections['landmarks']) + count($typeSections['page-list']),
            'tocSectionCount' => count($typeSections['toc']),
            'landmarksSectionCount' => count($typeSections['landmarks']),
            'pageListSectionCount' => count($typeSections['page-list']),
            'itemCount' => $navItemCount,
            'targetedItemCount' => $targetedNavItemCount,
            'duplicatePrimaryTypeCount' => $duplicatePrimaryTypeCount,
            'duplicateTargetGroupCount' => count($duplicateTargetDiagnostics),
            'duplicateTargetItemCount' => $duplicateTargetItemCount,
            'duplicateTargetDiagnostics' => $duplicateTargetDiagnostics,
            'duplicateItemIdCount' => count($duplicateItemIdDiagnostics),
            'duplicateItemIdDiagnostics' => $duplicateItemIdDiagnostics,
            'duplicateLabelIdCount' => count($duplicateLabelIdDiagnostics),
            'duplicateLabelIdDiagnostics' => $duplicateLabelIdDiagnostics,
            'emptySectionCount' => $emptySectionCount,
            'hiddenPrimarySectionCount' => $hiddenPrimarySectionCount,
            'missingHeadingSectionCount' => $missingHeadingSectionCount,
            'missingPrimaryItemLabelCount' => $missingPrimaryItemLabelCount,
            'missingOrderedListSectionCount' => $missingOrderedListSectionCount,
            'missingEntryLabelCount' => $missingEntryLabelCount,
            'multiPrimarySectionCount' => $multiPrimarySectionCount,
            'untypedSectionCount' => $untypedSectionCount,
            'missingItemLabelCount' => $missingItemLabelCount,
            'emptyItemLabelCount' => $emptyItemLabelCount,
            'missingItemHrefCount' => $missingItemHrefCount,
            'missingItemLinkCount' => $missingItemLinkCount,
            'unlabeledParentItemCount' => $unlabeledParentItemCount,
            'hiddenItemCount' => $hiddenItemCount,
            'itemDiagnosticCount' => $itemDiagnosticCount,
            'itemDiagnostics' => $itemDiagnostics,
            'sourceDiagnosticCount' => $sourceDiagnosticCount,
            'sourceDiagnostics' => $sourceDiagnostics,
            'invalidFragmentTargetCount' => $invalidFragmentTargetCount,
            'invalidFragmentDiagnostics' => $invalidFragmentDiagnostics,
            'diagnosticTypes' => $diagnosticTypes,
            'diagnosticCount' => count($diagnostics),
            'diagnostics' => $diagnostics,
        ];
    }

    /**
     * @param list<array<string, mixed>> $items
     *
     * @return array{hiddenCount:int, missingLabelCount:int, emptyLabelCount:int, missingHrefCount:int, missingLinkCount:int, unlabeledParentCount:int, diagnosticCount:int}
     */
    private static function navItemDocumentDiagnosticSummary(array $items): array
    {
        $hiddenCount = 0;
        $missingLabelCount = 0;
        $emptyLabelCount = 0;
        $missingHrefCount = 0;
        $missingLinkCount = 0;
        $unlabeledParentCount = 0;
        $diagnosticCount = 0;

        foreach (self::flattenNavigationItems($items) as $flat) {
            $item = is_array($flat['item'] ?? null) ? $flat['item'] : [];
            foreach (is_array($item['documentDiagnostics'] ?? null) ? $item['documentDiagnostics'] : [] as $diagnostic) {
                if (!is_array($diagnostic)) {
                    continue;
                }

                ++$diagnosticCount;
                if (($diagnostic['type'] ?? null) === 'hidden-nav-item') {
                    ++$hiddenCount;
                } elseif (($diagnostic['type'] ?? null) === 'missing-nav-item-label') {
                    ++$missingLabelCount;
                } elseif (($diagnostic['type'] ?? null) === 'empty-nav-item-label') {
                    ++$emptyLabelCount;
                } elseif (($diagnostic['type'] ?? null) === 'missing-nav-item-href') {
                    ++$missingHrefCount;
                } elseif (($diagnostic['type'] ?? null) === 'missing-nav-item-link') {
                    ++$missingLinkCount;
                } elseif (($diagnostic['type'] ?? null) === 'nav-item-child-list-without-label') {
                    ++$unlabeledParentCount;
                }
            }
        }

        return [
            'hiddenCount' => $hiddenCount,
            'missingLabelCount' => $missingLabelCount,
            'emptyLabelCount' => $emptyLabelCount,
            'missingHrefCount' => $missingHrefCount,
            'missingLinkCount' => $missingLinkCount,
            'unlabeledParentCount' => $unlabeledParentCount,
            'diagnosticCount' => $diagnosticCount,
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
     * @param array<string, mixed> $nav
     * @param list<array<string, mixed>> $spine
     *
     * @return array<string, mixed>
     */
    private static function navWithPrimaryNavigationTargetPolicy(array $nav, array $spine): array
    {
        $sections = is_array($nav['sections'] ?? null) ? array_values($nav['sections']) : [];
        $nav['primaryNavigationTargetPolicy'] = self::primaryNavigationTargetPolicyReport($sections, $spine);

        return $nav;
    }

    /**
     * @param list<array<string, mixed>> $sections
     * @param list<array<string, mixed>> $spine
     *
     * @return array{
     *     present:bool,
     *     sectionCount:int,
     *     itemCount:int,
     *     targetedItemCount:int,
     *     validTargetCount:int,
     *     externalTargetCount:int,
     *     missingTargetCount:int,
     *     missingReferenceCount:int,
     *     outsideSpineTargetCount:int,
     *     landmarkCount:int,
     *     landmarkMissingTypeCount:int,
     *     diagnosticCount:int,
     *     types:list<string>,
     *     sections:list<array<string, mixed>>,
     *     sectionsByType:array<string, list<array<string, mixed>>>,
     *     items:list<array<string, mixed>>,
     *     itemsBySectionType:array<string, list<array<string, mixed>>>,
     *     diagnostics:list<array<string, mixed>>
     * }
     */
    private static function primaryNavigationTargetPolicyReport(array $sections, array $spine): array
    {
        $primaryTypes = [
            'toc' => true,
            'landmarks' => true,
            'page-list' => true,
        ];
        $spineByContentPart = [];
        foreach ($spine as $spineItem) {
            $contentPart = is_string($spineItem['contentPart'] ?? null)
                ? $spineItem['contentPart']
                : (is_string($spineItem['part'] ?? null) ? $spineItem['part'] : null);
            if ($contentPart !== null && $contentPart !== '' && !isset($spineByContentPart[$contentPart])) {
                $spineByContentPart[$contentPart] = $spineItem;
            }
        }

        $reportedSections = [];
        $sectionsByType = [];
        $items = [];
        $itemsBySectionType = [];
        $diagnostics = [];
        $types = [];
        $targetedItemCount = 0;
        $validTargetCount = 0;
        $externalTargetCount = 0;
        $missingTargetCount = 0;
        $missingReferenceCount = 0;
        $outsideSpineTargetCount = 0;
        $landmarkCount = 0;
        $landmarkMissingTypeCount = 0;
        $mediaFragmentTargets = [];

        foreach ($sections as $sectionIndex => $section) {
            if (!is_array($section)) {
                continue;
            }

            $sectionTypes = array_values(array_filter(
                is_array($section['types'] ?? null) ? $section['types'] : [],
                static fn (mixed $type): bool => is_string($type) && $type !== '',
            ));
            $constrainedTypes = array_values(array_filter(
                $sectionTypes,
                static fn (string $type): bool => isset($primaryTypes[$type]),
            ));
            if ($constrainedTypes === []) {
                continue;
            }

            $sectionType = $constrainedTypes[0];
            $sectionItems = is_array($section['items'] ?? null) ? array_values($section['items']) : [];
            $flatItems = self::flattenNavigationItems($sectionItems);
            $summary = [
                'sectionIndex' => $sectionIndex,
                'id' => is_string($section['id'] ?? null) ? $section['id'] : null,
                'class' => is_string($section['class'] ?? null) ? $section['class'] : null,
                'classes' => is_array($section['classes'] ?? null) ? array_values($section['classes']) : [],
                'language' => is_string($section['language'] ?? null) ? $section['language'] : null,
                'direction' => is_string($section['direction'] ?? null) ? $section['direction'] : null,
                'hidden' => (bool) ($section['hidden'] ?? false),
                'type' => $sectionType,
                'types' => $sectionTypes,
                'primaryTypes' => $constrainedTypes,
                'title' => is_string($section['title'] ?? null) ? $section['title'] : '',
                'itemCount' => count($flatItems),
                'items' => $sectionItems,
            ];

            $reportedSections[] = $summary;
            foreach ($constrainedTypes as $type) {
                $types[$type] = true;
                $sectionsByType[$type][] = $summary;
            }

            foreach ($flatItems as $flat) {
                $item = self::primaryNavigationTargetPolicyItem(
                    is_array($flat['item'] ?? null) ? $flat['item'] : [],
                    $sectionIndex,
                    $sectionType,
                    $sectionTypes,
                    $constrainedTypes,
                    is_string($section['id'] ?? null) ? $section['id'] : null,
                    (int) ($flat['depth'] ?? 0),
                    count($items),
                    $spineByContentPart
                );

                if ($item['href'] !== null) {
                    ++$targetedItemCount;
                }
                if (($item['validTarget'] ?? false) === true) {
                    ++$validTargetCount;
                }
                if (($item['external'] ?? false) === true) {
                    ++$externalTargetCount;
                }
                if ($item['href'] === null || $item['target'] === null) {
                    ++$missingTargetCount;
                }
                if (($item['exists'] ?? true) !== true && ($item['external'] ?? false) !== true && $item['target'] !== null) {
                    ++$missingReferenceCount;
                }
                if (($item['outsideSpine'] ?? false) === true) {
                    ++$outsideSpineTargetCount;
                }
                if (is_array($item['mediaFragment'] ?? null)) {
                    $mediaFragmentTargets[] = $item;
                }
                if ($sectionType === 'landmarks') {
                    ++$landmarkCount;
                    if (($item['missingLandmarkType'] ?? false) === true) {
                        ++$landmarkMissingTypeCount;
                    }
                }

                foreach ($item['diagnostics'] as $diagnostic) {
                    if (!is_array($diagnostic)) {
                        continue;
                    }

                    $diagnostics[] = [
                        'index' => $item['index'],
                        'sectionIndex' => $item['sectionIndex'],
                        'sectionType' => $item['sectionType'],
                        'sectionId' => $item['sectionId'],
                    ] + $diagnostic;
                }

                $items[] = $item;
                $itemsBySectionType[$sectionType][] = $item;
                foreach (array_slice($constrainedTypes, 1) as $extraType) {
                    $itemsBySectionType[$extraType][] = $item;
                }
            }
        }

        return [
            'present' => $reportedSections !== [],
            'sectionCount' => count($reportedSections),
            'itemCount' => count($items),
            'targetedItemCount' => $targetedItemCount,
            'validTargetCount' => $validTargetCount,
            'externalTargetCount' => $externalTargetCount,
            'missingTargetCount' => $missingTargetCount,
            'missingReferenceCount' => $missingReferenceCount,
            'outsideSpineTargetCount' => $outsideSpineTargetCount,
            'mediaFragmentTargetCount' => count($mediaFragmentTargets),
            'landmarkCount' => $landmarkCount,
            'landmarkMissingTypeCount' => $landmarkMissingTypeCount,
            'diagnosticCount' => count($diagnostics),
            'types' => array_keys($types),
            'sections' => $reportedSections,
            'sectionsByType' => $sectionsByType,
            'items' => $items,
            'mediaFragmentTargets' => $mediaFragmentTargets,
            'itemsBySectionType' => $itemsBySectionType,
            'diagnostics' => $diagnostics,
        ];
    }

    /**
     * @param array<string, mixed> $item
     * @param list<string> $sectionTypes
     * @param list<string> $primaryTypes
     * @param array<string, array<string, mixed>> $spineByContentPart
     *
     * @return array<string, mixed>
     */
    private static function primaryNavigationTargetPolicyItem(
        array $item,
        int $sectionIndex,
        string $sectionType,
        array $sectionTypes,
        array $primaryTypes,
        ?string $sectionId,
        int $depth,
        int $index,
        array $spineByContentPart
    ): array {
        $target = is_string($item['target'] ?? null) ? $item['target'] : null;
        $part = is_string($item['part'] ?? null) ? $item['part'] : null;
        $href = is_string($item['href'] ?? null) ? $item['href'] : null;
        $external = (bool) ($item['external'] ?? false);
        $exists = (bool) ($item['exists'] ?? false);
        $spineItem = $part !== null ? ($spineByContentPart[$part] ?? null) : null;
        $types = is_array($item['types'] ?? null) ? array_values($item['types']) : [];
        $sourceDiagnostics = is_array($item['diagnostics'] ?? null) ? array_values($item['diagnostics']) : [];
        $diagnostics = [];
        $fragmentFields = self::targetFragmentFields($target);
        $mediaFragment = is_array($item['mediaFragment'] ?? null) ? $item['mediaFragment'] : $fragmentFields['mediaFragment'];

        if ($external) {
            $diagnostics[] = [
                'type' => 'external-primary-nav-target',
                'target' => $target,
                'message' => 'EPUB primary navigation target points outside the package and was not fetched',
            ];
        } elseif ($target === null || $href === null) {
            $diagnostics[] = [
                'type' => 'missing-primary-nav-target',
                'message' => 'EPUB primary navigation item does not carry a resolvable target',
            ];
        } elseif (!$exists) {
            $diagnostics[] = [
                'type' => 'missing-primary-nav-reference',
                'part' => $part,
                'message' => 'EPUB primary navigation target is missing from the package',
            ];
        } elseif (!is_array($spineItem)) {
            $diagnostics[] = [
                'type' => 'primary-nav-target-outside-spine',
                'part' => $part,
                'message' => 'EPUB primary navigation target exists in the package but is not part of the resolved spine handoff',
            ];
        }

        $missingLandmarkType = $sectionType === 'landmarks' && $types === [];
        if ($missingLandmarkType) {
            $diagnostics[] = [
                'type' => 'missing-landmark-nav-type',
                'target' => $target,
                'message' => 'EPUB landmark navigation item is missing an epub:type value for import handoff classification',
            ];
        }
        if ($mediaFragment !== null) {
            $diagnostics[] = [
                'type' => 'primary-nav-media-fragment-target',
                'target' => $target,
                'fragment' => is_string($item['fragment'] ?? null) ? $item['fragment'] : $fragmentFields['fragment'],
                'message' => 'EPUB primary navigation target uses a W3C media fragment for import handoff metadata',
            ];
        }

        return [
            'index' => $index,
            'sectionIndex' => $sectionIndex,
            'sectionId' => $sectionId,
            'sectionType' => $sectionType,
            'sectionTypes' => $sectionTypes,
            'primaryTypes' => $primaryTypes,
            'depth' => $depth,
            'id' => is_string($item['id'] ?? null) ? $item['id'] : null,
            'itemId' => is_string($item['itemId'] ?? null) ? $item['itemId'] : null,
            'labelId' => is_string($item['labelId'] ?? null) ? $item['labelId'] : null,
            'labelElement' => is_string($item['labelElement'] ?? null) ? $item['labelElement'] : null,
            'label' => is_string($item['title'] ?? null) ? $item['title'] : '',
            'href' => $href,
            'target' => $target,
            'part' => $part,
            'fragment' => is_string($item['fragment'] ?? null) ? $item['fragment'] : self::targetFragment($target),
            'fragmentKind' => is_string($item['fragmentKind'] ?? null) ? $item['fragmentKind'] : $fragmentFields['fragmentKind'],
            'epubCfi' => is_array($item['epubCfi'] ?? null) ? $item['epubCfi'] : $fragmentFields['epubCfi'],
            'mediaFragment' => $mediaFragment,
            'external' => $external,
            'exists' => $exists,
            'outsideSpine' => $target !== null && !$external && $exists && !is_array($spineItem),
            'validTarget' => $target !== null && !$external && $exists && is_array($spineItem),
            'missingLandmarkType' => $missingLandmarkType,
            'type' => is_string($item['type'] ?? null) ? $item['type'] : null,
            'types' => $types,
            'itemTypes' => is_array($item['itemTypes'] ?? null) ? array_values($item['itemTypes']) : [],
            'labelTypes' => is_array($item['labelTypes'] ?? null) ? array_values($item['labelTypes']) : [],
            'typeSource' => is_string($item['typeSource'] ?? null) ? $item['typeSource'] : null,
            'typeSources' => is_array($item['typeSources'] ?? null) ? array_values($item['typeSources']) : [],
            'class' => is_string($item['class'] ?? null) ? $item['class'] : null,
            'classes' => is_array($item['classes'] ?? null) ? array_values($item['classes']) : [],
            'language' => is_string($item['language'] ?? null) ? $item['language'] : null,
            'direction' => is_string($item['direction'] ?? null) ? $item['direction'] : null,
            'hidden' => (bool) ($item['hidden'] ?? false),
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
     * @param list<array<string, mixed>> $sections
     *
     * @return array{
     *     present:bool,
     *     sectionCount:int,
     *     itemCount:int,
     *     types:list<string>,
     *     sections:list<array<string, mixed>>,
     *     sectionsByType:array<string, list<array<string, mixed>>>,
     *     items:list<array<string, mixed>>
     * }
     */
    private static function auxiliaryNavReport(array $sections): array
    {
        $primaryTypes = [
            'toc' => true,
            'landmarks' => true,
            'page-list' => true,
        ];
        $reportedSections = [];
        $sectionsByType = [];
        $items = [];
        $types = [];

        foreach ($sections as $sectionIndex => $section) {
            if (!is_array($section)) {
                continue;
            }

            $sectionTypes = array_values(array_filter(
                is_array($section['types'] ?? null) ? $section['types'] : [],
                static fn (mixed $type): bool => is_string($type) && $type !== '',
            ));
            $auxiliaryTypes = array_values(array_filter(
                $sectionTypes,
                static fn (string $type): bool => !isset($primaryTypes[$type]),
            ));
            if ($auxiliaryTypes === []) {
                continue;
            }

            $sectionItems = is_array($section['items'] ?? null) ? array_values($section['items']) : [];
            $flatItems = self::flattenNavigationItems($sectionItems);
            $summary = [
                'sectionIndex' => $sectionIndex,
                'id' => is_string($section['id'] ?? null) ? $section['id'] : null,
                'class' => is_string($section['class'] ?? null) ? $section['class'] : null,
                'classes' => is_array($section['classes'] ?? null) ? array_values($section['classes']) : [],
                'language' => is_string($section['language'] ?? null) ? $section['language'] : null,
                'direction' => is_string($section['direction'] ?? null) ? $section['direction'] : null,
                'hidden' => (bool) ($section['hidden'] ?? false),
                'type' => $auxiliaryTypes[0],
                'types' => $sectionTypes,
                'auxiliaryTypes' => $auxiliaryTypes,
                'title' => is_string($section['title'] ?? null) ? $section['title'] : '',
                'itemCount' => count($flatItems),
                'items' => $sectionItems,
            ];

            $reportedSections[] = $summary;
            foreach ($auxiliaryTypes as $type) {
                $types[$type] = true;
                $sectionsByType[$type][] = $summary;
            }

            foreach ($flatItems as $flat) {
                $navItem = $flat['item'];
                if (!is_array($navItem)) {
                    continue;
                }

                $items[] = [
                    'sectionIndex' => $sectionIndex,
                    'sectionId' => $summary['id'],
                    'sectionType' => $summary['type'],
                    'sectionTypes' => $auxiliaryTypes,
                    'sectionTitle' => $summary['title'],
                    'depth' => (int) $flat['depth'],
                ] + $navItem;
            }
        }

        return [
            'present' => $reportedSections !== [],
            'sectionCount' => count($reportedSections),
            'itemCount' => count($items),
            'types' => array_keys($types),
            'sections' => $reportedSections,
            'sectionsByType' => $sectionsByType,
            'items' => $items,
        ];
    }

    /**
     * @param ?array<string, mixed> $nav
     * @param ?array<string, mixed> $ncx
     * @param list<array<string, mixed>> $spine
     * @param array<string, mixed> $xhtmlResourceReport
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
    private static function pageBreakReport(?array $nav, ?array $ncx, array $spine, array $xhtmlResourceReport = []): array
    {
        $navPageList = is_array($nav) && is_array($nav['pageList'] ?? null)
            ? $nav['pageList']
            : [];
        $ncxPageList = is_array($ncx) && is_array($ncx['pageList'] ?? null)
            ? $ncx['pageList']
            : [];
        $pageList = $navPageList;
        $pageListSource = 'nav-page-list';
        $itemSource = 'nav';
        if ($pageList === [] && $ncxPageList !== []) {
            $pageList = $ncxPageList;
            $pageListSource = 'ncx-page-list';
            $itemSource = 'ncx';
        }

        $spineByContentPart = self::spineByContentPart($spine);
        if ($pageList === []) {
            $semanticPageBreaks = self::xhtmlSemanticPageBreakReport($xhtmlResourceReport, $spineByContentPart);
            if (($semanticPageBreaks['present'] ?? false) === true) {
                return $semanticPageBreaks;
            }

            return self::emptyPageBreakReport('nav-page-list');
        }

        $items = [];
        $diagnostics = [];
        foreach (self::flattenNavigationItems($pageList) as $pageItem) {
            $navItem = $pageItem['item'];
            $index = count($items);
            $target = is_string($navItem['target'] ?? null) ? $navItem['target'] : null;
            $part = is_string($navItem['part'] ?? null) ? $navItem['part'] : null;
            $spineItem = $part !== null ? ($spineByContentPart[$part] ?? null) : null;
            $sourceDiagnostics = is_array($navItem['diagnostics'] ?? null) ? array_values($navItem['diagnostics']) : [];
            $itemDiagnostics = [];
            $fragmentFields = self::targetFragmentFields($target);
            $mediaFragment = is_array($navItem['mediaFragment'] ?? null) ? $navItem['mediaFragment'] : $fragmentFields['mediaFragment'];

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
            if ($mediaFragment !== null) {
                $itemDiagnostics[] = [
                    'type' => 'page-list-media-fragment-target',
                    'target' => $target,
                    'fragment' => is_string($navItem['fragment'] ?? null) ? $navItem['fragment'] : $fragmentFields['fragment'],
                    'message' => 'EPUB page-list target uses a W3C media fragment for import handoff metadata',
                ];
            }

            foreach ($itemDiagnostics as $diagnostic) {
                $diagnostics[] = ['index' => $index] + $diagnostic;
            }

            $items[] = [
                'index' => $index,
                'source' => $itemSource,
                'id' => is_string($navItem['id'] ?? null) ? $navItem['id'] : null,
                'depth' => $pageItem['depth'],
                'label' => is_string($navItem['title'] ?? null) ? $navItem['title'] : '',
                'href' => is_string($navItem['href'] ?? null) ? $navItem['href'] : null,
                'target' => $target,
                'part' => $part,
                'fragment' => is_string($navItem['fragment'] ?? null) ? $navItem['fragment'] : self::targetFragment($target),
                'fragmentKind' => is_string($navItem['fragmentKind'] ?? null) ? $navItem['fragmentKind'] : $fragmentFields['fragmentKind'],
                'epubCfi' => is_array($navItem['epubCfi'] ?? null) ? $navItem['epubCfi'] : $fragmentFields['epubCfi'],
                'mediaFragment' => $mediaFragment,
                'external' => (bool) ($navItem['external'] ?? false),
                'exists' => (bool) ($navItem['exists'] ?? false),
                'type' => is_string($navItem['type'] ?? null) ? $navItem['type'] : null,
                'types' => is_array($navItem['types'] ?? null) ? array_values($navItem['types']) : [],
                'itemTypes' => is_array($navItem['itemTypes'] ?? null) ? array_values($navItem['itemTypes']) : [],
                'labelTypes' => is_array($navItem['labelTypes'] ?? null) ? array_values($navItem['labelTypes']) : [],
                'typeSource' => is_string($navItem['typeSource'] ?? null) ? $navItem['typeSource'] : null,
                'typeSources' => is_array($navItem['typeSources'] ?? null) ? array_values($navItem['typeSources']) : [],
                'itemId' => is_string($navItem['itemId'] ?? null) ? $navItem['itemId'] : null,
                'labelId' => is_string($navItem['labelId'] ?? null) ? $navItem['labelId'] : null,
                'labelElement' => is_string($navItem['labelElement'] ?? null) ? $navItem['labelElement'] : null,
                'classes' => is_array($navItem['classes'] ?? null) ? array_values($navItem['classes']) : [],
                'language' => is_string($navItem['language'] ?? null) ? $navItem['language'] : null,
                'direction' => is_string($navItem['direction'] ?? null) ? $navItem['direction'] : null,
                'hidden' => (bool) ($navItem['hidden'] ?? false),
                'attributes' => is_array($navItem['attributes'] ?? null) ? $navItem['attributes'] : [],
                'labelAttributes' => is_array($navItem['labelAttributes'] ?? null) ? $navItem['labelAttributes'] : [],
                'labelTextAttributes' => is_array($navItem['labelTextAttributes'] ?? null) ? $navItem['labelTextAttributes'] : [],
                'labelAudioCount' => is_array($navItem['labelAudio'] ?? null) ? count($navItem['labelAudio']) : 0,
                'labelAudio' => is_array($navItem['labelAudio'] ?? null) ? array_values($navItem['labelAudio']) : [],
                'labelAudioDiagnostics' => is_array($navItem['labelAudioDiagnostics'] ?? null) ? array_values($navItem['labelAudioDiagnostics']) : [],
                'contentAttributes' => is_array($navItem['contentAttributes'] ?? null) ? $navItem['contentAttributes'] : [],
                'byteLength' => is_int($navItem['byteLength'] ?? null) ? $navItem['byteLength'] : null,
                'crc32' => is_string($navItem['crc32'] ?? null) ? $navItem['crc32'] : null,
                'manifestId' => is_string($navItem['manifestId'] ?? null) ? $navItem['manifestId'] : null,
                'mediaType' => is_string($navItem['mediaType'] ?? null) ? $navItem['mediaType'] : null,
                'encrypted' => (bool) ($navItem['encrypted'] ?? false),
                'canExposeBytes' => (bool) ($navItem['canExposeBytes'] ?? false),
                'value' => is_string($navItem['value'] ?? null) ? $navItem['value'] : null,
                'playOrder' => is_string($navItem['playOrder'] ?? null) ? $navItem['playOrder'] : null,
                'class' => is_string($navItem['class'] ?? null) ? $navItem['class'] : null,
                'spineIndex' => is_array($spineItem) ? (int) ($spineItem['index'] ?? 0) : null,
                'spineIdref' => is_array($spineItem) ? (string) ($spineItem['idref'] ?? '') : null,
                'spinePart' => is_array($spineItem) ? (string) ($spineItem['part'] ?? '') : null,
                'contentPart' => is_array($spineItem) ? (string) ($spineItem['contentPart'] ?? $spineItem['part'] ?? '') : null,
                'linear' => is_array($spineItem) ? (bool) ($spineItem['linear'] ?? true) : null,
                'pageSpread' => is_array($spineItem) ? ($spineItem['pageSpread'] ?? null) : null,
                'sourceDiagnostics' => $sourceDiagnostics,
                'navDiagnostics' => $sourceDiagnostics,
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
        $cfiItems = array_values(array_filter(
            $items,
            static fn (array $item): bool => ($item['fragmentKind'] ?? null) === 'epub-cfi',
        ));
        $mediaFragmentItems = array_values(array_filter(
            $items,
            static fn (array $item): bool => ($item['fragmentKind'] ?? null) === 'media-fragment',
        ));

        return [
            'present' => $items !== [],
            'source' => $pageListSource,
            'count' => count($items),
            'cfiPageBreakCount' => count($cfiItems),
            'cfiPageBreaks' => $cfiItems,
            'mediaFragmentPageBreakCount' => count($mediaFragmentItems),
            'mediaFragmentPageBreaks' => $mediaFragmentItems,
            'items' => $items,
            'itemsByPart' => $itemsByPart,
            'diagnostics' => $diagnostics,
        ];
    }

    /**
     * @param list<array<string, mixed>> $spine
     *
     * @return array<string, array<string, mixed>>
     */
    private static function spineByContentPart(array $spine): array
    {
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

        return $spineByContentPart;
    }

    /**
     * @return array<string, mixed>
     */
    private static function emptyPageBreakReport(string $source): array
    {
        return [
            'present' => false,
            'source' => $source,
            'count' => 0,
            'cfiPageBreakCount' => 0,
            'cfiPageBreaks' => [],
            'mediaFragmentPageBreakCount' => 0,
            'mediaFragmentPageBreaks' => [],
            'items' => [],
            'itemsByPart' => [],
            'diagnostics' => [],
        ];
    }

    /**
     * @param array<string, mixed> $xhtmlResourceReport
     * @param array<string, array<string, mixed>> $spineByContentPart
     *
     * @return array<string, mixed>
     */
    private static function xhtmlSemanticPageBreakReport(array $xhtmlResourceReport, array $spineByContentPart): array
    {
        $semanticItems = is_array($xhtmlResourceReport['semanticItemsByType']['pagebreak'] ?? null)
            ? array_values($xhtmlResourceReport['semanticItemsByType']['pagebreak'])
            : [];
        if ($semanticItems === []) {
            return self::emptyPageBreakReport('xhtml-semantic-pagebreak');
        }

        $items = [];
        $diagnostics = [];
        foreach ($semanticItems as $semantic) {
            if (!is_array($semantic)) {
                continue;
            }

            $index = count($items);
            $sourcePart = is_string($semantic['sourcePart'] ?? null) ? $semantic['sourcePart'] : null;
            $id = is_string($semantic['id'] ?? null) && $semantic['id'] !== '' ? $semantic['id'] : null;
            $target = is_string($semantic['target'] ?? null) ? $semantic['target'] : null;
            $part = is_string($semantic['part'] ?? null) ? $semantic['part'] : null;
            if ($target === null && $sourcePart !== null && $id !== null) {
                $target = $sourcePart . '#' . $id;
                $part = $sourcePart;
            }
            if ($part === null) {
                $part = $sourcePart;
            }

            $spineItem = $part !== null ? ($spineByContentPart[$part] ?? null) : null;
            $fragmentFields = self::targetFragmentFields($target);
            $fragment = is_string($semantic['fragment'] ?? null)
                ? $semantic['fragment']
                : ($fragmentFields['fragment'] ?? ($id !== null ? $id : null));
            $fragmentKind = is_string($semantic['fragmentKind'] ?? null)
                ? $semantic['fragmentKind']
                : ($fragmentFields['fragmentKind'] ?? ($fragment !== null ? 'id' : null));
            $sourceDiagnostics = is_array($semantic['diagnostics'] ?? null) ? array_values($semantic['diagnostics']) : [];
            $itemDiagnostics = [];

            if (($semantic['external'] ?? false) === true) {
                $itemDiagnostics[] = [
                    'type' => 'external-xhtml-semantic-pagebreak',
                    'target' => $target,
                    'message' => 'EPUB XHTML semantic pagebreak points outside the package and was not fetched',
                ];
            } elseif ($target === null) {
                $itemDiagnostics[] = [
                    'type' => 'missing-xhtml-semantic-pagebreak-target',
                    'sourcePart' => $sourcePart,
                    'message' => 'EPUB XHTML semantic pagebreak does not carry an id or resolvable href target',
                ];
            } elseif (($semantic['exists'] ?? true) !== true) {
                $itemDiagnostics[] = [
                    'type' => 'missing-xhtml-semantic-pagebreak-reference',
                    'part' => $part,
                    'message' => 'EPUB XHTML semantic pagebreak target is missing from the package',
                ];
            } elseif (!is_array($spineItem)) {
                $itemDiagnostics[] = [
                    'type' => 'xhtml-semantic-pagebreak-outside-spine',
                    'part' => $part,
                    'message' => 'EPUB XHTML semantic pagebreak target exists in the package but is not part of the resolved spine handoff',
                ];
            }

            foreach ($itemDiagnostics as $diagnostic) {
                $diagnostics[] = ['index' => $index] + $diagnostic;
            }

            $items[] = [
                'index' => $index,
                'source' => 'xhtml-semantic',
                'id' => $id,
                'depth' => 0,
                'label' => self::xhtmlSemanticPageBreakLabel($semantic),
                'href' => is_string($semantic['href'] ?? null) ? $semantic['href'] : null,
                'target' => $target,
                'part' => $part,
                'fragment' => $fragment,
                'fragmentKind' => $fragmentKind,
                'epubCfi' => is_array($semantic['epubCfi'] ?? null) ? $semantic['epubCfi'] : $fragmentFields['epubCfi'],
                'mediaFragment' => is_array($semantic['mediaFragment'] ?? null) ? $semantic['mediaFragment'] : $fragmentFields['mediaFragment'],
                'external' => (bool) ($semantic['external'] ?? false),
                'exists' => (bool) ($semantic['exists'] ?? true),
                'type' => is_string($semantic['primaryType'] ?? null) ? $semantic['primaryType'] : 'pagebreak',
                'types' => is_array($semantic['types'] ?? null) ? array_values($semantic['types']) : ['pagebreak'],
                'itemTypes' => is_array($semantic['types'] ?? null) ? array_values($semantic['types']) : ['pagebreak'],
                'labelTypes' => [],
                'typeSource' => 'xhtml',
                'typeSources' => [[
                    'type' => 'pagebreak',
                    'source' => 'xhtml',
                    'element' => is_string($semantic['element'] ?? null) ? $semantic['element'] : null,
                ]],
                'itemId' => null,
                'labelId' => $id,
                'labelElement' => is_string($semantic['element'] ?? null) ? $semantic['element'] : null,
                'classes' => is_array($semantic['classes'] ?? null) ? array_values($semantic['classes']) : [],
                'language' => is_string($semantic['language'] ?? null) ? $semantic['language'] : null,
                'direction' => is_string($semantic['direction'] ?? null) ? $semantic['direction'] : null,
                'hidden' => false,
                'attributes' => is_array($semantic['attributes'] ?? null) ? $semantic['attributes'] : [],
                'labelAttributes' => is_array($semantic['attributes'] ?? null) ? $semantic['attributes'] : [],
                'labelTextAttributes' => [],
                'contentAttributes' => [],
                'byteLength' => is_int($semantic['byteLength'] ?? null) ? $semantic['byteLength'] : null,
                'crc32' => is_string($semantic['crc32'] ?? null) ? $semantic['crc32'] : null,
                'manifestId' => is_array($spineItem) ? (string) ($spineItem['idref'] ?? '') : (is_string($semantic['manifestId'] ?? null) ? $semantic['manifestId'] : null),
                'mediaType' => is_array($spineItem) ? (string) ($spineItem['contentMediaType'] ?? $spineItem['mediaType'] ?? '') : (is_string($semantic['mediaType'] ?? null) ? $semantic['mediaType'] : null),
                'encrypted' => (bool) ($semantic['encrypted'] ?? false),
                'canExposeBytes' => (bool) ($semantic['canExposeBytes'] ?? true),
                'value' => self::xhtmlSemanticPageBreakLabel($semantic),
                'playOrder' => null,
                'class' => is_string($semantic['class'] ?? null) ? $semantic['class'] : null,
                'spineIndex' => is_array($spineItem) ? (int) ($spineItem['index'] ?? 0) : null,
                'spineIdref' => is_array($spineItem) ? (string) ($spineItem['idref'] ?? '') : null,
                'spinePart' => is_array($spineItem) ? (string) ($spineItem['part'] ?? '') : null,
                'contentPart' => is_array($spineItem) ? (string) ($spineItem['contentPart'] ?? $spineItem['part'] ?? '') : null,
                'linear' => is_array($spineItem) ? (bool) ($spineItem['linear'] ?? true) : null,
                'pageSpread' => is_array($spineItem) ? ($spineItem['pageSpread'] ?? null) : null,
                'sourceDiagnostics' => $sourceDiagnostics,
                'navDiagnostics' => $sourceDiagnostics,
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
        $cfiItems = array_values(array_filter(
            $items,
            static fn (array $item): bool => ($item['fragmentKind'] ?? null) === 'epub-cfi',
        ));
        $mediaFragmentItems = array_values(array_filter(
            $items,
            static fn (array $item): bool => ($item['fragmentKind'] ?? null) === 'media-fragment',
        ));

        return [
            'present' => $items !== [],
            'source' => 'xhtml-semantic-pagebreak',
            'count' => count($items),
            'cfiPageBreakCount' => count($cfiItems),
            'cfiPageBreaks' => $cfiItems,
            'mediaFragmentPageBreakCount' => count($mediaFragmentItems),
            'mediaFragmentPageBreaks' => $mediaFragmentItems,
            'items' => $items,
            'itemsByPart' => $itemsByPart,
            'diagnostics' => $diagnostics,
        ];
    }

    /**
     * @param array<string, mixed> $semantic
     */
    private static function xhtmlSemanticPageBreakLabel(array $semantic): string
    {
        $text = is_string($semantic['text'] ?? null) ? trim($semantic['text']) : '';
        if ($text !== '') {
            return $text;
        }

        $attributes = is_array($semantic['attributes'] ?? null) ? $semantic['attributes'] : [];
        foreach (['title', 'aria-label', 'data-label'] as $attribute) {
            if (is_string($attributes[$attribute] ?? null) && trim($attributes[$attribute]) !== '') {
                return trim($attributes[$attribute]);
            }
        }

        return is_string($semantic['id'] ?? null) ? $semantic['id'] : '';
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
        return self::targetFragmentFields($target)['fragment'];
    }

    /**
     * @return array{fragment:?string, fragmentKind:?string, epubCfi:?array<string, mixed>, mediaFragment:?array<string, mixed>}
     */
    private static function targetFragmentFields(?string $target): array
    {
        if ($target === null) {
            return [
                'fragment' => null,
                'fragmentKind' => null,
                'epubCfi' => null,
                'mediaFragment' => null,
            ];
        }

        $offset = strpos($target, '#');
        if ($offset === false) {
            return [
                'fragment' => null,
                'fragmentKind' => null,
                'epubCfi' => null,
                'mediaFragment' => null,
            ];
        }

        $fragment = substr($target, $offset + 1);

        if ($fragment === '') {
            return [
                'fragment' => null,
                'fragmentKind' => null,
                'epubCfi' => null,
                'mediaFragment' => null,
            ];
        }

        $epubCfi = self::epubCfiFragmentReport($fragment);
        $mediaFragment = $epubCfi === null ? self::mediaFragmentReport($fragment) : null;

        return [
            'fragment' => $fragment,
            'fragmentKind' => $epubCfi !== null ? 'epub-cfi' : ($mediaFragment !== null ? 'media-fragment' : 'id'),
            'epubCfi' => $epubCfi,
            'mediaFragment' => $mediaFragment,
        ];
    }

    /**
     * @return ?array{
     *     present:bool,
     *     raw:string,
     *     path:string,
     *     valid:bool,
     *     range:bool,
     *     assertionCount:int,
     *     diagnostics:list<array<string, mixed>>
     * }
     */
    private static function epubCfiFragmentReport(string $fragment): ?array
    {
        if (strtolower(substr($fragment, 0, 8)) !== 'epubcfi(') {
            return null;
        }

        $diagnostics = [];
        $path = '';
        if (!str_ends_with($fragment, ')')) {
            $diagnostics[] = [
                'type' => 'invalid-epub-cfi-fragment',
                'fragment' => $fragment,
                'message' => 'EPUB CFI fragments must be wrapped as epubcfi(...)',
            ];
        } else {
            $path = substr($fragment, 8, -1);
            if (trim($path) === '') {
                $diagnostics[] = [
                    'type' => 'empty-epub-cfi-fragment',
                    'fragment' => $fragment,
                    'message' => 'EPUB CFI fragment path must not be empty',
                ];
            }
        }

        return [
            'present' => true,
            'raw' => $fragment,
            'path' => $path,
            'valid' => $diagnostics === [],
            'range' => str_contains($path, ','),
            'assertionCount' => substr_count($path, '['),
            'diagnostics' => $diagnostics,
        ];
    }

    /**
     * @return ?array<string, mixed>
     */
    private static function mediaFragmentReport(string $fragment): ?array
    {
        $rawComponents = preg_split('/[&;]/', $fragment) ?: [];
        $components = [];
        $hasMediaDimension = false;
        $recognizedNames = [
            't' => true,
            'xywh' => true,
            'track' => true,
            'id' => true,
        ];

        foreach ($rawComponents as $index => $component) {
            if ($component === '') {
                continue;
            }

            $separator = strpos($component, '=');
            if ($separator === false) {
                continue;
            }

            $name = strtolower(rawurldecode(substr($component, 0, $separator)));
            $rawValue = substr($component, $separator + 1);
            $value = rawurldecode($rawValue);
            $components[] = [
                'index' => $index,
                'name' => $name,
                'rawValue' => $rawValue,
                'value' => $value,
            ];

            if (in_array($name, ['t', 'xywh', 'track'], true)) {
                $hasMediaDimension = true;
            }
        }

        if (!$hasMediaDimension) {
            return null;
        }

        $dimensions = [];
        $byName = [];
        $diagnostics = [];

        foreach ($components as $component) {
            $name = (string) $component['name'];
            $dimensionDiagnostics = [];
            if (!isset($recognizedNames[$name])) {
                $dimensionDiagnostics[] = [
                    'type' => 'unsupported-media-fragment-dimension',
                    'name' => $name,
                    'message' => 'EPUB target media fragment includes a dimension that is not interpreted by this bounded handoff',
                ];
            }
            if (isset($byName[$name])) {
                $dimensionDiagnostics[] = [
                    'type' => 'duplicate-media-fragment-dimension',
                    'name' => $name,
                    'message' => 'EPUB target media fragment repeats a dimension; values are preserved in source order',
                ];
            }

            $dimension = [
                'index' => (int) $component['index'],
                'name' => $name,
                'rawValue' => (string) $component['rawValue'],
                'value' => (string) $component['value'],
                'valid' => $dimensionDiagnostics === [],
                'diagnostics' => $dimensionDiagnostics,
            ];

            $details = [];
            if ($name === 't') {
                $details = self::mediaFragmentTimeDimension((string) $component['value']);
            } elseif ($name === 'xywh') {
                $details = self::mediaFragmentXywhDimension((string) $component['value']);
            } elseif ($name === 'track' && trim((string) $component['value']) === '') {
                $dimension['valid'] = false;
                $dimension['diagnostics'][] = [
                    'type' => 'invalid-media-fragment-track',
                    'message' => 'EPUB target media fragment track dimension must not be empty',
                ];
            }
            if ($details !== []) {
                $detailDiagnostics = is_array($details['diagnostics'] ?? null) ? $details['diagnostics'] : [];
                $dimension = array_replace($dimension, $details);
                $dimension['diagnostics'] = array_merge($dimensionDiagnostics, $detailDiagnostics);
                $dimension['valid'] = $dimension['diagnostics'] === [];
            }

            if (($dimension['valid'] ?? true) !== true) {
                foreach ($dimension['diagnostics'] as $diagnostic) {
                    $diagnostics[] = [
                        'dimension' => $name,
                        'index' => (int) $component['index'],
                    ] + $diagnostic;
                }
            }

            $dimensions[] = $dimension;
            $byName[$name][] = $dimension;
        }

        return [
            'present' => true,
            'raw' => $fragment,
            'valid' => $diagnostics === [],
            'dimensionCount' => count($dimensions),
            'dimensionNames' => array_keys($byName),
            'dimensions' => $dimensions,
            'byName' => $byName,
            'time' => $byName['t'][0] ?? null,
            'xywh' => $byName['xywh'][0] ?? null,
            'track' => $byName['track'][0] ?? null,
            'id' => $byName['id'][0] ?? null,
            'diagnostics' => $diagnostics,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function mediaFragmentTimeDimension(string $value): array
    {
        $scheme = null;
        $spec = trim($value);
        if (str_starts_with(strtolower($spec), 'npt:')) {
            $scheme = 'npt';
            $spec = substr($spec, 4);
        }

        $parts = explode(',', $spec);
        $diagnostics = [];
        if (count($parts) > 2) {
            $diagnostics[] = [
                'type' => 'invalid-media-fragment-time',
                'value' => $value,
                'message' => 'EPUB target time media fragment must be a start/end pair',
            ];
            $parts = array_slice($parts, 0, 2);
        }

        $startText = isset($parts[0]) && trim($parts[0]) !== '' ? trim($parts[0]) : null;
        $endText = isset($parts[1]) && trim($parts[1]) !== '' ? trim($parts[1]) : null;
        $startSeconds = $startText === null ? null : self::mediaFragmentClockSeconds($startText);
        $endSeconds = $endText === null ? null : self::mediaFragmentClockSeconds($endText);

        if ($startText === null && $endText === null) {
            $diagnostics[] = [
                'type' => 'invalid-media-fragment-time',
                'value' => $value,
                'message' => 'EPUB target time media fragment must include a start or end time',
            ];
        }
        if ($startText !== null && $startSeconds === null) {
            $diagnostics[] = [
                'type' => 'invalid-media-fragment-time-start',
                'value' => $startText,
                'message' => 'EPUB target time media fragment start must be a non-negative normal play time',
            ];
        }
        if ($endText !== null && $endSeconds === null) {
            $diagnostics[] = [
                'type' => 'invalid-media-fragment-time-end',
                'value' => $endText,
                'message' => 'EPUB target time media fragment end must be a non-negative normal play time',
            ];
        }
        if ($startSeconds !== null && $endSeconds !== null && $endSeconds < $startSeconds) {
            $diagnostics[] = [
                'type' => 'invalid-media-fragment-time-range',
                'startSeconds' => $startSeconds,
                'endSeconds' => $endSeconds,
                'message' => 'EPUB target time media fragment end must not be earlier than the start',
            ];
        }

        return [
            'scheme' => $scheme,
            'start' => $startText,
            'end' => $endText,
            'startSeconds' => $startSeconds,
            'endSeconds' => $endSeconds,
            'durationSeconds' => $startSeconds !== null && $endSeconds !== null && $endSeconds >= $startSeconds
                ? $endSeconds - $startSeconds
                : null,
            'valid' => $diagnostics === [],
            'diagnostics' => $diagnostics,
        ];
    }

    private static function mediaFragmentClockSeconds(string $value): ?float
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        if (preg_match('/^\d+(?:\.\d+)?$/', $value) === 1) {
            return (float) $value;
        }
        if (preg_match('/^(\d+):([0-5]\d(?:\.\d+)?)$/', $value, $matches) === 1) {
            return ((float) $matches[1] * 60.0) + (float) $matches[2];
        }
        if (preg_match('/^(\d+):([0-5]\d):([0-5]\d(?:\.\d+)?)$/', $value, $matches) === 1) {
            return ((float) $matches[1] * 3600.0) + ((float) $matches[2] * 60.0) + (float) $matches[3];
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private static function mediaFragmentXywhDimension(string $value): array
    {
        $unit = 'pixel';
        $spec = trim($value);
        $lower = strtolower($spec);
        if (str_starts_with($lower, 'pixel:')) {
            $unit = 'pixel';
            $spec = substr($spec, 6);
        } elseif (str_starts_with($lower, 'percent:')) {
            $unit = 'percent';
            $spec = substr($spec, 8);
        }

        $parts = array_map('trim', explode(',', $spec));
        $diagnostics = [];
        $numbers = [];
        if (count($parts) !== 4) {
            $diagnostics[] = [
                'type' => 'invalid-media-fragment-xywh',
                'value' => $value,
                'message' => 'EPUB target spatial media fragment must include x,y,width,height',
            ];
        }

        foreach (array_slice($parts, 0, 4) as $part) {
            if (preg_match('/^-?\d+(?:\.\d+)?$/', $part) !== 1) {
                $numbers[] = null;
            } else {
                $numbers[] = (float) $part;
            }
        }
        while (count($numbers) < 4) {
            $numbers[] = null;
        }

        [$x, $y, $width, $height] = $numbers;
        if ($x === null || $y === null || $width === null || $height === null) {
            $diagnostics[] = [
                'type' => 'invalid-media-fragment-xywh-number',
                'value' => $value,
                'message' => 'EPUB target spatial media fragment coordinates must be numeric',
            ];
        } elseif ($x < 0.0 || $y < 0.0 || $width <= 0.0 || $height <= 0.0) {
            $diagnostics[] = [
                'type' => 'invalid-media-fragment-xywh-bounds',
                'value' => $value,
                'message' => 'EPUB target spatial media fragment x/y must be non-negative and width/height must be positive',
            ];
        }

        return [
            'unit' => $unit,
            'x' => $x,
            'y' => $y,
            'width' => $width,
            'height' => $height,
            'valid' => $diagnostics === [],
            'diagnostics' => $diagnostics,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function readNavList(ZipPackage $package, \DOMElement $list, string $navPart, array $manifestByPart): array
    {
        $items = [];
        foreach (self::childElements($list, 'li', self::XHTML_NS) as $li) {
            $link = self::firstChildElement($li, 'a', self::XHTML_NS);
            $label = $link instanceof \DOMElement ? $link : self::firstChildElement($li, 'span', self::XHTML_NS);
            $href = $link instanceof \DOMElement ? trim($link->getAttribute('href')) : '';
            $childList = self::firstChildElement($li, 'ol', self::XHTML_NS);
            $typeReport = self::navItemTypeReport($li, $label);
            $reference = $href === ''
                ? self::emptyPackageReference()
                : $this->packageReference($package, $navPart, $href, $manifestByPart, 'nav');
            $itemClasses = self::spaceDelimited($li->getAttribute('class'));
            $labelClasses = $label instanceof \DOMElement ? self::spaceDelimited($label->getAttribute('class')) : [];
            $classes = array_values(array_unique(array_merge($itemClasses, $labelClasses)));
            $title = $label instanceof \DOMElement ? self::normalizedText($label) : '';
            $documentDiagnostics = [];
            $hidden = self::elementHidden($li) || ($label instanceof \DOMElement && self::elementHidden($label));

            if ($hidden) {
                $documentDiagnostics[] = [
                    'type' => 'hidden-nav-item',
                    'itemId' => self::nullableAttribute($li, 'id'),
                    'labelElement' => $label instanceof \DOMElement ? $label->localName : null,
                    'labelId' => $label instanceof \DOMElement ? self::nullableAttribute($label, 'id') : null,
                    'label' => $title,
                    'href' => $link instanceof \DOMElement && $href !== '' ? $href : null,
                    'message' => 'EPUB navigation item is hidden and may be skipped by reading systems',
                ];
            }

            if (!$label instanceof \DOMElement) {
                $documentDiagnostics[] = [
                    'type' => 'missing-nav-item-label',
                    'itemId' => self::nullableAttribute($li, 'id'),
                    'message' => 'EPUB navigation list item is missing a direct a or span label',
                ];
                if ($childList instanceof \DOMElement) {
                    $documentDiagnostics[] = [
                        'type' => 'nav-item-child-list-without-label',
                        'itemId' => self::nullableAttribute($li, 'id'),
                        'message' => 'EPUB navigation list item has child entries but no direct parent label',
                    ];
                }
            } else {
                if ($title === '') {
                    $documentDiagnostics[] = [
                        'type' => 'empty-nav-item-label',
                        'itemId' => self::nullableAttribute($li, 'id'),
                        'labelElement' => $label->localName,
                        'labelId' => self::nullableAttribute($label, 'id'),
                        'href' => $link instanceof \DOMElement && $href !== '' ? $href : null,
                        'message' => 'EPUB navigation list item label is empty',
                    ];
                }
                if ($link instanceof \DOMElement && $href === '') {
                    $documentDiagnostics[] = [
                        'type' => 'missing-nav-item-href',
                        'itemId' => self::nullableAttribute($li, 'id'),
                        'label' => $title,
                        'labelId' => self::nullableAttribute($label, 'id'),
                        'message' => 'EPUB navigation link item is missing an href target',
                    ];
                } elseif (!$link instanceof \DOMElement && !$childList instanceof \DOMElement) {
                    $documentDiagnostics[] = [
                        'type' => 'missing-nav-item-link',
                        'itemId' => self::nullableAttribute($li, 'id'),
                        'label' => $title,
                        'labelId' => self::nullableAttribute($label, 'id'),
                        'message' => 'EPUB navigation leaf item has a label but no anchor target',
                    ];
                }
            }

            $items[] = [
                'id' => $label instanceof \DOMElement
                    ? (self::nullableAttribute($label, 'id') ?? self::nullableAttribute($li, 'id'))
                    : self::nullableAttribute($li, 'id'),
                'itemId' => self::nullableAttribute($li, 'id'),
                'labelId' => $label instanceof \DOMElement ? self::nullableAttribute($label, 'id') : null,
                'labelElement' => $label instanceof \DOMElement ? $label->localName : null,
                'class' => $classes === [] ? null : implode(' ', $classes),
                'classes' => $classes,
                'itemClass' => self::nullableAttribute($li, 'class'),
                'itemClasses' => $itemClasses,
                'labelClass' => $label instanceof \DOMElement ? self::nullableAttribute($label, 'class') : null,
                'labelClasses' => $labelClasses,
                'language' => $label instanceof \DOMElement ? (self::xmlLang($label) ?? self::xmlLang($li)) : self::xmlLang($li),
                'direction' => $label instanceof \DOMElement ? (self::direction($label) ?? self::direction($li)) : self::direction($li),
                'hidden' => $hidden,
                'attributes' => self::elementAttributes($li),
                'labelAttributes' => $label instanceof \DOMElement ? self::elementAttributes($label) : [],
                'title' => $title,
                'href' => $href === '' ? null : $href,
                'target' => $reference['target'],
                'part' => $reference['part'],
                'fragment' => $reference['fragment'],
                'fragmentKind' => $reference['fragmentKind'],
                'epubCfi' => $reference['epubCfi'],
                'mediaFragment' => $reference['mediaFragment'],
                'external' => $reference['external'],
                'exists' => $reference['exists'],
                'manifestId' => $reference['manifestId'],
                'mediaType' => $reference['mediaType'],
                'encrypted' => $reference['encrypted'],
                'canExposeBytes' => $reference['canExposeBytes'],
                'diagnostics' => $reference['diagnostics'],
                'documentDiagnostics' => $documentDiagnostics,
                'documentDiagnosticCount' => count($documentDiagnostics),
                'type' => $typeReport['type'],
                'types' => $typeReport['types'],
                'itemTypes' => $typeReport['itemTypes'],
                'labelTypes' => $typeReport['labelTypes'],
                'typeSource' => $typeReport['typeSource'],
                'typeSources' => $typeReport['typeSources'],
                'children' => $childList instanceof \DOMElement ? $this->readNavList($package, $childList, $navPart, $manifestByPart) : [],
            ];
        }

        return $items;
    }

    /**
     * @return array{
     *     type:?string,
     *     types:list<string>,
     *     itemTypes:list<string>,
     *     labelTypes:list<string>,
     *     typeSource:?string,
     *     typeSources:list<array{type:string, source:string, element:string}>
     * }
     */
    private static function navItemTypeReport(\DOMElement $item, ?\DOMElement $label): array
    {
        $itemTypes = self::epubTypes($item);
        $labelTypes = $label instanceof \DOMElement ? self::epubTypes($label) : [];
        $types = [];
        $typeSources = [];
        $sourceByType = [];

        $addTypes = static function (array $sourceTypes, string $source, string $element) use (&$types, &$typeSources, &$sourceByType): void {
            foreach ($sourceTypes as $type) {
                if (!is_string($type) || $type === '') {
                    continue;
                }

                if (!in_array($type, $types, true)) {
                    $types[] = $type;
                    $sourceByType[$type] = $source;
                }

                $typeSources[] = [
                    'type' => $type,
                    'source' => $source,
                    'element' => $element,
                ];
            }
        };

        $addTypes($labelTypes, 'label', $label instanceof \DOMElement ? $label->localName : '');
        $addTypes($itemTypes, 'item', $item->localName);

        $type = $types[0] ?? null;

        return [
            'type' => $type,
            'types' => $types,
            'itemTypes' => $itemTypes,
            'labelTypes' => $labelTypes,
            'typeSource' => $type === null ? null : ($sourceByType[$type] ?? null),
            'typeSources' => $typeSources,
        ];
    }

    /**
     * @param array<string, mixed> $item
     *
     * @return array{part:string, items:list<array<string, mixed>>, pageList:list<array<string, mixed>>}
     */
    private function readNcxDocument(ZipPackage $package, array $item, array $manifestByPart): array
    {
        if (self::isEncryptedManifestItem($item)) {
            return [
                'part' => (string) $item['part'],
                'version' => null,
                'language' => null,
                'head' => self::emptyNcxHeadReport(),
                'docTitle' => null,
                'docTitleEntries' => [],
                'docAuthors' => [],
                'docAuthorDetails' => [],
                'items' => [],
                'pageList' => [],
                'pageListCount' => 0,
                'pageListReport' => self::emptyNcxPageListReport(),
                'pageListDiagnostics' => [],
                'navListCount' => 0,
                'navLists' => [],
                'navListRoleReport' => self::ncxNavListRoleSummary([]),
                'navListDiagnostics' => [],
                'audioLabelCount' => 0,
                'audioLabelReport' => self::ncxLabelAudioReport([], self::emptyNcxPageListReport(), []),
                'audioLabelDiagnostics' => [],
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
        $pageList = self::firstChildElement($root, 'pageList', self::NCX_NS);
        $pageListReport = $pageList instanceof \DOMElement
            ? $this->readNcxPageList($package, $pageList, (string) $item['part'], $manifestByPart)
            : self::emptyNcxPageListReport();
        $navLists = $this->readNcxNavLists($package, $root, (string) $item['part'], $manifestByPart);
        $docTitleEntries = $this->readNcxTextElementEntries(
            $package,
            $root,
            (string) $item['part'],
            $manifestByPart,
            'docTitle',
            'doc-title'
        );
        $docAuthorDetails = $this->readNcxTextElementEntries(
            $package,
            $root,
            (string) $item['part'],
            $manifestByPart,
            'docAuthor',
            'doc-author'
        );
        $navMapItems = $navMap instanceof \DOMElement ? $this->readNcxPoints($package, $navMap, (string) $item['part'], $manifestByPart) : [];
        $audioLabelReport = self::ncxLabelAudioReport(
            $navMapItems,
            $pageListReport,
            $navLists['items'],
            $docTitleEntries,
            $docAuthorDetails
        );

        return [
            'part' => (string) $item['part'],
            'version' => self::nullableAttribute($root, 'version'),
            'language' => self::xmlLang($root),
            'head' => self::readNcxHeadReport(self::firstChildElement($root, 'head', self::NCX_NS)),
            'docTitle' => $docTitleEntries[0]['text'] ?? null,
            'docTitleEntries' => $docTitleEntries,
            'docAuthors' => array_map(
                static fn (array $entry): string => (string) $entry['text'],
                $docAuthorDetails
            ),
            'docAuthorDetails' => $docAuthorDetails,
            'items' => $navMapItems,
            'pageList' => $pageListReport['items'],
            'pageListCount' => $pageListReport['itemCount'],
            'pageListReport' => $pageListReport,
            'pageListDiagnostics' => $pageListReport['diagnostics'],
            'navListCount' => count($navLists['items']),
            'navLists' => $navLists['items'],
            'navListRoleReport' => self::ncxNavListRoleSummary($navLists['items']),
            'navListDiagnostics' => $navLists['diagnostics'],
            'audioLabelCount' => $audioLabelReport['count'],
            'audioLabelReport' => $audioLabelReport,
            'audioLabelDiagnostics' => $audioLabelReport['diagnostics'],
        ];
    }

    /**
     * @return array{
     *     present:bool,
     *     metaCount:int,
     *     items:list<array<string, mixed>>,
     *     byName:array<string, list<array<string, mixed>>>,
     *     uid:?string,
     *     depth:?string,
     *     totalPageCount:?string,
     *     maxPageNumber:?string,
     *     diagnostics:list<array<string, mixed>>
     * }
     */
    private static function emptyNcxHeadReport(): array
    {
        return [
            'present' => false,
            'metaCount' => 0,
            'items' => [],
            'byName' => [],
            'uid' => null,
            'depth' => null,
            'totalPageCount' => null,
            'maxPageNumber' => null,
            'diagnostics' => [],
        ];
    }

    /**
     * @return array{
     *     present:bool,
     *     metaCount:int,
     *     items:list<array<string, mixed>>,
     *     byName:array<string, list<array<string, mixed>>>,
     *     uid:?string,
     *     depth:?string,
     *     totalPageCount:?string,
     *     maxPageNumber:?string,
     *     diagnostics:list<array<string, mixed>>
     * }
     */
    private static function readNcxHeadReport(?\DOMElement $head): array
    {
        if (!$head instanceof \DOMElement) {
            return self::emptyNcxHeadReport();
        }

        $items = [];
        $byName = [];
        $diagnostics = [];
        foreach (self::childElements($head, 'meta', self::NCX_NS) as $index => $meta) {
            $name = self::nullableAttribute($meta, 'name');
            $content = self::nullableAttribute($meta, 'content');
            $entry = [
                'index' => $index,
                'id' => self::nullableAttribute($meta, 'id'),
                'name' => $name,
                'content' => $content,
                'scheme' => self::nullableAttribute($meta, 'scheme'),
                'attributes' => self::elementAttributes($meta),
            ];

            if ($name === null) {
                $diagnostics[] = [
                    'type' => 'missing-ncx-head-meta-name',
                    'index' => $index,
                    'content' => $content,
                    'message' => 'EPUB NCX head meta entry is missing a name attribute',
                ];
            } else {
                $byName[$name][] = $entry;
            }

            if ($content === null) {
                $diagnostics[] = [
                    'type' => 'missing-ncx-head-meta-content',
                    'index' => $index,
                    'name' => $name,
                    'message' => 'EPUB NCX head meta entry is missing a content attribute',
                ];
            }

            $items[] = $entry;
        }

        return [
            'present' => true,
            'metaCount' => count($items),
            'items' => $items,
            'byName' => $byName,
            'uid' => self::firstNcxHeadMetaContent($byName, 'dtb:uid'),
            'depth' => self::firstNcxHeadMetaContent($byName, 'dtb:depth'),
            'totalPageCount' => self::firstNcxHeadMetaContent($byName, 'dtb:totalPageCount'),
            'maxPageNumber' => self::firstNcxHeadMetaContent($byName, 'dtb:maxPageNumber'),
            'diagnostics' => $diagnostics,
        ];
    }

    /**
     * @param array<string, list<array<string, mixed>>> $byName
     */
    private static function firstNcxHeadMetaContent(array $byName, string $name): ?string
    {
        foreach ($byName[$name] ?? [] as $entry) {
            if (is_string($entry['content'] ?? null) && $entry['content'] !== '') {
                return $entry['content'];
            }
        }

        return null;
    }

    /**
     * @return array{
     *     present:bool,
     *     id:?string,
     *     class:?string,
     *     classes:list<string>,
     *     language:?string,
     *     direction:?string,
     *     title:string,
     *     attributes:array<string, string>,
     *     labelAttributes:array<string, string>,
     *     labelTextAttributes:array<string, string>,
     *     itemCount:int,
     *     items:list<array<string, mixed>>,
     *     diagnosticCount:int,
     *     diagnostics:list<array<string, mixed>>
     * }
     */
    private static function emptyNcxPageListReport(): array
    {
        return [
            'present' => false,
            'id' => null,
            'class' => null,
            'classes' => [],
            'language' => null,
            'direction' => null,
            'title' => '',
            'attributes' => [],
            'labelAttributes' => [],
            'labelTextAttributes' => [],
            'labelAudioCount' => 0,
            'labelAudio' => [],
            'labelAudioDiagnostics' => [],
            'itemCount' => 0,
            'items' => [],
            'diagnosticCount' => 0,
            'diagnostics' => [],
        ];
    }

    /**
     * @return array{
     *     present:bool,
     *     id:?string,
     *     class:?string,
     *     classes:list<string>,
     *     language:?string,
     *     direction:?string,
     *     title:string,
     *     attributes:array<string, string>,
     *     labelAttributes:array<string, string>,
     *     labelTextAttributes:array<string, string>,
     *     itemCount:int,
     *     items:list<array<string, mixed>>,
     *     diagnosticCount:int,
     *     diagnostics:list<array<string, mixed>>
     * }
     */
    private function readNcxPageList(ZipPackage $package, \DOMElement $pageList, string $ncxPart, array $manifestByPart): array
    {
        $navLabel = self::firstChildElement($pageList, 'navLabel', self::NCX_NS);
        $label = $navLabel instanceof \DOMElement
            ? self::firstDescendantElement($navLabel, 'text', self::NCX_NS)
            : null;
        $labelAudio = $this->readNcxLabelAudio($package, $navLabel, $ncxPart, $manifestByPart, 'page-list');
        $targets = $this->readNcxPageTargets($package, $pageList, $ncxPart, $manifestByPart);
        $diagnostics = [];

        foreach ($targets as $targetIndex => $target) {
            foreach (($target['diagnostics'] ?? []) as $diagnostic) {
                if (!is_array($diagnostic)) {
                    continue;
                }

                $diagnostics[] = [
                    'targetIndex' => $targetIndex,
                    'targetId' => is_string($target['id'] ?? null) ? $target['id'] : null,
                ] + $diagnostic;
            }
        }
        foreach ($labelAudio as $audioIndex => $audio) {
            foreach (($audio['diagnostics'] ?? []) as $diagnostic) {
                if (!is_array($diagnostic)) {
                    continue;
                }

                $diagnostics[] = [
                    'audioIndex' => $audioIndex,
                    'scope' => 'page-list-label',
                ] + $diagnostic;
            }
        }

        return [
            'present' => true,
            'id' => self::nullableAttribute($pageList, 'id'),
            'class' => self::nullableAttribute($pageList, 'class'),
            'classes' => self::spaceDelimited($pageList->getAttribute('class')),
            'language' => self::xmlLang($pageList),
            'direction' => self::direction($pageList),
            'title' => $label instanceof \DOMElement ? self::normalizedText($label) : '',
            'attributes' => self::elementAttributes($pageList),
            'labelAttributes' => $navLabel instanceof \DOMElement ? self::elementAttributes($navLabel) : [],
            'labelTextAttributes' => $label instanceof \DOMElement ? self::elementAttributes($label) : [],
            'labelAudioCount' => count($labelAudio),
            'labelAudio' => $labelAudio,
            'labelAudioDiagnostics' => self::labelAudioDiagnostics($labelAudio),
            'itemCount' => count($targets),
            'items' => $targets,
            'diagnosticCount' => count($diagnostics),
            'diagnostics' => $diagnostics,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function readNcxTextElementEntries(
        ZipPackage $package,
        \DOMElement $root,
        string $ncxPart,
        array $manifestByPart,
        string $localName,
        string $owner
    ): array
    {
        $items = [];
        foreach (self::childElements($root, $localName, self::NCX_NS) as $index => $element) {
            $textElement = self::firstDescendantElement($element, 'text', self::NCX_NS);
            $labelAudio = $this->readNcxLabelAudio($package, $element, $ncxPart, $manifestByPart, $owner);
            $items[] = [
                'index' => $index,
                'id' => self::nullableAttribute($element, 'id'),
                'class' => self::nullableAttribute($element, 'class'),
                'classes' => self::spaceDelimited($element->getAttribute('class')),
                'language' => self::xmlLang($element),
                'direction' => self::direction($element),
                'text' => $textElement instanceof \DOMElement
                    ? self::normalizedText($textElement)
                    : self::normalizedText($element),
                'attributes' => self::elementAttributes($element),
                'textAttributes' => $textElement instanceof \DOMElement ? self::elementAttributes($textElement) : [],
                'labelAudioCount' => count($labelAudio),
                'labelAudio' => $labelAudio,
                'labelAudioDiagnostics' => self::labelAudioDiagnostics($labelAudio),
            ];
        }

        return $items;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function readNcxPageTargets(ZipPackage $package, \DOMElement $parent, string $ncxPart, array $manifestByPart): array
    {
        $items = [];
        foreach (self::childElements($parent, 'pageTarget', self::NCX_NS) as $target) {
            $navLabel = self::firstChildElement($target, 'navLabel', self::NCX_NS);
            $label = $navLabel instanceof \DOMElement
                ? self::firstDescendantElement($navLabel, 'text', self::NCX_NS)
                : null;
            $content = self::firstChildElement($target, 'content', self::NCX_NS);
            $src = $content instanceof \DOMElement ? trim($content->getAttribute('src')) : '';
            $reference = $src === ''
                ? self::emptyPackageReference()
                : $this->packageReference($package, $ncxPart, $src, $manifestByPart, 'ncx-page-list');
            $type = self::nullableAttribute($target, 'type');
            $labelAudio = $this->readNcxLabelAudio($package, $navLabel, $ncxPart, $manifestByPart, 'page-target');

            $items[] = [
                'id' => self::nullableAttribute($target, 'id'),
                'playOrder' => self::nullableAttribute($target, 'playOrder'),
                'value' => self::nullableAttribute($target, 'value'),
                'class' => self::nullableAttribute($target, 'class'),
                'classes' => self::spaceDelimited($target->getAttribute('class')),
                'language' => self::xmlLang($target),
                'direction' => self::direction($target),
                'type' => $type,
                'types' => $type === null ? [] : [$type],
                'title' => $label instanceof \DOMElement ? self::normalizedText($label) : '',
                'href' => $src === '' ? null : $src,
                'target' => $reference['target'],
                'part' => $reference['part'],
                'fragment' => $reference['fragment'],
                'fragmentKind' => $reference['fragmentKind'],
                'epubCfi' => $reference['epubCfi'],
                'mediaFragment' => $reference['mediaFragment'],
                'external' => $reference['external'],
                'exists' => $reference['exists'],
                'byteLength' => $reference['byteLength'],
                'crc32' => $reference['crc32'],
                'manifestId' => $reference['manifestId'],
                'mediaType' => $reference['mediaType'],
                'encrypted' => $reference['encrypted'],
                'canExposeBytes' => $reference['canExposeBytes'],
                'hidden' => self::elementHidden($target),
                'attributes' => self::elementAttributes($target),
                'labelAttributes' => $navLabel instanceof \DOMElement ? self::elementAttributes($navLabel) : [],
                'labelTextAttributes' => $label instanceof \DOMElement ? self::elementAttributes($label) : [],
                'labelAudioCount' => count($labelAudio),
                'labelAudio' => $labelAudio,
                'labelAudioDiagnostics' => self::labelAudioDiagnostics($labelAudio),
                'contentAttributes' => $content instanceof \DOMElement ? self::elementAttributes($content) : [],
                'diagnostics' => $reference['diagnostics'],
                'children' => [],
            ];
        }

        return $items;
    }

    /**
     * @param list<string> $classes
     *
     * @return array{
     *     type:?string,
     *     types:list<string>,
     *     role:?string,
     *     roles:list<string>,
     *     roleAliases:list<string>,
     *     roleSources:list<array{class:string, role:string}>,
     *     unmappedRoleClasses:list<string>,
     *     diagnostics:list<array<string, mixed>>
     * }
     */
    private static function ncxNavListRoleReport(array $classes): array
    {
        $roles = [];
        $aliases = [];
        $sources = [];
        $unmapped = [];

        foreach ($classes as $class) {
            if (!is_string($class) || $class === '') {
                continue;
            }

            $lower = strtolower($class);
            $role = self::NCX_NAV_LIST_CLASS_ROLES[$lower] ?? null;
            if ($role === null) {
                $unmapped[] = $class;
                continue;
            }

            $aliases[] = $class;
            if (!in_array($role, $roles, true)) {
                $roles[] = $role;
            }
            $sources[] = [
                'class' => $class,
                'role' => $role,
            ];
        }

        $diagnostics = [];
        if ($roles === []) {
            $diagnostics[] = [
                'type' => 'missing-ncx-nav-list-role',
                'classes' => $classes,
                'message' => 'EPUB NCX navList class tokens do not identify a known supplemental navigation role for review handoff',
            ];
        } elseif (count($roles) > 1) {
            $diagnostics[] = [
                'type' => 'conflicting-ncx-nav-list-roles',
                'roles' => $roles,
                'aliases' => $aliases,
                'message' => 'EPUB NCX navList class tokens identify more than one supplemental navigation role',
            ];
        }

        $role = $roles[0] ?? null;

        return [
            'type' => $role,
            'types' => $roles,
            'role' => $role,
            'roles' => $roles,
            'roleAliases' => $aliases,
            'roleSources' => $sources,
            'unmappedRoleClasses' => $unmapped,
            'diagnostics' => $diagnostics,
        ];
    }

    /**
     * @param list<array<string, mixed>> $lists
     *
     * @return array<string, mixed>
     */
    private static function ncxNavListRoleSummary(array $lists): array
    {
        $roles = [];
        $byRole = [];
        $items = [];
        $diagnostics = [];
        $typedListCount = 0;
        $untypedListCount = 0;
        $conflictingListCount = 0;

        foreach ($lists as $list) {
            $listRoles = array_values(array_filter(
                is_array($list['roles'] ?? null) ? $list['roles'] : [],
                static fn (mixed $role): bool => is_string($role) && $role !== '',
            ));
            if ($listRoles === []) {
                ++$untypedListCount;
            } else {
                ++$typedListCount;
            }
            if (count($listRoles) > 1) {
                ++$conflictingListCount;
            }

            $item = [
                'index' => is_int($list['index'] ?? null) ? $list['index'] : count($items),
                'id' => is_string($list['id'] ?? null) ? $list['id'] : null,
                'title' => is_string($list['title'] ?? null) ? $list['title'] : '',
                'class' => is_string($list['class'] ?? null) ? $list['class'] : null,
                'classes' => is_array($list['classes'] ?? null) ? array_values($list['classes']) : [],
                'type' => is_string($list['type'] ?? null) ? $list['type'] : null,
                'types' => $listRoles,
                'role' => is_string($list['role'] ?? null) ? $list['role'] : null,
                'roles' => $listRoles,
                'roleAliases' => is_array($list['roleAliases'] ?? null) ? array_values($list['roleAliases']) : [],
                'unmappedRoleClasses' => is_array($list['unmappedRoleClasses'] ?? null) ? array_values($list['unmappedRoleClasses']) : [],
                'itemCount' => is_int($list['itemCount'] ?? null) ? $list['itemCount'] : 0,
                'diagnostics' => is_array($list['roleDiagnostics'] ?? null) ? array_values($list['roleDiagnostics']) : [],
            ];

            foreach ($listRoles as $role) {
                $roles[$role] = true;
                $byRole[$role][] = $item;
            }
            foreach ($item['diagnostics'] as $diagnostic) {
                if (!is_array($diagnostic)) {
                    continue;
                }

                $diagnostics[] = [
                    'listIndex' => $item['index'],
                    'listId' => $item['id'],
                ] + $diagnostic;
            }

            $items[] = $item;
        }

        return [
            'present' => $lists !== [],
            'listCount' => count($lists),
            'typedListCount' => $typedListCount,
            'untypedListCount' => $untypedListCount,
            'conflictingListCount' => $conflictingListCount,
            'roleCount' => count($roles),
            'roles' => array_keys($roles),
            'items' => $items,
            'byRole' => $byRole,
            'diagnosticCount' => count($diagnostics),
            'diagnostics' => $diagnostics,
        ];
    }

    /**
     * @return array{items:list<array<string, mixed>>, diagnostics:list<array<string, mixed>>}
     */
    private function readNcxNavLists(ZipPackage $package, \DOMElement $root, string $ncxPart, array $manifestByPart): array
    {
        $lists = [];
        $diagnostics = [];

        foreach (self::childElements($root, 'navList', self::NCX_NS) as $listIndex => $navList) {
            $navLabel = self::firstChildElement($navList, 'navLabel', self::NCX_NS);
            $label = $navLabel instanceof \DOMElement
                ? self::firstDescendantElement($navLabel, 'text', self::NCX_NS)
                : null;
            $targets = $this->readNcxNavTargets($package, $navList, $ncxPart, $manifestByPart);
            $listDiagnostics = [];
            $listId = self::nullableAttribute($navList, 'id');
            $classes = self::spaceDelimited($navList->getAttribute('class'));
            $roleReport = self::ncxNavListRoleReport($classes);
            $labelAudio = $this->readNcxLabelAudio($package, $navLabel, $ncxPart, $manifestByPart, 'nav-list');

            foreach ($targets as $targetIndex => $target) {
                foreach (($target['diagnostics'] ?? []) as $diagnostic) {
                    if (!is_array($diagnostic)) {
                        continue;
                    }

                    $entry = [
                        'listIndex' => $listIndex,
                        'listId' => $listId,
                        'targetIndex' => $targetIndex,
                        'targetId' => is_string($target['id'] ?? null) ? $target['id'] : null,
                    ] + $diagnostic;
                    $listDiagnostics[] = $entry;
                    $diagnostics[] = $entry;
                }
            }
            foreach ($labelAudio as $audioIndex => $audio) {
                foreach (($audio['diagnostics'] ?? []) as $diagnostic) {
                    if (!is_array($diagnostic)) {
                        continue;
                    }

                    $entry = [
                        'listIndex' => $listIndex,
                        'listId' => $listId,
                        'audioIndex' => $audioIndex,
                        'scope' => 'nav-list-label',
                    ] + $diagnostic;
                    $listDiagnostics[] = $entry;
                    $diagnostics[] = $entry;
                }
            }

            $lists[] = [
                'index' => $listIndex,
                'id' => $listId,
                'class' => self::nullableAttribute($navList, 'class'),
                'classes' => $classes,
                'language' => self::xmlLang($navList),
                'direction' => self::direction($navList),
                'type' => $roleReport['type'],
                'types' => $roleReport['types'],
                'role' => $roleReport['role'],
                'roles' => $roleReport['roles'],
                'roleAliases' => $roleReport['roleAliases'],
                'roleSources' => $roleReport['roleSources'],
                'unmappedRoleClasses' => $roleReport['unmappedRoleClasses'],
                'roleDiagnostics' => $roleReport['diagnostics'],
                'title' => $label instanceof \DOMElement ? self::normalizedText($label) : '',
                'attributes' => self::elementAttributes($navList),
                'labelAttributes' => $navLabel instanceof \DOMElement ? self::elementAttributes($navLabel) : [],
                'labelTextAttributes' => $label instanceof \DOMElement ? self::elementAttributes($label) : [],
                'labelAudioCount' => count($labelAudio),
                'labelAudio' => $labelAudio,
                'labelAudioDiagnostics' => self::labelAudioDiagnostics($labelAudio),
                'itemCount' => count($targets),
                'items' => $targets,
                'diagnostics' => $listDiagnostics,
            ];
        }

        return [
            'items' => $lists,
            'diagnostics' => $diagnostics,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function readNcxNavTargets(ZipPackage $package, \DOMElement $parent, string $ncxPart, array $manifestByPart): array
    {
        $items = [];

        foreach (self::childElements($parent, 'navTarget', self::NCX_NS) as $target) {
            $navLabel = self::firstChildElement($target, 'navLabel', self::NCX_NS);
            $label = $navLabel instanceof \DOMElement
                ? self::firstDescendantElement($navLabel, 'text', self::NCX_NS)
                : null;
            $content = self::firstChildElement($target, 'content', self::NCX_NS);
            $src = $content instanceof \DOMElement ? trim($content->getAttribute('src')) : '';
            $reference = $this->packageReference($package, $ncxPart, $src, $manifestByPart, 'ncx-nav-list');
            $labelAudio = $this->readNcxLabelAudio($package, $navLabel, $ncxPart, $manifestByPart, 'nav-target');

            $items[] = [
                'id' => self::nullableAttribute($target, 'id'),
                'playOrder' => self::nullableAttribute($target, 'playOrder'),
                'class' => self::nullableAttribute($target, 'class'),
                'classes' => self::spaceDelimited($target->getAttribute('class')),
                'language' => self::xmlLang($target),
                'direction' => self::direction($target),
                'title' => $label instanceof \DOMElement ? self::normalizedText($label) : '',
                'href' => $src === '' ? null : $src,
                'target' => $reference['target'],
                'part' => $reference['part'],
                'fragment' => $reference['fragment'],
                'fragmentKind' => $reference['fragmentKind'],
                'epubCfi' => $reference['epubCfi'],
                'mediaFragment' => $reference['mediaFragment'],
                'external' => $reference['external'],
                'exists' => $reference['exists'],
                'byteLength' => $reference['byteLength'],
                'crc32' => $reference['crc32'],
                'manifestId' => $reference['manifestId'],
                'mediaType' => $reference['mediaType'],
                'encrypted' => $reference['encrypted'],
                'canExposeBytes' => $reference['canExposeBytes'],
                'attributes' => self::elementAttributes($target),
                'labelAttributes' => $navLabel instanceof \DOMElement ? self::elementAttributes($navLabel) : [],
                'labelTextAttributes' => $label instanceof \DOMElement ? self::elementAttributes($label) : [],
                'labelAudioCount' => count($labelAudio),
                'labelAudio' => $labelAudio,
                'labelAudioDiagnostics' => self::labelAudioDiagnostics($labelAudio),
                'contentAttributes' => $content instanceof \DOMElement ? self::elementAttributes($content) : [],
                'diagnostics' => $reference['diagnostics'],
                'children' => [],
            ];
        }

        return $items;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function readNcxPoints(ZipPackage $package, \DOMElement $parent, string $ncxPart, array $manifestByPart): array
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
                : $this->packageReference($package, $ncxPart, $src, $manifestByPart, 'ncx');
            $classes = self::spaceDelimited($point->getAttribute('class'));
            $labelAudio = $this->readNcxLabelAudio($package, $navLabel, $ncxPart, $manifestByPart, 'nav-point');

            $items[] = [
                'id' => self::nullableAttribute($point, 'id'),
                'playOrder' => self::nullableAttribute($point, 'playOrder'),
                'class' => self::nullableAttribute($point, 'class'),
                'classes' => $classes,
                'language' => self::xmlLang($point),
                'direction' => self::direction($point),
                'title' => $label instanceof \DOMElement ? self::normalizedText($label) : '',
                'href' => $src === '' ? null : $src,
                'target' => $reference['target'],
                'part' => $reference['part'],
                'fragment' => $reference['fragment'],
                'fragmentKind' => $reference['fragmentKind'],
                'epubCfi' => $reference['epubCfi'],
                'mediaFragment' => $reference['mediaFragment'],
                'external' => $reference['external'],
                'exists' => $reference['exists'],
                'byteLength' => $reference['byteLength'],
                'crc32' => $reference['crc32'],
                'manifestId' => $reference['manifestId'],
                'mediaType' => $reference['mediaType'],
                'encrypted' => $reference['encrypted'],
                'canExposeBytes' => $reference['canExposeBytes'],
                'attributes' => self::elementAttributes($point),
                'labelAttributes' => $navLabel instanceof \DOMElement ? self::elementAttributes($navLabel) : [],
                'labelTextAttributes' => $label instanceof \DOMElement ? self::elementAttributes($label) : [],
                'labelAudioCount' => count($labelAudio),
                'labelAudio' => $labelAudio,
                'labelAudioDiagnostics' => self::labelAudioDiagnostics($labelAudio),
                'contentAttributes' => $content instanceof \DOMElement ? self::elementAttributes($content) : [],
                'diagnostics' => $reference['diagnostics'],
                'children' => $this->readNcxPoints($package, $point, $ncxPart, $manifestByPart),
            ];
        }

        return $items;
    }

    /**
     * @param array<string, array<string, mixed>> $manifestByPart
     *
     * @return list<array<string, mixed>>
     */
    private function readNcxLabelAudio(
        ZipPackage $package,
        ?\DOMElement $navLabel,
        string $ncxPart,
        array $manifestByPart,
        string $owner
    ): array {
        if (!$navLabel instanceof \DOMElement) {
            return [];
        }

        $items = [];
        foreach (self::childElements($navLabel, 'audio', self::NCX_NS) as $audio) {
            $src = self::nullableAttribute($audio, 'src');
            $reference = $this->ncxAudioReference($package, $ncxPart, $src, $manifestByPart);
            $clipBegin = self::nullableAttribute($audio, 'clipBegin');
            $clipEnd = self::nullableAttribute($audio, 'clipEnd');
            $clipTiming = self::ncxAudioClipTiming($clipBegin, $clipEnd);
            $diagnostics = array_merge($reference['diagnostics'], $clipTiming['diagnostics']);

            if (
                ($reference['mediaType'] ?? null) !== null
                && ($reference['external'] ?? false) !== true
                && ($reference['exists'] ?? false) === true
                && !str_starts_with(strtolower((string) $reference['mediaType']), 'audio/')
            ) {
                $diagnostics[] = [
                    'type' => 'unexpected-ncx-audio-media-type',
                    'mediaType' => $reference['mediaType'],
                    'part' => $reference['part'],
                    'message' => 'EPUB NCX audio label target should resolve to an audio media type',
                ];
            }

            $items[] = [
                'index' => count($items),
                'owner' => $owner,
                'id' => self::nullableAttribute($audio, 'id'),
                'class' => self::nullableAttribute($audio, 'class'),
                'classes' => self::spaceDelimited($audio->getAttribute('class')),
                'language' => self::xmlLang($audio),
                'direction' => self::direction($audio),
                'src' => $src,
                'target' => $reference['target'],
                'part' => $reference['part'],
                'fragment' => $reference['fragment'],
                'fragmentKind' => $reference['fragmentKind'],
                'epubCfi' => $reference['epubCfi'],
                'mediaFragment' => $reference['mediaFragment'],
                'external' => $reference['external'],
                'exists' => $reference['exists'],
                'byteLength' => $reference['byteLength'],
                'crc32' => $reference['crc32'],
                'byteSha256' => $reference['byteSha256'],
                'manifestId' => $reference['manifestId'],
                'mediaType' => $reference['mediaType'],
                'encrypted' => $reference['encrypted'],
                'canExposeBytes' => $reference['canExposeBytes'],
                'clipBegin' => $clipBegin,
                'clipBeginSeconds' => $clipTiming['clipBeginSeconds'],
                'clipEnd' => $clipEnd,
                'clipEndSeconds' => $clipTiming['clipEndSeconds'],
                'clipDurationSeconds' => $clipTiming['clipDurationSeconds'],
                'clipValid' => $clipTiming['valid'],
                'clipDiagnostics' => $clipTiming['diagnostics'],
                'attributes' => self::elementAttributes($audio),
                'diagnostics' => $diagnostics,
            ];
        }

        return $items;
    }

    /**
     * @param array<string, array<string, mixed>> $manifestByPart
     *
     * @return array{target:?string, part:?string, fragment:?string, fragmentKind:?string, epubCfi:?array<string, mixed>, mediaFragment:?array<string, mixed>, external:bool, exists:bool, byteLength:?int, crc32:?string, byteSha256:?string, manifestId:?string, mediaType:?string, encrypted:bool, canExposeBytes:bool, diagnostics:list<array<string, mixed>>}
     */
    private function ncxAudioReference(ZipPackage $package, string $ncxPart, ?string $src, array $manifestByPart): array
    {
        $reference = $this->packageReference($package, $ncxPart, (string) $src, $manifestByPart, 'ncx-audio');
        $diagnostics = $reference['diagnostics'];
        if (($reference['encrypted'] ?? false) === true) {
            $diagnostics[] = [
                'type' => 'encrypted-ncx-audio-reference',
                'part' => $reference['part'],
                'message' => 'EPUB NCX audio label target is encrypted and cannot expose source bytes',
            ];
        }

        $byteSha256 = null;
        if (
            ($reference['external'] ?? false) !== true
            && ($reference['exists'] ?? false) === true
            && ($reference['canExposeBytes'] ?? false) === true
            && is_string($reference['part'] ?? null)
        ) {
            try {
                $byteSha256 = hash('sha256', $package->read((string) $reference['part']));
            } catch (\Throwable $exception) {
                $diagnostics[] = [
                    'type' => 'ncx-audio-reference-bytes-unavailable',
                    'part' => $reference['part'],
                    'message' => $exception->getMessage(),
                ];
            }
        }

        return [
            'target' => $reference['target'],
            'part' => $reference['part'],
            'fragment' => $reference['fragment'],
            'fragmentKind' => $reference['fragmentKind'],
            'epubCfi' => $reference['epubCfi'],
            'mediaFragment' => $reference['mediaFragment'],
            'external' => $reference['external'],
            'exists' => $reference['exists'],
            'byteLength' => $reference['byteLength'],
            'crc32' => $reference['crc32'],
            'byteSha256' => $byteSha256,
            'manifestId' => $reference['manifestId'],
            'mediaType' => $reference['mediaType'],
            'encrypted' => $reference['encrypted'],
            'canExposeBytes' => $reference['canExposeBytes'],
            'diagnostics' => $diagnostics,
        ];
    }

    /**
     * @return array{clipBeginSeconds:?float, clipEndSeconds:?float, clipDurationSeconds:?float, valid:bool, diagnostics:list<array<string, mixed>>}
     */
    private static function ncxAudioClipTiming(?string $clipBegin, ?string $clipEnd): array
    {
        $beginSeconds = is_string($clipBegin) ? self::smilClockSeconds($clipBegin) : null;
        $endSeconds = is_string($clipEnd) ? self::smilClockSeconds($clipEnd) : null;
        $diagnostics = [];

        if (is_string($clipBegin) && trim($clipBegin) !== '' && $beginSeconds === null) {
            $diagnostics[] = [
                'type' => 'invalid-ncx-audio-clip-begin',
                'clipBegin' => $clipBegin,
                'message' => 'EPUB NCX audio clipBegin must be a bounded clock value',
            ];
        }

        if (is_string($clipEnd) && trim($clipEnd) !== '' && $endSeconds === null) {
            $diagnostics[] = [
                'type' => 'invalid-ncx-audio-clip-end',
                'clipEnd' => $clipEnd,
                'message' => 'EPUB NCX audio clipEnd must be a bounded clock value',
            ];
        }

        $durationSeconds = null;
        if ($beginSeconds !== null && $endSeconds !== null) {
            if ($endSeconds < $beginSeconds) {
                $diagnostics[] = [
                    'type' => 'ncx-audio-clip-end-before-begin',
                    'clipBegin' => $clipBegin,
                    'clipEnd' => $clipEnd,
                    'clipBeginSeconds' => $beginSeconds,
                    'clipEndSeconds' => $endSeconds,
                    'message' => 'EPUB NCX audio clipEnd must not be earlier than clipBegin',
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
     * @param list<array<string, mixed>> $items
     *
     * @return list<array<string, mixed>>
     */
    private static function labelAudioDiagnostics(array $items): array
    {
        $diagnostics = [];
        foreach ($items as $audioIndex => $audio) {
            foreach (($audio['diagnostics'] ?? []) as $diagnostic) {
                if (!is_array($diagnostic)) {
                    continue;
                }

                $diagnostics[] = [
                    'audioIndex' => $audioIndex,
                    'audioId' => is_string($audio['id'] ?? null) ? $audio['id'] : null,
                    'owner' => is_string($audio['owner'] ?? null) ? $audio['owner'] : null,
                ] + $diagnostic;
            }
        }

        return $diagnostics;
    }

    /**
     * @param list<array<string, mixed>> $navMapItems
     * @param array<string, mixed> $pageListReport
     * @param list<array<string, mixed>> $navLists
     *
     * @return array<string, mixed>
     */
    private static function ncxLabelAudioReport(
        array $navMapItems,
        array $pageListReport,
        array $navLists,
        array $docTitleEntries = [],
        array $docAuthorDetails = []
    ): array
    {
        $items = [];
        $byOwner = [];
        $diagnostics = [];
        $localCount = 0;
        $externalCount = 0;
        $missingCount = 0;
        $encryptedCount = 0;

        $appendAudio = static function (array $audio, string $scope, ?string $ownerId) use (&$items, &$byOwner, &$diagnostics, &$localCount, &$externalCount, &$missingCount, &$encryptedCount): void {
            $index = count($items);
            $entry = [
                'reportIndex' => $index,
                'scope' => $scope,
                'ownerId' => $ownerId,
            ] + $audio;

            $items[] = $entry;
            $byOwner[$scope][] = $entry;
            if (($entry['external'] ?? false) === true) {
                ++$externalCount;
            } elseif (($entry['exists'] ?? false) === true) {
                ++$localCount;
            } else {
                ++$missingCount;
            }
            if (($entry['encrypted'] ?? false) === true) {
                ++$encryptedCount;
            }

            foreach (($entry['diagnostics'] ?? []) as $diagnostic) {
                if (!is_array($diagnostic)) {
                    continue;
                }

                $diagnostics[] = [
                    'index' => $index,
                    'scope' => $scope,
                    'ownerId' => $ownerId,
                    'audioId' => is_string($entry['id'] ?? null) ? $entry['id'] : null,
                ] + $diagnostic;
            }
        };

        $walkTargets = static function (array $targets, string $scope) use (&$walkTargets, $appendAudio): void {
            foreach ($targets as $target) {
                if (!is_array($target)) {
                    continue;
                }

                $ownerId = is_string($target['id'] ?? null) ? $target['id'] : null;
                foreach (is_array($target['labelAudio'] ?? null) ? $target['labelAudio'] : [] as $audio) {
                    if (is_array($audio)) {
                        $appendAudio($audio, $scope, $ownerId);
                    }
                }

                if (is_array($target['children'] ?? null) && $target['children'] !== []) {
                    $walkTargets($target['children'], $scope);
                }
            }
        };

        $walkTargets($navMapItems, 'nav-map');

        foreach ($docTitleEntries as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            foreach (is_array($entry['labelAudio'] ?? null) ? $entry['labelAudio'] : [] as $audio) {
                if (is_array($audio)) {
                    $appendAudio($audio, 'doc-title', is_string($entry['id'] ?? null) ? $entry['id'] : null);
                }
            }
        }

        foreach ($docAuthorDetails as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            foreach (is_array($entry['labelAudio'] ?? null) ? $entry['labelAudio'] : [] as $audio) {
                if (is_array($audio)) {
                    $appendAudio($audio, 'doc-author', is_string($entry['id'] ?? null) ? $entry['id'] : null);
                }
            }
        }

        foreach (is_array($pageListReport['labelAudio'] ?? null) ? $pageListReport['labelAudio'] : [] as $audio) {
            if (is_array($audio)) {
                $appendAudio($audio, 'page-list', is_string($pageListReport['id'] ?? null) ? $pageListReport['id'] : null);
            }
        }
        $walkTargets(is_array($pageListReport['items'] ?? null) ? $pageListReport['items'] : [], 'page-target');

        foreach ($navLists as $list) {
            if (!is_array($list)) {
                continue;
            }

            $listId = is_string($list['id'] ?? null) ? $list['id'] : null;
            foreach (is_array($list['labelAudio'] ?? null) ? $list['labelAudio'] : [] as $audio) {
                if (is_array($audio)) {
                    $appendAudio($audio, 'nav-list', $listId);
                }
            }
            $walkTargets(is_array($list['items'] ?? null) ? $list['items'] : [], 'nav-target');
        }

        return [
            'present' => $items !== [],
            'count' => count($items),
            'localCount' => $localCount,
            'externalCount' => $externalCount,
            'missingCount' => $missingCount,
            'encryptedCount' => $encryptedCount,
            'items' => $items,
            'byOwner' => $byOwner,
            'diagnosticCount' => count($diagnostics),
            'diagnostics' => $diagnostics,
        ];
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
        $supplementalItems = [];
        $supplementalDiagnostics = [];
        $supplementalTargetsBySpineIndex = [];
        $supplementalMappedCount = 0;
        $supplementalExternalCount = 0;
        $supplementalMissingCount = 0;
        $supplementalOutsideSpineCount = 0;
        $ncxNavListTargetCount = 0;

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

        $ncxNavLists = is_array($ncx) && is_array($ncx['navLists'] ?? null) ? $ncx['navLists'] : [];
        $ncxNavListRoleReport = is_array($ncx) && is_array($ncx['navListRoleReport'] ?? null)
            ? $ncx['navListRoleReport']
            : self::ncxNavListRoleSummary($ncxNavLists);
        foreach ($ncxNavLists as $listOffset => $list) {
            if (!is_array($list)) {
                continue;
            }

            $listItems = is_array($list['items'] ?? null) ? $list['items'] : [];
            foreach (self::flattenNavigationItems($listItems) as $flat) {
                $item = self::navigationTargetItem(
                    $flat['item'],
                    'ncx-nav-list',
                    $ncxNavListTargetCount,
                    (int) $flat['depth'],
                    count($supplementalItems),
                    $spineByContentPart
                );
                $item['supplemental'] = true;
                $item['listIndex'] = is_int($list['index'] ?? null) ? $list['index'] : $listOffset;
                $item['listId'] = is_string($list['id'] ?? null) ? $list['id'] : null;
                $item['listTitle'] = is_string($list['title'] ?? null) ? $list['title'] : '';
                $item['listClass'] = is_string($list['class'] ?? null) ? $list['class'] : null;
                $item['listClasses'] = is_array($list['classes'] ?? null) ? array_values($list['classes']) : [];
                $item['listType'] = is_string($list['type'] ?? null) ? $list['type'] : null;
                $item['listTypes'] = is_array($list['types'] ?? null) ? array_values($list['types']) : [];
                $item['listRole'] = is_string($list['role'] ?? null) ? $list['role'] : null;
                $item['listRoles'] = is_array($list['roles'] ?? null) ? array_values($list['roles']) : [];
                $item['listRoleAliases'] = is_array($list['roleAliases'] ?? null) ? array_values($list['roleAliases']) : [];
                $item['listRoleSources'] = is_array($list['roleSources'] ?? null) ? array_values($list['roleSources']) : [];
                $item['listRoleDiagnostics'] = is_array($list['roleDiagnostics'] ?? null) ? array_values($list['roleDiagnostics']) : [];
                $supplementalItems[] = $item;
                ++$ncxNavListTargetCount;

                self::accumulateNavigationTarget(
                    $item,
                    $supplementalDiagnostics,
                    $supplementalTargetsBySpineIndex,
                    $supplementalMappedCount,
                    $supplementalExternalCount,
                    $supplementalMissingCount,
                    $supplementalOutsideSpineCount
                );
            }
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
        $cfiTargets = array_values(array_filter(
            $items,
            static fn (array $item): bool => ($item['fragmentKind'] ?? null) === 'epub-cfi',
        ));
        $mediaFragmentTargets = array_values(array_filter(
            $items,
            static fn (array $item): bool => ($item['fragmentKind'] ?? null) === 'media-fragment',
        ));

        return [
            'present' => $navTocCount > 0 || $ncxCount > 0,
            'source' => 'nav-ncx',
            'navTocCount' => $navTocCount,
            'ncxCount' => $ncxCount,
            'ncxNavListCount' => count($ncxNavLists),
            'ncxNavListRoleReport' => $ncxNavListRoleReport,
            'ncxNavListTargetCount' => $ncxNavListTargetCount,
            'targetCount' => count($items),
            'supplementalTargetCount' => count($supplementalItems),
            'supplementalMappedSpineTargetCount' => $supplementalMappedCount,
            'supplementalOutsideSpineTargetCount' => $supplementalOutsideSpineCount,
            'supplementalMissingTargetCount' => $supplementalMissingCount,
            'supplementalExternalTargetCount' => $supplementalExternalCount,
            'cfiTargetCount' => count($cfiTargets),
            'mediaFragmentTargetCount' => count($mediaFragmentTargets),
            'mappedSpineTargetCount' => $mappedCount,
            'outsideSpineTargetCount' => $outsideSpineCount,
            'missingTargetCount' => $missingCount,
            'externalTargetCount' => $externalCount,
            'uncoveredLinearSpineItemCount' => count($uncoveredLinearSpineItems),
            'items' => $items,
            'supplementalItems' => $supplementalItems,
            'cfiTargets' => $cfiTargets,
            'mediaFragmentTargets' => $mediaFragmentTargets,
            'spineCoverage' => array_values($spineCoverage),
            'uncoveredLinearSpineItems' => $uncoveredLinearSpineItems,
            'diagnostics' => $diagnostics,
            'supplementalDiagnostics' => $supplementalDiagnostics,
            'spineDiagnostics' => $spineDiagnostics,
        ];
    }

    /**
     * @param ?array<string, mixed> $nav
     * @param ?array<string, mixed> $ncx
     * @param array<string, mixed> $navigation
     *
     * @return array<string, mixed>
     */
    private static function navigationOutlineReport(?array $nav, ?array $ncx, array $navigation): array
    {
        $source = 'none';
        $sourceNavigationType = null;
        $navigationSource = null;
        $sourceItems = [];

        if (is_array($nav)) {
            $sectionsByType = is_array($nav['sectionsByType'] ?? null) ? $nav['sectionsByType'] : [];
            $tocSections = is_array($sectionsByType['toc'] ?? null) ? array_values($sectionsByType['toc']) : [];
            foreach ($tocSections as $section) {
                if (!is_array($section)) {
                    continue;
                }
                $items = is_array($section['items'] ?? null) ? array_values($section['items']) : [];
                if ($items === []) {
                    continue;
                }

                $source = 'nav';
                $sourceNavigationType = 'toc';
                $navigationSource = 'nav';
                $sourceItems = $items;
                break;
            }
        }

        if ($sourceItems === [] && is_array($ncx)) {
            $items = is_array($ncx['items'] ?? null) ? array_values($ncx['items']) : [];
            if ($items !== []) {
                $source = 'ncx';
                $sourceNavigationType = 'navMap';
                $navigationSource = 'ncx';
                $sourceItems = $items;
            }
        }

        if ($sourceItems === [] && is_array($nav)) {
            $items = is_array($nav['items'] ?? null) ? array_values($nav['items']) : [];
            if ($items !== []) {
                $source = 'nav-fallback';
                $sourceNavigationType = 'fallback';
                $navigationSource = 'nav';
                $sourceItems = $items;
            }
        }

        if ($sourceItems === [] || $navigationSource === null) {
            return [
                'present' => false,
                'source' => 'none',
                'sourceNavigationType' => null,
                'itemCount' => 0,
                'topLevelItemCount' => 0,
                'localTargetCount' => 0,
                'externalTargetCount' => 0,
                'missingTargetCount' => 0,
                'mappedSpineTargetCount' => 0,
                'diagnosticCount' => 0,
                'maxDepth' => 0,
                'items' => [],
                'flatItems' => [],
                'html' => '',
                'htmlSha256' => null,
                'diagnostics' => [],
            ];
        }

        $navigationItemsBySourceIndex = [];
        foreach (is_array($navigation['items'] ?? null) ? $navigation['items'] : [] as $navigationItem) {
            if (!is_array($navigationItem) || ($navigationItem['source'] ?? null) !== $navigationSource) {
                continue;
            }
            if (is_int($navigationItem['sourceIndex'] ?? null)) {
                $navigationItemsBySourceIndex[(int) $navigationItem['sourceIndex']] = $navigationItem;
            }
        }

        $sourceIndex = 0;
        $maxDepth = 0;
        $items = self::navigationOutlineItems(
            $sourceItems,
            $source,
            $navigationItemsBySourceIndex,
            0,
            $sourceIndex,
            $maxDepth
        );
        $flatItems = self::flattenNavigationOutlineItems($items);
        $diagnostics = [];
        $localTargetCount = 0;
        $externalTargetCount = 0;
        $missingTargetCount = 0;
        $mappedSpineTargetCount = 0;

        foreach ($flatItems as $item) {
            if (($item['external'] ?? false) === true) {
                ++$externalTargetCount;
            } elseif (($item['exists'] ?? false) === true) {
                ++$localTargetCount;
            } else {
                ++$missingTargetCount;
            }

            if (is_int($item['spineIndex'] ?? null)) {
                ++$mappedSpineTargetCount;
            }

            foreach (is_array($item['diagnostics'] ?? null) ? $item['diagnostics'] : [] as $diagnostic) {
                if (!is_array($diagnostic)) {
                    continue;
                }

                $diagnostics[] = [
                    'index' => $item['index'],
                    'source' => $item['source'],
                    'sourceIndex' => $item['sourceIndex'],
                ] + $diagnostic;
            }
        }

        $html = self::navigationOutlineHtml($items, $source, count($flatItems));

        return [
            'present' => $flatItems !== [],
            'source' => $source,
            'sourceNavigationType' => $sourceNavigationType,
            'itemCount' => count($flatItems),
            'topLevelItemCount' => count($items),
            'localTargetCount' => $localTargetCount,
            'externalTargetCount' => $externalTargetCount,
            'missingTargetCount' => $missingTargetCount,
            'mappedSpineTargetCount' => $mappedSpineTargetCount,
            'diagnosticCount' => count($diagnostics),
            'maxDepth' => $maxDepth,
            'items' => $items,
            'flatItems' => $flatItems,
            'html' => $html,
            'htmlSha256' => $html === '' ? null : hash('sha256', $html),
            'diagnostics' => $diagnostics,
        ];
    }

    /**
     * @param list<array<string, mixed>> $sourceItems
     * @param array<int, array<string, mixed>> $navigationItemsBySourceIndex
     *
     * @return list<array<string, mixed>>
     */
    private static function navigationOutlineItems(
        array $sourceItems,
        string $source,
        array $navigationItemsBySourceIndex,
        int $depth,
        int &$sourceIndex,
        int &$maxDepth
    ): array {
        $items = [];
        foreach ($sourceItems as $sourceItem) {
            if (!is_array($sourceItem)) {
                continue;
            }

            $currentSourceIndex = $sourceIndex;
            ++$sourceIndex;
            $navigationItem = $navigationItemsBySourceIndex[$currentSourceIndex] ?? [];
            $children = is_array($sourceItem['children'] ?? null)
                ? self::navigationOutlineItems(
                    array_values($sourceItem['children']),
                    $source,
                    $navigationItemsBySourceIndex,
                    $depth + 1,
                    $sourceIndex,
                    $maxDepth
                )
                : [];
            $maxDepth = max($maxDepth, $depth);

            $diagnostics = is_array($navigationItem['diagnostics'] ?? null)
                ? array_values($navigationItem['diagnostics'])
                : (is_array($sourceItem['diagnostics'] ?? null) ? array_values($sourceItem['diagnostics']) : []);
            $sourceDiagnostics = is_array($navigationItem['sourceDiagnostics'] ?? null)
                ? array_values($navigationItem['sourceDiagnostics'])
                : (is_array($sourceItem['diagnostics'] ?? null) ? array_values($sourceItem['diagnostics']) : []);

            $items[] = [
                'index' => $currentSourceIndex,
                'source' => $source,
                'sourceIndex' => is_int($navigationItem['sourceIndex'] ?? null)
                    ? $navigationItem['sourceIndex']
                    : $currentSourceIndex,
                'depth' => $depth,
                'id' => self::navigationOutlineString($navigationItem, 'id') ?? self::navigationOutlineString($sourceItem, 'id'),
                'itemId' => self::navigationOutlineString($navigationItem, 'itemId') ?? self::navigationOutlineString($sourceItem, 'itemId'),
                'labelId' => self::navigationOutlineString($navigationItem, 'labelId') ?? self::navigationOutlineString($sourceItem, 'labelId'),
                'playOrder' => self::navigationOutlineString($navigationItem, 'playOrder') ?? self::navigationOutlineString($sourceItem, 'playOrder'),
                'label' => self::navigationOutlineString($navigationItem, 'label') ?? self::navigationOutlineString($sourceItem, 'title') ?? '',
                'href' => self::navigationOutlineString($navigationItem, 'href') ?? self::navigationOutlineString($sourceItem, 'href'),
                'target' => self::navigationOutlineString($navigationItem, 'target') ?? self::navigationOutlineString($sourceItem, 'target'),
                'part' => self::navigationOutlineString($navigationItem, 'part') ?? self::navigationOutlineString($sourceItem, 'part'),
                'fragment' => self::navigationOutlineString($navigationItem, 'fragment') ?? self::navigationOutlineString($sourceItem, 'fragment'),
                'fragmentKind' => self::navigationOutlineString($navigationItem, 'fragmentKind') ?? self::navigationOutlineString($sourceItem, 'fragmentKind'),
                'external' => (bool) ($navigationItem['external'] ?? $sourceItem['external'] ?? false),
                'exists' => (bool) ($navigationItem['exists'] ?? $sourceItem['exists'] ?? false),
                'manifestId' => self::navigationOutlineString($navigationItem, 'manifestId') ?? self::navigationOutlineString($sourceItem, 'manifestId'),
                'mediaType' => self::navigationOutlineString($navigationItem, 'mediaType') ?? self::navigationOutlineString($sourceItem, 'mediaType'),
                'encrypted' => (bool) ($navigationItem['encrypted'] ?? $sourceItem['encrypted'] ?? false),
                'canExposeBytes' => (bool) ($navigationItem['canExposeBytes'] ?? $sourceItem['canExposeBytes'] ?? false),
                'spineIndex' => is_int($navigationItem['spineIndex'] ?? null) ? $navigationItem['spineIndex'] : null,
                'spineIdref' => self::navigationOutlineString($navigationItem, 'spineIdref'),
                'spineItemId' => self::navigationOutlineString($navigationItem, 'spineItemId'),
                'spinePart' => self::navigationOutlineString($navigationItem, 'spinePart'),
                'contentPart' => self::navigationOutlineString($navigationItem, 'contentPart'),
                'linear' => is_bool($navigationItem['linear'] ?? null) ? $navigationItem['linear'] : null,
                'type' => self::navigationOutlineString($navigationItem, 'type') ?? self::navigationOutlineString($sourceItem, 'type'),
                'types' => is_array($navigationItem['types'] ?? null)
                    ? array_values($navigationItem['types'])
                    : (is_array($sourceItem['types'] ?? null) ? array_values($sourceItem['types']) : []),
                'classes' => is_array($navigationItem['classes'] ?? null)
                    ? array_values($navigationItem['classes'])
                    : (is_array($sourceItem['classes'] ?? null) ? array_values($sourceItem['classes']) : []),
                'language' => self::navigationOutlineString($navigationItem, 'language') ?? self::navigationOutlineString($sourceItem, 'language'),
                'direction' => self::navigationOutlineString($navigationItem, 'direction') ?? self::navigationOutlineString($sourceItem, 'direction'),
                'hidden' => (bool) ($navigationItem['hidden'] ?? $sourceItem['hidden'] ?? false),
                'sourceDiagnostics' => $sourceDiagnostics,
                'diagnostics' => $diagnostics,
                'childCount' => count($children),
                'children' => $children,
            ];
        }

        return $items;
    }

    /**
     * @param list<array<string, mixed>> $items
     *
     * @return list<array<string, mixed>>
     */
    private static function flattenNavigationOutlineItems(array $items): array
    {
        $flat = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $copy = $item;
            $children = is_array($copy['children'] ?? null) ? $copy['children'] : [];
            unset($copy['children']);
            $flat[] = $copy;
            if ($children !== []) {
                array_push($flat, ...self::flattenNavigationOutlineItems($children));
            }
        }

        return $flat;
    }

    /**
     * @param list<array<string, mixed>> $items
     */
    private static function navigationOutlineHtml(array $items, string $source, int $itemCount): string
    {
        if ($items === []) {
            return '';
        }

        return '<nav'
            . self::navigationOutlineHtmlAttributes([
                'class' => 'epub-navigation-outline',
                'data-epub-source' => $source,
                'data-epub-item-count' => (string) $itemCount,
            ])
            . '><ol>'
            . self::navigationOutlineItemsHtml($items)
            . '</ol></nav>';
    }

    /**
     * @param list<array<string, mixed>> $items
     */
    private static function navigationOutlineItemsHtml(array $items): string
    {
        $html = '';
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $attrs = [
                'data-epub-source' => (string) ($item['source'] ?? ''),
                'data-epub-index' => (string) ($item['index'] ?? ''),
                'data-epub-source-index' => (string) ($item['sourceIndex'] ?? ''),
                'data-epub-depth' => (string) ($item['depth'] ?? ''),
            ];
            foreach ([
                'target' => 'data-epub-target',
                'part' => 'data-epub-part',
                'fragmentKind' => 'data-epub-fragment-kind',
                'manifestId' => 'data-epub-manifest-id',
                'spineIdref' => 'data-epub-spine-idref',
            ] as $field => $attribute) {
                if (is_string($item[$field] ?? null) && $item[$field] !== '') {
                    $attrs[$attribute] = $item[$field];
                }
            }
            if (is_int($item['spineIndex'] ?? null)) {
                $attrs['data-epub-spine-index'] = (string) $item['spineIndex'];
            }
            if (($item['external'] ?? false) === true) {
                $attrs['data-epub-external'] = 'true';
            }
            if (($item['exists'] ?? true) !== true && ($item['external'] ?? false) !== true) {
                $attrs['data-epub-missing'] = 'true';
            }
            if (($item['hidden'] ?? false) === true) {
                $attrs['data-epub-hidden'] = 'true';
            }

            $children = is_array($item['children'] ?? null) ? $item['children'] : [];
            $html .= '<li' . self::navigationOutlineHtmlAttributes($attrs) . '>'
                . '<span class="epub-navigation-outline-label">'
                . self::navigationOutlineHtmlText((string) ($item['label'] ?? ''))
                . '</span>';
            if ($children !== []) {
                $html .= '<ol>' . self::navigationOutlineItemsHtml($children) . '</ol>';
            }
            $html .= '</li>';
        }

        return $html;
    }

    /**
     * @param array<string, string> $attributes
     */
    private static function navigationOutlineHtmlAttributes(array $attributes): string
    {
        $html = '';
        foreach ($attributes as $name => $value) {
            if ($value === '') {
                continue;
            }

            $html .= ' ' . $name . '="' . self::navigationOutlineHtmlAttribute($value) . '"';
        }

        return $html;
    }

    private static function navigationOutlineHtmlAttribute(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private static function navigationOutlineHtmlText(string $value): string
    {
        return htmlspecialchars($value, ENT_NOQUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * @param array<string, mixed> $item
     */
    private static function navigationOutlineString(array $item, string $key): ?string
    {
        return is_string($item[$key] ?? null) ? $item[$key] : null;
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
        $fragmentFields = self::targetFragmentFields($target);
        $mediaFragment = is_array($item['mediaFragment'] ?? null) ? $item['mediaFragment'] : $fragmentFields['mediaFragment'];

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
        if ($mediaFragment !== null) {
            $diagnostics[] = [
                'type' => 'navigation-media-fragment-target',
                'source' => $source,
                'target' => $target,
                'fragment' => is_string($item['fragment'] ?? null) ? $item['fragment'] : $fragmentFields['fragment'],
                'message' => 'EPUB navigation target uses a W3C media fragment for import handoff metadata',
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
            'fragment' => is_string($item['fragment'] ?? null) ? $item['fragment'] : self::targetFragment($target),
            'fragmentKind' => is_string($item['fragmentKind'] ?? null) ? $item['fragmentKind'] : $fragmentFields['fragmentKind'],
            'epubCfi' => is_array($item['epubCfi'] ?? null) ? $item['epubCfi'] : $fragmentFields['epubCfi'],
            'mediaFragment' => $mediaFragment,
            'external' => (bool) ($item['external'] ?? false),
            'exists' => (bool) ($item['exists'] ?? false),
            'manifestId' => is_string($item['manifestId'] ?? null) ? $item['manifestId'] : null,
            'mediaType' => is_string($item['mediaType'] ?? null) ? $item['mediaType'] : null,
            'encrypted' => (bool) ($item['encrypted'] ?? false),
            'canExposeBytes' => (bool) ($item['canExposeBytes'] ?? false),
            'type' => is_string($item['type'] ?? null) ? $item['type'] : null,
            'types' => is_array($item['types'] ?? null) ? array_values($item['types']) : [],
            'itemTypes' => is_array($item['itemTypes'] ?? null) ? array_values($item['itemTypes']) : [],
            'labelTypes' => is_array($item['labelTypes'] ?? null) ? array_values($item['labelTypes']) : [],
            'typeSource' => is_string($item['typeSource'] ?? null) ? $item['typeSource'] : null,
            'typeSources' => is_array($item['typeSources'] ?? null) ? array_values($item['typeSources']) : [],
            'itemId' => is_string($item['itemId'] ?? null) ? $item['itemId'] : null,
            'labelId' => is_string($item['labelId'] ?? null) ? $item['labelId'] : null,
            'labelElement' => is_string($item['labelElement'] ?? null) ? $item['labelElement'] : null,
            'class' => is_string($item['class'] ?? null) ? $item['class'] : null,
            'classes' => is_array($item['classes'] ?? null) ? array_values($item['classes']) : [],
            'language' => is_string($item['language'] ?? null) ? $item['language'] : null,
            'direction' => is_string($item['direction'] ?? null) ? $item['direction'] : null,
            'hidden' => (bool) ($item['hidden'] ?? false),
            'attributes' => is_array($item['attributes'] ?? null) ? $item['attributes'] : [],
            'labelAttributes' => is_array($item['labelAttributes'] ?? null) ? $item['labelAttributes'] : [],
            'labelTextAttributes' => is_array($item['labelTextAttributes'] ?? null) ? $item['labelTextAttributes'] : [],
            'labelAudioCount' => is_array($item['labelAudio'] ?? null) ? count($item['labelAudio']) : 0,
            'labelAudio' => is_array($item['labelAudio'] ?? null) ? array_values($item['labelAudio']) : [],
            'labelAudioDiagnostics' => is_array($item['labelAudioDiagnostics'] ?? null) ? array_values($item['labelAudioDiagnostics']) : [],
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
    private function readMediaOverlays(
        ZipPackage $package,
        array $manifestById,
        array $mediaDurations,
        array $mediaOverlayStyles
    ): array {
        $references = [];
        foreach ($manifestById as $item) {
            $mediaOverlay = $item['mediaOverlay'] ?? null;
            if (!is_string($mediaOverlay) || $mediaOverlay === '') {
                continue;
            }

            $references[$mediaOverlay][] = (string) $item['id'];
        }

        $manifestByPart = self::manifestByPart($manifestById);
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
                    'activeClass' => null,
                    'activeClassTokens' => [],
                    'activeClassMetadata' => null,
                    'playbackActiveClass' => null,
                    'playbackActiveClassTokens' => [],
                    'playbackActiveClassMetadata' => null,
                    'styleMetadata' => null,
                    'textRef' => null,
                    'textRefTarget' => null,
                    'textRefPart' => null,
                    'textRefFragment' => null,
                    'textRefFragmentKind' => null,
                    'textRefEpubCfi' => null,
                    'textRefMediaFragment' => null,
                    'textRefExternal' => false,
                    'textRefExists' => false,
                    'textRefByteLength' => null,
                    'textRefCrc32' => null,
                    'textRefByteSha256' => null,
                    'textRefManifestId' => null,
                    'textRefMediaType' => null,
                    'textRefEncrypted' => false,
                    'textRefCanExposeBytes' => false,
                    'textRefDiagnostics' => [],
                    'sequences' => [],
                    'sequenceCount' => 0,
                    'sequenceDiagnostics' => [],
                    'items' => [],
                    'diagnostics' => [[
                        'type' => 'missing-media-overlay-manifest-item',
                        'id' => $id,
                        'message' => 'EPUB OPF manifest item references a media-overlay id that is not in the OPF manifest',
                    ]],
                ];
                continue;
            }

            $durationMetadata = is_array($mediaDurations['overlaysById'][$id] ?? null)
                ? $mediaDurations['overlaysById'][$id]
                : null;
            $styleMetadata = is_array($mediaOverlayStyles['overlaysById'][$id] ?? null)
                ? $mediaOverlayStyles['overlaysById'][$id]
                : null;
            $overlays[$id] = $this->readMediaOverlayItem($package, $item, $referencedBy, $durationMetadata, $styleMetadata, $manifestByPart);
        }

        return $overlays;
    }

    /**
     * @param array<string, mixed> $item
     * @param list<string> $referencedBy
     *
     * @return array<string, mixed>
     */
    private function readMediaOverlayItem(
        ZipPackage $package,
        array $item,
        array $referencedBy,
        ?array $durationMetadata,
        ?array $styleMetadata,
        array $manifestByPart
    ): array {
        $diagnostics = [];
        if (!self::mediaTypeBaseEquals($item['mediaType'] ?? null, self::SMIL_MEDIA_TYPE)) {
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
        $sequences = [];
        $sequenceDiagnostics = [];
        $items = [];
        if (
            ($item['exists'] ?? false) === true
            && !self::isEncryptedManifestItem($item)
            && self::mediaTypeBaseEquals($item['mediaType'] ?? null, self::SMIL_MEDIA_TYPE)
        ) {
            $dom = self::loadXml($package->read((string) $item['part']), 'EPUB SMIL media overlay XML');
            $root = $dom->documentElement;
            if (!$root instanceof \DOMElement || $root->localName !== 'smil' || $root->namespaceURI !== self::SMIL_NS) {
                throw new \InvalidArgumentException('EPUB SMIL media overlay must use the SMIL namespace');
            }

            $body = self::firstChildElement($root, 'body', self::SMIL_NS) ?? $root;
            $textRef = self::firstSmilTextRef($body);
            if ($textRef !== null) {
                $textRefReference = $this->smilReference($package, (string) $item['part'], $textRef, $manifestByPart);
                $textRefTarget = $textRefReference['target'];
            }
            $timeline = $this->readSmilOverlayTimeline($package, $body, (string) $item['part'], $textRefTarget, $manifestByPart);
            $items = $timeline['items'];
            $sequences = $timeline['sequences'];
            $sequenceDiagnostics = $timeline['sequenceDiagnostics'];
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
            'activeClass' => is_array($styleMetadata) ? $styleMetadata['activeClass'] : null,
            'activeClassTokens' => is_array($styleMetadata) ? $styleMetadata['activeClassTokens'] : [],
            'activeClassMetadata' => is_array($styleMetadata) ? $styleMetadata['activeClassMetadata'] : null,
            'playbackActiveClass' => is_array($styleMetadata) ? $styleMetadata['playbackActiveClass'] : null,
            'playbackActiveClassTokens' => is_array($styleMetadata) ? $styleMetadata['playbackActiveClassTokens'] : [],
            'playbackActiveClassMetadata' => is_array($styleMetadata) ? $styleMetadata['playbackActiveClassMetadata'] : null,
            'styleMetadata' => $styleMetadata,
            'textRef' => $textRef,
            'textRefTarget' => $textRefTarget,
            'textRefPart' => $textRefReference['part'] ?? null,
            'textRefFragment' => $textRefReference['fragment'] ?? null,
            'textRefFragmentKind' => $textRefReference['fragmentKind'] ?? null,
            'textRefEpubCfi' => $textRefReference['epubCfi'] ?? null,
            'textRefMediaFragment' => $textRefReference['mediaFragment'] ?? null,
            'textRefExternal' => $textRefReference['external'] ?? false,
            'textRefExists' => $textRefReference['exists'] ?? false,
            'textRefByteLength' => $textRefReference['byteLength'] ?? null,
            'textRefCrc32' => $textRefReference['crc32'] ?? null,
            'textRefByteSha256' => $textRefReference['byteSha256'] ?? null,
            'textRefManifestId' => $textRefReference['manifestId'] ?? null,
            'textRefMediaType' => $textRefReference['mediaType'] ?? null,
            'textRefEncrypted' => $textRefReference['encrypted'] ?? false,
            'textRefCanExposeBytes' => $textRefReference['canExposeBytes'] ?? false,
            'textRefDiagnostics' => $textRefReference['diagnostics'] ?? [],
            'sequences' => $sequences,
            'sequenceCount' => count($sequences),
            'sequenceDiagnostics' => $sequenceDiagnostics,
            'items' => $items,
            'diagnostics' => $diagnostics,
        ];
    }

    /**
     * @param array<string, array<string, mixed>> $manifestByPart
     *
     * @return array{items:list<array<string, mixed>>, sequences:list<array<string, mixed>>, sequenceDiagnostics:list<array<string, mixed>>}
     */
    private function readSmilOverlayTimeline(
        ZipPackage $package,
        \DOMElement $element,
        string $smilPart,
        ?string $inheritedTextTarget,
        array $manifestByPart
    ): array {
        $items = [];
        $sequences = [];
        $sequenceDiagnostics = [];
        $this->collectSmilOverlayTimeline(
            $package,
            $element,
            $smilPart,
            $inheritedTextTarget,
            $manifestByPart,
            [],
            $items,
            $sequences,
            $sequenceDiagnostics,
        );

        return [
            'items' => $items,
            'sequences' => $sequences,
            'sequenceDiagnostics' => $sequenceDiagnostics,
        ];
    }

    /**
     * @param array<string, array<string, mixed>> $manifestByPart
     * @param list<array<string, mixed>> $sequenceStack
     * @param list<array<string, mixed>> $items
     * @param list<array<string, mixed>> $sequences
     * @param list<array<string, mixed>> $sequenceDiagnostics
     */
    private function collectSmilOverlayTimeline(
        ZipPackage $package,
        \DOMElement $element,
        string $smilPart,
        ?string $inheritedTextTarget,
        array $manifestByPart,
        array $sequenceStack,
        array &$items,
        array &$sequences,
        array &$sequenceDiagnostics
    ): void {
        $currentTextTarget = $inheritedTextTarget;
        $textRef = self::smilTextRef($element);
        $textRefReference = null;
        if ($textRef !== null) {
            $textRefReference = $this->smilReference($package, $smilPart, $textRef, $manifestByPart);
            $currentTextTarget = $textRefReference['target'];
        }

        $currentSequenceStack = $sequenceStack;
        if ($element->localName === 'seq' && $element->namespaceURI === self::SMIL_NS) {
            $sequenceIndex = count($sequences);
            $sequenceId = self::nullableAttribute($element, 'id');
            $pathItem = self::smilSequencePathItem($sequenceId, $sequenceIndex);
            $parentSequence = $sequenceStack === [] ? null : $sequenceStack[array_key_last($sequenceStack)];
            $parentPath = is_array($parentSequence) && is_array($parentSequence['path'] ?? null) ? $parentSequence['path'] : [];
            $path = array_merge($parentPath, [$pathItem]);
            $reference = $textRefReference ?? self::emptySmilReference($currentTextTarget);
            $sequence = [
                'index' => $sequenceIndex,
                'id' => $sequenceId,
                'types' => self::epubTypes($element),
                'depth' => count($sequenceStack),
                'parentIndex' => is_array($parentSequence) ? $parentSequence['index'] : null,
                'path' => $path,
                'textRef' => $textRef,
                'textRefTarget' => $reference['target'],
                'textRefPart' => $reference['part'],
                'textRefFragment' => $reference['fragment'],
                'textRefFragmentKind' => $reference['fragmentKind'],
                'textRefEpubCfi' => $reference['epubCfi'],
                'textRefMediaFragment' => $reference['mediaFragment'],
                'textRefExternal' => $reference['external'],
                'textRefExists' => $reference['exists'],
                'textRefByteLength' => $reference['byteLength'],
                'textRefCrc32' => $reference['crc32'],
                'textRefByteSha256' => $reference['byteSha256'],
                'textRefManifestId' => $reference['manifestId'],
                'textRefMediaType' => $reference['mediaType'],
                'textRefEncrypted' => $reference['encrypted'],
                'textRefCanExposeBytes' => $reference['canExposeBytes'],
                'repeatCount' => self::nullableAttribute($element, 'repeatCount'),
                'repeatDur' => self::nullableAttribute($element, 'repeatDur'),
                'dur' => self::nullableAttribute($element, 'dur'),
                'directParCount' => count(self::childElements($element, 'par', self::SMIL_NS)),
                'childSequenceCount' => count(self::childElements($element, 'seq', self::SMIL_NS)),
                'diagnostics' => $reference['diagnostics'],
            ];

            foreach ($sequence['diagnostics'] as $diagnostic) {
                $sequenceDiagnostics[] = [
                    'sequenceIndex' => $sequenceIndex,
                    'sequenceId' => $sequenceId,
                ] + $diagnostic;
            }

            $sequences[] = $sequence;
            $currentSequenceStack[] = [
                'index' => $sequenceIndex,
                'id' => $sequenceId,
                'path' => $path,
                'types' => $sequence['types'],
                'textRef' => $textRef,
                'textRefTarget' => $reference['target'],
                'depth' => $sequence['depth'],
            ];
        }

        if ($element->localName === 'par' && $element->namespaceURI === self::SMIL_NS) {
            $items[] = $this->readSmilPar($package, $element, $smilPart, $currentTextTarget, $manifestByPart, $currentSequenceStack);
        }

        foreach (self::childElements($element) as $child) {
            if ($child->namespaceURI !== self::SMIL_NS || !in_array($child->localName, ['body', 'seq', 'par'], true)) {
                continue;
            }

            $this->collectSmilOverlayTimeline(
                $package,
                $child,
                $smilPart,
                $currentTextTarget,
                $manifestByPart,
                $currentSequenceStack,
                $items,
                $sequences,
                $sequenceDiagnostics,
            );
        }
    }

    /**
     * @param list<array<string, mixed>> $sequenceStack
     *
     * @return array<string, mixed>
     */
    private function readSmilPar(
        ZipPackage $package,
        \DOMElement $par,
        string $smilPart,
        ?string $textTarget,
        array $manifestByPart,
        array $sequenceStack = []
    ): array
    {
        $text = self::firstChildElement($par, 'text', self::SMIL_NS);
        $audio = self::firstChildElement($par, 'audio', self::SMIL_NS);
        $textSrc = $text instanceof \DOMElement ? self::nullableAttribute($text, 'src') : null;
        $audioSrc = $audio instanceof \DOMElement ? self::nullableAttribute($audio, 'src') : null;
        $textReference = $textSrc === null ? self::emptySmilReference($textTarget) : $this->smilReference($package, $smilPart, $textSrc, $manifestByPart);
        $audioReference = $this->smilReference($package, $smilPart, $audioSrc, $manifestByPart);
        $clipBegin = $audio instanceof \DOMElement ? self::nullableAttribute($audio, 'clipBegin') : null;
        $clipEnd = $audio instanceof \DOMElement ? self::nullableAttribute($audio, 'clipEnd') : null;
        $clipTiming = self::smilClipTiming($clipBegin, $clipEnd);
        $sequenceContext = self::smilSequenceContext($sequenceStack);

        return [
            'id' => self::nullableAttribute($par, 'id'),
            'types' => self::epubTypes($par),
            'sequenceIndex' => $sequenceContext['index'],
            'sequenceId' => $sequenceContext['id'],
            'sequenceDepth' => $sequenceContext['depth'],
            'sequencePath' => $sequenceContext['path'],
            'sequenceTypes' => $sequenceContext['types'],
            'sequenceTextRef' => $sequenceContext['textRef'],
            'sequenceTextTarget' => $sequenceContext['textRefTarget'],
            'textSrc' => $textSrc,
            'textTarget' => $textReference['target'],
            'textPart' => $textReference['part'],
            'textFragment' => $textReference['fragment'],
            'textFragmentKind' => $textReference['fragmentKind'],
            'textEpubCfi' => $textReference['epubCfi'],
            'textMediaFragment' => $textReference['mediaFragment'],
            'textExternal' => $textReference['external'],
            'textExists' => $textReference['exists'],
            'textByteLength' => $textReference['byteLength'],
            'textCrc32' => $textReference['crc32'],
            'textByteSha256' => $textReference['byteSha256'],
            'textManifestId' => $textReference['manifestId'],
            'textMediaType' => $textReference['mediaType'],
            'textEncrypted' => $textReference['encrypted'],
            'textCanExposeBytes' => $textReference['canExposeBytes'],
            'audioSrc' => $audioSrc,
            'audioTarget' => $audioReference['target'],
            'audioPart' => $audioReference['part'],
            'audioFragment' => $audioReference['fragment'],
            'audioFragmentKind' => $audioReference['fragmentKind'],
            'audioEpubCfi' => $audioReference['epubCfi'],
            'audioMediaFragment' => $audioReference['mediaFragment'],
            'audioExternal' => $audioReference['external'],
            'audioExists' => $audioReference['exists'],
            'audioByteLength' => $audioReference['byteLength'],
            'audioCrc32' => $audioReference['crc32'],
            'audioByteSha256' => $audioReference['byteSha256'],
            'audioManifestId' => $audioReference['manifestId'],
            'audioMediaType' => $audioReference['mediaType'],
            'audioEncrypted' => $audioReference['encrypted'],
            'audioCanExposeBytes' => $audioReference['canExposeBytes'],
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

    private static function smilSequencePathItem(?string $id, int $index): string
    {
        return $id !== null && $id !== '' ? $id : 'seq#' . $index;
    }

    /**
     * @param list<array<string, mixed>> $sequenceStack
     *
     * @return array{index:?int, id:?string, depth:?int, path:list<string>, types:list<string>, textRef:?string, textRefTarget:?string}
     */
    private static function smilSequenceContext(array $sequenceStack): array
    {
        if ($sequenceStack === []) {
            return [
                'index' => null,
                'id' => null,
                'depth' => null,
                'path' => [],
                'types' => [],
                'textRef' => null,
                'textRefTarget' => null,
            ];
        }

        $last = $sequenceStack[array_key_last($sequenceStack)];
        $types = [];
        foreach ($sequenceStack as $sequence) {
            if (!is_array($sequence['types'] ?? null)) {
                continue;
            }

            self::appendUniqueStrings($types, $sequence['types']);
        }

        return [
            'index' => is_int($last['index'] ?? null) ? $last['index'] : null,
            'id' => is_string($last['id'] ?? null) ? $last['id'] : null,
            'depth' => is_int($last['depth'] ?? null) ? $last['depth'] : null,
            'path' => is_array($last['path'] ?? null) ? array_values($last['path']) : [],
            'types' => $types,
            'textRef' => is_string($last['textRef'] ?? null) ? $last['textRef'] : null,
            'textRefTarget' => is_string($last['textRefTarget'] ?? null) ? $last['textRefTarget'] : null,
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
     * @param array<string, array<string, mixed>> $manifestByPart
     *
     * @return array{target:?string, part:?string, fragment:?string, fragmentKind:?string, epubCfi:?array<string, mixed>, mediaFragment:?array<string, mixed>, external:bool, exists:bool, byteLength:?int, crc32:?string, byteSha256:?string, manifestId:?string, mediaType:?string, encrypted:bool, canExposeBytes:bool, diagnostics:list<array<string, mixed>>}
     */
    private function smilReference(ZipPackage $package, string $basePart, ?string $src, array $manifestByPart): array
    {
        $src = trim((string) $src);
        if ($src === '') {
            return self::emptySmilReference(null);
        }

        $reference = $this->packageReference($package, $basePart, $src, $manifestByPart, 'media-overlay');
        $diagnostics = $reference['diagnostics'];
        if (($reference['encrypted'] ?? false) === true) {
            $diagnostics[] = [
                'type' => 'encrypted-media-overlay-reference',
                'part' => $reference['part'],
                'message' => 'EPUB media-overlay reference targets an encrypted package part that cannot expose source bytes',
            ];
        }

        $byteSha256 = null;
        if (
            ($reference['external'] ?? false) !== true
            && ($reference['exists'] ?? false) === true
            && ($reference['canExposeBytes'] ?? false) === true
            && is_string($reference['part'] ?? null)
        ) {
            try {
                $byteSha256 = hash('sha256', $package->read((string) $reference['part']));
            } catch (\Throwable $exception) {
                $diagnostics[] = [
                    'type' => 'media-overlay-reference-bytes-unavailable',
                    'part' => $reference['part'],
                    'message' => $exception->getMessage(),
                ];
            }
        }

        return [
            'target' => $reference['target'],
            'part' => $reference['part'],
            'fragment' => $reference['fragment'],
            'fragmentKind' => $reference['fragmentKind'],
            'epubCfi' => $reference['epubCfi'],
            'mediaFragment' => $reference['mediaFragment'],
            'external' => $reference['external'],
            'exists' => $reference['exists'],
            'byteLength' => $reference['byteLength'],
            'crc32' => $reference['crc32'],
            'byteSha256' => $byteSha256,
            'manifestId' => $reference['manifestId'],
            'mediaType' => $reference['mediaType'],
            'encrypted' => $reference['encrypted'],
            'canExposeBytes' => $reference['canExposeBytes'],
            'diagnostics' => $diagnostics,
        ];
    }

    /**
     * @return array{target:?string, part:?string, fragment:?string, fragmentKind:?string, epubCfi:?array<string, mixed>, mediaFragment:?array<string, mixed>, external:bool, exists:bool, byteLength:?int, crc32:?string, byteSha256:?string, manifestId:?string, mediaType:?string, encrypted:bool, canExposeBytes:bool, diagnostics:list<array<string, mixed>>}
     */
    private static function emptySmilReference(?string $target): array
    {
        $external = is_string($target) && self::isExternalReference($target);
        $fragmentFields = self::targetFragmentFields($target);

        return [
            'target' => $target,
            'part' => $target === null || $external ? null : OpcPackagePath::stripQueryAndFragment($target),
            'fragment' => $fragmentFields['fragment'],
            'fragmentKind' => $fragmentFields['fragmentKind'],
            'epubCfi' => $fragmentFields['epubCfi'],
            'mediaFragment' => $fragmentFields['mediaFragment'],
            'external' => $external,
            'exists' => false,
            'byteLength' => null,
            'crc32' => null,
            'byteSha256' => null,
            'manifestId' => null,
            'mediaType' => null,
            'encrypted' => false,
            'canExposeBytes' => false,
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
                !self::mediaTypeBaseEquals($item['mediaType'] ?? null, self::XHTML_MEDIA_TYPE)
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
                'manifestLanguage' => $item['language'] ?? null,
                'manifestDirection' => $item['direction'] ?? null,
                'manifestAttributes' => $item['attributes'] ?? [],
                'manifestCustomAttributes' => $item['customAttributes'] ?? [],
                'resourceFlags' => $item['resourceFlags'] ?? self::resourcePropertyFlags($item['properties'] ?? []),
                'resourceReviewFlags' => $item['resourceReviewFlags'] ?? [],
                'mediaOverlay' => $item['mediaOverlay'],
                'mediaOverlayReference' => $item['mediaOverlayReference'] ?? null,
                'mediaOverlayDiagnostics' => $item['mediaOverlayDiagnostics'] ?? [],
                'byteLength' => is_int($item['byteLength'] ?? null) ? $item['byteLength'] : null,
                'crc32' => is_string($item['crc32'] ?? null) ? $item['crc32'] : null,
                'byteSha256' => is_string($item['byteSha256'] ?? null) ? $item['byteSha256'] : null,
                'html' => $html,
                'htmlByteLength' => strlen($html),
                'htmlSha256' => hash('sha256', $html),
                'htmlLineCount' => $html === '' ? 0 : substr_count($html, "\n") + 1,
                'contentResourceReport' => $contentReport,
                'contentResourceFlags' => $contentReport['flags'],
                'contentResourceReviewFlags' => $contentReport['reviewFlags'],
                'contentMetadata' => $contentReport['metadata'],
                'contentLanguage' => $contentReport['metadata']['language'] ?? null,
                'contentDirection' => $contentReport['metadata']['direction'] ?? null,
                'contentBodyEpubTypes' => $contentReport['metadata']['bodyEpubTypes'] ?? [],
                'contentViewport' => $contentReport['metadata']['viewport'],
                'contentViewports' => $contentReport['metadata']['viewports'],
                'contentReferences' => $contentReport['references'],
                'contentEmbeddedResources' => $contentReport['embeddedResources'],
                'contentEmbeddedResourceDiagnostics' => $contentReport['embeddedResourceDiagnostics'],
                'contentLinks' => $contentReport['links'],
                'contentLinkDiagnostics' => $contentReport['linkDiagnostics'],
                'contentRefreshes' => $contentReport['refreshes'],
                'contentRefreshDiagnostics' => $contentReport['refreshDiagnostics'],
                'contentSideEffects' => $contentReport['sideEffects'],
                'contentSideEffectDiagnostics' => $contentReport['sideEffectDiagnostics'],
                'contentStyles' => $contentReport['styles'],
                'contentStyleDiagnostics' => $contentReport['styleDiagnostics'],
                'contentScripts' => $contentReport['scripts'],
                'contentScriptEventHandlers' => $contentReport['scriptEventHandlers'],
                'contentJavascriptReferences' => $contentReport['javascriptReferences'],
                'contentScriptDiagnostics' => $contentReport['scriptDiagnostics'],
                'contentSwitches' => $contentReport['switches'],
                'contentTriggers' => $contentReport['triggers'],
                'contentSemantics' => $contentReport['semantics'],
                'contentSemanticTypes' => $contentReport['semanticTypes'],
                'contentSemanticDiagnostics' => $contentReport['semanticDiagnostics'],
                'contentTables' => $contentReport['tables'],
                'contentTableDiagnostics' => $contentReport['tableDiagnostics'],
                'contentRubies' => $contentReport['rubies'],
                'contentRubyDiagnostics' => $contentReport['rubyDiagnostics'],
                'contentDiagnostics' => $contentReport['diagnostics'],
            ];
        }

        return $assets;
    }

    /**
     * @param list<array<string, mixed>> $spine
     * @param list<array<string, mixed>> $xhtmlAssets
     *
     * @return array<string, mixed>
     */
    private static function spineContentProvenanceReport(array $spine, array $xhtmlAssets): array
    {
        $assetsByPart = [];
        foreach ($xhtmlAssets as $asset) {
            $part = is_string($asset['part'] ?? null) ? $asset['part'] : '';
            if ($part !== '') {
                $assetsByPart[$part] = $asset;
            }
        }

        $items = [];
        $itemsByPart = [];
        $itemsByIdref = [];
        $hashedItems = [];
        $fallbackItems = [];
        $totalByteLength = 0;

        foreach ($spine as $index => $item) {
            $contentMediaType = $item['contentMediaType'] ?? $item['mediaType'] ?? null;
            if (!self::mediaTypeBaseEquals($contentMediaType, self::XHTML_MEDIA_TYPE)) {
                continue;
            }

            $contentPart = (string) ($item['contentPart'] ?? $item['part'] ?? '');
            $asset = $assetsByPart[$contentPart] ?? null;
            if (!is_array($asset)) {
                continue;
            }

            $byteLength = is_int($asset['byteLength'] ?? null) ? $asset['byteLength'] : null;
            $byteSha256 = is_string($asset['byteSha256'] ?? null) ? $asset['byteSha256'] : null;
            $isFallback = ($item['contentIsFallback'] ?? false) === true;
            $summary = [
                'spineIndex' => (int) $index,
                'idref' => (string) ($item['idref'] ?? ''),
                'spineItemId' => is_string($item['id'] ?? null) ? $item['id'] : null,
                'spinePart' => is_string($item['part'] ?? null) ? $item['part'] : null,
                'spineMediaType' => is_string($item['mediaType'] ?? null) ? $item['mediaType'] : null,
                'spineItemLanguage' => is_string($item['language'] ?? null) ? $item['language'] : null,
                'spineItemDirection' => is_string($item['direction'] ?? null) ? $item['direction'] : null,
                'spineItemAttributes' => is_array($item['attributes'] ?? null) ? $item['attributes'] : [],
                'spineItemCustomAttributes' => is_array($item['customAttributes'] ?? null) ? $item['customAttributes'] : [],
                'contentManifestId' => (string) ($asset['id'] ?? ''),
                'contentHref' => (string) ($asset['href'] ?? ''),
                'contentTarget' => is_string($asset['target'] ?? null) ? $asset['target'] : null,
                'contentPart' => $contentPart,
                'contentMediaType' => is_string($contentMediaType) ? $contentMediaType : null,
                'contentIsFallback' => $isFallback,
                'fallbackOf' => $isFallback ? (string) ($item['idref'] ?? '') : null,
                'byteLength' => $byteLength,
                'crc32' => is_string($asset['crc32'] ?? null) ? $asset['crc32'] : null,
                'byteSha256' => $byteSha256,
                'htmlByteLength' => is_int($asset['htmlByteLength'] ?? null) ? $asset['htmlByteLength'] : null,
                'htmlSha256' => is_string($asset['htmlSha256'] ?? null) ? $asset['htmlSha256'] : null,
                'htmlLineCount' => is_int($asset['htmlLineCount'] ?? null) ? $asset['htmlLineCount'] : null,
                'manifestLanguage' => is_string($asset['manifestLanguage'] ?? null) ? $asset['manifestLanguage'] : null,
                'manifestDirection' => is_string($asset['manifestDirection'] ?? null) ? $asset['manifestDirection'] : null,
                'manifestAttributes' => is_array($asset['manifestAttributes'] ?? null) ? $asset['manifestAttributes'] : [],
                'manifestCustomAttributes' => is_array($asset['manifestCustomAttributes'] ?? null) ? $asset['manifestCustomAttributes'] : [],
                'resourceReviewFlags' => is_array($asset['resourceReviewFlags'] ?? null) ? array_values($asset['resourceReviewFlags']) : [],
                'contentResourceReviewFlags' => is_array($asset['contentResourceReviewFlags'] ?? null) ? array_values($asset['contentResourceReviewFlags']) : [],
                'diagnosticCount' => count(is_array($asset['contentDiagnostics'] ?? null) ? $asset['contentDiagnostics'] : []),
            ];

            $items[] = $summary;
            if ($contentPart !== '' && !isset($itemsByPart[$contentPart])) {
                $itemsByPart[$contentPart] = $summary;
            }
            if ($summary['idref'] !== '' && !isset($itemsByIdref[$summary['idref']])) {
                $itemsByIdref[$summary['idref']] = $summary;
            }
            if ($byteSha256 !== null) {
                $hashedItems[] = $summary;
            }
            if ($isFallback) {
                $fallbackItems[] = $summary;
            }
            if ($byteLength !== null) {
                $totalByteLength += $byteLength;
            }
        }

        ksort($itemsByPart, SORT_STRING);
        ksort($itemsByIdref, SORT_STRING);

        return [
            'present' => $items !== [],
            'spineItemCount' => count($spine),
            'contentDocumentCount' => count($items),
            'directContentCount' => count($items) - count($fallbackItems),
            'fallbackContentCount' => count($fallbackItems),
            'hashedContentCount' => count($hashedItems),
            'totalByteLength' => $totalByteLength,
            'items' => $items,
            'itemsByPart' => $itemsByPart,
            'itemsByIdref' => $itemsByIdref,
            'hashedItems' => $hashedItems,
            'fallbackItems' => $fallbackItems,
        ];
    }

    /**
     * @param list<array<string, mixed>> $manifest
     * @param list<array<string, mixed>> $xhtmlAssets
     * @param array<string, mixed> $xhtmlResourceReport
     * @param array<string, mixed> $cssResourceReport
     *
     * @return array<string, mixed>
     */
    private static function remoteResourceReport(
        array $manifest,
        array $xhtmlAssets,
        array $xhtmlResourceReport,
        array $cssResourceReport = []
    ): array
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

        $cssRemoteReferences = [];
        foreach (is_array($cssResourceReport['items'] ?? null) ? $cssResourceReport['items'] : [] as $asset) {
            if (!is_array($asset)) {
                continue;
            }

            $references = array_values(array_filter(
                is_array($asset['references'] ?? null) ? $asset['references'] : [],
                static fn (array $reference): bool => ($reference['external'] ?? false) === true,
            ));
            if ($references === []) {
                continue;
            }

            array_push($remoteReferences, ...$references);
            array_push($cssRemoteReferences, ...$references);
            $part = is_string($asset['part'] ?? null) ? $asset['part'] : '';
            $manifestProperties = is_array($asset['manifestProperties'] ?? null) ? array_values($asset['manifestProperties']) : [];
            $manifestDeclared = isset($declaredByPart[$part])
                || in_array('remote-resources', $manifestProperties, true);
            $observed = [
                'id' => (string) ($asset['id'] ?? ''),
                'href' => (string) ($asset['href'] ?? ''),
                'part' => $part,
                'source' => 'css',
                'manifestDeclared' => $manifestDeclared,
                'manifestProperties' => $manifestProperties,
                'remoteReferenceCount' => count($references),
                'remoteReferences' => $references,
                'reviewFlags' => is_array($asset['reviewFlags'] ?? null)
                    ? array_values($asset['reviewFlags'])
                    : [],
                'diagnostics' => [],
            ];

            if (!$manifestDeclared) {
                $diagnostic = [
                    'type' => 'undeclared-css-remote-resources',
                    'id' => $observed['id'],
                    'part' => $part === '' ? null : $part,
                    'remoteReferenceCount' => count($references),
                    'message' => 'EPUB CSS content references remote resources but the OPF manifest item does not declare remote-resources',
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
            $isXhtml = self::mediaTypeBaseEquals($mediaType, self::XHTML_MEDIA_TYPE);
            $diagnostic = [
                'type' => $isXhtml
                    ? 'declared-remote-resources-not-observed'
                    : 'declared-remote-resources-unscanned-resource',
                'id' => (string) ($declared['id'] ?? ''),
                'part' => $part,
                'mediaType' => $mediaType,
                'message' => $isXhtml
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
            'cssExternalReferenceCount' => count($cssRemoteReferences),
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
     * @param list<array<string, mixed>> $manifest
     * @param array<string, array<string, mixed>> $manifestByPart
     *
     * @return array<string, mixed>
     */
    private function cssResourceReport(ZipPackage $package, array $manifest, array $manifestByPart): array
    {
        $items = [];
        $itemsByPart = [];
        $externalReferences = [];
        $missingReferences = [];
        $encryptedReferences = [];
        $diagnostics = [];
        $referenceCount = 0;
        $importReferenceCount = 0;
        $urlReferenceCount = 0;
        $imageSetReferenceCount = 0;
        $fontFaceCount = 0;
        $fontFaceSourceCount = 0;
        $fontFaceLocalSourceCount = 0;
        $fontFaceUrlSourceCount = 0;
        $fontFaceExternalSourceCount = 0;
        $fontFaceMissingSourceCount = 0;
        $conditionalRuleCount = 0;
        $mediaRuleCount = 0;
        $supportsRuleCount = 0;
        $importConditionCount = 0;
        $pageRuleCount = 0;
        $namedPageRuleCount = 0;
        $pagePseudoClassCount = 0;
        $pageMarginBoxCount = 0;
        $fontFaceItems = [];
        $fontFaceFamilies = [];
        $fontFaceDiagnostics = [];
        $conditionalRules = [];
        $mediaConditions = [];
        $supportsConditions = [];
        $importConditions = [];
        $pageRules = [];
        $pageRuleDiagnostics = [];
        $pageRuleNames = [];
        $pagePseudoClasses = [];
        $pageMarginBoxNames = [];
        $reviewRequiredCount = 0;

        foreach ($manifest as $asset) {
            if (
                !self::mediaTypeBaseEquals($asset['mediaType'] ?? null, 'text/css')
                || ($asset['exists'] ?? false) !== true
                || self::isEncryptedManifestItem($asset)
                || !is_string($asset['part'] ?? null)
                || (string) $asset['part'] === ''
            ) {
                continue;
            }

            $part = (string) $asset['part'];
            try {
                $css = $package->read($part);
            } catch (\Throwable $exception) {
                $assetDiagnostics = [[
                    'type' => 'css-resource-bytes-unavailable',
                    'part' => $part,
                    'message' => $exception->getMessage(),
                ]];
                $diagnostics[] = $assetDiagnostics[0];
                $item = [
                    'id' => (string) ($asset['id'] ?? ''),
                    'href' => (string) ($asset['href'] ?? ''),
                    'part' => $part,
                    'manifestProperties' => is_array($asset['properties'] ?? null) ? array_values($asset['properties']) : [],
                    'referenceCount' => 0,
                    'importReferenceCount' => 0,
                    'urlReferenceCount' => 0,
                    'imageSetReferenceCount' => 0,
                    'fontFaceCount' => 0,
                    'fontFaceSourceCount' => 0,
                    'fontFaceLocalSourceCount' => 0,
                    'fontFaceUrlSourceCount' => 0,
                    'fontFaceExternalSourceCount' => 0,
                    'fontFaceMissingSourceCount' => 0,
                    'fontFaceFamilies' => [],
                    'fontFaces' => [],
                    'fontFaceDiagnostics' => [],
                    'fontFaceDiagnosticCount' => 0,
                    'conditionalRuleCount' => 0,
                    'mediaRuleCount' => 0,
                    'supportsRuleCount' => 0,
                    'importConditionCount' => 0,
                    'pageRuleCount' => 0,
                    'namedPageRuleCount' => 0,
                    'pagePseudoClassCount' => 0,
                    'pageMarginBoxCount' => 0,
                    'conditionalRules' => [],
                    'mediaConditions' => [],
                    'supportsConditions' => [],
                    'importConditions' => [],
                    'pageRules' => [],
                    'pageRuleDiagnostics' => [],
                    'pageRuleDiagnosticCount' => 0,
                    'pageRuleNames' => [],
                    'pagePseudoClasses' => [],
                    'pageMarginBoxNames' => [],
                    'reviewFlags' => ['missing-references'],
                    'references' => [],
                    'diagnostics' => $assetDiagnostics,
                ];
                $item['exportPolicy'] = self::cssResourceExportPolicy($item);
                $items[] = $item;
                $itemsByPart[$part] = $item;
                ++$reviewRequiredCount;
                continue;
            }

            $references = $this->cssContentReferences($package, $part, $css, $manifestByPart);
            $assetDiagnostics = [];
            $assetImportCount = 0;
            $assetUrlCount = 0;
            $assetImageSetCount = 0;
            foreach ($references as $reference) {
                if (($reference['kind'] ?? null) === 'import') {
                    ++$assetImportCount;
                } elseif (($reference['kind'] ?? null) === 'image-set') {
                    ++$assetImageSetCount;
                } else {
                    ++$assetUrlCount;
                }

                if (($reference['external'] ?? false) === true) {
                    $externalReferences[] = $reference;
                }
                if (($reference['exists'] ?? true) !== true && ($reference['external'] ?? false) !== true) {
                    $missingReferences[] = $reference;
                }
                if (($reference['encrypted'] ?? false) === true) {
                    $encryptedReferences[] = $reference;
                }
                foreach ($reference['diagnostics'] as $diagnostic) {
                    $assetDiagnostics[] = [
                        'index' => $reference['index'],
                        'kind' => $reference['kind'],
                        'href' => $reference['href'],
                    ] + $diagnostic;
                }
            }

            $assetConditionalRules = self::cssConditionalAtRuleReports($css);
            $assetMediaRules = array_values(array_filter(
                $assetConditionalRules,
                static fn (array $rule): bool => ($rule['kind'] ?? null) === 'media',
            ));
            $assetSupportsRules = array_values(array_filter(
                $assetConditionalRules,
                static fn (array $rule): bool => ($rule['kind'] ?? null) === 'supports',
            ));
            $assetImportConditions = array_values(array_filter(
                array_map(
                    static fn (array $reference): ?string => is_string($reference['importCondition'] ?? null)
                        ? $reference['importCondition']
                        : null,
                    $references,
                ),
                static fn (?string $condition): bool => $condition !== null && $condition !== '',
            ));
            $assetMediaConditions = self::cssConditionalRuleConditions($assetMediaRules);
            $assetSupportsConditions = self::cssConditionalRuleConditions($assetSupportsRules);
            $assetPageRules = self::cssPageAtRuleReports($css);
            $assetPageRuleDiagnostics = [];
            foreach ($assetPageRules as $pageRule) {
                foreach (is_array($pageRule['diagnostics'] ?? null) ? $pageRule['diagnostics'] : [] as $diagnostic) {
                    if (!is_array($diagnostic)) {
                        continue;
                    }

                    $assetPageRuleDiagnostics[] = [
                        'pageRuleIndex' => is_int($pageRule['index'] ?? null) ? $pageRule['index'] : null,
                        'selector' => is_string($pageRule['selector'] ?? null) ? $pageRule['selector'] : null,
                    ] + $diagnostic;
                }
            }
            array_push($assetDiagnostics, ...$assetPageRuleDiagnostics);
            $assetPageRuleNames = self::cssPageRuleNames($assetPageRules);
            $assetPagePseudoClasses = self::cssPageRulePseudoClasses($assetPageRules);
            $assetPageMarginBoxNames = self::cssPageRuleMarginBoxNames($assetPageRules);
            $assetPageMarginBoxCount = array_sum(array_map(
                static fn (array $pageRule): int => is_int($pageRule['marginBoxCount'] ?? null) ? $pageRule['marginBoxCount'] : 0,
                $assetPageRules,
            ));
            $assetNamedPageRuleCount = count(array_filter(
                $assetPageRules,
                static fn (array $pageRule): bool => is_string($pageRule['name'] ?? null) && $pageRule['name'] !== '',
            ));

            $fontFaces = $this->cssFontFaceReports($package, $part, $css, $manifestByPart);
            $assetFontFaceCount = count($fontFaces);
            $assetFontFaceFamilies = [];
            $assetFontFaceSourceCount = 0;
            $assetFontFaceLocalSourceCount = 0;
            $assetFontFaceUrlSourceCount = 0;
            $assetFontFaceExternalSourceCount = 0;
            $assetFontFaceMissingSourceCount = 0;
            $assetFontFaceDiagnostics = [];
            foreach ($fontFaces as $fontFace) {
                $assetFontFaceSourceCount += (int) ($fontFace['sourceCount'] ?? 0);
                $assetFontFaceLocalSourceCount += (int) ($fontFace['localSourceCount'] ?? 0);
                $assetFontFaceUrlSourceCount += (int) ($fontFace['urlSourceCount'] ?? 0);
                $assetFontFaceExternalSourceCount += (int) ($fontFace['externalSourceCount'] ?? 0);
                $assetFontFaceMissingSourceCount += (int) ($fontFace['missingSourceCount'] ?? 0);

                $family = is_string($fontFace['family'] ?? null) ? $fontFace['family'] : null;
                if ($family !== null && $family !== '' && !in_array($family, $assetFontFaceFamilies, true)) {
                    $assetFontFaceFamilies[] = $family;
                }
                foreach (is_array($fontFace['diagnostics'] ?? null) ? $fontFace['diagnostics'] : [] as $diagnostic) {
                    $assetFontFaceDiagnostics[] = [
                        'fontFaceIndex' => $fontFace['index'],
                        'family' => $family,
                    ] + $diagnostic;
                }
            }
            $reviewFlags = self::cssResourceReviewFlags($references, $assetConditionalRules, $assetPageRules);
            if ($reviewFlags !== []) {
                ++$reviewRequiredCount;
            }

            $item = [
                'id' => (string) ($asset['id'] ?? ''),
                'href' => (string) ($asset['href'] ?? ''),
                'part' => $part,
                'manifestProperties' => is_array($asset['properties'] ?? null) ? array_values($asset['properties']) : [],
                'referenceCount' => count($references),
                'importReferenceCount' => $assetImportCount,
                'urlReferenceCount' => $assetUrlCount,
                'imageSetReferenceCount' => $assetImageSetCount,
                'fontFaceCount' => $assetFontFaceCount,
                'fontFaceSourceCount' => $assetFontFaceSourceCount,
                'fontFaceLocalSourceCount' => $assetFontFaceLocalSourceCount,
                'fontFaceUrlSourceCount' => $assetFontFaceUrlSourceCount,
                'fontFaceExternalSourceCount' => $assetFontFaceExternalSourceCount,
                'fontFaceMissingSourceCount' => $assetFontFaceMissingSourceCount,
                'fontFaceFamilies' => $assetFontFaceFamilies,
                'fontFaces' => $fontFaces,
                'fontFaceDiagnostics' => $assetFontFaceDiagnostics,
                'fontFaceDiagnosticCount' => count($assetFontFaceDiagnostics),
                'conditionalRuleCount' => count($assetConditionalRules),
                'mediaRuleCount' => count($assetMediaRules),
                'supportsRuleCount' => count($assetSupportsRules),
                'importConditionCount' => count($assetImportConditions),
                'pageRuleCount' => count($assetPageRules),
                'namedPageRuleCount' => $assetNamedPageRuleCount,
                'pagePseudoClassCount' => count($assetPagePseudoClasses),
                'pageMarginBoxCount' => $assetPageMarginBoxCount,
                'conditionalRules' => $assetConditionalRules,
                'mediaConditions' => $assetMediaConditions,
                'supportsConditions' => $assetSupportsConditions,
                'importConditions' => $assetImportConditions,
                'pageRules' => $assetPageRules,
                'pageRuleDiagnostics' => $assetPageRuleDiagnostics,
                'pageRuleDiagnosticCount' => count($assetPageRuleDiagnostics),
                'pageRuleNames' => $assetPageRuleNames,
                'pagePseudoClasses' => $assetPagePseudoClasses,
                'pageMarginBoxNames' => $assetPageMarginBoxNames,
                'reviewFlags' => $reviewFlags,
                'references' => $references,
                'diagnostics' => $assetDiagnostics,
            ];
            $item['exportPolicy'] = self::cssResourceExportPolicy($item);

            $referenceCount += $item['referenceCount'];
            $importReferenceCount += $assetImportCount;
            $urlReferenceCount += $assetUrlCount;
            $imageSetReferenceCount += $assetImageSetCount;
            $fontFaceCount += $assetFontFaceCount;
            $fontFaceSourceCount += $assetFontFaceSourceCount;
            $fontFaceLocalSourceCount += $assetFontFaceLocalSourceCount;
            $fontFaceUrlSourceCount += $assetFontFaceUrlSourceCount;
            $fontFaceExternalSourceCount += $assetFontFaceExternalSourceCount;
            $fontFaceMissingSourceCount += $assetFontFaceMissingSourceCount;
            $conditionalRuleCount += count($assetConditionalRules);
            $mediaRuleCount += count($assetMediaRules);
            $supportsRuleCount += count($assetSupportsRules);
            $importConditionCount += count($assetImportConditions);
            $pageRuleCount += count($assetPageRules);
            $namedPageRuleCount += $assetNamedPageRuleCount;
            $pagePseudoClassCount += count($assetPagePseudoClasses);
            $pageMarginBoxCount += $assetPageMarginBoxCount;
            array_push($fontFaceItems, ...$fontFaces);
            array_push($conditionalRules, ...$assetConditionalRules);
            array_push($pageRules, ...$assetPageRules);
            foreach ($assetPageRuleDiagnostics as $diagnostic) {
                $pageRuleDiagnostics[] = ['part' => $part] + $diagnostic;
            }
            foreach ($assetFontFaceFamilies as $family) {
                if (!in_array($family, $fontFaceFamilies, true)) {
                    $fontFaceFamilies[] = $family;
                }
            }
            self::appendUniqueStrings($mediaConditions, $assetMediaConditions);
            self::appendUniqueStrings($supportsConditions, $assetSupportsConditions);
            self::appendUniqueStrings($importConditions, $assetImportConditions);
            self::appendUniqueStrings($pageRuleNames, $assetPageRuleNames);
            self::appendUniqueStrings($pagePseudoClasses, $assetPagePseudoClasses);
            self::appendUniqueStrings($pageMarginBoxNames, $assetPageMarginBoxNames);
            foreach ($assetFontFaceDiagnostics as $diagnostic) {
                $fontFaceDiagnostics[] = ['part' => $part] + $diagnostic;
            }
            foreach ($assetDiagnostics as $diagnostic) {
                $diagnostics[] = ['part' => $part] + $diagnostic;
            }

            $items[] = $item;
            $itemsByPart[$part] = $item;
        }

        return [
            'present' => $items !== [],
            'assetCount' => count($items),
            'referenceCount' => $referenceCount,
            'importReferenceCount' => $importReferenceCount,
            'urlReferenceCount' => $urlReferenceCount,
            'imageSetReferenceCount' => $imageSetReferenceCount,
            'fontFaceCount' => $fontFaceCount,
            'fontFaceSourceCount' => $fontFaceSourceCount,
            'fontFaceLocalSourceCount' => $fontFaceLocalSourceCount,
            'fontFaceUrlSourceCount' => $fontFaceUrlSourceCount,
            'fontFaceExternalSourceCount' => $fontFaceExternalSourceCount,
            'fontFaceMissingSourceCount' => $fontFaceMissingSourceCount,
            'fontFaceFamilyCount' => count($fontFaceFamilies),
            'fontFaceFamilies' => $fontFaceFamilies,
            'fontFaceItems' => $fontFaceItems,
            'fontFaceDiagnostics' => $fontFaceDiagnostics,
            'fontFaceDiagnosticCount' => count($fontFaceDiagnostics),
            'conditionalRuleCount' => $conditionalRuleCount,
            'mediaRuleCount' => $mediaRuleCount,
            'supportsRuleCount' => $supportsRuleCount,
            'importConditionCount' => $importConditionCount,
            'pageRuleCount' => $pageRuleCount,
            'namedPageRuleCount' => $namedPageRuleCount,
            'pagePseudoClassCount' => $pagePseudoClassCount,
            'pageMarginBoxCount' => $pageMarginBoxCount,
            'conditionalRules' => $conditionalRules,
            'mediaConditions' => $mediaConditions,
            'supportsConditions' => $supportsConditions,
            'importConditions' => $importConditions,
            'pageRules' => $pageRules,
            'pageRuleDiagnostics' => $pageRuleDiagnostics,
            'pageRuleDiagnosticCount' => count($pageRuleDiagnostics),
            'pageRuleNames' => $pageRuleNames,
            'pagePseudoClasses' => $pagePseudoClasses,
            'pageMarginBoxNames' => $pageMarginBoxNames,
            'externalReferenceCount' => count($externalReferences),
            'missingReferenceCount' => count($missingReferences),
            'encryptedReferenceCount' => count($encryptedReferences),
            'reviewRequiredCount' => $reviewRequiredCount,
            'exportPolicy' => self::cssResourceExportPolicySummary($items),
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
     * @return list<array<string, mixed>>
     */
    private function cssContentReferences(
        ZipPackage $package,
        string $part,
        string $css,
        array $manifestByPart
    ): array {
        $references = [];
        foreach (self::cssReferenceTokens($css) as $token) {
            $reference = $this->packageReference(
                $package,
                $part,
                (string) $token['href'],
                $manifestByPart,
                'css-resource'
            );
            $diagnostics = $reference['diagnostics'];
            if (($reference['encrypted'] ?? false) === true) {
                $diagnostics[] = [
                    'type' => 'encrypted-css-resource-reference',
                    'part' => $reference['part'],
                    'message' => 'EPUB CSS references an encrypted package part that cannot be exposed directly',
                ];
            }

            $references[] = [
                'index' => count($references),
                'kind' => (string) $token['kind'],
                'href' => (string) $token['href'],
                'raw' => (string) $token['raw'],
                'target' => $reference['target'],
                'part' => $reference['part'],
                'fragment' => $reference['fragment'],
                'fragmentKind' => $reference['fragmentKind'],
                'epubCfi' => $reference['epubCfi'],
                'mediaFragment' => $reference['mediaFragment'],
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
            foreach (['imageSetCandidateIndex', 'imageSetCandidate', 'imageSetDescriptor', 'imageSetType'] as $tokenKey) {
                if (array_key_exists($tokenKey, $token)) {
                    $references[array_key_last($references)][$tokenKey] = $token[$tokenKey];
                }
            }
            foreach (['importCondition', 'importLayer', 'importLayerAnonymous', 'importSupports', 'importMedia'] as $tokenKey) {
                if (array_key_exists($tokenKey, $token)) {
                    $references[array_key_last($references)][$tokenKey] = $token[$tokenKey];
                }
            }
        }

        return $references;
    }

    /**
     * @param array<string, array<string, mixed>> $manifestByPart
     *
     * @return list<array<string, mixed>>
     */
    private function cssFontFaceReports(
        ZipPackage $package,
        string $part,
        string $css,
        array $manifestByPart
    ): array {
        $fontFaces = [];
        foreach (self::cssFontFaceBlocks($css) as $block) {
            $descriptors = self::cssDeclarationMap($block['body']);
            $family = self::cssUnquotedString($descriptors['font-family'] ?? null);
            $src = is_string($descriptors['src'] ?? null) ? trim($descriptors['src']) : null;
            $sources = [];
            $diagnostics = [];

            if ($family === null || $family === '') {
                $diagnostics[] = [
                    'type' => 'missing-css-font-face-family',
                    'message' => 'EPUB CSS @font-face block is missing font-family metadata',
                ];
            }
            if ($src === null || $src === '') {
                $diagnostics[] = [
                    'type' => 'missing-css-font-face-src',
                    'message' => 'EPUB CSS @font-face block is missing src metadata',
                ];
            }

            foreach (self::cssFontFaceSourceTokens($src ?? '') as $sourceToken) {
                $source = $this->cssFontFaceSourceReport(
                    $package,
                    $part,
                    $sourceToken,
                    $manifestByPart,
                    count($sources)
                );
                foreach ($source['diagnostics'] as $diagnostic) {
                    $diagnostics[] = [
                        'sourceIndex' => $source['index'],
                        'sourceKind' => $source['kind'],
                        'href' => $source['href'],
                    ] + $diagnostic;
                }
                $sources[] = $source;
            }

            if ($src !== null && $src !== '' && $sources === []) {
                $diagnostics[] = [
                    'type' => 'unparsed-css-font-face-src',
                    'src' => $src,
                    'message' => 'EPUB CSS @font-face src metadata did not contain a bounded local() or url() source candidate',
                ];
            }

            $localSources = array_values(array_filter(
                $sources,
                static fn (array $source): bool => ($source['kind'] ?? null) === 'local',
            ));
            $urlSources = array_values(array_filter(
                $sources,
                static fn (array $source): bool => ($source['kind'] ?? null) === 'url',
            ));
            $externalSources = array_values(array_filter(
                $urlSources,
                static fn (array $source): bool => ($source['external'] ?? false) === true,
            ));
            $missingSources = array_values(array_filter(
                $urlSources,
                static fn (array $source): bool => ($source['exists'] ?? true) !== true && ($source['external'] ?? false) !== true,
            ));

            $fontFaces[] = [
                'index' => count($fontFaces),
                'sourcePart' => $part,
                'raw' => $block['raw'],
                'rawSha256' => hash('sha256', $block['raw']),
                'descriptorCount' => count($descriptors),
                'descriptors' => $descriptors,
                'family' => $family,
                'style' => self::cssNullableDescriptor($descriptors, 'font-style'),
                'weight' => self::cssNullableDescriptor($descriptors, 'font-weight'),
                'stretch' => self::cssNullableDescriptor($descriptors, 'font-stretch'),
                'display' => self::cssNullableDescriptor($descriptors, 'font-display'),
                'unicodeRange' => self::cssNullableDescriptor($descriptors, 'unicode-range'),
                'src' => $src,
                'sourceCount' => count($sources),
                'localSourceCount' => count($localSources),
                'urlSourceCount' => count($urlSources),
                'externalSourceCount' => count($externalSources),
                'missingSourceCount' => count($missingSources),
                'sources' => $sources,
                'diagnostics' => $diagnostics,
                'valid' => $diagnostics === [],
            ];
        }

        return $fontFaces;
    }

    /**
     * @param array{kind:string, raw:string, name?:string, href?:string, format?:string|null, descriptor?:string|null} $sourceToken
     * @param array<string, array<string, mixed>> $manifestByPart
     *
     * @return array<string, mixed>
     */
    private function cssFontFaceSourceReport(
        ZipPackage $package,
        string $part,
        array $sourceToken,
        array $manifestByPart,
        int $index
    ): array {
        if (($sourceToken['kind'] ?? null) === 'local') {
            return [
                'index' => $index,
                'kind' => 'local',
                'raw' => (string) ($sourceToken['raw'] ?? ''),
                'name' => (string) ($sourceToken['name'] ?? ''),
                'href' => null,
                'target' => null,
                'part' => null,
                'fragment' => null,
                'fragmentKind' => null,
                'epubCfi' => null,
                'mediaFragment' => null,
                'external' => false,
                'exists' => null,
                'byteLength' => null,
                'crc32' => null,
                'manifestId' => null,
                'mediaType' => null,
                'encrypted' => false,
                'canExposeBytes' => false,
                'format' => null,
                'descriptor' => null,
                'diagnostics' => [],
            ];
        }

        $href = (string) ($sourceToken['href'] ?? '');
        $reference = $this->packageReference($package, $part, $href, $manifestByPart, 'css-font-face-source');
        $diagnostics = self::cssFontFaceReferenceDiagnostics($reference['diagnostics']);
        if (($reference['encrypted'] ?? false) === true) {
            $diagnostics[] = [
                'type' => 'encrypted-css-font-face-source',
                'part' => $reference['part'],
                'message' => 'EPUB CSS @font-face src references an encrypted package font that cannot be exposed directly',
            ];
        }

        return [
            'index' => $index,
            'kind' => 'url',
            'raw' => (string) ($sourceToken['raw'] ?? $href),
            'name' => null,
            'href' => $href,
            'target' => $reference['target'],
            'part' => $reference['part'],
            'fragment' => $reference['fragment'],
            'fragmentKind' => $reference['fragmentKind'],
            'epubCfi' => $reference['epubCfi'],
            'mediaFragment' => $reference['mediaFragment'],
            'external' => $reference['external'],
            'exists' => $reference['exists'],
            'byteLength' => $reference['byteLength'],
            'crc32' => $reference['crc32'],
            'manifestId' => $reference['manifestId'],
            'mediaType' => $reference['mediaType'],
            'encrypted' => $reference['encrypted'],
            'canExposeBytes' => $reference['canExposeBytes'],
            'format' => $sourceToken['format'] ?? null,
            'descriptor' => $sourceToken['descriptor'] ?? null,
            'diagnostics' => $diagnostics,
        ];
    }

    /**
     * @param list<array<string, mixed>> $diagnostics
     *
     * @return list<array<string, mixed>>
     */
    private static function cssFontFaceReferenceDiagnostics(array $diagnostics): array
    {
        foreach ($diagnostics as $index => $diagnostic) {
            if (($diagnostic['type'] ?? null) === 'external-css-font-face-source-reference') {
                $diagnostics[$index]['type'] = 'external-css-font-face-source';
                $diagnostics[$index]['message'] = 'EPUB CSS @font-face src points outside the package and was not fetched';
            } elseif (($diagnostic['type'] ?? null) === 'missing-css-font-face-source-reference') {
                $diagnostics[$index]['type'] = 'missing-css-font-face-source';
                $diagnostics[$index]['message'] = 'EPUB CSS @font-face src target is missing from the package';
            }
        }

        return $diagnostics;
    }

    /**
     * @return list<array{body:string, raw:string, start:int, end:int}>
     */
    private static function cssFontFaceBlocks(string $css): array
    {
        $blocks = [];
        $stripped = self::stripCssComments($css);
        $matchCount = preg_match_all('/@font-face\s*\{/i', $stripped, $matches, \PREG_SET_ORDER | \PREG_OFFSET_CAPTURE);
        if (!is_int($matchCount) || $matchCount === 0) {
            return $blocks;
        }

        foreach ($matches as $match) {
            $rawStart = is_int($match[0][1] ?? null) ? $match[0][1] : null;
            if ($rawStart === null) {
                continue;
            }

            $open = strpos($stripped, '{', $rawStart);
            if ($open === false) {
                continue;
            }
            $close = self::cssMatchingBraceOffset($stripped, $open);
            if ($close === null) {
                continue;
            }

            $blocks[] = [
                'body' => substr($stripped, $open + 1, $close - $open - 1),
                'raw' => substr($stripped, $rawStart, $close - $rawStart + 1),
                'start' => $rawStart,
                'end' => $close + 1,
            ];
        }

        return $blocks;
    }

    private static function cssMatchingBraceOffset(string $css, int $open): ?int
    {
        $length = strlen($css);
        $depth = 0;
        $quote = null;
        $escaped = false;
        for ($offset = $open; $offset < $length; ++$offset) {
            $char = $css[$offset];
            if ($quote !== null) {
                if ($escaped) {
                    $escaped = false;
                    continue;
                }
                if ($char === '\\') {
                    $escaped = true;
                    continue;
                }
                if ($char === $quote) {
                    $quote = null;
                }
                continue;
            }
            if ($char === '"' || $char === "'") {
                $quote = $char;
                continue;
            }
            if ($char === '{') {
                ++$depth;
                continue;
            }
            if ($char === '}') {
                --$depth;
                if ($depth === 0) {
                    return $offset;
                }
            }
        }

        return null;
    }

    /**
     * @return array<string, string>
     */
    private static function cssDeclarationMap(string $body): array
    {
        $declarations = [];
        foreach (self::splitCssTopLevelSemicolonList($body) as $declaration) {
            if (preg_match('/^\s*([A-Za-z-]+)\s*:\s*(.*?)\s*$/s', $declaration, $match) !== 1) {
                continue;
            }

            $name = strtolower((string) $match[1]);
            if (isset($declarations[$name])) {
                continue;
            }
            $value = trim((string) $match[2]);
            if ($value !== '') {
                $declarations[$name] = $value;
            }
        }

        return $declarations;
    }

    /**
     * @return list<string>
     */
    private static function splitCssTopLevelSemicolonList(string $value): array
    {
        $items = [];
        $start = 0;
        $length = strlen($value);
        $depth = 0;
        $quote = null;
        $escaped = false;

        for ($offset = 0; $offset < $length; ++$offset) {
            $char = $value[$offset];
            if ($quote !== null) {
                if ($escaped) {
                    $escaped = false;
                    continue;
                }
                if ($char === '\\') {
                    $escaped = true;
                    continue;
                }
                if ($char === $quote) {
                    $quote = null;
                }
                continue;
            }
            if ($char === '"' || $char === "'") {
                $quote = $char;
                continue;
            }
            if ($char === '(') {
                ++$depth;
                continue;
            }
            if ($char === ')') {
                $depth = max(0, $depth - 1);
                continue;
            }
            if ($char === ';' && $depth === 0) {
                $items[] = substr($value, $start, $offset - $start);
                $start = $offset + 1;
            }
        }

        $items[] = substr($value, $start);

        return $items;
    }

    /**
     * @return list<array{kind:string, raw:string, name?:string, href?:string, format?:string|null, descriptor?:string|null}>
     */
    private static function cssFontFaceSourceTokens(string $src): array
    {
        $tokens = [];
        foreach (self::splitCssTopLevelCommaList($src) as $candidate) {
            $candidate = trim($candidate);
            if ($candidate === '') {
                continue;
            }

            if (preg_match('/^local\(\s*(["\']?)(.*?)\1\s*\)(.*)$/is', $candidate, $match) === 1) {
                $tokens[] = [
                    'kind' => 'local',
                    'raw' => trim((string) $match[0]),
                    'name' => self::cssUnquotedString((string) $match[2]) ?? '',
                    'descriptor' => trim((string) ($match[3] ?? '')) ?: null,
                ];
                continue;
            }

            if (preg_match('/^url\(\s*(["\']?)(.*?)\1\s*\)(.*)$/is', $candidate, $match) === 1) {
                $descriptor = trim((string) ($match[3] ?? ''));
                $format = null;
                if (preg_match('/\bformat\(\s*(["\']?)(.*?)\1\s*\)/i', $descriptor, $formatMatch) === 1) {
                    $format = trim((string) ($formatMatch[2] ?? ''));
                    $format = $format === '' ? null : $format;
                }

                $tokens[] = [
                    'kind' => 'url',
                    'raw' => trim((string) $match[0]),
                    'href' => trim((string) ($match[2] ?? '')),
                    'format' => $format,
                    'descriptor' => $descriptor === '' ? null : $descriptor,
                ];
            }
        }

        return array_values(array_filter(
            $tokens,
            static fn (array $token): bool => ($token['kind'] ?? null) !== 'url' || (string) ($token['href'] ?? '') !== ''
        ));
    }

    /**
     * @param array<string, string> $descriptors
     */
    private static function cssNullableDescriptor(array $descriptors, string $name): ?string
    {
        $value = trim((string) ($descriptors[$name] ?? ''));

        return $value === '' ? null : $value;
    }

    private static function cssUnquotedString(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);
        if ($value === '') {
            return null;
        }

        $quote = $value[0] ?? '';
        if (($quote === '"' || $quote === "'") && str_ends_with($value, $quote) && strlen($value) >= 2) {
            $value = substr($value, 1, -1);
        }

        $value = preg_replace('/\\\\(.)/s', '$1', $value);

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function cssImportRuleTokens(string $css): array
    {
        $tokens = [];
        $matchCount = preg_match_all('/@import\b/i', $css, $matches, \PREG_SET_ORDER | \PREG_OFFSET_CAPTURE);
        if (!is_int($matchCount) || $matchCount === 0) {
            return [];
        }

        foreach ($matches as $match) {
            $start = is_int($match[0][1] ?? null) ? $match[0][1] : null;
            if ($start === null) {
                continue;
            }

            $end = self::cssTopLevelStatementEnd($css, $start);
            $raw = trim(substr($css, $start, $end - $start));
            if ($raw === '') {
                continue;
            }

            $body = trim(substr($raw, strlen('@import')));
            if (str_ends_with($body, ';')) {
                $body = trim(substr($body, 0, -1));
            }

            $href = null;
            $condition = '';
            if (preg_match('/^url\(\s*(["\']?)(.*?)\1\s*\)\s*(.*)$/is', $body, $urlMatch) === 1) {
                $href = trim((string) ($urlMatch[2] ?? ''));
                $condition = trim((string) ($urlMatch[3] ?? ''));
            } elseif (preg_match('/^(["\'])(.*?)\1\s*(.*)$/s', $body, $stringMatch) === 1) {
                $href = trim((string) ($stringMatch[2] ?? ''));
                $condition = trim((string) ($stringMatch[3] ?? ''));
            }

            if ($href === null || $href === '') {
                continue;
            }

            $conditionFields = self::cssImportConditionFields($condition);
            $tokens[] = [
                'kind' => 'import',
                'href' => $href,
                'raw' => $raw,
                '_offset' => $start,
            ] + $conditionFields;
        }

        return $tokens;
    }

    private static function cssTopLevelStatementEnd(string $css, int $start): int
    {
        $length = strlen($css);
        $quote = null;
        $escaped = false;
        $depth = 0;

        for ($offset = $start; $offset < $length; ++$offset) {
            $char = $css[$offset];
            if ($quote !== null) {
                if ($escaped) {
                    $escaped = false;
                    continue;
                }
                if ($char === '\\') {
                    $escaped = true;
                    continue;
                }
                if ($char === $quote) {
                    $quote = null;
                }
                continue;
            }

            if ($char === '"' || $char === "'") {
                $quote = $char;
                continue;
            }
            if ($char === '(') {
                ++$depth;
                continue;
            }
            if ($char === ')') {
                $depth = max(0, $depth - 1);
                continue;
            }
            if ($char === ';' && $depth === 0) {
                return $offset + 1;
            }
            if ($char === '{' && $depth === 0) {
                return $offset;
            }
        }

        return $length;
    }

    /**
     * @return array{importCondition:?string, importLayer:?string, importLayerAnonymous:bool, importSupports:?string, importMedia:?string}
     */
    private static function cssImportConditionFields(string $condition): array
    {
        $remaining = trim($condition);
        $layer = null;
        $layerAnonymous = false;
        $supports = null;
        $media = null;

        if ($remaining !== '' && preg_match('/^layer\b/i', $remaining) === 1) {
            $afterLayer = trim(substr($remaining, strlen('layer')));
            if (($afterLayer[0] ?? '') === '(') {
                $close = self::cssMatchingParenOffset($afterLayer, 0);
                if ($close !== null) {
                    $layer = trim(substr($afterLayer, 1, $close - 1));
                    $remaining = trim(substr($afterLayer, $close + 1));
                } else {
                    $remaining = $afterLayer;
                }
            } else {
                $layerAnonymous = true;
                $layer = '';
                $remaining = $afterLayer;
            }
        }

        if ($remaining !== '' && preg_match('/^supports\s*\(/i', $remaining, $supportsMatch) === 1) {
            $open = strpos($remaining, '(');
            if ($open !== false) {
                $close = self::cssMatchingParenOffset($remaining, $open);
                if ($close !== null) {
                    $supports = trim(substr($remaining, $open + 1, $close - $open - 1));
                    $remaining = trim(substr($remaining, $close + 1));
                }
            }
        }

        if ($remaining !== '') {
            $media = $remaining;
        }

        return [
            'importCondition' => $condition === '' ? null : $condition,
            'importLayer' => $layer,
            'importLayerAnonymous' => $layerAnonymous,
            'importSupports' => $supports,
            'importMedia' => $media,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function cssReferenceTokens(string $css): array
    {
        $tokens = [];
        $stripped = self::stripCssComments($css);
        $urlSkipSpans = [];
        $sequence = 0;
        foreach (self::cssImportRuleTokens($stripped) as $importToken) {
            $start = is_int($importToken['_offset'] ?? null) ? $importToken['_offset'] : 0;
            $raw = (string) ($importToken['raw'] ?? '');
            $urlSkipSpans[] = [$start, $start + strlen($raw)];
            $importToken['_sequence'] = $sequence++;
            $tokens[] = $importToken;
        }

        foreach (self::cssImageSetFunctions($stripped) as $imageSet) {
            $urlSkipSpans[] = [$imageSet['start'], $imageSet['end']];
            foreach (self::cssImageSetCandidateTokens($imageSet['body']) as $candidate) {
                $tokens[] = [
                    'kind' => 'image-set',
                    'href' => $candidate['href'],
                    'raw' => $candidate['raw'],
                    'imageSetCandidateIndex' => $candidate['index'],
                    'imageSetCandidate' => $candidate['candidate'],
                    'imageSetDescriptor' => $candidate['descriptor'],
                    'imageSetType' => $candidate['type'],
                    '_offset' => $imageSet['start'],
                    '_sequence' => $sequence++,
                ];
            }
        }

        $urlMatchCount = preg_match_all('/url\(\s*(["\']?)(.*?)\1\s*\)/is', $stripped, $urls, \PREG_SET_ORDER | \PREG_OFFSET_CAPTURE);
        if (is_int($urlMatchCount) && $urlMatchCount > 0) {
            foreach ($urls as $match) {
                $start = is_int($match[0][1] ?? null) ? $match[0][1] : null;
                if ($start !== null) {
                    foreach ($urlSkipSpans as [$importStart, $importEnd]) {
                        if ($start >= $importStart && $start < $importEnd) {
                            continue 2;
                        }
                    }
                }

                $href = trim((string) ($match[2][0] ?? ''));
                if ($href === '' || preg_match('/^data:/i', $href) === 1) {
                    continue;
                }

                $tokens[] = [
                    'kind' => 'url',
                    'href' => $href,
                    'raw' => (string) ($match[0][0] ?? $href),
                    '_offset' => $start ?? 0,
                    '_sequence' => $sequence++,
                ];
            }
        }

        usort(
            $tokens,
            static fn (array $left, array $right): int => (($left['_offset'] ?? 0) <=> ($right['_offset'] ?? 0))
                ?: (($left['_sequence'] ?? 0) <=> ($right['_sequence'] ?? 0))
        );
        foreach ($tokens as $index => $token) {
            unset($token['_offset'], $token['_sequence']);
            $tokens[$index] = $token;
        }

        return $tokens;
    }

    /**
     * @return list<array{body:string, raw:string, start:int, end:int}>
     */
    private static function cssImageSetFunctions(string $css): array
    {
        $functions = [];
        $matchCount = preg_match_all(
            '/(?:^|[^A-Za-z0-9_-])((?:-webkit-)?image-set)\s*\(/i',
            $css,
            $matches,
            \PREG_SET_ORDER | \PREG_OFFSET_CAPTURE
        );
        if (!is_int($matchCount) || $matchCount === 0) {
            return $functions;
        }

        foreach ($matches as $match) {
            $functionName = (string) ($match[1][0] ?? '');
            $functionStart = is_int($match[1][1] ?? null) ? $match[1][1] : null;
            if ($functionName === '' || $functionStart === null) {
                continue;
            }

            $open = strpos($css, '(', $functionStart + strlen($functionName));
            if ($open === false) {
                continue;
            }

            $close = self::cssMatchingParenOffset($css, $open);
            if ($close === null) {
                continue;
            }

            $functions[] = [
                'body' => substr($css, $open + 1, $close - $open - 1),
                'raw' => substr($css, $functionStart, $close - $functionStart + 1),
                'start' => $functionStart,
                'end' => $close + 1,
            ];
        }

        return $functions;
    }

    private static function cssMatchingParenOffset(string $css, int $open): ?int
    {
        $length = strlen($css);
        $depth = 0;
        $quote = null;
        $escaped = false;
        for ($offset = $open; $offset < $length; ++$offset) {
            $char = $css[$offset];
            if ($quote !== null) {
                if ($escaped) {
                    $escaped = false;
                    continue;
                }
                if ($char === '\\') {
                    $escaped = true;
                    continue;
                }
                if ($char === $quote) {
                    $quote = null;
                }
                continue;
            }

            if ($char === '"' || $char === "'") {
                $quote = $char;
                continue;
            }
            if ($char === '(') {
                ++$depth;
                continue;
            }
            if ($char === ')') {
                --$depth;
                if ($depth === 0) {
                    return $offset;
                }
            }
        }

        return null;
    }

    /**
     * @return list<array{index:int, href:string, raw:string, candidate:string, descriptor:?string, type:?string}>
     */
    private static function cssImageSetCandidateTokens(string $body): array
    {
        $tokens = [];
        foreach (self::splitCssTopLevelCommaList($body) as $index => $candidate) {
            $candidate = trim($candidate);
            if ($candidate === '') {
                continue;
            }

            $parsed = self::cssImageSetReferenceCandidate($candidate);
            if ($parsed === null || preg_match('/^data:/i', $parsed['href']) === 1) {
                continue;
            }

            $descriptor = trim($parsed['descriptor']);
            $type = null;
            if (preg_match('/\btype\(\s*(["\']?)(.*?)\1\s*\)/i', $descriptor, $typeMatch) === 1) {
                $type = trim((string) ($typeMatch[2] ?? ''));
                $type = $type === '' ? null : $type;
            }

            $tokens[] = [
                'index' => $index,
                'href' => $parsed['href'],
                'raw' => $parsed['raw'],
                'candidate' => $candidate,
                'descriptor' => $descriptor === '' ? null : $descriptor,
                'type' => $type,
            ];
        }

        return $tokens;
    }

    /**
     * @return list<string>
     */
    private static function splitCssTopLevelCommaList(string $value): array
    {
        $items = [];
        $start = 0;
        $length = strlen($value);
        $depth = 0;
        $quote = null;
        $escaped = false;

        for ($offset = 0; $offset < $length; ++$offset) {
            $char = $value[$offset];
            if ($quote !== null) {
                if ($escaped) {
                    $escaped = false;
                    continue;
                }
                if ($char === '\\') {
                    $escaped = true;
                    continue;
                }
                if ($char === $quote) {
                    $quote = null;
                }
                continue;
            }

            if ($char === '"' || $char === "'") {
                $quote = $char;
                continue;
            }
            if ($char === '(') {
                ++$depth;
                continue;
            }
            if ($char === ')') {
                $depth = max(0, $depth - 1);
                continue;
            }
            if ($char === ',' && $depth === 0) {
                $items[] = substr($value, $start, $offset - $start);
                $start = $offset + 1;
            }
        }

        $items[] = substr($value, $start);

        return $items;
    }

    /**
     * @return array{href:string, raw:string, descriptor:string}|null
     */
    private static function cssImageSetReferenceCandidate(string $candidate): ?array
    {
        if (preg_match('/^url\(\s*(["\']?)(.*?)\1\s*\)(.*)$/is', $candidate, $match) === 1) {
            $href = trim((string) ($match[2] ?? ''));
            if ($href === '') {
                return null;
            }

            return [
                'href' => $href,
                'raw' => trim((string) ($match[0] ?? $href)),
                'descriptor' => trim((string) ($match[3] ?? '')),
            ];
        }

        $first = $candidate[0] ?? '';
        if ($first !== '"' && $first !== "'") {
            return null;
        }

        $length = strlen($candidate);
        $escaped = false;
        for ($offset = 1; $offset < $length; ++$offset) {
            $char = $candidate[$offset];
            if ($escaped) {
                $escaped = false;
                continue;
            }
            if ($char === '\\') {
                $escaped = true;
                continue;
            }
            if ($char !== $first) {
                continue;
            }

            $href = trim(substr($candidate, 1, $offset - 1));
            if ($href === '') {
                return null;
            }

            return [
                'href' => $href,
                'raw' => substr($candidate, 0, $offset + 1),
                'descriptor' => trim(substr($candidate, $offset + 1)),
            ];
        }

        return null;
    }

    private static function stripCssComments(string $css): string
    {
        $stripped = preg_replace('/\/\*.*?\*\//s', '', $css);

        return is_string($stripped) ? $stripped : $css;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function cssConditionalAtRuleReports(string $css): array
    {
        $rules = [];
        $stripped = self::stripCssComments($css);
        $matchCount = preg_match_all('/@(media|supports)\s+([^{};]+)\{/i', $stripped, $matches, \PREG_SET_ORDER | \PREG_OFFSET_CAPTURE);
        if (!is_int($matchCount) || $matchCount === 0) {
            return [];
        }

        foreach ($matches as $match) {
            $kind = strtolower((string) ($match[1][0] ?? ''));
            $condition = trim((string) ($match[2][0] ?? ''));
            $start = is_int($match[0][1] ?? null) ? $match[0][1] : null;
            if (($kind !== 'media' && $kind !== 'supports') || $condition === '' || $start === null) {
                continue;
            }

            $open = strpos($stripped, '{', $start);
            if ($open === false) {
                continue;
            }
            $close = self::cssMatchingBraceOffset($stripped, $open);
            if ($close === null) {
                continue;
            }

            $body = substr($stripped, $open + 1, $close - $open - 1);
            $nestedReferences = self::cssReferenceTokens($body);
            $conditionItems = $kind === 'media'
                ? array_values(array_filter(
                    array_map(
                        static fn (string $item): string => trim($item),
                        self::splitCssTopLevelCommaList($condition),
                    ),
                    static fn (string $item): bool => $item !== '',
                ))
                : [$condition];

            $rules[] = [
                'index' => count($rules),
                'kind' => $kind,
                'condition' => $condition,
                'conditionItems' => $conditionItems,
                'conditionItemCount' => count($conditionItems),
                'raw' => substr($stripped, $start, $close - $start + 1),
                'rawSha256' => hash('sha256', substr($stripped, $start, $close - $start + 1)),
                'bodySha256' => hash('sha256', $body),
                'nestedReferenceCount' => count($nestedReferences),
                'nestedReferences' => $nestedReferences,
            ];
        }

        return $rules;
    }

    /**
     * @param list<array<string, mixed>> $rules
     *
     * @return list<string>
     */
    private static function cssConditionalRuleConditions(array $rules): array
    {
        $conditions = [];
        foreach ($rules as $rule) {
            foreach (is_array($rule['conditionItems'] ?? null) ? $rule['conditionItems'] : [] as $condition) {
                if (is_string($condition) && $condition !== '' && !in_array($condition, $conditions, true)) {
                    $conditions[] = $condition;
                }
            }
        }

        return $conditions;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function cssPageAtRuleReports(string $css): array
    {
        $rules = [];
        $stripped = self::stripCssComments($css);
        $matchCount = preg_match_all('/@page\b([^{}]*)\{/i', $stripped, $matches, \PREG_SET_ORDER | \PREG_OFFSET_CAPTURE);
        if (!is_int($matchCount) || $matchCount === 0) {
            return [];
        }

        foreach ($matches as $match) {
            $selector = trim((string) ($match[1][0] ?? ''));
            $start = is_int($match[0][1] ?? null) ? $match[0][1] : null;
            if ($start === null) {
                continue;
            }

            $open = strpos($stripped, '{', $start);
            if ($open === false) {
                continue;
            }
            $close = self::cssMatchingBraceOffset($stripped, $open);
            if ($close === null) {
                continue;
            }

            $body = substr($stripped, $open + 1, $close - $open - 1);
            $raw = substr($stripped, $start, $close - $start + 1);
            $selectorReport = self::cssPageSelectorReport($selector);
            $marginBoxes = self::cssPageMarginBoxReports($body);
            $descriptors = self::cssDeclarationMap(self::cssWithoutNestedAtRules($body));

            $rules[] = [
                'index' => count($rules),
                'kind' => 'page',
                'selector' => $selector,
                'name' => $selectorReport['name'],
                'pseudoClasses' => $selectorReport['pseudoClasses'],
                'pseudoClassCount' => count($selectorReport['pseudoClasses']),
                'descriptors' => $descriptors,
                'descriptorCount' => count($descriptors),
                'size' => self::cssNullableDescriptor($descriptors, 'size'),
                'margin' => self::cssNullableDescriptor($descriptors, 'margin'),
                'bleed' => self::cssNullableDescriptor($descriptors, 'bleed'),
                'marks' => self::cssNullableDescriptor($descriptors, 'marks'),
                'marginBoxCount' => count($marginBoxes),
                'marginBoxes' => $marginBoxes,
                'marginBoxNames' => self::cssPageMarginBoxNamesFromBoxes($marginBoxes),
                'raw' => $raw,
                'rawSha256' => hash('sha256', $raw),
                'bodySha256' => hash('sha256', $body),
                'diagnostics' => $selectorReport['diagnostics'],
            ];
        }

        return $rules;
    }

    /**
     * @return array{name:?string, pseudoClasses:list<string>, diagnostics:list<array<string, mixed>>}
     */
    private static function cssPageSelectorReport(string $selector): array
    {
        $name = null;
        $pseudoClasses = [];
        $diagnostics = [];

        if ($selector !== '') {
            $parts = explode(':', $selector);
            $namePart = trim((string) array_shift($parts));
            if ($namePart !== '') {
                $name = $namePart;
                if (preg_match('/^-?[A-Za-z_][A-Za-z0-9_-]*$/', $name) !== 1) {
                    $diagnostics[] = [
                        'type' => 'invalid-css-page-name',
                        'selector' => $selector,
                        'name' => $name,
                        'message' => 'EPUB CSS @page selector name is preserved but is not a bounded CSS identifier',
                    ];
                }
            }

            foreach ($parts as $part) {
                $pseudoClass = trim((string) $part);
                if ($pseudoClass === '') {
                    continue;
                }
                $pseudoClasses[] = strtolower($pseudoClass);
                if (preg_match('/^[A-Za-z-]+$/', $pseudoClass) !== 1) {
                    $diagnostics[] = [
                        'type' => 'invalid-css-page-pseudo-class',
                        'selector' => $selector,
                        'pseudoClass' => $pseudoClass,
                        'message' => 'EPUB CSS @page pseudo-class is preserved but is not a bounded page pseudo-class token',
                    ];
                }
            }
        }

        return [
            'name' => $name,
            'pseudoClasses' => $pseudoClasses,
            'diagnostics' => $diagnostics,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function cssPageMarginBoxReports(string $body): array
    {
        $boxes = [];
        $matchCount = preg_match_all('/@([A-Za-z][A-Za-z-]*)\s*\{/i', $body, $matches, \PREG_SET_ORDER | \PREG_OFFSET_CAPTURE);
        if (!is_int($matchCount) || $matchCount === 0) {
            return [];
        }

        foreach ($matches as $match) {
            $name = strtolower((string) ($match[1][0] ?? ''));
            $start = is_int($match[0][1] ?? null) ? $match[0][1] : null;
            if ($name === '' || $start === null) {
                continue;
            }

            $open = strpos($body, '{', $start);
            if ($open === false) {
                continue;
            }
            $close = self::cssMatchingBraceOffset($body, $open);
            if ($close === null) {
                continue;
            }

            $boxBody = substr($body, $open + 1, $close - $open - 1);
            $raw = substr($body, $start, $close - $start + 1);
            $descriptors = self::cssDeclarationMap($boxBody);
            $boxes[] = [
                'index' => count($boxes),
                'name' => $name,
                'raw' => $raw,
                'rawSha256' => hash('sha256', $raw),
                'bodySha256' => hash('sha256', $boxBody),
                'descriptors' => $descriptors,
                'descriptorCount' => count($descriptors),
                'content' => self::cssNullableDescriptor($descriptors, 'content'),
            ];
        }

        return $boxes;
    }

    private static function cssWithoutNestedAtRules(string $body): string
    {
        $result = $body;
        $ranges = [];
        $matchCount = preg_match_all('/@[A-Za-z][A-Za-z-]*\s*\{/i', $body, $matches, \PREG_SET_ORDER | \PREG_OFFSET_CAPTURE);
        if (!is_int($matchCount) || $matchCount === 0) {
            return $body;
        }

        foreach ($matches as $match) {
            $start = is_int($match[0][1] ?? null) ? $match[0][1] : null;
            if ($start === null) {
                continue;
            }
            $open = strpos($body, '{', $start);
            if ($open === false) {
                continue;
            }
            $close = self::cssMatchingBraceOffset($body, $open);
            if ($close === null) {
                continue;
            }
            $ranges[] = [$start, $close + 1];
        }

        for ($index = count($ranges) - 1; $index >= 0; --$index) {
            [$start, $end] = $ranges[$index];
            $result = substr_replace($result, str_repeat(' ', $end - $start), $start, $end - $start);
        }

        return $result;
    }

    /**
     * @param list<array<string, mixed>> $rules
     *
     * @return list<string>
     */
    private static function cssPageRuleNames(array $rules): array
    {
        $names = [];
        foreach ($rules as $rule) {
            $name = is_string($rule['name'] ?? null) ? $rule['name'] : '';
            if ($name !== '' && !in_array($name, $names, true)) {
                $names[] = $name;
            }
        }

        return $names;
    }

    /**
     * @param list<array<string, mixed>> $rules
     *
     * @return list<string>
     */
    private static function cssPageRulePseudoClasses(array $rules): array
    {
        $pseudoClasses = [];
        foreach ($rules as $rule) {
            foreach (is_array($rule['pseudoClasses'] ?? null) ? $rule['pseudoClasses'] : [] as $pseudoClass) {
                if (is_string($pseudoClass) && $pseudoClass !== '' && !in_array($pseudoClass, $pseudoClasses, true)) {
                    $pseudoClasses[] = $pseudoClass;
                }
            }
        }

        return $pseudoClasses;
    }

    /**
     * @param list<array<string, mixed>> $rules
     *
     * @return list<string>
     */
    private static function cssPageRuleMarginBoxNames(array $rules): array
    {
        $names = [];
        foreach ($rules as $rule) {
            self::appendUniqueStrings(
                $names,
                self::cssPageMarginBoxNamesFromBoxes(is_array($rule['marginBoxes'] ?? null) ? $rule['marginBoxes'] : []),
            );
        }

        return $names;
    }

    /**
     * @param list<array<string, mixed>> $boxes
     *
     * @return list<string>
     */
    private static function cssPageMarginBoxNamesFromBoxes(array $boxes): array
    {
        $names = [];
        foreach ($boxes as $box) {
            $name = is_string($box['name'] ?? null) ? $box['name'] : '';
            if ($name !== '' && !in_array($name, $names, true)) {
                $names[] = $name;
            }
        }

        return $names;
    }

    /**
     * @param list<string> $target
     * @param list<string> $values
     */
    private static function appendUniqueStrings(array &$target, array $values): void
    {
        foreach ($values as $value) {
            if ($value !== '' && !in_array($value, $target, true)) {
                $target[] = $value;
            }
        }
    }

    /**
     * @return list<string>
     */
    private static function stringList(mixed $values): array
    {
        if (!is_array($values)) {
            return [];
        }

        return array_values(array_filter($values, static fn (mixed $value): bool => is_string($value) && $value !== ''));
    }

    /**
     * @param list<array<string, mixed>> $references
     * @param list<array<string, mixed>> $conditionalRules
     * @param list<array<string, mixed>> $pageRules
     *
     * @return list<string>
     */
    private static function cssResourceReviewFlags(array $references, array $conditionalRules = [], array $pageRules = []): array
    {
        $flags = [];
        $hasConditionalStyles = $conditionalRules !== [];
        foreach ($references as $reference) {
            if (($reference['external'] ?? false) === true) {
                $flags['remote-resources'] = true;
            }
            if (($reference['exists'] ?? true) !== true && ($reference['external'] ?? false) !== true) {
                $flags['missing-references'] = true;
            }
            if (($reference['encrypted'] ?? false) === true) {
                $flags['encrypted-references'] = true;
            }
            if (($reference['kind'] ?? null) === 'import' && is_string($reference['importCondition'] ?? null)) {
                $hasConditionalStyles = true;
            }
        }
        if ($hasConditionalStyles) {
            $flags['conditional-styles'] = true;
        }
        if ($pageRules !== []) {
            $flags['paged-media'] = true;
            foreach ($pageRules as $pageRule) {
                if (is_array($pageRule['diagnostics'] ?? null) && $pageRule['diagnostics'] !== []) {
                    $flags['page-rule-diagnostics'] = true;
                    break;
                }
            }
        }

        return array_keys($flags);
    }

    /**
     * @param array<string, mixed> $item
     *
     * @return array<string, mixed>
     */
    private static function cssResourceExportPolicy(array $item): array
    {
        $references = is_array($item['references'] ?? null) ? array_values($item['references']) : [];
        $diagnostics = is_array($item['diagnostics'] ?? null) ? array_values($item['diagnostics']) : [];
        $reviewFlags = is_array($item['reviewFlags'] ?? null)
            ? array_values(array_filter($item['reviewFlags'], static fn (mixed $flag): bool => is_string($flag) && $flag !== ''))
            : [];

        $externalReferenceCount = 0;
        $missingReferenceCount = 0;
        $encryptedReferenceCount = 0;
        foreach ($references as $reference) {
            if (!is_array($reference)) {
                continue;
            }
            if (($reference['external'] ?? false) === true) {
                ++$externalReferenceCount;
            }
            if (($reference['exists'] ?? true) !== true && ($reference['external'] ?? false) !== true) {
                ++$missingReferenceCount;
            }
            if (($reference['encrypted'] ?? false) === true) {
                ++$encryptedReferenceCount;
            }
        }

        $blockingReasons = [];
        if ($missingReferenceCount > 0) {
            $blockingReasons[] = 'missing-references';
        }
        if ($encryptedReferenceCount > 0) {
            $blockingReasons[] = 'encrypted-references';
        }
        foreach ($diagnostics as $diagnostic) {
            if (is_array($diagnostic) && ($diagnostic['type'] ?? null) === 'css-resource-bytes-unavailable') {
                $blockingReasons[] = 'bytes-unavailable';
                break;
            }
        }
        $blockingReasons = array_values(array_unique($blockingReasons));

        $conditionalRuleCount = (int) ($item['conditionalRuleCount'] ?? 0);
        $importConditionCount = (int) ($item['importConditionCount'] ?? 0);
        $pageRuleCount = (int) ($item['pageRuleCount'] ?? 0);

        $reviewReasons = [];
        if ($externalReferenceCount > 0 || in_array('remote-resources', $reviewFlags, true)) {
            $reviewReasons[] = 'remote-resources';
        }
        if ($conditionalRuleCount > 0 || $importConditionCount > 0 || in_array('conditional-styles', $reviewFlags, true)) {
            $reviewReasons[] = 'conditional-styles';
        }
        if ($pageRuleCount > 0 || in_array('paged-media', $reviewFlags, true)) {
            $reviewReasons[] = 'paged-media';
        }
        foreach ($reviewFlags as $flag) {
            if (
                !in_array($flag, ['remote-resources', 'missing-references', 'encrypted-references', 'conditional-styles', 'paged-media'], true)
                && !in_array($flag, $reviewReasons, true)
            ) {
                $reviewReasons[] = $flag;
            }
        }

        $status = 'exportable';
        if ($blockingReasons !== []) {
            $status = 'blocked';
        } elseif ($reviewReasons !== []) {
            $status = 'review-required';
        }

        return [
            'status' => $status,
            'canExport' => $status !== 'blocked',
            'requiresReview' => $status !== 'exportable',
            'reviewReasons' => $reviewReasons,
            'blockingReasons' => $blockingReasons,
            'reasons' => array_values(array_unique(array_merge($reviewReasons, $blockingReasons))),
            'manifestId' => (string) ($item['id'] ?? ''),
            'href' => (string) ($item['href'] ?? ''),
            'part' => (string) ($item['part'] ?? ''),
            'referenceCount' => (int) ($item['referenceCount'] ?? count($references)),
            'externalReferenceCount' => $externalReferenceCount,
            'missingReferenceCount' => $missingReferenceCount,
            'encryptedReferenceCount' => $encryptedReferenceCount,
            'conditionalRuleCount' => $conditionalRuleCount,
            'importConditionCount' => $importConditionCount,
            'pageRuleCount' => $pageRuleCount,
            'fontFaceCount' => (int) ($item['fontFaceCount'] ?? 0),
            'diagnosticCount' => count($diagnostics),
        ];
    }

    /**
     * @param list<array<string, mixed>> $items
     *
     * @return array<string, mixed>
     */
    private static function cssResourceExportPolicySummary(array $items): array
    {
        $policyItems = [];
        $itemsByPart = [];
        $statusCounts = [
            'exportable' => 0,
            'review-required' => 0,
            'blocked' => 0,
        ];
        $statuses = [];
        $reviewReasons = [];
        $blockingReasons = [];
        $reasons = [];

        foreach ($items as $item) {
            $policy = is_array($item['exportPolicy'] ?? null)
                ? $item['exportPolicy']
                : self::cssResourceExportPolicy($item);
            $status = is_string($policy['status'] ?? null) ? $policy['status'] : 'exportable';
            if (!array_key_exists($status, $statusCounts)) {
                $statusCounts[$status] = 0;
            }
            ++$statusCounts[$status];
            if (!in_array($status, $statuses, true)) {
                $statuses[] = $status;
            }

            self::appendUniqueStrings($reviewReasons, self::stringList($policy['reviewReasons'] ?? []));
            self::appendUniqueStrings($blockingReasons, self::stringList($policy['blockingReasons'] ?? []));
            self::appendUniqueStrings($reasons, self::stringList($policy['reasons'] ?? []));

            $policyItems[] = $policy;
            if (is_string($policy['part'] ?? null) && $policy['part'] !== '') {
                $itemsByPart[$policy['part']] = $policy;
            }
        }

        return [
            'present' => $items !== [],
            'assetCount' => count($items),
            'exportableAssetCount' => $statusCounts['exportable'] ?? 0,
            'reviewRequiredAssetCount' => $statusCounts['review-required'] ?? 0,
            'blockedAssetCount' => $statusCounts['blocked'] ?? 0,
            'statusCounts' => $statusCounts,
            'canExportAll' => ($statusCounts['blocked'] ?? 0) === 0,
            'requiresReview' => ($statusCounts['review-required'] ?? 0) > 0 || ($statusCounts['blocked'] ?? 0) > 0,
            'statuses' => $statuses,
            'reviewReasons' => $reviewReasons,
            'blockingReasons' => $blockingReasons,
            'reasons' => $reasons,
            'items' => $policyItems,
            'itemsByPart' => $itemsByPart,
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
     * @param list<array<string, mixed>> $references
     *
     * @return list<array<string, mixed>>
     */
    private static function xhtmlEmbeddedResourceReferences(array $references): array
    {
        $items = [];
        foreach ($references as $reference) {
            $kind = self::xhtmlEmbeddedResourceKind($reference);
            if ($kind === null) {
                continue;
            }

            $diagnostics = is_array($reference['diagnostics'] ?? null)
                ? array_values($reference['diagnostics'])
                : [];
            $authoring = is_array($reference['embeddedAuthoring'] ?? null)
                ? $reference['embeddedAuthoring']
                : null;
            $authoringDiagnostics = is_array($authoring['diagnostics'] ?? null)
                ? array_values($authoring['diagnostics'])
                : [];
            array_push($diagnostics, ...$authoringDiagnostics);
            $external = ($reference['external'] ?? false) === true;
            $missing = ($reference['exists'] ?? true) !== true && !$external;
            $encrypted = ($reference['encrypted'] ?? false) === true;
            $policy = self::xhtmlEmbeddedResourcePolicy($kind);

            $item = [
                'index' => count($items),
                'sourceReferenceIndex' => is_int($reference['index'] ?? null) ? $reference['index'] : null,
                'kind' => $kind,
                'policy' => $policy,
                'requiresReview' => in_array($kind, ['embed', 'iframe', 'object'], true)
                    || $external
                    || $missing
                    || $encrypted
                    || $authoringDiagnostics !== []
                    || $diagnostics !== [],
                'element' => strtolower((string) ($reference['element'] ?? '')),
                'elementId' => is_string($reference['elementId'] ?? null) ? $reference['elementId'] : null,
                'elementClass' => is_string($reference['elementClass'] ?? null) ? $reference['elementClass'] : null,
                'elementClasses' => is_array($reference['elementClasses'] ?? null) ? array_values($reference['elementClasses']) : [],
                'elementAttributes' => is_array($reference['elementAttributes'] ?? null) ? $reference['elementAttributes'] : [],
                'attribute' => (string) ($reference['attribute'] ?? ''),
                'href' => is_string($reference['href'] ?? null) ? $reference['href'] : null,
                'target' => is_string($reference['target'] ?? null) ? $reference['target'] : null,
                'part' => is_string($reference['part'] ?? null) ? $reference['part'] : null,
                'fragment' => is_string($reference['fragment'] ?? null) ? $reference['fragment'] : null,
                'fragmentKind' => is_string($reference['fragmentKind'] ?? null) ? $reference['fragmentKind'] : null,
                'epubCfi' => is_array($reference['epubCfi'] ?? null) ? $reference['epubCfi'] : null,
                'mediaFragment' => is_array($reference['mediaFragment'] ?? null) ? $reference['mediaFragment'] : null,
                'external' => $external,
                'exists' => (bool) ($reference['exists'] ?? false),
                'byteLength' => is_int($reference['byteLength'] ?? null) ? $reference['byteLength'] : null,
                'crc32' => is_string($reference['crc32'] ?? null) ? $reference['crc32'] : null,
                'manifestId' => is_string($reference['manifestId'] ?? null) ? $reference['manifestId'] : null,
                'mediaType' => is_string($reference['mediaType'] ?? null) ? $reference['mediaType'] : null,
                'encrypted' => $encrypted,
                'canExposeBytes' => (bool) ($reference['canExposeBytes'] ?? false),
                'authoring' => $authoring,
                'authoringDiagnostics' => $authoringDiagnostics,
                'diagnostics' => $diagnostics,
            ];

            if (isset($reference['srcsetCandidateIndex'])) {
                $item['srcsetCandidateIndex'] = (int) $reference['srcsetCandidateIndex'];
                $item['srcsetCandidate'] = is_string($reference['srcsetCandidate'] ?? null)
                    ? $reference['srcsetCandidate']
                    : $item['href'];
                $item['srcsetDescriptor'] = is_string($reference['srcsetDescriptor'] ?? null)
                    ? $reference['srcsetDescriptor']
                    : null;
            }

            $items[] = $item;
        }

        return $items;
    }

    /**
     * @param array<string, mixed> $reference
     */
    private static function xhtmlEmbeddedResourceKind(array $reference): ?string
    {
        $element = strtolower((string) ($reference['element'] ?? ''));
        $attribute = strtolower((string) ($reference['attribute'] ?? ''));

        if ($element === 'audio' && $attribute === 'src') {
            return 'audio';
        }
        if ($element === 'video') {
            return $attribute === 'poster' ? 'poster' : ($attribute === 'src' ? 'video' : null);
        }
        if ($element === 'source' && in_array($attribute, ['src', 'srcset'], true)) {
            return 'source';
        }
        if ($element === 'track' && $attribute === 'src') {
            return 'track';
        }
        if ($element === 'object' && $attribute === 'data') {
            return 'object';
        }
        if ($element === 'embed' && $attribute === 'src') {
            return 'embed';
        }
        if ($element === 'iframe' && $attribute === 'src') {
            return 'iframe';
        }

        return null;
    }

    private static function xhtmlEmbeddedResourcePolicy(string $kind): string
    {
        return match ($kind) {
            'audio', 'video' => 'media-playback',
            'poster' => 'media-poster',
            'source' => 'media-source',
            'track' => 'timed-text-track',
            'embed', 'object' => 'interactive-embedded-content',
            'iframe' => 'embedded-frame',
            default => 'embedded-resource',
        };
    }

    /**
     * @param list<array<string, mixed>> $resources
     *
     * @return list<string>
     */
    private static function xhtmlEmbeddedResourceKinds(array $resources): array
    {
        $kinds = [];
        foreach ($resources as $resource) {
            $kind = is_string($resource['kind'] ?? null) ? $resource['kind'] : '';
            if ($kind !== '' && !in_array($kind, $kinds, true)) {
                $kinds[] = $kind;
            }
        }

        return $kinds;
    }

    /**
     * @param list<array<string, mixed>> $resources
     *
     * @return array<string, list<array<string, mixed>>>
     */
    private static function xhtmlEmbeddedResourcesByKind(array $resources): array
    {
        $byKind = [];
        foreach ($resources as $resource) {
            $kind = is_string($resource['kind'] ?? null) ? $resource['kind'] : '';
            if ($kind === '') {
                continue;
            }

            $byKind[$kind] ??= [];
            $byKind[$kind][] = $resource;
        }

        return $byKind;
    }

    /**
     * @param list<array<string, mixed>> $resources
     *
     * @return list<array<string, mixed>>
     */
    private static function xhtmlEmbeddedResourceDiagnostics(array $resources): array
    {
        $diagnostics = [];
        foreach ($resources as $resource) {
            foreach (is_array($resource['diagnostics'] ?? null) ? $resource['diagnostics'] : [] as $diagnostic) {
                if (!is_array($diagnostic)) {
                    continue;
                }

                $diagnostics[] = [
                    'embeddedResourceIndex' => $resource['index'] ?? null,
                    'kind' => $resource['kind'] ?? null,
                    'element' => $resource['element'] ?? null,
                    'attribute' => $resource['attribute'] ?? null,
                    'href' => $resource['href'] ?? null,
                ] + $diagnostic;
            }
        }

        return $diagnostics;
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
        $cfiReferences = [];
        $mediaFragmentReferences = [];
        $diagnostics = [];
        $mathmlAssetCount = 0;
        $svgAssetCount = 0;
        $scriptedAssetCount = 0;
        $switchAssetCount = 0;
        $triggerAssetCount = 0;
        $triggerCount = 0;
        $switchCount = 0;
        $switchCaseCount = 0;
        $switchDefaultCount = 0;
        $validSwitchCount = 0;
        $invalidSwitchCount = 0;
        $tableAssetCount = 0;
        $tableCount = 0;
        $tableRowCount = 0;
        $tableHeaderCellCount = 0;
        $tableCaptionCount = 0;
        $tableHeadSectionCount = 0;
        $tableBodySectionCount = 0;
        $tableFootSectionCount = 0;
        $tableItems = [];
        $tableDiagnostics = [];
        $rubyAssetCount = 0;
        $rubyCount = 0;
        $rubyAnnotationCount = 0;
        $validRubyCount = 0;
        $invalidRubyCount = 0;
        $rubyItems = [];
        $rubyDiagnostics = [];
        $semanticAssetCount = 0;
        $semanticItemCount = 0;
        $semanticItems = [];
        $semanticDiagnostics = [];
        $viewportAssetCount = 0;
        $viewportCount = 0;
        $validViewportCount = 0;
        $invalidViewportCount = 0;
        $viewportItems = [];
        $viewportDiagnostics = [];
        $embeddedResourceAssetCount = 0;
        $embeddedResourceCount = 0;
        $externalEmbeddedResourceCount = 0;
        $missingEmbeddedResourceCount = 0;
        $encryptedEmbeddedResourceCount = 0;
        $embeddedResourceItems = [];
        $embeddedResourceDiagnostics = [];
        $scriptCount = 0;
        $linkAssetCount = 0;
        $linkCount = 0;
        $activeLinkCount = 0;
        $passiveLinkCount = 0;
        $linkReviewRequiredCount = 0;
        $linkItems = [];
        $linkDiagnostics = [];
        $refreshAssetCount = 0;
        $refreshCount = 0;
        $refreshReviewRequiredCount = 0;
        $externalRefreshCount = 0;
        $missingRefreshCount = 0;
        $refreshItems = [];
        $refreshDiagnostics = [];
        $sideEffectAssetCount = 0;
        $sideEffectCount = 0;
        $sideEffectReferenceCount = 0;
        $externalSideEffectReferenceCount = 0;
        $missingSideEffectReferenceCount = 0;
        $encryptedSideEffectReferenceCount = 0;
        $sideEffectReviewRequiredCount = 0;
        $sideEffectItems = [];
        $sideEffectDiagnostics = [];
        $styleAssetCount = 0;
        $styleCount = 0;
        $styleElementCount = 0;
        $styleAttributeCount = 0;
        $styleReferenceCount = 0;
        $externalStyleReferenceCount = 0;
        $missingStyleReferenceCount = 0;
        $encryptedStyleReferenceCount = 0;
        $styleReviewRequiredCount = 0;
        $styleItems = [];
        $styleDiagnostics = [];
        $scriptEventHandlerCount = 0;
        $javascriptReferenceCount = 0;
        $scriptItems = [];
        $scriptEventHandlers = [];
        $javascriptReferences = [];
        $scriptDiagnostics = [];
        $reviewRequiredCount = 0;
        $referenceCount = 0;

        foreach ($xhtmlAssets as $asset) {
            $report = is_array($asset['contentResourceReport'] ?? null) ? $asset['contentResourceReport'] : null;
            if ($report === null) {
                continue;
            }

            $part = (string) ($asset['part'] ?? $report['part'] ?? '');
            $metadata = is_array($report['metadata'] ?? null)
                ? $report['metadata']
                : self::emptyXhtmlContentMetadataReport($part);
            $assetSideEffects = [];
            foreach (is_array($report['sideEffects'] ?? null) ? $report['sideEffects'] : [] as $sideEffect) {
                if (is_array($sideEffect)) {
                    $assetSideEffects[] = $sideEffect;
                }
            }
            $assetSideEffectDiagnostics = [];
            foreach (is_array($report['sideEffectDiagnostics'] ?? null) ? $report['sideEffectDiagnostics'] : [] as $diagnostic) {
                if (is_array($diagnostic)) {
                    $assetSideEffectDiagnostics[] = $diagnostic;
                }
            }
            $assetSideEffectReferenceCount = 0;
            $assetExternalSideEffectReferenceCount = 0;
            $assetMissingSideEffectReferenceCount = 0;
            $assetEncryptedSideEffectReferenceCount = 0;
            $assetSideEffectReviewRequiredCount = 0;
            foreach ($assetSideEffects as $sideEffect) {
                $assetSideEffectReferenceCount += is_int($sideEffect['referenceCount'] ?? null) ? $sideEffect['referenceCount'] : 0;
                $assetExternalSideEffectReferenceCount += is_int($sideEffect['externalReferenceCount'] ?? null) ? $sideEffect['externalReferenceCount'] : 0;
                $assetMissingSideEffectReferenceCount += is_int($sideEffect['missingReferenceCount'] ?? null) ? $sideEffect['missingReferenceCount'] : 0;
                $assetEncryptedSideEffectReferenceCount += is_int($sideEffect['encryptedReferenceCount'] ?? null) ? $sideEffect['encryptedReferenceCount'] : 0;
                if (($sideEffect['requiresReview'] ?? false) === true) {
                    ++$assetSideEffectReviewRequiredCount;
                }
            }
            $assetStyles = [];
            foreach (is_array($report['styles'] ?? null) ? $report['styles'] : [] as $style) {
                if (is_array($style)) {
                    $assetStyles[] = $style;
                }
            }
            $assetStyleDiagnostics = [];
            foreach (is_array($report['styleDiagnostics'] ?? null) ? $report['styleDiagnostics'] : [] as $diagnostic) {
                if (is_array($diagnostic)) {
                    $assetStyleDiagnostics[] = $diagnostic;
                }
            }
            $assetStyleReferenceCount = 0;
            $assetExternalStyleReferenceCount = 0;
            $assetMissingStyleReferenceCount = 0;
            $assetEncryptedStyleReferenceCount = 0;
            $assetStyleReviewRequiredCount = 0;
            foreach ($assetStyles as $style) {
                $assetStyleReferenceCount += is_int($style['referenceCount'] ?? null) ? $style['referenceCount'] : 0;
                $assetExternalStyleReferenceCount += is_int($style['externalReferenceCount'] ?? null) ? $style['externalReferenceCount'] : 0;
                $assetMissingStyleReferenceCount += is_int($style['missingReferenceCount'] ?? null) ? $style['missingReferenceCount'] : 0;
                $assetEncryptedStyleReferenceCount += is_int($style['encryptedReferenceCount'] ?? null) ? $style['encryptedReferenceCount'] : 0;
                if (($style['requiresReview'] ?? false) === true) {
                    ++$assetStyleReviewRequiredCount;
                }
            }
            $assetEmbeddedResources = [];
            foreach (is_array($report['embeddedResources'] ?? null) ? $report['embeddedResources'] : [] as $resource) {
                if (is_array($resource)) {
                    $assetEmbeddedResources[] = $resource;
                }
            }
            $assetEmbeddedResourceDiagnostics = [];
            foreach (is_array($report['embeddedResourceDiagnostics'] ?? null) ? $report['embeddedResourceDiagnostics'] : [] as $diagnostic) {
                if (is_array($diagnostic)) {
                    $assetEmbeddedResourceDiagnostics[] = $diagnostic;
                }
            }
            $assetExternalEmbeddedResourceCount = count(array_filter(
                $assetEmbeddedResources,
                static fn (array $resource): bool => ($resource['external'] ?? false) === true,
            ));
            $assetMissingEmbeddedResourceCount = count(array_filter(
                $assetEmbeddedResources,
                static fn (array $resource): bool => ($resource['exists'] ?? true) !== true
                    && ($resource['external'] ?? false) !== true,
            ));
            $assetEncryptedEmbeddedResourceCount = count(array_filter(
                $assetEmbeddedResources,
                static fn (array $resource): bool => ($resource['encrypted'] ?? false) === true,
            ));
            $item = [
                'id' => (string) ($asset['id'] ?? ''),
                'part' => $part,
                'href' => (string) ($asset['href'] ?? ''),
                'manifestProperties' => is_array($asset['properties'] ?? null) ? array_values($asset['properties']) : [],
                'flags' => is_array($report['flags'] ?? null) ? $report['flags'] : [],
                'reviewFlags' => is_array($report['reviewFlags'] ?? null) ? array_values($report['reviewFlags']) : [],
                'metadata' => $metadata,
                'title' => is_string($metadata['title'] ?? null) ? $metadata['title'] : null,
                'language' => is_string($metadata['language'] ?? null) ? $metadata['language'] : null,
                'direction' => is_string($metadata['direction'] ?? null) ? $metadata['direction'] : null,
                'htmlLanguage' => is_string($metadata['htmlLanguage'] ?? null) ? $metadata['htmlLanguage'] : null,
                'htmlDirection' => is_string($metadata['htmlDirection'] ?? null) ? $metadata['htmlDirection'] : null,
                'bodyLanguage' => is_string($metadata['bodyLanguage'] ?? null) ? $metadata['bodyLanguage'] : null,
                'bodyDirection' => is_string($metadata['bodyDirection'] ?? null) ? $metadata['bodyDirection'] : null,
                'bodyEpubTypes' => is_array($metadata['bodyEpubTypes'] ?? null) ? array_values($metadata['bodyEpubTypes']) : [],
                'viewportCount' => is_int($metadata['viewportCount'] ?? null) ? $metadata['viewportCount'] : 0,
                'validViewportCount' => is_int($metadata['validViewportCount'] ?? null) ? $metadata['validViewportCount'] : 0,
                'invalidViewportCount' => is_int($metadata['invalidViewportCount'] ?? null) ? $metadata['invalidViewportCount'] : 0,
                'viewport' => is_array($metadata['viewport'] ?? null) ? $metadata['viewport'] : self::emptyXhtmlViewportReport(),
                'viewports' => is_array($metadata['viewports'] ?? null) ? array_values($metadata['viewports']) : [],
                'metadataDiagnostics' => is_array($metadata['diagnostics'] ?? null) ? array_values($metadata['diagnostics']) : [],
                'referenceCount' => count(is_array($report['references'] ?? null) ? $report['references'] : []),
                'references' => is_array($report['references'] ?? null) ? array_values($report['references']) : [],
                'embeddedResourceCount' => count($assetEmbeddedResources),
                'embeddedResources' => $assetEmbeddedResources,
                'embeddedResourceKinds' => self::xhtmlEmbeddedResourceKinds($assetEmbeddedResources),
                'embeddedResourcesByKind' => self::xhtmlEmbeddedResourcesByKind($assetEmbeddedResources),
                'externalEmbeddedResourceCount' => $assetExternalEmbeddedResourceCount,
                'missingEmbeddedResourceCount' => $assetMissingEmbeddedResourceCount,
                'encryptedEmbeddedResourceCount' => $assetEncryptedEmbeddedResourceCount,
                'embeddedResourceDiagnosticCount' => count($assetEmbeddedResourceDiagnostics),
                'embeddedResourceDiagnostics' => $assetEmbeddedResourceDiagnostics,
                'linkCount' => count(is_array($report['links'] ?? null) ? $report['links'] : []),
                'links' => is_array($report['links'] ?? null) ? array_values($report['links']) : [],
                'activeLinkCount' => count(array_filter(
                    is_array($report['links'] ?? null) ? $report['links'] : [],
                    static fn (array $link): bool => ($link['active'] ?? false) === true,
                )),
                'passiveLinkCount' => count(array_filter(
                    is_array($report['links'] ?? null) ? $report['links'] : [],
                    static fn (array $link): bool => ($link['active'] ?? false) !== true,
                )),
                'linkReviewRequiredCount' => count(array_filter(
                    is_array($report['links'] ?? null) ? $report['links'] : [],
                    static fn (array $link): bool => ($link['requiresReview'] ?? false) === true,
                )),
                'linkDiagnosticCount' => count(is_array($report['linkDiagnostics'] ?? null) ? $report['linkDiagnostics'] : []),
                'linkDiagnostics' => is_array($report['linkDiagnostics'] ?? null) ? array_values($report['linkDiagnostics']) : [],
                'refreshCount' => count(is_array($report['refreshes'] ?? null) ? $report['refreshes'] : []),
                'refreshes' => is_array($report['refreshes'] ?? null) ? array_values($report['refreshes']) : [],
                'refreshReviewRequiredCount' => count(array_filter(
                    is_array($report['refreshes'] ?? null) ? $report['refreshes'] : [],
                    static fn (array $refresh): bool => ($refresh['requiresReview'] ?? false) === true,
                )),
                'externalRefreshCount' => count(array_filter(
                    is_array($report['refreshes'] ?? null) ? $report['refreshes'] : [],
                    static fn (array $refresh): bool => ($refresh['external'] ?? false) === true,
                )),
                'missingRefreshCount' => count(array_filter(
                    is_array($report['refreshes'] ?? null) ? $report['refreshes'] : [],
                    static fn (array $refresh): bool => ($refresh['exists'] ?? true) !== true
                        && ($refresh['external'] ?? false) !== true
                        && is_string($refresh['url'] ?? null)
                        && $refresh['url'] !== '',
                )),
                'refreshDiagnosticCount' => count(is_array($report['refreshDiagnostics'] ?? null) ? $report['refreshDiagnostics'] : []),
                'refreshDiagnostics' => is_array($report['refreshDiagnostics'] ?? null) ? array_values($report['refreshDiagnostics']) : [],
                'sideEffectCount' => count($assetSideEffects),
                'sideEffects' => $assetSideEffects,
                'sideEffectReferenceCount' => $assetSideEffectReferenceCount,
                'externalSideEffectReferenceCount' => $assetExternalSideEffectReferenceCount,
                'missingSideEffectReferenceCount' => $assetMissingSideEffectReferenceCount,
                'encryptedSideEffectReferenceCount' => $assetEncryptedSideEffectReferenceCount,
                'sideEffectReviewRequiredCount' => $assetSideEffectReviewRequiredCount,
                'sideEffectDiagnosticCount' => count($assetSideEffectDiagnostics),
                'sideEffectDiagnostics' => $assetSideEffectDiagnostics,
                'styleCount' => count($assetStyles),
                'styles' => $assetStyles,
                'styleElementCount' => count(array_filter(
                    $assetStyles,
                    static fn (array $style): bool => ($style['kind'] ?? null) === 'style-element',
                )),
                'styleAttributeCount' => count(array_filter(
                    $assetStyles,
                    static fn (array $style): bool => ($style['kind'] ?? null) === 'style-attribute',
                )),
                'styleReferenceCount' => $assetStyleReferenceCount,
                'externalStyleReferenceCount' => $assetExternalStyleReferenceCount,
                'missingStyleReferenceCount' => $assetMissingStyleReferenceCount,
                'encryptedStyleReferenceCount' => $assetEncryptedStyleReferenceCount,
                'styleReviewRequiredCount' => $assetStyleReviewRequiredCount,
                'styleDiagnosticCount' => count($assetStyleDiagnostics),
                'styleDiagnostics' => $assetStyleDiagnostics,
                'scriptCount' => count(is_array($report['scripts'] ?? null) ? $report['scripts'] : []),
                'scripts' => is_array($report['scripts'] ?? null) ? array_values($report['scripts']) : [],
                'scriptEventHandlerCount' => count(is_array($report['scriptEventHandlers'] ?? null) ? $report['scriptEventHandlers'] : []),
                'scriptEventHandlers' => is_array($report['scriptEventHandlers'] ?? null) ? array_values($report['scriptEventHandlers']) : [],
                'javascriptReferenceCount' => count(is_array($report['javascriptReferences'] ?? null) ? $report['javascriptReferences'] : []),
                'javascriptReferences' => is_array($report['javascriptReferences'] ?? null) ? array_values($report['javascriptReferences']) : [],
                'scriptDiagnosticCount' => count(is_array($report['scriptDiagnostics'] ?? null) ? $report['scriptDiagnostics'] : []),
                'scriptDiagnostics' => is_array($report['scriptDiagnostics'] ?? null) ? array_values($report['scriptDiagnostics']) : [],
                'switchCount' => count(is_array($report['switches'] ?? null) ? $report['switches'] : []),
                'switches' => is_array($report['switches'] ?? null) ? array_values($report['switches']) : [],
                'switchCaseCount' => is_int($report['switchCaseCount'] ?? null) ? $report['switchCaseCount'] : 0,
                'switchDefaultCount' => is_int($report['switchDefaultCount'] ?? null) ? $report['switchDefaultCount'] : 0,
                'validSwitchCount' => is_int($report['validSwitchCount'] ?? null) ? $report['validSwitchCount'] : 0,
                'invalidSwitchCount' => is_int($report['invalidSwitchCount'] ?? null) ? $report['invalidSwitchCount'] : 0,
                'triggerCount' => count(is_array($report['triggers'] ?? null) ? $report['triggers'] : []),
                'triggers' => is_array($report['triggers'] ?? null) ? array_values($report['triggers']) : [],
                'validTriggerCount' => is_int($report['validTriggerCount'] ?? null) ? $report['validTriggerCount'] : 0,
                'invalidTriggerCount' => is_int($report['invalidTriggerCount'] ?? null) ? $report['invalidTriggerCount'] : 0,
                'tableCount' => count(is_array($report['tables'] ?? null) ? $report['tables'] : []),
                'tables' => is_array($report['tables'] ?? null) ? array_values($report['tables']) : [],
                'tableRowCount' => array_sum(array_map(
                    static fn (array $table): int => is_int($table['rowCount'] ?? null) ? $table['rowCount'] : 0,
                    is_array($report['tables'] ?? null) ? $report['tables'] : [],
                )),
                'tableHeaderCellCount' => array_sum(array_map(
                    static fn (array $table): int => is_int($table['headerCellCount'] ?? null) ? $table['headerCellCount'] : 0,
                    is_array($report['tables'] ?? null) ? $report['tables'] : [],
                )),
                'tableCaptionCount' => array_sum(array_map(
                    static fn (array $table): int => is_int($table['captionCount'] ?? null) ? $table['captionCount'] : 0,
                    is_array($report['tables'] ?? null) ? $report['tables'] : [],
                )),
                'tableHeadSectionCount' => array_sum(array_map(
                    static fn (array $table): int => is_int($table['sectionCounts']['thead'] ?? null) ? $table['sectionCounts']['thead'] : 0,
                    is_array($report['tables'] ?? null) ? $report['tables'] : [],
                )),
                'tableBodySectionCount' => array_sum(array_map(
                    static fn (array $table): int => is_int($table['sectionCounts']['tbody'] ?? null) ? $table['sectionCounts']['tbody'] : 0,
                    is_array($report['tables'] ?? null) ? $report['tables'] : [],
                )),
                'tableFootSectionCount' => array_sum(array_map(
                    static fn (array $table): int => is_int($table['sectionCounts']['tfoot'] ?? null) ? $table['sectionCounts']['tfoot'] : 0,
                    is_array($report['tables'] ?? null) ? $report['tables'] : [],
                )),
                'tableDiagnosticCount' => count(is_array($report['tableDiagnostics'] ?? null) ? $report['tableDiagnostics'] : []),
                'tableDiagnostics' => is_array($report['tableDiagnostics'] ?? null) ? array_values($report['tableDiagnostics']) : [],
                'rubyCount' => count(is_array($report['rubies'] ?? null) ? $report['rubies'] : []),
                'rubies' => is_array($report['rubies'] ?? null) ? array_values($report['rubies']) : [],
                'rubyAnnotationCount' => array_sum(array_map(
                    static fn (array $ruby): int => is_int($ruby['annotationCount'] ?? null) ? $ruby['annotationCount'] : 0,
                    is_array($report['rubies'] ?? null) ? $report['rubies'] : [],
                )),
                'validRubyCount' => count(array_filter(
                    is_array($report['rubies'] ?? null) ? $report['rubies'] : [],
                    static fn (array $ruby): bool => ($ruby['valid'] ?? false) === true,
                )),
                'invalidRubyCount' => count(array_filter(
                    is_array($report['rubies'] ?? null) ? $report['rubies'] : [],
                    static fn (array $ruby): bool => ($ruby['valid'] ?? true) !== true,
                )),
                'rubyDiagnosticCount' => count(is_array($report['rubyDiagnostics'] ?? null) ? $report['rubyDiagnostics'] : []),
                'rubyDiagnostics' => is_array($report['rubyDiagnostics'] ?? null) ? array_values($report['rubyDiagnostics']) : [],
                'semanticCount' => count(is_array($report['semantics'] ?? null) ? $report['semantics'] : []),
                'semantics' => is_array($report['semantics'] ?? null) ? array_values($report['semantics']) : [],
                'semanticTypes' => is_array($report['semanticTypes'] ?? null) ? array_values($report['semanticTypes']) : [],
                'semanticItemsByType' => is_array($report['semanticItemsByType'] ?? null) ? $report['semanticItemsByType'] : [],
                'semanticDiagnosticCount' => count(is_array($report['semanticDiagnostics'] ?? null) ? $report['semanticDiagnostics'] : []),
                'semanticDiagnostics' => is_array($report['semanticDiagnostics'] ?? null) ? array_values($report['semanticDiagnostics']) : [],
                'diagnostics' => is_array($report['diagnostics'] ?? null) ? array_values($report['diagnostics']) : [],
            ];

            $referenceCount += $item['referenceCount'];
            $embeddedResourceCount += $item['embeddedResourceCount'];
            $externalEmbeddedResourceCount += $item['externalEmbeddedResourceCount'];
            $missingEmbeddedResourceCount += $item['missingEmbeddedResourceCount'];
            $encryptedEmbeddedResourceCount += $item['encryptedEmbeddedResourceCount'];
            if ($item['embeddedResourceCount'] > 0) {
                ++$embeddedResourceAssetCount;
                array_push($embeddedResourceItems, ...$item['embeddedResources']);
            }
            $linkCount += $item['linkCount'];
            $activeLinkCount += $item['activeLinkCount'];
            $passiveLinkCount += $item['passiveLinkCount'];
            $linkReviewRequiredCount += $item['linkReviewRequiredCount'];
            if ($item['linkCount'] > 0) {
                ++$linkAssetCount;
                array_push($linkItems, ...$item['links']);
            }
            $refreshCount += $item['refreshCount'];
            $refreshReviewRequiredCount += $item['refreshReviewRequiredCount'];
            $externalRefreshCount += $item['externalRefreshCount'];
            $missingRefreshCount += $item['missingRefreshCount'];
            if ($item['refreshCount'] > 0) {
                ++$refreshAssetCount;
                array_push($refreshItems, ...$item['refreshes']);
            }
            $sideEffectCount += $item['sideEffectCount'];
            $sideEffectReferenceCount += $item['sideEffectReferenceCount'];
            $externalSideEffectReferenceCount += $item['externalSideEffectReferenceCount'];
            $missingSideEffectReferenceCount += $item['missingSideEffectReferenceCount'];
            $encryptedSideEffectReferenceCount += $item['encryptedSideEffectReferenceCount'];
            $sideEffectReviewRequiredCount += $item['sideEffectReviewRequiredCount'];
            if ($item['sideEffectCount'] > 0) {
                ++$sideEffectAssetCount;
                array_push($sideEffectItems, ...$item['sideEffects']);
            }
            $styleCount += $item['styleCount'];
            $styleElementCount += $item['styleElementCount'];
            $styleAttributeCount += $item['styleAttributeCount'];
            $styleReferenceCount += $item['styleReferenceCount'];
            $externalStyleReferenceCount += $item['externalStyleReferenceCount'];
            $missingStyleReferenceCount += $item['missingStyleReferenceCount'];
            $encryptedStyleReferenceCount += $item['encryptedStyleReferenceCount'];
            $styleReviewRequiredCount += $item['styleReviewRequiredCount'];
            if ($item['styleCount'] > 0) {
                ++$styleAssetCount;
                array_push($styleItems, ...$item['styles']);
            }
            $scriptCount += $item['scriptCount'];
            $scriptEventHandlerCount += $item['scriptEventHandlerCount'];
            $javascriptReferenceCount += $item['javascriptReferenceCount'];
            array_push($scriptItems, ...$item['scripts']);
            array_push($scriptEventHandlers, ...$item['scriptEventHandlers']);
            array_push($javascriptReferences, ...$item['javascriptReferences']);
            $switchCount += $item['switchCount'];
            $switchCaseCount += $item['switchCaseCount'];
            $switchDefaultCount += $item['switchDefaultCount'];
            $validSwitchCount += $item['validSwitchCount'];
            $invalidSwitchCount += $item['invalidSwitchCount'];
            $triggerCount += $item['triggerCount'];
            $tableCount += $item['tableCount'];
            $tableRowCount += $item['tableRowCount'];
            $tableHeaderCellCount += $item['tableHeaderCellCount'];
            $tableCaptionCount += $item['tableCaptionCount'];
            $tableHeadSectionCount += $item['tableHeadSectionCount'];
            $tableBodySectionCount += $item['tableBodySectionCount'];
            $tableFootSectionCount += $item['tableFootSectionCount'];
            $rubyCount += $item['rubyCount'];
            $rubyAnnotationCount += $item['rubyAnnotationCount'];
            $validRubyCount += $item['validRubyCount'];
            $invalidRubyCount += $item['invalidRubyCount'];
            if ($item['tableCount'] > 0) {
                ++$tableAssetCount;
                array_push($tableItems, ...$item['tables']);
            }
            if ($item['rubyCount'] > 0) {
                ++$rubyAssetCount;
                array_push($rubyItems, ...$item['rubies']);
            }
            $semanticItemCount += $item['semanticCount'];
            $viewportCount += $item['viewportCount'];
            $validViewportCount += $item['validViewportCount'];
            $invalidViewportCount += $item['invalidViewportCount'];
            if ($item['semanticCount'] > 0) {
                ++$semanticAssetCount;
                array_push($semanticItems, ...$item['semantics']);
            }
            if ($item['viewportCount'] > 0) {
                ++$viewportAssetCount;
                array_push($viewportItems, ...$item['viewports']);
            }
            if (($item['flags']['mathml'] ?? false) === true) {
                ++$mathmlAssetCount;
            }
            if (($item['flags']['svg'] ?? false) === true) {
                ++$svgAssetCount;
            }
            if (($item['flags']['scripted'] ?? false) === true) {
                ++$scriptedAssetCount;
            }
            if (($item['flags']['switch'] ?? false) === true) {
                ++$switchAssetCount;
            }
            if (($item['flags']['trigger'] ?? false) === true) {
                ++$triggerAssetCount;
            }
            if (($item['flags']['ruby'] ?? false) === true && $item['rubyCount'] === 0) {
                ++$rubyAssetCount;
            }
            if (($item['flags']['sideEffects'] ?? false) === true && $item['sideEffectCount'] === 0) {
                ++$sideEffectAssetCount;
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
                if (($reference['fragmentKind'] ?? null) === 'epub-cfi') {
                    $cfiReferences[] = $reference;
                }
                if (($reference['fragmentKind'] ?? null) === 'media-fragment') {
                    $mediaFragmentReferences[] = $reference;
                }
            }

            foreach ($item['diagnostics'] as $diagnostic) {
                $diagnostics[] = [
                    'part' => $part,
                ] + $diagnostic;
            }
            foreach ($item['embeddedResourceDiagnostics'] as $diagnostic) {
                $embeddedResourceDiagnostics[] = [
                    'part' => $part,
                ] + $diagnostic;
            }
            foreach ($item['semanticDiagnostics'] as $diagnostic) {
                $semanticDiagnostics[] = [
                    'part' => $part,
                ] + $diagnostic;
            }
            foreach ($item['tableDiagnostics'] as $diagnostic) {
                $tableDiagnostics[] = [
                    'part' => $part,
                ] + $diagnostic;
            }
            foreach ($item['rubyDiagnostics'] as $diagnostic) {
                $rubyDiagnostics[] = [
                    'part' => $part,
                ] + $diagnostic;
            }
            foreach ($item['scriptDiagnostics'] as $diagnostic) {
                $scriptDiagnostics[] = [
                    'part' => $part,
                ] + $diagnostic;
            }
            foreach ($item['linkDiagnostics'] as $diagnostic) {
                $linkDiagnostics[] = [
                    'part' => $part,
                ] + $diagnostic;
            }
            foreach ($item['refreshDiagnostics'] as $diagnostic) {
                $refreshDiagnostics[] = [
                    'part' => $part,
                ] + $diagnostic;
            }
            foreach ($item['sideEffectDiagnostics'] as $diagnostic) {
                $sideEffectDiagnostics[] = [
                    'part' => $part,
                ] + $diagnostic;
            }
            foreach ($item['styleDiagnostics'] as $diagnostic) {
                $styleDiagnostics[] = [
                    'part' => $part,
                ] + $diagnostic;
            }
            foreach ($item['metadataDiagnostics'] as $diagnostic) {
                $viewportDiagnostics[] = [
                    'part' => $part,
                ] + $diagnostic;
            }

            $items[] = $item;
            if ($part !== '') {
                $itemsByPart[$part] = $item;
            }
        }

        $semanticNotes = self::xhtmlSemanticNoteReport($semanticItems);

        return [
            'present' => $items !== [],
            'assetCount' => count($items),
            'referenceCount' => $referenceCount,
            'externalReferenceCount' => count($externalReferences),
            'missingReferenceCount' => count($missingReferences),
            'encryptedReferenceCount' => count($encryptedReferences),
            'cfiReferenceCount' => count($cfiReferences),
            'mediaFragmentReferenceCount' => count($mediaFragmentReferences),
            'embeddedResourceAssetCount' => $embeddedResourceAssetCount,
            'embeddedResourceCount' => $embeddedResourceCount,
            'externalEmbeddedResourceCount' => $externalEmbeddedResourceCount,
            'missingEmbeddedResourceCount' => $missingEmbeddedResourceCount,
            'encryptedEmbeddedResourceCount' => $encryptedEmbeddedResourceCount,
            'embeddedResourceKinds' => self::xhtmlEmbeddedResourceKinds($embeddedResourceItems),
            'embeddedResourcesByKind' => self::xhtmlEmbeddedResourcesByKind($embeddedResourceItems),
            'embeddedResourceItems' => $embeddedResourceItems,
            'embeddedResourceDiagnostics' => $embeddedResourceDiagnostics,
            'mathmlAssetCount' => $mathmlAssetCount,
            'svgAssetCount' => $svgAssetCount,
            'scriptedAssetCount' => $scriptedAssetCount,
            'linkAssetCount' => $linkAssetCount,
            'linkCount' => $linkCount,
            'activeLinkCount' => $activeLinkCount,
            'passiveLinkCount' => $passiveLinkCount,
            'linkReviewRequiredCount' => $linkReviewRequiredCount,
            'linkItems' => $linkItems,
            'linkDiagnostics' => $linkDiagnostics,
            'refreshAssetCount' => $refreshAssetCount,
            'refreshCount' => $refreshCount,
            'refreshReviewRequiredCount' => $refreshReviewRequiredCount,
            'externalRefreshCount' => $externalRefreshCount,
            'missingRefreshCount' => $missingRefreshCount,
            'refreshItems' => $refreshItems,
            'refreshDiagnostics' => $refreshDiagnostics,
            'sideEffectAssetCount' => $sideEffectAssetCount,
            'sideEffectCount' => $sideEffectCount,
            'sideEffectReferenceCount' => $sideEffectReferenceCount,
            'externalSideEffectReferenceCount' => $externalSideEffectReferenceCount,
            'missingSideEffectReferenceCount' => $missingSideEffectReferenceCount,
            'encryptedSideEffectReferenceCount' => $encryptedSideEffectReferenceCount,
            'sideEffectReviewRequiredCount' => $sideEffectReviewRequiredCount,
            'sideEffectItems' => $sideEffectItems,
            'sideEffectDiagnostics' => $sideEffectDiagnostics,
            'styleAssetCount' => $styleAssetCount,
            'styleCount' => $styleCount,
            'styleElementCount' => $styleElementCount,
            'styleAttributeCount' => $styleAttributeCount,
            'styleReferenceCount' => $styleReferenceCount,
            'externalStyleReferenceCount' => $externalStyleReferenceCount,
            'missingStyleReferenceCount' => $missingStyleReferenceCount,
            'encryptedStyleReferenceCount' => $encryptedStyleReferenceCount,
            'styleReviewRequiredCount' => $styleReviewRequiredCount,
            'styleItems' => $styleItems,
            'styleDiagnostics' => $styleDiagnostics,
            'scriptCount' => $scriptCount,
            'scriptEventHandlerCount' => $scriptEventHandlerCount,
            'javascriptReferenceCount' => $javascriptReferenceCount,
            'scriptItems' => $scriptItems,
            'scriptEventHandlers' => $scriptEventHandlers,
            'javascriptReferences' => $javascriptReferences,
            'scriptDiagnostics' => $scriptDiagnostics,
            'switchAssetCount' => $switchAssetCount,
            'switchCount' => $switchCount,
            'switchCaseCount' => $switchCaseCount,
            'switchDefaultCount' => $switchDefaultCount,
            'validSwitchCount' => $validSwitchCount,
            'invalidSwitchCount' => $invalidSwitchCount,
            'triggerAssetCount' => $triggerAssetCount,
            'triggerCount' => $triggerCount,
            'tableAssetCount' => $tableAssetCount,
            'tableCount' => $tableCount,
            'tableRowCount' => $tableRowCount,
            'tableHeaderCellCount' => $tableHeaderCellCount,
            'tableCaptionCount' => $tableCaptionCount,
            'tableHeadSectionCount' => $tableHeadSectionCount,
            'tableBodySectionCount' => $tableBodySectionCount,
            'tableFootSectionCount' => $tableFootSectionCount,
            'tableItems' => $tableItems,
            'tableDiagnostics' => $tableDiagnostics,
            'rubyAssetCount' => $rubyAssetCount,
            'rubyCount' => $rubyCount,
            'rubyAnnotationCount' => $rubyAnnotationCount,
            'validRubyCount' => $validRubyCount,
            'invalidRubyCount' => $invalidRubyCount,
            'rubyItems' => $rubyItems,
            'rubyDiagnostics' => $rubyDiagnostics,
            'semanticAssetCount' => $semanticAssetCount,
            'semanticItemCount' => $semanticItemCount,
            'semanticTypes' => self::xhtmlSemanticTypes($semanticItems),
            'semanticItems' => $semanticItems,
            'semanticItemsByType' => self::xhtmlSemanticItemsByType($semanticItems),
            'semanticNoteCount' => $semanticNotes['noteCount'],
            'semanticNoterefCount' => $semanticNotes['noterefCount'],
            'semanticMatchedNoterefCount' => $semanticNotes['matchedNoterefCount'],
            'semanticUnmatchedNoterefCount' => $semanticNotes['unmatchedNoterefCount'],
            'semanticNoteDiagnostics' => $semanticNotes['diagnostics'],
            'semanticNotes' => $semanticNotes,
            'semanticDiagnostics' => $semanticDiagnostics,
            'viewportAssetCount' => $viewportAssetCount,
            'viewportCount' => $viewportCount,
            'validViewportCount' => $validViewportCount,
            'invalidViewportCount' => $invalidViewportCount,
            'viewportItems' => $viewportItems,
            'viewportDiagnostics' => $viewportDiagnostics,
            'reviewRequiredCount' => $reviewRequiredCount,
            'items' => $items,
            'itemsByPart' => $itemsByPart,
            'externalReferences' => $externalReferences,
            'missingReferences' => $missingReferences,
            'encryptedReferences' => $encryptedReferences,
            'cfiReferences' => $cfiReferences,
            'mediaFragmentReferences' => $mediaFragmentReferences,
            'diagnostics' => $diagnostics,
        ];
    }

    /**
     * @param list<array<string, mixed>> $semanticItems
     *
     * @return array<string, mixed>
     */
    private static function xhtmlSemanticNoteReport(array $semanticItems): array
    {
        $notes = [];
        $notesByTarget = [];
        $notesByType = [];
        $noterefs = [];
        $diagnostics = [];
        $noteTypes = ['footnote' => true, 'endnote' => true];

        foreach ($semanticItems as $semantic) {
            $types = is_array($semantic['types'] ?? null) ? array_values($semantic['types']) : [];
            $noteType = null;
            foreach ($types as $type) {
                if (is_string($type) && isset($noteTypes[$type])) {
                    $noteType = $type;
                    break;
                }
            }
            if ($noteType === null) {
                continue;
            }

            $id = is_string($semantic['id'] ?? null) && $semantic['id'] !== '' ? $semantic['id'] : null;
            $sourcePart = is_string($semantic['sourcePart'] ?? null) && $semantic['sourcePart'] !== ''
                ? $semantic['sourcePart']
                : null;
            $target = $id !== null && $sourcePart !== null ? $sourcePart . '#' . $id : null;
            $note = [
                'index' => count($notes),
                'sourceIndex' => is_int($semantic['index'] ?? null) ? $semantic['index'] : null,
                'type' => $noteType,
                'types' => $types,
                'id' => $id,
                'sourcePart' => $sourcePart,
                'target' => $target,
                'addressable' => $target !== null,
                'element' => is_string($semantic['element'] ?? null) ? $semantic['element'] : null,
                'text' => is_string($semantic['text'] ?? null) ? $semantic['text'] : '',
                'language' => is_string($semantic['language'] ?? null) ? $semantic['language'] : null,
                'direction' => is_string($semantic['direction'] ?? null) ? $semantic['direction'] : null,
                'attributes' => is_array($semantic['attributes'] ?? null) ? $semantic['attributes'] : [],
            ];

            if ($target === null) {
                $diagnostics[] = [
                    'type' => 'unaddressable-semantic-note',
                    'noteType' => $noteType,
                    'id' => $id,
                    'sourcePart' => $sourcePart,
                    'message' => 'EPUB XHTML semantic note is missing an id or source part and cannot be matched from a noteref',
                ];
            } else {
                $notesByTarget[$target][] = $note;
            }

            $notes[] = $note;
            $notesByType[$noteType][] = $note;
        }

        foreach ($semanticItems as $semantic) {
            $types = is_array($semantic['types'] ?? null) ? array_values($semantic['types']) : [];
            if (!in_array('noteref', $types, true)) {
                continue;
            }

            $targetPart = is_string($semantic['part'] ?? null) && $semantic['part'] !== ''
                ? $semantic['part']
                : null;
            $fragment = is_string($semantic['fragment'] ?? null) && $semantic['fragment'] !== ''
                ? $semantic['fragment']
                : null;
            $target = is_string($semantic['target'] ?? null) && $semantic['target'] !== ''
                ? $semantic['target']
                : null;
            $targetKey = $targetPart !== null && $fragment !== null ? $targetPart . '#' . $fragment : null;
            $matches = $targetKey !== null && isset($notesByTarget[$targetKey]) ? $notesByTarget[$targetKey] : [];
            $matched = $matches !== [];
            $noterefDiagnostics = [];

            if (!$matched) {
                if (($semantic['external'] ?? false) === true) {
                    $noterefDiagnostics[] = [
                        'type' => 'external-noteref-note-target',
                        'target' => $target,
                        'message' => 'EPUB XHTML noteref points outside the package and was not fetched for note matching',
                    ];
                } elseif ($targetKey === null) {
                    $noterefDiagnostics[] = [
                        'type' => 'missing-noteref-note-target',
                        'target' => $target,
                        'message' => 'EPUB XHTML noteref does not identify a package-local note target',
                    ];
                } elseif (($semantic['fragmentExists'] ?? null) === false || ($semantic['exists'] ?? null) === false) {
                    $noterefDiagnostics[] = [
                        'type' => 'missing-noteref-note-target',
                        'target' => $targetKey,
                        'message' => 'EPUB XHTML noteref target does not resolve to an element in the content document',
                    ];
                } else {
                    $noterefDiagnostics[] = [
                        'type' => 'noteref-target-not-semantic-note',
                        'target' => $targetKey,
                        'message' => 'EPUB XHTML noteref target exists but is not marked as a footnote or endnote',
                    ];
                }
            }

            $firstMatch = $matches[0] ?? null;
            $noteref = [
                'index' => count($noterefs),
                'sourceIndex' => is_int($semantic['index'] ?? null) ? $semantic['index'] : null,
                'id' => is_string($semantic['id'] ?? null) ? $semantic['id'] : null,
                'sourcePart' => is_string($semantic['sourcePart'] ?? null) ? $semantic['sourcePart'] : null,
                'href' => is_string($semantic['href'] ?? null) ? $semantic['href'] : null,
                'target' => $target,
                'targetPart' => $targetPart,
                'fragment' => $fragment,
                'fragmentExists' => is_bool($semantic['fragmentExists'] ?? null) ? $semantic['fragmentExists'] : null,
                'external' => ($semantic['external'] ?? false) === true,
                'exists' => is_bool($semantic['exists'] ?? null) ? $semantic['exists'] : null,
                'text' => is_string($semantic['text'] ?? null) ? $semantic['text'] : '',
                'matched' => $matched,
                'matchCount' => count($matches),
                'noteType' => is_array($firstMatch) ? $firstMatch['type'] : null,
                'noteId' => is_array($firstMatch) ? $firstMatch['id'] : null,
                'notePart' => is_array($firstMatch) ? $firstMatch['sourcePart'] : null,
                'noteTarget' => is_array($firstMatch) ? $firstMatch['target'] : null,
                'noteText' => is_array($firstMatch) ? $firstMatch['text'] : null,
                'noteElement' => is_array($firstMatch) ? $firstMatch['element'] : null,
                'diagnostics' => $noterefDiagnostics,
            ];

            if ($noterefDiagnostics !== []) {
                foreach ($noterefDiagnostics as $diagnostic) {
                    $diagnostics[] = [
                        'noterefIndex' => $noteref['index'],
                        'id' => $noteref['id'],
                    ] + $diagnostic;
                }
            }

            $noterefs[] = $noteref;
        }

        $addressableNotes = array_values(array_filter(
            $notes,
            static fn (array $note): bool => ($note['addressable'] ?? false) === true,
        ));
        $matchedNoterefs = array_values(array_filter(
            $noterefs,
            static fn (array $noteref): bool => ($noteref['matched'] ?? false) === true,
        ));
        $unmatchedNoterefs = array_values(array_filter(
            $noterefs,
            static fn (array $noteref): bool => ($noteref['matched'] ?? false) !== true,
        ));
        $missingTargetNoterefs = array_values(array_filter(
            $noterefs,
            static fn (array $noteref): bool => in_array(
                'missing-noteref-note-target',
                array_map(static fn (array $diagnostic): string => (string) ($diagnostic['type'] ?? ''), $noteref['diagnostics']),
                true
            ),
        ));
        $nonNoteTargetNoterefs = array_values(array_filter(
            $noterefs,
            static fn (array $noteref): bool => in_array(
                'noteref-target-not-semantic-note',
                array_map(static fn (array $diagnostic): string => (string) ($diagnostic['type'] ?? ''), $noteref['diagnostics']),
                true
            ),
        ));
        $externalNoterefs = array_values(array_filter(
            $noterefs,
            static fn (array $noteref): bool => ($noteref['external'] ?? false) === true,
        ));

        ksort($notesByTarget, SORT_STRING);
        ksort($notesByType, SORT_STRING);

        return [
            'present' => $notes !== [] || $noterefs !== [],
            'noteCount' => count($notes),
            'footnoteCount' => count($notesByType['footnote'] ?? []),
            'endnoteCount' => count($notesByType['endnote'] ?? []),
            'addressableNoteCount' => count($addressableNotes),
            'noterefCount' => count($noterefs),
            'matchedNoterefCount' => count($matchedNoterefs),
            'unmatchedNoterefCount' => count($unmatchedNoterefs),
            'missingTargetNoterefCount' => count($missingTargetNoterefs),
            'nonNoteTargetNoterefCount' => count($nonNoteTargetNoterefs),
            'externalNoterefCount' => count($externalNoterefs),
            'diagnosticCount' => count($diagnostics),
            'notes' => $notes,
            'notesByTarget' => $notesByTarget,
            'notesByType' => $notesByType,
            'noterefs' => $noterefs,
            'matchedNoterefs' => $matchedNoterefs,
            'unmatchedNoterefs' => $unmatchedNoterefs,
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
        $scripts = [];
        $links = [];
        $refreshes = [];
        $sideEffects = [];
        $styles = [];
        $scriptEventHandlers = [];
        $javascriptReferences = [];
        $switches = [];
        $triggers = [];
        $semantics = [];
        $tables = [];
        $rubies = [];
        $elementIds = [];
        $diagnostics = [];

        try {
            $dom = self::loadXml($html, 'EPUB XHTML content document');
        } catch (\Throwable $exception) {
            return [
                'part' => $part,
                'flags' => $flags,
                'reviewFlags' => [],
                'metadata' => self::emptyXhtmlContentMetadataReport($part),
                'references' => [],
                'embeddedResources' => [],
                'embeddedResourceDiagnostics' => [],
                'links' => [],
                'linkDiagnostics' => [],
                'refreshes' => [],
                'refreshDiagnostics' => [],
                'sideEffects' => [],
                'sideEffectDiagnostics' => [],
                'styles' => [],
                'styleDiagnostics' => [],
                'scripts' => [],
                'scriptEventHandlers' => [],
                'javascriptReferences' => [],
                'scriptDiagnostics' => [],
                'switches' => [],
                'tables' => [],
                'tableDiagnostics' => [],
                'rubies' => [],
                'rubyDiagnostics' => [],
                'switchCaseCount' => 0,
                'switchDefaultCount' => 0,
                'validSwitchCount' => 0,
                'invalidSwitchCount' => 0,
                'triggers' => [],
                'semantics' => [],
                'semanticTypes' => [],
                'semanticItemsByType' => [],
                'semanticDiagnostics' => [],
                'validTriggerCount' => 0,
                'invalidTriggerCount' => 0,
                'diagnostics' => [[
                    'type' => 'xhtml-content-resource-scan-failed',
                    'part' => $part,
                    'message' => $exception->getMessage(),
                ]],
            ];
        }

        $root = $dom->documentElement;
        $metadata = self::xhtmlContentMetadataReport($dom, $part);
        if ($root instanceof \DOMElement) {
            $this->scanXhtmlContentElement(
                $package,
                $part,
                $root,
                $manifestByPart,
                $flags,
                $references,
                $links,
                $refreshes,
                $sideEffects,
                $styles,
                $scripts,
                $scriptEventHandlers,
                $javascriptReferences,
                $switches,
                $triggers,
                $semantics,
                $tables,
                $rubies,
                $elementIds
            );
        }

        $triggers = self::xhtmlTriggersWithElementResolution($triggers, $elementIds);
        $semantics = self::xhtmlSemanticsWithElementResolution($semantics, $elementIds);

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
        $scriptDiagnostics = [];
        $linkDiagnostics = [];
        $refreshDiagnostics = [];
        foreach ($links as $link) {
            foreach ($link['diagnostics'] as $diagnostic) {
                $linkDiagnostics[] = [
                    'linkIndex' => $link['index'],
                    'linkId' => $link['id'],
                ] + $diagnostic;
            }
        }
        array_push($diagnostics, ...$linkDiagnostics);
        foreach ($refreshes as $refresh) {
            foreach ($refresh['diagnostics'] as $diagnostic) {
                $refreshDiagnostics[] = [
                    'refreshIndex' => $refresh['index'],
                    'refreshId' => $refresh['id'],
                ] + $diagnostic;
            }
        }
        array_push($diagnostics, ...$refreshDiagnostics);
        $sideEffectDiagnostics = [];
        foreach ($sideEffects as $sideEffect) {
            foreach ($sideEffect['diagnostics'] as $diagnostic) {
                $sideEffectDiagnostics[] = [
                    'sideEffectIndex' => $sideEffect['index'],
                    'sideEffectKind' => $sideEffect['kind'],
                    'sideEffectId' => $sideEffect['id'],
                ] + $diagnostic;
            }
        }
        array_push($diagnostics, ...$sideEffectDiagnostics);
        $styleDiagnostics = [];
        foreach ($styles as $style) {
            foreach ($style['diagnostics'] as $diagnostic) {
                $styleDiagnostics[] = [
                    'styleIndex' => $style['index'],
                    'styleKind' => $style['kind'],
                    'styleId' => $style['id'],
                ] + $diagnostic;
            }
        }
        foreach ($scripts as $script) {
            foreach ($script['diagnostics'] as $diagnostic) {
                $scriptDiagnostics[] = [
                    'scriptIndex' => $script['index'],
                    'scriptId' => $script['id'],
                ] + $diagnostic;
            }
        }
        foreach ($scriptEventHandlers as $eventHandler) {
            foreach ($eventHandler['diagnostics'] as $diagnostic) {
                $scriptDiagnostics[] = [
                    'eventHandlerIndex' => $eventHandler['index'],
                    'element' => $eventHandler['element'],
                    'elementId' => $eventHandler['elementId'],
                    'attribute' => $eventHandler['attribute'],
                ] + $diagnostic;
            }
        }
        foreach ($javascriptReferences as $javascriptReference) {
            foreach ($javascriptReference['diagnostics'] as $diagnostic) {
                $scriptDiagnostics[] = [
                    'javascriptReferenceIndex' => $javascriptReference['index'],
                    'element' => $javascriptReference['element'],
                    'attribute' => $javascriptReference['attribute'],
                    'href' => $javascriptReference['href'],
                ] + $diagnostic;
            }
        }
        array_push($diagnostics, ...$scriptDiagnostics);
        foreach ($switches as $switch) {
            foreach ($switch['diagnostics'] as $diagnostic) {
                $diagnostics[] = [
                    'switchIndex' => $switch['index'],
                    'switchId' => $switch['id'],
                ] + $diagnostic;
            }
        }
        foreach ($triggers as $trigger) {
            foreach ($trigger['diagnostics'] as $diagnostic) {
                $diagnostics[] = [
                    'triggerIndex' => $trigger['index'],
                    'triggerId' => $trigger['id'],
                ] + $diagnostic;
            }
        }
        $tableDiagnostics = [];
        foreach ($tables as $table) {
            foreach ($table['diagnostics'] as $diagnostic) {
                $tableDiagnostics[] = [
                    'tableIndex' => $table['index'],
                    'tableId' => $table['id'],
                ] + $diagnostic;
            }
        }
        $rubyDiagnostics = [];
        foreach ($rubies as $ruby) {
            foreach ($ruby['diagnostics'] as $diagnostic) {
                $rubyDiagnostics[] = [
                    'rubyIndex' => $ruby['index'],
                    'rubyId' => $ruby['id'],
                ] + $diagnostic;
            }
        }
        $semanticDiagnostics = [];
        foreach ($semantics as $semantic) {
            foreach ($semantic['diagnostics'] as $diagnostic) {
                $semanticDiagnostics[] = [
                    'semanticIndex' => $semantic['index'],
                    'semanticId' => $semantic['id'],
                    'element' => $semantic['element'],
                    'primaryType' => $semantic['primaryType'],
                ] + $diagnostic;
            }
        }
        foreach ($metadata['diagnostics'] as $diagnostic) {
            $diagnostics[] = $diagnostic;
        }
        $embeddedResources = self::xhtmlEmbeddedResourceReferences($references);
        $embeddedResourceDiagnostics = self::xhtmlEmbeddedResourceDiagnostics($embeddedResources);

        return [
            'part' => $part,
            'flags' => $flags,
            'reviewFlags' => self::xhtmlContentReviewFlags($flags),
            'metadata' => $metadata,
            'references' => $references,
            'embeddedResources' => $embeddedResources,
            'embeddedResourceDiagnostics' => $embeddedResourceDiagnostics,
            'links' => $links,
            'linkDiagnostics' => $linkDiagnostics,
            'refreshes' => $refreshes,
            'refreshDiagnostics' => $refreshDiagnostics,
            'sideEffects' => $sideEffects,
            'sideEffectDiagnostics' => $sideEffectDiagnostics,
            'styles' => $styles,
            'styleDiagnostics' => $styleDiagnostics,
            'scripts' => $scripts,
            'scriptEventHandlers' => $scriptEventHandlers,
            'javascriptReferences' => $javascriptReferences,
            'scriptDiagnostics' => $scriptDiagnostics,
            'switches' => $switches,
            'tables' => $tables,
            'tableDiagnostics' => $tableDiagnostics,
            'rubies' => $rubies,
            'rubyDiagnostics' => $rubyDiagnostics,
            'switchCaseCount' => array_sum(array_map(
                static fn (array $switch): int => is_int($switch['caseCount'] ?? null) ? $switch['caseCount'] : 0,
                $switches,
            )),
            'switchDefaultCount' => array_sum(array_map(
                static fn (array $switch): int => is_int($switch['defaultCount'] ?? null) ? $switch['defaultCount'] : 0,
                $switches,
            )),
            'validSwitchCount' => count(array_filter(
                $switches,
                static fn (array $switch): bool => ($switch['valid'] ?? false) === true,
            )),
            'invalidSwitchCount' => count(array_filter(
                $switches,
                static fn (array $switch): bool => ($switch['valid'] ?? true) !== true,
            )),
            'triggers' => $triggers,
            'semantics' => $semantics,
            'semanticTypes' => self::xhtmlSemanticTypes($semantics),
            'semanticItemsByType' => self::xhtmlSemanticItemsByType($semantics),
            'semanticDiagnostics' => $semanticDiagnostics,
            'validTriggerCount' => count(array_filter(
                $triggers,
                static fn (array $trigger): bool => ($trigger['valid'] ?? false) === true,
            )),
            'invalidTriggerCount' => count(array_filter(
                $triggers,
                static fn (array $trigger): bool => ($trigger['valid'] ?? true) !== true,
            )),
            'diagnostics' => $diagnostics,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function emptyXhtmlContentMetadataReport(string $part): array
    {
        return [
            'present' => false,
            'part' => $part,
            'headPresent' => false,
            'title' => null,
            'htmlXmlLang' => null,
            'htmlLang' => null,
            'htmlLanguage' => null,
            'htmlDirection' => null,
            'bodyPresent' => false,
            'bodyId' => null,
            'bodyClass' => null,
            'bodyClasses' => [],
            'bodyXmlLang' => null,
            'bodyLang' => null,
            'bodyLanguage' => null,
            'bodyDirection' => null,
            'bodyEpubTypes' => [],
            'bodyAttributes' => [],
            'language' => null,
            'direction' => null,
            'metaCount' => 0,
            'viewportCount' => 0,
            'validViewportCount' => 0,
            'invalidViewportCount' => 0,
            'viewport' => self::emptyXhtmlViewportReport(),
            'viewports' => [],
            'diagnostics' => [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function xhtmlContentMetadataReport(\DOMDocument $dom, string $part): array
    {
        $empty = self::emptyXhtmlContentMetadataReport($part);
        $root = $dom->documentElement;
        if (!$root instanceof \DOMElement) {
            return $empty;
        }

        $htmlXmlLang = self::xmlLang($root);
        $htmlLang = self::nullableAttribute($root, 'lang');
        $htmlLanguage = $htmlXmlLang ?? $htmlLang;
        $htmlDirection = self::direction($root);
        $body = self::firstChildElement($root, 'body', self::XHTML_NS);
        $bodyXmlLang = $body instanceof \DOMElement ? self::xmlLang($body) : null;
        $bodyLang = $body instanceof \DOMElement ? self::nullableAttribute($body, 'lang') : null;
        $bodyLanguage = $bodyXmlLang ?? $bodyLang;
        $bodyDirection = $body instanceof \DOMElement ? self::direction($body) : null;
        $bodyFields = [
            'htmlXmlLang' => $htmlXmlLang,
            'htmlLang' => $htmlLang,
            'htmlLanguage' => $htmlLanguage,
            'htmlDirection' => $htmlDirection,
            'bodyPresent' => $body instanceof \DOMElement,
            'bodyId' => $body instanceof \DOMElement ? self::nullableAttribute($body, 'id') : null,
            'bodyClass' => $body instanceof \DOMElement ? self::nullableAttribute($body, 'class') : null,
            'bodyClasses' => $body instanceof \DOMElement ? self::spaceDelimited($body->getAttribute('class')) : [],
            'bodyXmlLang' => $bodyXmlLang,
            'bodyLang' => $bodyLang,
            'bodyLanguage' => $bodyLanguage,
            'bodyDirection' => $bodyDirection,
            'bodyEpubTypes' => $body instanceof \DOMElement ? self::epubTypes($body) : [],
            'bodyAttributes' => $body instanceof \DOMElement ? self::elementAttributes($body) : [],
            'language' => $bodyLanguage ?? $htmlLanguage,
            'direction' => $bodyDirection ?? $htmlDirection,
        ];

        $head = self::firstChildElement($root, 'head', self::XHTML_NS);
        if (!$head instanceof \DOMElement) {
            return [
                'present' => true,
                'part' => $part,
                'headPresent' => false,
                'title' => null,
                'metaCount' => 0,
                'viewportCount' => 0,
                'validViewportCount' => 0,
                'invalidViewportCount' => 0,
                'viewport' => self::emptyXhtmlViewportReport(),
                'viewports' => [],
                'diagnostics' => [],
            ] + $bodyFields;
        }

        $titleElement = self::firstChildElement($head, 'title', self::XHTML_NS);
        $title = $titleElement instanceof \DOMElement ? self::normalizedText($titleElement) : null;
        $metaElements = self::childElements($head, 'meta', self::XHTML_NS);
        $viewports = [];
        $diagnostics = [];

        foreach ($metaElements as $meta) {
            $name = strtolower(trim($meta->getAttribute('name')));
            if ($name !== 'viewport') {
                continue;
            }

            $viewport = self::xhtmlViewportReport($meta, $part, count($viewports));
            foreach ($viewport['diagnostics'] as $diagnostic) {
                $diagnostics[] = $diagnostic;
            }
            $viewports[] = $viewport;
        }

        $validViewports = array_values(array_filter(
            $viewports,
            static fn (array $viewport): bool => ($viewport['valid'] ?? false) === true,
        ));
        $invalidViewports = array_values(array_filter(
            $viewports,
            static fn (array $viewport): bool => ($viewport['valid'] ?? true) !== true,
        ));
        $selectedViewport = $validViewports[0] ?? ($viewports[0] ?? self::emptyXhtmlViewportReport());

        return [
            'present' => true,
            'part' => $part,
            'headPresent' => true,
            'title' => $title,
            'htmlXmlLang' => $htmlXmlLang,
            'htmlLang' => $htmlLang,
            'htmlLanguage' => $htmlLanguage,
            'htmlDirection' => $htmlDirection,
            'bodyPresent' => $bodyFields['bodyPresent'],
            'bodyId' => $bodyFields['bodyId'],
            'bodyClass' => $bodyFields['bodyClass'],
            'bodyClasses' => $bodyFields['bodyClasses'],
            'bodyXmlLang' => $bodyXmlLang,
            'bodyLang' => $bodyLang,
            'bodyLanguage' => $bodyLanguage,
            'bodyDirection' => $bodyDirection,
            'bodyEpubTypes' => $bodyFields['bodyEpubTypes'],
            'bodyAttributes' => $bodyFields['bodyAttributes'],
            'language' => $bodyFields['language'],
            'direction' => $bodyFields['direction'],
            'metaCount' => count($metaElements),
            'viewportCount' => count($viewports),
            'validViewportCount' => count($validViewports),
            'invalidViewportCount' => count($invalidViewports),
            'viewport' => $selectedViewport,
            'viewports' => $viewports,
            'diagnostics' => $diagnostics,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function xhtmlViewportReport(\DOMElement $element, string $part, int $index): array
    {
        $raw = trim($element->getAttribute('content'));
        $parameters = [];
        $diagnostics = [];
        $unknownParameterDiagnostics = [];
        $segments = $raw === '' ? [] : preg_split('/\s*,\s*/', $raw);

        foreach ($segments ?: [] as $segment) {
            $segment = trim((string) $segment);
            if ($segment === '') {
                continue;
            }

            if (preg_match('/^([A-Za-z][A-Za-z0-9_-]*)\s*=\s*(.+)$/', $segment, $matches) !== 1) {
                $diagnostics[] = [
                    'type' => 'invalid-xhtml-viewport-parameter',
                    'property' => 'meta[name=viewport]',
                    'index' => $index,
                    'segment' => $segment,
                    'message' => 'EPUB XHTML viewport meta content parameters must be key=value pairs',
                ];
                continue;
            }

            $key = strtolower($matches[1]);
            $value = trim($matches[2]);
            if (isset($parameters[$key])) {
                $diagnostics[] = [
                    'type' => 'duplicate-xhtml-viewport-parameter',
                    'property' => 'meta[name=viewport]',
                    'index' => $index,
                    'parameter' => $key,
                    'message' => 'EPUB XHTML viewport meta content repeats a parameter; first value is retained',
                ];
                continue;
            }

            $parameters[$key] = $value;
            if (!in_array($key, ['width', 'height'], true)) {
                $unknownParameterDiagnostics[] = [
                    'type' => 'unknown-xhtml-viewport-parameter',
                    'property' => 'meta[name=viewport]',
                    'index' => $index,
                    'parameter' => $key,
                    'value' => $value,
                    'message' => 'EPUB XHTML viewport meta content parameter is preserved but not used by the bounded package review parser',
                ];
            }
        }

        if ($raw === '') {
            $diagnostics[] = [
                'type' => 'empty-xhtml-viewport',
                'property' => 'meta[name=viewport]',
                'index' => $index,
                'message' => 'EPUB XHTML viewport meta content is empty',
            ];
        }

        foreach (['width', 'height'] as $dimension) {
            $value = $parameters[$dimension] ?? null;
            if ($value === null || preg_match('/^[1-9][0-9]*$/', $value) !== 1) {
                $diagnostics[] = [
                    'type' => 'invalid-xhtml-viewport-' . $dimension,
                    'property' => 'meta[name=viewport]',
                    'index' => $index,
                    'parameter' => $dimension,
                    'value' => $value,
                    'message' => 'EPUB XHTML viewport width and height must be positive integer CSS pixels',
                ];
            }
        }
        $diagnostics = array_merge($diagnostics, $unknownParameterDiagnostics);

        return [
            'present' => true,
            'index' => $index,
            'sourcePart' => $part,
            'property' => 'meta[name=viewport]',
            'id' => self::nullableAttribute($element, 'id'),
            'name' => self::nullableAttribute($element, 'name'),
            'raw' => $raw,
            'parameters' => $parameters,
            'widthRaw' => $parameters['width'] ?? null,
            'heightRaw' => $parameters['height'] ?? null,
            'width' => isset($parameters['width']) && preg_match('/^[1-9][0-9]*$/', $parameters['width']) === 1
                ? (int) $parameters['width']
                : null,
            'height' => isset($parameters['height']) && preg_match('/^[1-9][0-9]*$/', $parameters['height']) === 1
                ? (int) $parameters['height']
                : null,
            'language' => self::xmlLang($element),
            'direction' => self::direction($element),
            'attributes' => self::elementAttributes($element),
            'valid' => $diagnostics === [],
            'diagnostics' => $diagnostics,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function emptyXhtmlViewportReport(): array
    {
        return [
            'present' => false,
            'index' => null,
            'sourcePart' => null,
            'property' => 'meta[name=viewport]',
            'id' => null,
            'name' => null,
            'raw' => null,
            'parameters' => [],
            'widthRaw' => null,
            'heightRaw' => null,
            'width' => null,
            'height' => null,
            'language' => null,
            'direction' => null,
            'attributes' => [],
            'valid' => true,
            'diagnostics' => [],
        ];
    }

    /**
     * @param array<string, array<string, mixed>> $manifestByPart
     * @param array<string, bool> $flags
     * @param list<array<string, mixed>> $references
     * @param list<array<string, mixed>> $links
     * @param list<array<string, mixed>> $refreshes
     * @param list<array<string, mixed>> $sideEffects
     * @param list<array<string, mixed>> $styles
     * @param list<array<string, mixed>> $scripts
     * @param list<array<string, mixed>> $scriptEventHandlers
     * @param list<array<string, mixed>> $javascriptReferences
     * @param list<array<string, mixed>> $switches
     * @param list<array<string, mixed>> $triggers
     * @param list<array<string, mixed>> $semantics
     * @param list<array<string, mixed>> $tables
     * @param list<array<string, mixed>> $rubies
     * @param array<string, array<string, mixed>> $elementIds
     */
    private function scanXhtmlContentElement(
        ZipPackage $package,
        string $part,
        \DOMElement $element,
        array $manifestByPart,
        array &$flags,
        array &$references,
        array &$links,
        array &$refreshes,
        array &$sideEffects,
        array &$styles,
        array &$scripts,
        array &$scriptEventHandlers,
        array &$javascriptReferences,
        array &$switches,
        array &$triggers,
        array &$semantics,
        array &$tables,
        array &$rubies,
        array &$elementIds
    ): void {
        $namespace = (string) $element->namespaceURI;
        $localName = strtolower($element->localName);
        self::registerXhtmlElementId($element, $elementIds);
        if ($namespace === 'http://www.w3.org/1998/Math/MathML' || $localName === 'math') {
            $flags['mathml'] = true;
        }
        if ($namespace === 'http://www.w3.org/2000/svg' || $localName === 'svg') {
            $flags['svg'] = true;
        }
        if ($namespace === self::XHTML_NS && $localName === 'script') {
            $flags['scripted'] = true;
            $scripts[] = $this->xhtmlScriptReport(
                $package,
                $part,
                $element,
                $manifestByPart,
                count($scripts)
            );
        }
        if ($namespace === self::XHTML_NS && $localName === 'link') {
            $link = $this->xhtmlLinkReport(
                $package,
                $part,
                $element,
                $manifestByPart,
                count($links)
            );
            if ($link['active'] || ($link['requiresReview'] ?? false) === true) {
                $flags['linkedResources'] = true;
            }
            if (($link['external'] ?? false) === true) {
                $flags['remoteResources'] = true;
            }
            if (($link['exists'] ?? true) !== true && ($link['external'] ?? false) !== true) {
                $flags['missingReferences'] = true;
            }
            if (($link['encrypted'] ?? false) === true) {
                $flags['encryptedReferences'] = true;
            }
            $links[] = $link;
        }
        if ($namespace === self::XHTML_NS && $localName === 'style') {
            $style = $this->xhtmlInlineStyleReport(
                $package,
                $part,
                $element,
                $manifestByPart,
                'style-element',
                'text',
                (string) $element->textContent,
                count($styles),
                count($references)
            );
            if ($style['referenceCount'] > 0 || $style['diagnostics'] !== []) {
                $flags['inlineStyles'] = true;
                $flags['linkedResources'] = true;
            }
            if ($style['externalReferenceCount'] > 0) {
                $flags['remoteResources'] = true;
            }
            if ($style['missingReferenceCount'] > 0) {
                $flags['missingReferences'] = true;
            }
            if ($style['encryptedReferenceCount'] > 0) {
                $flags['encryptedReferences'] = true;
            }
            array_push($references, ...$style['references']);
            $styles[] = $style;
        }
        if ($namespace === self::XHTML_NS && $localName === 'meta') {
            $refresh = $this->xhtmlMetaRefreshReport(
                $package,
                $part,
                $element,
                $manifestByPart,
                count($refreshes),
                count($references)
            );
            if (is_array($refresh)) {
                $flags['linkedResources'] = true;
                if (($refresh['external'] ?? false) === true) {
                    $flags['remoteResources'] = true;
                }
                if (
                    ($refresh['exists'] ?? true) !== true
                    && ($refresh['external'] ?? false) !== true
                    && is_string($refresh['url'] ?? null)
                    && $refresh['url'] !== ''
                ) {
                    $flags['missingReferences'] = true;
                }
                if (($refresh['encrypted'] ?? false) === true) {
                    $flags['encryptedReferences'] = true;
                }

                $reference = $refresh['reference'] ?? null;
                unset($refresh['reference']);
                if (is_array($reference)) {
                    $references[] = $reference;
                }
                $refreshes[] = $refresh;
            }
        }
        if ($namespace === self::XHTML_NS && $localName === 'form') {
            $flags['sideEffects'] = true;
            $sideEffects[] = $this->xhtmlFormSideEffectReport(
                $package,
                $part,
                $element,
                $manifestByPart,
                count($sideEffects)
            );
        }
        if ($namespace === self::XHTML_NS && ($localName === 'input' || $localName === 'button')) {
            $controlSideEffect = $this->xhtmlFormControlSideEffectReport(
                $package,
                $part,
                $element,
                $manifestByPart,
                count($sideEffects)
            );
            if (is_array($controlSideEffect)) {
                $flags['sideEffects'] = true;
                $sideEffects[] = $controlSideEffect;
            }
        }
        if ($namespace === self::XHTML_NS && $localName === 'a') {
            $pingSideEffect = $this->xhtmlAnchorPingSideEffectReport(
                $package,
                $part,
                $element,
                $manifestByPart,
                count($sideEffects)
            );
            if (is_array($pingSideEffect)) {
                $flags['sideEffects'] = true;
                $sideEffects[] = $pingSideEffect;
            }
        }
        if ($namespace === self::EPUB_OPS_NS && $localName === 'switch') {
            $flags['switch'] = true;
            $switches[] = self::xhtmlSwitchReport($element, count($switches));
        }
        if ($namespace === self::EPUB_OPS_NS && $localName === 'trigger') {
            $flags['trigger'] = true;
            $triggers[] = self::xhtmlTriggerReport($element, count($triggers));
        }
        if ($namespace === self::XHTML_NS && $localName === 'table') {
            $flags['tables'] = true;
            $tables[] = self::xhtmlTableReport($part, $element, count($tables));
        }
        if ($namespace === self::XHTML_NS && $localName === 'ruby') {
            $flags['ruby'] = true;
            $rubies[] = self::xhtmlRubyReport($part, $element, count($rubies));
        }
        $epubTypes = self::epubTypes($element);
        if ($epubTypes !== []) {
            $semantics[] = $this->xhtmlSemanticReport(
                $package,
                $part,
                $element,
                $epubTypes,
                $manifestByPart,
                count($semantics)
            );
        }

        $styleAttribute = self::nullableAttribute($element, 'style');
        if ($namespace === self::XHTML_NS && $styleAttribute !== null && trim($styleAttribute) !== '') {
            $style = $this->xhtmlInlineStyleReport(
                $package,
                $part,
                $element,
                $manifestByPart,
                'style-attribute',
                'style',
                $styleAttribute,
                count($styles),
                count($references)
            );
            if ($style['referenceCount'] > 0 || $style['diagnostics'] !== []) {
                $flags['inlineStyles'] = true;
                $flags['linkedResources'] = true;
            }
            if ($style['externalReferenceCount'] > 0) {
                $flags['remoteResources'] = true;
            }
            if ($style['missingReferenceCount'] > 0) {
                $flags['missingReferences'] = true;
            }
            if ($style['encryptedReferenceCount'] > 0) {
                $flags['encryptedReferences'] = true;
            }
            array_push($references, ...$style['references']);
            $styles[] = $style;
        }

        foreach (self::xhtmlEventHandlerAttributes($element) as $attributeName) {
            $flags['scripted'] = true;
            $scriptEventHandlers[] = self::xhtmlScriptEventHandlerReport(
                $element,
                $attributeName,
                count($scriptEventHandlers)
            );
            $references[] = [
                'index' => count($references),
                'element' => $element->localName,
                'attribute' => $attributeName,
                'href' => null,
                'target' => null,
                'part' => $part,
                'fragment' => null,
                'fragmentKind' => null,
                'epubCfi' => null,
                'mediaFragment' => null,
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
            $href = is_string($attribute['href'] ?? null) ? trim($attribute['href']) : '';
            if ($href === '') {
                continue;
            }
            $javascriptReference = preg_match('/^javascript:/i', $href) === 1;
            if (preg_match('/^javascript:/i', $href) === 1) {
                $flags['scripted'] = true;
            }

            $reference = $this->xhtmlContentReference(
                $package,
                $part,
                $element,
                $attribute['attribute'],
                $href,
                $manifestByPart,
                count($references),
                $flags
            );
            if ($javascriptReference) {
                array_unshift($reference['diagnostics'], [
                    'type' => 'javascript-xhtml-content-reference',
                    'href' => $href,
                    'message' => 'EPUB XHTML content uses a javascript: URL that remains inert and requires review',
                ]);
                $javascriptReferences[] = self::xhtmlJavascriptReferenceReport(
                    $element,
                    $attribute['attribute'],
                    $reference,
                    count($javascriptReferences)
                );
            }
            if (isset($attribute['srcsetCandidateIndex'])) {
                $reference['srcsetCandidateIndex'] = (int) $attribute['srcsetCandidateIndex'];
                $reference['srcsetCandidate'] = is_string($attribute['srcsetCandidate'] ?? null)
                    ? $attribute['srcsetCandidate']
                    : $href;
                $reference['srcsetDescriptor'] = is_string($attribute['srcsetDescriptor'] ?? null)
                    ? $attribute['srcsetDescriptor']
                    : null;
            }

            $references[] = $reference;
        }

        foreach (self::childElements($element) as $child) {
            $this->scanXhtmlContentElement(
                $package,
                $part,
                $child,
                $manifestByPart,
                $flags,
                $references,
                $links,
                $refreshes,
                $sideEffects,
                $styles,
                $scripts,
                $scriptEventHandlers,
                $javascriptReferences,
                $switches,
                $triggers,
                $semantics,
                $tables,
                $rubies,
                $elementIds
            );
        }
    }

    /**
     * @param array<string, array<string, mixed>> $manifestByPart
     *
     * @return array<string, mixed>
     */
    private function xhtmlInlineStyleReport(
        ZipPackage $package,
        string $part,
        \DOMElement $element,
        array $manifestByPart,
        string $kind,
        string $attribute,
        string $css,
        int $index,
        int $referenceIndex
    ): array {
        $references = [];
        $diagnostics = [];
        foreach (self::cssReferenceTokens($css) as $token) {
            $reference = $this->packageReference(
                $package,
                $part,
                (string) $token['href'],
                $manifestByPart,
                'xhtml-inline-style'
            );
            $referenceDiagnostics = $reference['diagnostics'];
            if (($reference['encrypted'] ?? false) === true) {
                $referenceDiagnostics[] = [
                    'type' => 'encrypted-xhtml-inline-style-reference',
                    'part' => $reference['part'],
                    'message' => 'EPUB XHTML inline CSS references an encrypted package part that cannot be exposed directly',
                ];
            }

            $item = [
                'index' => $referenceIndex + count($references),
                'styleIndex' => $index,
                'styleReferenceIndex' => count($references),
                'kind' => (string) $token['kind'],
                'source' => $kind,
                'element' => $element->localName,
                'elementId' => self::nullableAttribute($element, 'id'),
                'attribute' => $attribute,
                'href' => (string) $token['href'],
                'raw' => (string) $token['raw'],
                'target' => $reference['target'],
                'part' => $reference['part'],
                'fragment' => $reference['fragment'],
                'fragmentKind' => $reference['fragmentKind'],
                'epubCfi' => $reference['epubCfi'],
                'mediaFragment' => $reference['mediaFragment'],
                'external' => $reference['external'],
                'exists' => $reference['exists'],
                'byteLength' => $reference['byteLength'],
                'crc32' => $reference['crc32'],
                'manifestId' => $reference['manifestId'],
                'mediaType' => $reference['mediaType'],
                'encrypted' => $reference['encrypted'],
                'canExposeBytes' => $reference['canExposeBytes'],
                'diagnostics' => $referenceDiagnostics,
            ];
            foreach (['imageSetCandidateIndex', 'imageSetCandidate', 'imageSetDescriptor', 'imageSetType'] as $tokenKey) {
                if (array_key_exists($tokenKey, $token)) {
                    $item[$tokenKey] = $token[$tokenKey];
                }
            }
            foreach (['importCondition', 'importLayer', 'importLayerAnonymous', 'importSupports', 'importMedia'] as $tokenKey) {
                if (array_key_exists($tokenKey, $token)) {
                    $item[$tokenKey] = $token[$tokenKey];
                }
            }

            foreach ($referenceDiagnostics as $diagnostic) {
                $diagnostics[] = [
                    'referenceIndex' => $item['index'],
                    'styleReferenceIndex' => $item['styleReferenceIndex'],
                    'kind' => $item['kind'],
                    'href' => $item['href'],
                ] + $diagnostic;
            }
            $references[] = $item;
        }

        $summary = self::xhtmlSideEffectReferenceSummary($references);

        return [
            'index' => $index,
            'kind' => $kind,
            'sourcePart' => $part,
            'element' => $element->localName,
            'namespace' => $element->namespaceURI,
            'id' => self::nullableAttribute($element, 'id'),
            'class' => self::nullableAttribute($element, 'class'),
            'classes' => self::spaceDelimited($element->getAttribute('class')),
            'attribute' => $attribute,
            'media' => $kind === 'style-element' ? self::nullableAttribute($element, 'media') : null,
            'type' => $kind === 'style-element' ? self::nullableAttribute($element, 'type') : null,
            'title' => $kind === 'style-element' ? self::nullableAttribute($element, 'title') : null,
            'cssLength' => strlen($css),
            'cssSha256' => $css === '' ? null : hash('sha256', $css),
            'references' => $references,
            'referenceCount' => $summary['referenceCount'],
            'externalReferenceCount' => $summary['externalReferenceCount'],
            'missingReferenceCount' => $summary['missingReferenceCount'],
            'encryptedReferenceCount' => $summary['encryptedReferenceCount'],
            'requiresReview' => $references !== [] || $diagnostics !== [],
            'language' => self::xmlLang($element),
            'direction' => self::direction($element),
            'attributes' => self::elementAttributes($element),
            'diagnostics' => $diagnostics,
        ];
    }

    /**
     * @param array<string, array<string, mixed>> $manifestByPart
     *
     * @return array<string, mixed>
     */
    private function xhtmlFormSideEffectReport(
        ZipPackage $package,
        string $part,
        \DOMElement $element,
        array $manifestByPart,
        int $index
    ): array {
        $action = self::nullableAttribute($element, 'action');
        $methodRaw = self::nullableAttribute($element, 'method');
        $method = self::xhtmlFormMethod($methodRaw);
        $reference = null;
        $referenceDiagnostics = [];
        if ($action !== null && trim($action) !== '') {
            $reference = $this->xhtmlSideEffectReferenceReport(
                $package,
                $part,
                $action,
                $manifestByPart,
                'xhtml-form-action'
            );
            $referenceDiagnostics = $reference['diagnostics'];
        }

        $controls = self::xhtmlFormControls($element);
        $diagnostics = [[
            'type' => 'active-xhtml-form-submission',
            'message' => 'EPUB XHTML form submission remains inert and requires side-effect target review',
        ]];
        if (
            $methodRaw !== null
            && trim($methodRaw) !== ''
            && !in_array(strtolower(trim($methodRaw)), ['get', 'post', 'dialog'], true)
        ) {
            $diagnostics[] = [
                'type' => 'invalid-xhtml-form-method',
                'method' => $methodRaw,
                'message' => 'EPUB XHTML form method is preserved but not a standard get, post, or dialog method',
            ];
        }
        array_push($diagnostics, ...$referenceDiagnostics);
        $summary = self::xhtmlSideEffectReferenceSummary($reference === null ? [] : [$reference]);

        return [
            'index' => $index,
            'kind' => 'form',
            'sourcePart' => $part,
            'element' => $element->localName,
            'namespace' => $element->namespaceURI,
            'id' => self::nullableAttribute($element, 'id'),
            'class' => self::nullableAttribute($element, 'class'),
            'classes' => self::spaceDelimited($element->getAttribute('class')),
            'name' => self::nullableAttribute($element, 'name'),
            'method' => $method,
            'methodRaw' => $methodRaw,
            'action' => $action,
            'targetFrame' => self::nullableAttribute($element, 'target'),
            'enctype' => self::nullableAttribute($element, 'enctype'),
            'autocomplete' => self::nullableAttribute($element, 'autocomplete'),
            'novalidate' => $element->hasAttribute('novalidate'),
            'target' => is_array($reference) ? $reference['target'] : null,
            'part' => is_array($reference) ? $reference['part'] : null,
            'fragment' => is_array($reference) ? $reference['fragment'] : null,
            'fragmentKind' => is_array($reference) ? $reference['fragmentKind'] : null,
            'epubCfi' => is_array($reference) ? $reference['epubCfi'] : null,
            'mediaFragment' => is_array($reference) ? $reference['mediaFragment'] : null,
            'external' => is_array($reference) ? $reference['external'] : false,
            'exists' => is_array($reference) ? $reference['exists'] : null,
            'byteLength' => is_array($reference) ? $reference['byteLength'] : null,
            'crc32' => is_array($reference) ? $reference['crc32'] : null,
            'manifestId' => is_array($reference) ? $reference['manifestId'] : null,
            'mediaType' => is_array($reference) ? $reference['mediaType'] : null,
            'encrypted' => is_array($reference) ? $reference['encrypted'] : false,
            'canExposeBytes' => is_array($reference) ? $reference['canExposeBytes'] : null,
            'controlCount' => count($controls),
            'submitControlCount' => count(array_filter(
                $controls,
                static fn (array $control): bool => ($control['submit'] ?? false) === true,
            )),
            'controls' => $controls,
            'references' => is_array($reference) ? [$reference] : [],
            'referenceCount' => $summary['referenceCount'],
            'externalReferenceCount' => $summary['externalReferenceCount'],
            'missingReferenceCount' => $summary['missingReferenceCount'],
            'encryptedReferenceCount' => $summary['encryptedReferenceCount'],
            'requiresReview' => true,
            'language' => self::xmlLang($element),
            'direction' => self::direction($element),
            'attributes' => self::elementAttributes($element),
            'diagnostics' => $diagnostics,
        ];
    }

    /**
     * @param array<string, array<string, mixed>> $manifestByPart
     *
     * @return ?array<string, mixed>
     */
    private function xhtmlFormControlSideEffectReport(
        ZipPackage $package,
        string $part,
        \DOMElement $element,
        array $manifestByPart,
        int $index
    ): ?array {
        $control = self::xhtmlFormControlReport($element, 0);
        if (($control['submit'] ?? false) !== true) {
            return null;
        }

        $formAction = is_string($control['formAction'] ?? null) ? $control['formAction'] : null;
        if ($formAction === null || trim($formAction) === '') {
            return null;
        }

        $reference = null;
        $referenceDiagnostics = [];
        $reference = $this->xhtmlSideEffectReferenceReport(
            $package,
            $part,
            $formAction,
            $manifestByPart,
            'xhtml-form-control-action'
        );
        $referenceDiagnostics = $reference['diagnostics'];

        $diagnostics = [[
            'type' => 'active-xhtml-form-control-submission',
            'message' => 'EPUB XHTML submit control remains inert and requires side-effect target review',
        ]];
        array_push($diagnostics, ...$referenceDiagnostics);
        $summary = self::xhtmlSideEffectReferenceSummary($reference === null ? [] : [$reference]);

        return [
            'index' => $index,
            'kind' => 'form-control',
            'sourcePart' => $part,
            'element' => $element->localName,
            'namespace' => $element->namespaceURI,
            'id' => self::nullableAttribute($element, 'id'),
            'class' => self::nullableAttribute($element, 'class'),
            'classes' => self::spaceDelimited($element->getAttribute('class')),
            'control' => $control,
            'controlElement' => $control['element'],
            'name' => $control['name'],
            'type' => $control['type'],
            'typeRaw' => $control['typeRaw'],
            'value' => $control['value'],
            'form' => $control['form'],
            'formAction' => $formAction,
            'target' => is_array($reference) ? $reference['target'] : null,
            'part' => is_array($reference) ? $reference['part'] : null,
            'fragment' => is_array($reference) ? $reference['fragment'] : null,
            'fragmentKind' => is_array($reference) ? $reference['fragmentKind'] : null,
            'epubCfi' => is_array($reference) ? $reference['epubCfi'] : null,
            'mediaFragment' => is_array($reference) ? $reference['mediaFragment'] : null,
            'external' => is_array($reference) ? $reference['external'] : false,
            'exists' => is_array($reference) ? $reference['exists'] : null,
            'byteLength' => is_array($reference) ? $reference['byteLength'] : null,
            'crc32' => is_array($reference) ? $reference['crc32'] : null,
            'manifestId' => is_array($reference) ? $reference['manifestId'] : null,
            'mediaType' => is_array($reference) ? $reference['mediaType'] : null,
            'encrypted' => is_array($reference) ? $reference['encrypted'] : false,
            'canExposeBytes' => is_array($reference) ? $reference['canExposeBytes'] : null,
            'references' => is_array($reference) ? [$reference] : [],
            'referenceCount' => $summary['referenceCount'],
            'externalReferenceCount' => $summary['externalReferenceCount'],
            'missingReferenceCount' => $summary['missingReferenceCount'],
            'encryptedReferenceCount' => $summary['encryptedReferenceCount'],
            'requiresReview' => true,
            'language' => self::xmlLang($element),
            'direction' => self::direction($element),
            'attributes' => self::elementAttributes($element),
            'diagnostics' => $diagnostics,
        ];
    }

    /**
     * @param array<string, array<string, mixed>> $manifestByPart
     *
     * @return ?array<string, mixed>
     */
    private function xhtmlAnchorPingSideEffectReport(
        ZipPackage $package,
        string $part,
        \DOMElement $element,
        array $manifestByPart,
        int $index
    ): ?array {
        $pingRaw = self::nullableAttribute($element, 'ping');
        if ($pingRaw === null || trim($pingRaw) === '') {
            return null;
        }

        $pings = [];
        $diagnostics = [[
            'type' => 'active-xhtml-anchor-ping',
            'message' => 'EPUB XHTML anchor ping targets remain inert and require side-effect target review',
        ]];
        foreach (self::spaceDelimited($pingRaw) as $pingIndex => $pingHref) {
            $reference = $this->xhtmlSideEffectReferenceReport(
                $package,
                $part,
                $pingHref,
                $manifestByPart,
                'xhtml-anchor-ping'
            );
            $reference['pingIndex'] = $pingIndex;
            $pings[] = $reference;
            foreach ($reference['diagnostics'] as $diagnostic) {
                $diagnostics[] = [
                    'pingIndex' => $pingIndex,
                    'ping' => $pingHref,
                ] + $diagnostic;
            }
        }
        if ($pings === []) {
            return null;
        }

        $summary = self::xhtmlSideEffectReferenceSummary($pings);

        return [
            'index' => $index,
            'kind' => 'anchor-ping',
            'sourcePart' => $part,
            'element' => $element->localName,
            'namespace' => $element->namespaceURI,
            'id' => self::nullableAttribute($element, 'id'),
            'class' => self::nullableAttribute($element, 'class'),
            'classes' => self::spaceDelimited($element->getAttribute('class')),
            'href' => self::nullableAttribute($element, 'href'),
            'pingRaw' => $pingRaw,
            'pingCount' => count($pings),
            'externalPingCount' => $summary['externalReferenceCount'],
            'missingPingCount' => $summary['missingReferenceCount'],
            'encryptedPingCount' => $summary['encryptedReferenceCount'],
            'pings' => $pings,
            'references' => $pings,
            'referenceCount' => $summary['referenceCount'],
            'externalReferenceCount' => $summary['externalReferenceCount'],
            'missingReferenceCount' => $summary['missingReferenceCount'],
            'encryptedReferenceCount' => $summary['encryptedReferenceCount'],
            'requiresReview' => true,
            'language' => self::xmlLang($element),
            'direction' => self::direction($element),
            'attributes' => self::elementAttributes($element),
            'diagnostics' => $diagnostics,
        ];
    }

    /**
     * @param array<string, array<string, mixed>> $manifestByPart
     *
     * @return array<string, mixed>
     */
    private function xhtmlSideEffectReferenceReport(
        ZipPackage $package,
        string $part,
        string $href,
        array $manifestByPart,
        string $context
    ): array {
        $reference = $this->packageReference($package, $part, $href, $manifestByPart, $context);
        $diagnostics = $reference['diagnostics'];
        if (($reference['encrypted'] ?? false) === true) {
            $diagnostics[] = [
                'type' => 'encrypted-' . $context . '-reference',
                'part' => $reference['part'],
                'message' => 'EPUB XHTML side-effect target references an encrypted package part that cannot be exposed directly',
            ];
        }

        return [
            'href' => $href,
            'target' => $reference['target'],
            'part' => $reference['part'],
            'fragment' => $reference['fragment'],
            'fragmentKind' => $reference['fragmentKind'],
            'epubCfi' => $reference['epubCfi'],
            'mediaFragment' => $reference['mediaFragment'],
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
     * @param list<array<string, mixed>> $references
     *
     * @return array{referenceCount:int, externalReferenceCount:int, missingReferenceCount:int, encryptedReferenceCount:int}
     */
    private static function xhtmlSideEffectReferenceSummary(array $references): array
    {
        $externalReferenceCount = 0;
        $missingReferenceCount = 0;
        $encryptedReferenceCount = 0;
        foreach ($references as $reference) {
            if (($reference['external'] ?? false) === true) {
                ++$externalReferenceCount;
            }
            if (($reference['exists'] ?? true) !== true && ($reference['external'] ?? false) !== true) {
                ++$missingReferenceCount;
            }
            if (($reference['encrypted'] ?? false) === true) {
                ++$encryptedReferenceCount;
            }
        }

        return [
            'referenceCount' => count($references),
            'externalReferenceCount' => $externalReferenceCount,
            'missingReferenceCount' => $missingReferenceCount,
            'encryptedReferenceCount' => $encryptedReferenceCount,
        ];
    }

    private static function xhtmlFormMethod(?string $methodRaw): string
    {
        $method = strtolower(trim((string) $methodRaw));
        if (in_array($method, ['get', 'post', 'dialog'], true)) {
            return $method;
        }

        return 'get';
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function xhtmlFormControls(\DOMElement $form): array
    {
        $controls = [];
        foreach ($form->getElementsByTagName('*') as $control) {
            if (!$control instanceof \DOMElement || (string) $control->namespaceURI !== self::XHTML_NS) {
                continue;
            }
            $localName = strtolower($control->localName);
            if (!in_array($localName, ['button', 'input', 'output', 'select', 'textarea'], true)) {
                continue;
            }

            $controls[] = self::xhtmlFormControlReport($control, count($controls));
        }

        return $controls;
    }

    /**
     * @return array<string, mixed>
     */
    private static function xhtmlFormControlReport(\DOMElement $element, int $index): array
    {
        $localName = strtolower($element->localName);
        $typeRaw = self::nullableAttribute($element, 'type');
        $type = self::xhtmlFormControlType($element);
        $selectOptions = $localName === 'select' ? self::xhtmlSelectOptions($element) : [];
        $selectedOptions = self::xhtmlSelectedOptions($selectOptions);

        return [
            'index' => $index,
            'element' => $localName,
            'id' => self::nullableAttribute($element, 'id'),
            'class' => self::nullableAttribute($element, 'class'),
            'classes' => self::spaceDelimited($element->getAttribute('class')),
            'name' => self::nullableAttribute($element, 'name'),
            'type' => $type,
            'typeRaw' => $typeRaw,
            'value' => self::nullableAttribute($element, 'value'),
            'text' => in_array($localName, ['button', 'output', 'textarea'], true) ? self::normalizedText($element) : null,
            'form' => self::nullableAttribute($element, 'form'),
            'formAction' => self::nullableAttribute($element, 'formaction'),
            'forRaw' => $localName === 'output' ? self::nullableAttribute($element, 'for') : null,
            'forIds' => $localName === 'output' ? self::spaceDelimited($element->getAttribute('for')) : [],
            'options' => $selectOptions,
            'optionCount' => count($selectOptions),
            'selectedOptionCount' => count($selectedOptions),
            'selectedValues' => array_map(
                static fn (array $option): string => (string) $option['value'],
                $selectedOptions
            ),
            'disabled' => $element->hasAttribute('disabled'),
            'required' => $element->hasAttribute('required'),
            'checked' => $element->hasAttribute('checked'),
            'readonly' => $element->hasAttribute('readonly'),
            'multiple' => $element->hasAttribute('multiple'),
            'submit' => self::xhtmlFormControlSubmits($element),
            'attributes' => self::elementAttributes($element),
        ];
    }

    private static function xhtmlFormControlType(\DOMElement $element): string
    {
        $localName = strtolower($element->localName);
        if ($localName === 'button') {
            $type = strtolower(trim($element->getAttribute('type')));

            return $type === '' ? 'submit' : $type;
        }
        if ($localName === 'input') {
            $type = strtolower(trim($element->getAttribute('type')));

            return $type === '' ? 'text' : $type;
        }

        return $localName;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function xhtmlSelectOptions(\DOMElement $select): array
    {
        $options = [];
        foreach ($select->getElementsByTagName('*') as $candidate) {
            if (
                !$candidate instanceof \DOMElement
                || (string) $candidate->namespaceURI !== self::XHTML_NS
                || strtolower($candidate->localName) !== 'option'
            ) {
                continue;
            }

            $parent = $candidate->parentNode;
            $optgroup = $parent instanceof \DOMElement
                && (string) $parent->namespaceURI === self::XHTML_NS
                && strtolower($parent->localName) === 'optgroup'
                    ? $parent
                    : null;
            $text = self::normalizedText($candidate);
            $valueRaw = self::nullableAttribute($candidate, 'value');

            $options[] = [
                'index' => count($options),
                'id' => self::nullableAttribute($candidate, 'id'),
                'value' => $valueRaw ?? $text,
                'valueRaw' => $valueRaw,
                'label' => self::nullableAttribute($candidate, 'label'),
                'text' => $text,
                'selected' => $candidate->hasAttribute('selected'),
                'optionDisabled' => $candidate->hasAttribute('disabled'),
                'optgroupLabel' => $optgroup instanceof \DOMElement ? self::nullableAttribute($optgroup, 'label') : null,
                'optgroupDisabled' => $optgroup instanceof \DOMElement && $optgroup->hasAttribute('disabled'),
                'disabled' => $candidate->hasAttribute('disabled')
                    || ($optgroup instanceof \DOMElement && $optgroup->hasAttribute('disabled')),
                'attributes' => self::elementAttributes($candidate),
                'optgroupAttributes' => $optgroup instanceof \DOMElement ? self::elementAttributes($optgroup) : [],
            ];
        }

        return $options;
    }

    /**
     * @param list<array<string, mixed>> $options
     *
     * @return list<array<string, mixed>>
     */
    private static function xhtmlSelectedOptions(array $options): array
    {
        return array_values(array_filter(
            $options,
            static fn (array $option): bool => ($option['selected'] ?? false) === true,
        ));
    }

    private static function xhtmlFormControlSubmits(\DOMElement $element): bool
    {
        $localName = strtolower($element->localName);
        $type = self::xhtmlFormControlType($element);
        if ($localName === 'button') {
            return $type === 'submit';
        }
        if ($localName === 'input') {
            return in_array($type, ['image', 'submit'], true);
        }

        return false;
    }

    /**
     * @param array<string, array<string, mixed>> $manifestByPart
     *
     * @return array<string, mixed>
     */
    private function xhtmlMetaRefreshReport(
        ZipPackage $package,
        string $part,
        \DOMElement $element,
        array $manifestByPart,
        int $index,
        int $referenceIndex
    ): ?array {
        $httpEquivRaw = self::nullableAttribute($element, 'http-equiv');
        $httpEquiv = $httpEquivRaw === null ? null : strtolower(trim($httpEquivRaw));
        if ($httpEquiv !== 'refresh') {
            return null;
        }

        $content = trim($element->getAttribute('content'));
        $parsed = self::parseXhtmlMetaRefreshContent($content);
        $diagnostics = [[
            'type' => 'active-xhtml-meta-refresh',
            'message' => 'EPUB XHTML meta refresh remains inert and requires redirect-target review',
        ]];
        if (($parsed['delayValid'] ?? false) !== true) {
            $diagnostics[] = [
                'type' => 'invalid-xhtml-meta-refresh-delay',
                'delay' => $parsed['delayRaw'],
                'message' => 'EPUB XHTML meta refresh delay must be a non-negative decimal number',
            ];
        }

        $reference = null;
        $referenceData = null;
        $referenceDiagnostics = [];
        $url = is_string($parsed['url'] ?? null) ? $parsed['url'] : null;
        if ($url === null || $url === '') {
            $diagnostics[] = [
                'type' => 'missing-xhtml-meta-refresh-url',
                'message' => 'EPUB XHTML meta refresh content does not contain a url target',
            ];
        } else {
            $referenceData = $this->packageReference(
                $package,
                $part,
                $url,
                $manifestByPart,
                'xhtml-meta-refresh'
            );
            $referenceDiagnostics = $referenceData['diagnostics'];
            if (($referenceData['encrypted'] ?? false) === true) {
                $referenceDiagnostics[] = [
                    'type' => 'encrypted-xhtml-meta-refresh-reference',
                    'part' => $referenceData['part'],
                    'message' => 'EPUB XHTML meta refresh references an encrypted package part that cannot be exposed directly',
                ];
            }
            array_push($diagnostics, ...$referenceDiagnostics);
            $reference = [
                'index' => $referenceIndex,
                'element' => $element->localName,
                'attribute' => 'content',
                'href' => $url,
                'target' => $referenceData['target'],
                'part' => $referenceData['part'],
                'fragment' => $referenceData['fragment'],
                'fragmentKind' => $referenceData['fragmentKind'],
                'epubCfi' => $referenceData['epubCfi'],
                'mediaFragment' => $referenceData['mediaFragment'],
                'external' => $referenceData['external'],
                'exists' => $referenceData['exists'],
                'byteLength' => $referenceData['byteLength'],
                'crc32' => $referenceData['crc32'],
                'manifestId' => $referenceData['manifestId'],
                'mediaType' => $referenceData['mediaType'],
                'encrypted' => $referenceData['encrypted'],
                'canExposeBytes' => $referenceData['canExposeBytes'],
                'refreshIndex' => $index,
                'metaHttpEquiv' => $httpEquiv,
                'metaContent' => $content,
                'diagnostics' => $referenceDiagnostics,
            ];
        }

        return [
            'index' => $index,
            'sourcePart' => $part,
            'element' => $element->localName,
            'namespace' => $element->namespaceURI,
            'id' => self::nullableAttribute($element, 'id'),
            'class' => self::nullableAttribute($element, 'class'),
            'classes' => self::spaceDelimited($element->getAttribute('class')),
            'httpEquiv' => $httpEquiv,
            'httpEquivRaw' => $httpEquivRaw,
            'content' => $content,
            'delayRaw' => $parsed['delayRaw'],
            'delaySeconds' => $parsed['delaySeconds'],
            'urlRaw' => $parsed['urlRaw'],
            'url' => $url,
            'target' => is_array($referenceData) ? $referenceData['target'] : null,
            'part' => is_array($referenceData) ? $referenceData['part'] : null,
            'fragment' => is_array($referenceData) ? $referenceData['fragment'] : null,
            'fragmentKind' => is_array($referenceData) ? $referenceData['fragmentKind'] : null,
            'epubCfi' => is_array($referenceData) ? $referenceData['epubCfi'] : null,
            'mediaFragment' => is_array($referenceData) ? $referenceData['mediaFragment'] : null,
            'external' => is_array($referenceData) ? $referenceData['external'] : false,
            'exists' => is_array($referenceData) ? $referenceData['exists'] : null,
            'byteLength' => is_array($referenceData) ? $referenceData['byteLength'] : null,
            'crc32' => is_array($referenceData) ? $referenceData['crc32'] : null,
            'manifestId' => is_array($referenceData) ? $referenceData['manifestId'] : null,
            'mediaType' => is_array($referenceData) ? $referenceData['mediaType'] : null,
            'encrypted' => is_array($referenceData) ? $referenceData['encrypted'] : false,
            'canExposeBytes' => is_array($referenceData) ? $referenceData['canExposeBytes'] : null,
            'requiresReview' => true,
            'valid' => $url !== null && ($parsed['delayValid'] ?? false) === true && $referenceDiagnostics === [],
            'language' => self::xmlLang($element),
            'direction' => self::direction($element),
            'attributes' => self::elementAttributes($element),
            'diagnostics' => $diagnostics,
            'reference' => $reference,
        ];
    }

    /**
     * @return array{delayRaw:string, delaySeconds:?float, delayValid:bool, urlRaw:?string, url:?string}
     */
    private static function parseXhtmlMetaRefreshContent(string $content): array
    {
        $segments = explode(';', $content);
        $delayRaw = trim((string) array_shift($segments));
        $urlRaw = null;

        foreach ($segments as $segment) {
            if (preg_match('/^\s*url\s*=\s*(.*?)\s*$/i', $segment, $matches) !== 1) {
                continue;
            }

            $urlRaw = trim($matches[1]);
            break;
        }

        $url = null;
        if ($urlRaw !== null) {
            $url = trim($urlRaw);
            $quote = $url[0] ?? '';
            if (($quote === '"' || $quote === "'") && substr($url, -1) === $quote) {
                $url = trim(substr($url, 1, -1));
            }
            if ($url === '') {
                $url = null;
            }
        }

        $delayValid = preg_match('/^(?:[0-9]+(?:\.[0-9]+)?|\.[0-9]+)$/', $delayRaw) === 1;

        return [
            'delayRaw' => $delayRaw,
            'delaySeconds' => $delayValid ? (float) $delayRaw : null,
            'delayValid' => $delayValid,
            'urlRaw' => $urlRaw,
            'url' => $url,
        ];
    }

    /**
     * @param array<string, array<string, mixed>> $manifestByPart
     *
     * @return array<string, mixed>
     */
    private function xhtmlLinkReport(
        ZipPackage $package,
        string $part,
        \DOMElement $element,
        array $manifestByPart,
        int $index
    ): array {
        $href = self::nullableAttribute($element, 'href');
        $rel = self::spaceDelimited($element->getAttribute('rel'));
        $relLower = array_values(array_map(
            static fn (string $token): string => strtolower($token),
            $rel,
        ));
        $policy = self::xhtmlLinkPolicy($relLower);
        $active = in_array($policy, ['stylesheet', 'preload', 'modulepreload', 'prefetch'], true);
        $reference = $href === null ? null : $this->packageReference(
            $package,
            $part,
            $href,
            $manifestByPart,
            'xhtml-link-resource'
        );
        $diagnostics = [];
        if ($href === null) {
            $diagnostics[] = [
                'type' => 'missing-xhtml-link-href',
                'message' => 'EPUB XHTML link element is missing href',
            ];
        }
        if ($rel === []) {
            $diagnostics[] = [
                'type' => 'missing-xhtml-link-rel',
                'message' => 'EPUB XHTML link element is missing rel tokens',
            ];
        }
        if ($active) {
            $diagnostics[] = [
                'type' => 'active-xhtml-link-resource',
                'policy' => $policy,
                'message' => 'EPUB XHTML link element declares an active resource that remains inert and requires review',
            ];
        }

        array_push($diagnostics, ...(is_array($reference) ? $reference['diagnostics'] : []));

        $as = self::nullableAttribute($element, 'as');
        if (in_array($policy, ['preload', 'modulepreload'], true) && $as === null) {
            $diagnostics[] = [
                'type' => 'xhtml-link-preload-missing-as',
                'policy' => $policy,
                'message' => 'EPUB XHTML preload link is missing an as attribute for static package review',
            ];
        }

        $declaredType = self::nullableAttribute($element, 'type');
        if ($policy === 'stylesheet' && $declaredType !== null && !self::mediaTypeBaseEquals($declaredType, 'text/css')) {
            $diagnostics[] = [
                'type' => 'xhtml-stylesheet-link-non-css-type',
                'typeAttribute' => $declaredType,
                'message' => 'EPUB XHTML stylesheet links should declare text/css when a type attribute is present',
            ];
        }
        if (is_array($reference) && ($reference['encrypted'] ?? false) === true) {
            $diagnostics[] = [
                'type' => 'encrypted-xhtml-link-resource',
                'part' => $reference['part'],
                'message' => 'EPUB XHTML link resource references an encrypted package part that cannot be exposed directly',
            ];
        }

        $byteSha256 = null;
        if (
            is_array($reference)
            && ($reference['external'] ?? false) !== true
            && ($reference['exists'] ?? false) === true
            && ($reference['canExposeBytes'] ?? false) === true
            && is_string($reference['part'] ?? null)
        ) {
            try {
                $byteSha256 = hash('sha256', $package->read((string) $reference['part']));
            } catch (\Throwable $exception) {
                $diagnostics[] = [
                    'type' => 'xhtml-link-resource-bytes-unavailable',
                    'part' => $reference['part'],
                    'message' => $exception->getMessage(),
                ];
            }
        }

        return [
            'index' => $index,
            'sourcePart' => $part,
            'element' => $element->localName,
            'namespace' => $element->namespaceURI,
            'id' => self::nullableAttribute($element, 'id'),
            'class' => self::nullableAttribute($element, 'class'),
            'classes' => self::spaceDelimited($element->getAttribute('class')),
            'relRaw' => self::nullableAttribute($element, 'rel'),
            'rel' => $relLower,
            'primaryRel' => $relLower[0] ?? null,
            'policy' => $policy,
            'active' => $active,
            'passive' => !$active,
            'requiresReview' => $active || $diagnostics !== [],
            'href' => $href,
            'target' => is_array($reference) ? $reference['target'] : null,
            'part' => is_array($reference) ? $reference['part'] : null,
            'fragment' => is_array($reference) ? $reference['fragment'] : null,
            'fragmentKind' => is_array($reference) ? $reference['fragmentKind'] : null,
            'epubCfi' => is_array($reference) ? $reference['epubCfi'] : null,
            'mediaFragment' => is_array($reference) ? $reference['mediaFragment'] : null,
            'external' => is_array($reference) ? $reference['external'] : false,
            'exists' => is_array($reference) ? $reference['exists'] : null,
            'byteLength' => is_array($reference) ? $reference['byteLength'] : null,
            'crc32' => is_array($reference) ? $reference['crc32'] : null,
            'byteSha256' => $byteSha256,
            'manifestId' => is_array($reference) ? $reference['manifestId'] : null,
            'mediaType' => is_array($reference) ? $reference['mediaType'] : null,
            'encrypted' => is_array($reference) ? $reference['encrypted'] : false,
            'canExposeBytes' => is_array($reference) ? $reference['canExposeBytes'] : null,
            'declaredType' => $declaredType,
            'media' => self::nullableAttribute($element, 'media'),
            'hreflang' => self::nullableAttribute($element, 'hreflang'),
            'title' => self::nullableAttribute($element, 'title'),
            'as' => $as,
            'sizes' => self::nullableAttribute($element, 'sizes'),
            'color' => self::nullableAttribute($element, 'color'),
            'crossorigin' => self::nullableAttribute($element, 'crossorigin'),
            'integrity' => self::nullableAttribute($element, 'integrity'),
            'referrerpolicy' => self::nullableAttribute($element, 'referrerpolicy'),
            'language' => self::xmlLang($element),
            'direction' => self::direction($element),
            'attributes' => self::elementAttributes($element),
            'diagnostics' => $diagnostics,
        ];
    }

    /**
     * @param list<string> $rel
     */
    private static function xhtmlLinkPolicy(array $rel): string
    {
        foreach ([
            'stylesheet',
            'modulepreload',
            'preload',
            'prefetch',
            'icon',
            'canonical',
            'alternate',
        ] as $policy) {
            if (in_array($policy, $rel, true)) {
                return $policy;
            }
        }

        return $rel === [] ? 'untyped' : 'metadata';
    }

    /**
     * @param array<string, array<string, mixed>> $manifestByPart
     *
     * @return array<string, mixed>
     */
    private function xhtmlScriptReport(
        ZipPackage $package,
        string $part,
        \DOMElement $element,
        array $manifestByPart,
        int $index
    ): array {
        $src = self::nullableAttribute($element, 'src');
        $reference = $src === null ? null : $this->packageReference(
            $package,
            $part,
            $src,
            $manifestByPart,
            'xhtml-script-source'
        );
        $diagnostics = is_array($reference) ? $reference['diagnostics'] : [];
        if (is_array($reference) && ($reference['encrypted'] ?? false) === true) {
            $diagnostics[] = [
                'type' => 'encrypted-xhtml-script-source',
                'part' => $reference['part'],
                'message' => 'EPUB XHTML script source references an encrypted package part that cannot be exposed directly',
            ];
        }

        $inlineText = $src === null ? (string) $element->textContent : '';
        if ($src === null && trim($inlineText) !== '') {
            $diagnostics[] = [
                'type' => 'inline-xhtml-script-content',
                'message' => 'EPUB XHTML content contains inline script source that remains inert and requires review',
            ];
        }

        $byteSha256 = null;
        if (
            is_array($reference)
            && ($reference['external'] ?? false) !== true
            && ($reference['exists'] ?? false) === true
            && ($reference['canExposeBytes'] ?? false) === true
            && is_string($reference['part'] ?? null)
        ) {
            try {
                $byteSha256 = hash('sha256', $package->read((string) $reference['part']));
            } catch (\Throwable $exception) {
                $diagnostics[] = [
                    'type' => 'xhtml-script-source-bytes-unavailable',
                    'part' => $reference['part'],
                    'message' => $exception->getMessage(),
                ];
            }
        }

        return [
            'index' => $index,
            'sourcePart' => $part,
            'element' => $element->localName,
            'namespace' => $element->namespaceURI,
            'id' => self::nullableAttribute($element, 'id'),
            'class' => self::nullableAttribute($element, 'class'),
            'classes' => self::spaceDelimited($element->getAttribute('class')),
            'type' => self::nullableAttribute($element, 'type'),
            'src' => $src,
            'target' => is_array($reference) ? $reference['target'] : null,
            'part' => is_array($reference) ? $reference['part'] : null,
            'fragment' => is_array($reference) ? $reference['fragment'] : null,
            'fragmentKind' => is_array($reference) ? $reference['fragmentKind'] : null,
            'epubCfi' => is_array($reference) ? $reference['epubCfi'] : null,
            'mediaFragment' => is_array($reference) ? $reference['mediaFragment'] : null,
            'external' => is_array($reference) ? $reference['external'] : false,
            'exists' => is_array($reference) ? $reference['exists'] : null,
            'byteLength' => is_array($reference) ? $reference['byteLength'] : null,
            'crc32' => is_array($reference) ? $reference['crc32'] : null,
            'byteSha256' => $byteSha256,
            'manifestId' => is_array($reference) ? $reference['manifestId'] : null,
            'mediaType' => is_array($reference) ? $reference['mediaType'] : null,
            'encrypted' => is_array($reference) ? $reference['encrypted'] : false,
            'canExposeBytes' => is_array($reference) ? $reference['canExposeBytes'] : null,
            'inline' => $src === null,
            'inlineTextLength' => strlen($inlineText),
            'inlineTextSha256' => $inlineText === '' ? null : hash('sha256', $inlineText),
            'async' => $element->hasAttribute('async'),
            'defer' => $element->hasAttribute('defer'),
            'nomodule' => $element->hasAttribute('nomodule'),
            'crossorigin' => self::nullableAttribute($element, 'crossorigin'),
            'integrity' => self::nullableAttribute($element, 'integrity'),
            'language' => self::xmlLang($element),
            'direction' => self::direction($element),
            'attributes' => self::elementAttributes($element),
            'diagnostics' => $diagnostics,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function xhtmlScriptEventHandlerReport(\DOMElement $element, string $attributeName, int $index): array
    {
        $value = trim($element->getAttribute($attributeName));

        return [
            'index' => $index,
            'element' => $element->localName,
            'namespace' => $element->namespaceURI,
            'elementId' => self::nullableAttribute($element, 'id'),
            'class' => self::nullableAttribute($element, 'class'),
            'classes' => self::spaceDelimited($element->getAttribute('class')),
            'attribute' => $attributeName,
            'value' => $value,
            'valueLength' => strlen($value),
            'valueSha256' => $value === '' ? null : hash('sha256', $value),
            'language' => self::xmlLang($element),
            'direction' => self::direction($element),
            'attributes' => self::elementAttributes($element),
            'diagnostics' => [[
                'type' => 'scripted-xhtml-content-attribute',
                'attribute' => $attributeName,
                'message' => 'EPUB XHTML content carries an inline script event handler that requires review',
            ]],
        ];
    }

    /**
     * @param array<string, mixed> $reference
     *
     * @return array<string, mixed>
     */
    private static function xhtmlJavascriptReferenceReport(
        \DOMElement $element,
        string $attributeName,
        array $reference,
        int $index
    ): array {
        return [
            'index' => $index,
            'element' => $element->localName,
            'namespace' => $element->namespaceURI,
            'elementId' => self::nullableAttribute($element, 'id'),
            'attribute' => $attributeName,
            'href' => is_string($reference['href'] ?? null) ? $reference['href'] : null,
            'target' => is_string($reference['target'] ?? null) ? $reference['target'] : null,
            'part' => is_string($reference['part'] ?? null) ? $reference['part'] : null,
            'external' => (bool) ($reference['external'] ?? false),
            'exists' => (bool) ($reference['exists'] ?? false),
            'diagnostics' => is_array($reference['diagnostics'] ?? null) ? array_values($reference['diagnostics']) : [],
        ];
    }

    /**
     * @param array<string, array<string, mixed>> $elementIds
     */
    private static function registerXhtmlElementId(\DOMElement $element, array &$elementIds): void
    {
        $id = trim($element->getAttribute('id'));
        if ($id === '' || isset($elementIds[$id])) {
            return;
        }

        $elementIds[$id] = [
            'id' => $id,
            'element' => $element->localName,
            'namespace' => $element->namespaceURI,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function xhtmlSwitchReport(\DOMElement $element, int $index): array
    {
        $cases = [];
        $defaults = [];
        $diagnostics = [];
        $caseDiagnostics = [];

        foreach (self::childElements($element) as $child) {
            if ($child->namespaceURI !== self::EPUB_OPS_NS) {
                continue;
            }

            $localName = strtolower($child->localName);
            if ($localName === 'case') {
                $case = self::xhtmlSwitchCaseReport($child, count($cases));
                foreach ($case['diagnostics'] as $diagnostic) {
                    $caseDiagnostics[] = [
                        'caseIndex' => $case['index'],
                        'caseId' => $case['id'],
                    ] + $diagnostic;
                }
                $cases[] = $case;
                continue;
            }

            if ($localName === 'default') {
                $defaults[] = self::xhtmlSwitchDefaultReport($child, count($defaults));
            }
        }

        if ($defaults === []) {
            $diagnostics[] = [
                'type' => 'missing-epub-switch-default',
                'message' => 'EPUB switch content has no default branch for static package review',
            ];
        } elseif (count($defaults) > 1) {
            $diagnostics[] = [
                'type' => 'multiple-epub-switch-defaults',
                'defaultCount' => count($defaults),
                'message' => 'EPUB switch content has more than one default branch; the bounded review parser preserves all defaults',
            ];
        }
        array_push($diagnostics, ...$caseDiagnostics);

        return [
            'index' => $index,
            'id' => self::nullableAttribute($element, 'id'),
            'element' => $element->localName,
            'namespace' => $element->namespaceURI,
            'class' => self::nullableAttribute($element, 'class'),
            'classes' => self::spaceDelimited($element->getAttribute('class')),
            'caseCount' => count($cases),
            'cases' => $cases,
            'defaultCount' => count($defaults),
            'defaults' => $defaults,
            'attributes' => self::elementAttributes($element),
            'valid' => $diagnostics === [],
            'diagnostics' => $diagnostics,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function xhtmlSwitchCaseReport(\DOMElement $element, int $index): array
    {
        $requiredNamespace = self::nullableAttribute($element, 'required-namespace');
        $requiredModules = self::spaceDelimited($element->getAttribute('required-modules'));
        $diagnostics = [];

        if ($requiredNamespace === null && $requiredModules === []) {
            $diagnostics[] = [
                'type' => 'epub-switch-case-missing-requirement',
                'message' => 'EPUB switch case has no required-namespace or required-modules gate',
            ];
        }

        return [
            'index' => $index,
            'id' => self::nullableAttribute($element, 'id'),
            'requiredNamespace' => $requiredNamespace,
            'requiredModules' => $requiredModules,
            'text' => self::normalizedText($element),
            'childElementCount' => count(self::childElements($element)),
            'attributes' => self::elementAttributes($element),
            'valid' => $diagnostics === [],
            'diagnostics' => $diagnostics,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function xhtmlSwitchDefaultReport(\DOMElement $element, int $index): array
    {
        return [
            'index' => $index,
            'id' => self::nullableAttribute($element, 'id'),
            'text' => self::normalizedText($element),
            'childElementCount' => count(self::childElements($element)),
            'attributes' => self::elementAttributes($element),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function xhtmlTriggerReport(\DOMElement $element, int $index): array
    {
        $action = self::nullableAttribute($element, 'action');
        $ref = self::nullableAttribute($element, 'ref');
        $event = self::nullableNamespacedAttribute($element, self::XML_EVENTS_NS, 'event', 'ev:event');
        $observer = self::nullableNamespacedAttribute($element, self::XML_EVENTS_NS, 'observer', 'ev:observer');
        $defaultAction = self::nullableNamespacedAttribute($element, self::XML_EVENTS_NS, 'defaultAction', 'ev:defaultAction');
        $phase = self::nullableNamespacedAttribute($element, self::XML_EVENTS_NS, 'phase', 'ev:phase');
        $propagate = self::nullableNamespacedAttribute($element, self::XML_EVENTS_NS, 'propagate', 'ev:propagate');
        $allowedActions = ['show', 'hide', 'play', 'pause', 'resume', 'mute', 'unmute'];
        $diagnostics = [];

        if ($action === null) {
            $diagnostics[] = [
                'type' => 'missing-epub-trigger-action',
                'message' => 'EPUB trigger is missing the required action attribute',
            ];
        } elseif (!in_array($action, $allowedActions, true)) {
            $diagnostics[] = [
                'type' => 'invalid-epub-trigger-action',
                'action' => $action,
                'allowedActions' => $allowedActions,
                'message' => 'EPUB trigger action is not one of the EPUB-defined multimedia actions',
            ];
        }
        if ($ref === null) {
            $diagnostics[] = [
                'type' => 'missing-epub-trigger-ref',
                'message' => 'EPUB trigger is missing the required ref IDREF target',
            ];
        }
        if ($event === null) {
            $diagnostics[] = [
                'type' => 'missing-epub-trigger-event',
                'message' => 'EPUB trigger is missing the required XML Events event attribute',
            ];
        }
        if ($observer === null) {
            $diagnostics[] = [
                'type' => 'missing-epub-trigger-observer',
                'message' => 'EPUB trigger is missing the required XML Events observer attribute',
            ];
        }

        return [
            'index' => $index,
            'id' => self::nullableAttribute($element, 'id'),
            'action' => $action,
            'actionValid' => $action !== null && in_array($action, $allowedActions, true),
            'ref' => $ref,
            'refExists' => false,
            'refElement' => null,
            'observer' => $observer,
            'observerExists' => false,
            'observerElement' => null,
            'event' => $event,
            'defaultAction' => $defaultAction,
            'phase' => $phase,
            'propagate' => $propagate,
            'attributes' => self::elementAttributes($element),
            'valid' => false,
            'diagnostics' => $diagnostics,
        ];
    }

    /**
     * @param list<array<string, mixed>> $triggers
     * @param array<string, array<string, mixed>> $elementIds
     *
     * @return list<array<string, mixed>>
     */
    private static function xhtmlTriggersWithElementResolution(array $triggers, array $elementIds): array
    {
        foreach ($triggers as $index => $trigger) {
            $ref = is_string($trigger['ref'] ?? null) ? $trigger['ref'] : null;
            if ($ref !== null) {
                $target = $elementIds[$ref] ?? null;
                if (is_array($target)) {
                    $triggers[$index]['refExists'] = true;
                    $triggers[$index]['refElement'] = is_string($target['element'] ?? null) ? $target['element'] : null;
                } else {
                    $triggers[$index]['diagnostics'][] = [
                        'type' => 'unresolved-epub-trigger-ref',
                        'ref' => $ref,
                        'message' => 'EPUB trigger ref does not match an element id in the XHTML content document',
                    ];
                }
            }

            $observer = is_string($trigger['observer'] ?? null) ? $trigger['observer'] : null;
            if ($observer !== null) {
                $source = $elementIds[$observer] ?? null;
                if (is_array($source)) {
                    $triggers[$index]['observerExists'] = true;
                    $triggers[$index]['observerElement'] = is_string($source['element'] ?? null) ? $source['element'] : null;
                } else {
                    $triggers[$index]['diagnostics'][] = [
                        'type' => 'unresolved-epub-trigger-observer',
                        'observer' => $observer,
                        'message' => 'EPUB trigger observer does not match an element id in the XHTML content document',
                    ];
                }
            }

            $triggers[$index]['valid'] = $triggers[$index]['diagnostics'] === [];
        }

        return $triggers;
    }

    /**
     * @return array<string, mixed>
     */
    private static function xhtmlTableReport(string $part, \DOMElement $table, int $index): array
    {
        $captionElements = [];
        $colgroupElements = [];
        $sectionElements = [];
        $directRows = [];
        $sectionOrder = [];

        foreach (self::childElements($table) as $child) {
            if ($child->namespaceURI !== self::XHTML_NS) {
                continue;
            }

            $localName = strtolower($child->localName);
            if ($localName === 'caption') {
                $captionElements[] = $child;
                $sectionOrder[] = 'caption';
            } elseif ($localName === 'colgroup') {
                $colgroupElements[] = $child;
                $sectionOrder[] = 'colgroup';
            } elseif (in_array($localName, ['thead', 'tbody', 'tfoot'], true)) {
                $sectionElements[] = $child;
                $sectionOrder[] = $localName;
            } elseif ($localName === 'tr') {
                $directRows[] = $child;
                $sectionOrder[] = 'implicit-body';
            }
        }

        $rows = [];
        $cells = [];
        $diagnostics = [];
        $sectionCounts = [
            'thead' => 0,
            'tbody' => 0,
            'tfoot' => 0,
            'implicit-body' => $directRows === [] ? 0 : 1,
        ];
        $rowCountsBySection = [
            'thead' => 0,
            'tbody' => 0,
            'tfoot' => 0,
            'implicit-body' => count($directRows),
        ];

        foreach ($sectionElements as $sectionElement) {
            $sectionName = strtolower($sectionElement->localName);
            ++$sectionCounts[$sectionName];
            $sectionRows = self::childElements($sectionElement, 'tr', self::XHTML_NS);
            $rowCountsBySection[$sectionName] += count($sectionRows);
            self::appendXhtmlTableRows($sectionRows, $sectionName, $rows, $cells, $diagnostics);
        }
        self::appendXhtmlTableRows($directRows, 'implicit-body', $rows, $cells, $diagnostics);

        if (count($captionElements) > 1) {
            $diagnostics[] = [
                'type' => 'multiple-xhtml-table-captions',
                'captionCount' => count($captionElements),
                'message' => 'EPUB XHTML table has multiple caption elements; bounded review preserves all captions but downstream table conversion should choose one caption policy',
            ];
        }

        foreach (['thead', 'tfoot'] as $sectionName) {
            if ($sectionCounts[$sectionName] > 1) {
                $diagnostics[] = [
                    'type' => 'multiple-xhtml-table-' . $sectionName . '-sections',
                    'section' => $sectionName,
                    'sectionCount' => $sectionCounts[$sectionName],
                    'message' => 'EPUB XHTML table declares multiple ' . $sectionName . ' sections; bounded review preserves the structure for explicit import policy',
                ];
            }
        }

        $headerCells = array_values(array_filter(
            $cells,
            static fn (array $cell): bool => ($cell['header'] ?? false) === true,
        ));
        $dataCells = array_values(array_filter(
            $cells,
            static fn (array $cell): bool => ($cell['header'] ?? false) !== true,
        ));
        $headerScopes = [];
        $headerReferenceCount = 0;
        foreach ($cells as $cell) {
            if (is_string($cell['scope'] ?? null) && $cell['scope'] !== '' && !in_array($cell['scope'], $headerScopes, true)) {
                $headerScopes[] = $cell['scope'];
            }
            $headers = is_array($cell['headers'] ?? null) ? $cell['headers'] : [];
            if ($headers !== []) {
                $headerReferenceCount += count($headers);
            }
        }

        return [
            'index' => $index,
            'sourcePart' => $part,
            'element' => 'table',
            'namespace' => $table->namespaceURI,
            'id' => self::nullableAttribute($table, 'id'),
            'class' => self::nullableAttribute($table, 'class'),
            'classes' => self::spaceDelimited($table->getAttribute('class')),
            'summary' => self::nullableAttribute($table, 'summary'),
            'captionCount' => count($captionElements),
            'captionTexts' => array_map(
                static fn (\DOMElement $caption): string => self::normalizedText($caption),
                $captionElements,
            ),
            'captionIds' => array_map(
                static fn (\DOMElement $caption): ?string => self::nullableAttribute($caption, 'id'),
                $captionElements,
            ),
            'captionAttributes' => array_map(
                static fn (\DOMElement $caption): array => self::elementAttributes($caption),
                $captionElements,
            ),
            'colgroupCount' => count($colgroupElements),
            'columnDeclarationCount' => array_sum(array_map(
                static fn (\DOMElement $colgroup): int => count(self::childElements($colgroup, 'col', self::XHTML_NS)),
                $colgroupElements,
            )),
            'sectionOrder' => $sectionOrder,
            'sectionCounts' => $sectionCounts,
            'rowCountsBySection' => $rowCountsBySection,
            'rowCount' => count($rows),
            'rows' => $rows,
            'cellCount' => count($cells),
            'headerCellCount' => count($headerCells),
            'dataCellCount' => count($dataCells),
            'headerScopes' => $headerScopes,
            'headerReferenceCount' => $headerReferenceCount,
            'cells' => $cells,
            'nestedTableCount' => $table->getElementsByTagNameNS(self::XHTML_NS, 'table')->length,
            'attributes' => self::elementAttributes($table),
            'diagnostics' => $diagnostics,
        ];
    }

    /**
     * @param list<\DOMElement> $sourceRows
     * @param list<array<string, mixed>> $rows
     * @param list<array<string, mixed>> $cells
     * @param list<array<string, mixed>> $diagnostics
     */
    private static function appendXhtmlTableRows(
        array $sourceRows,
        string $section,
        array &$rows,
        array &$cells,
        array &$diagnostics
    ): void {
        foreach ($sourceRows as $sectionRowIndex => $row) {
            $rowCells = [];
            foreach (self::childElements($row) as $cell) {
                if ($cell->namespaceURI !== self::XHTML_NS || !in_array(strtolower($cell->localName), ['th', 'td'], true)) {
                    continue;
                }

                $rowCells[] = self::xhtmlTableCellReport($cell, $section, count($rows), $sectionRowIndex, count($rowCells));
            }

            $rows[] = [
                'index' => count($rows),
                'section' => $section,
                'sectionRowIndex' => $sectionRowIndex,
                'id' => self::nullableAttribute($row, 'id'),
                'class' => self::nullableAttribute($row, 'class'),
                'classes' => self::spaceDelimited($row->getAttribute('class')),
                'cellCount' => count($rowCells),
                'headerCellCount' => count(array_filter(
                    $rowCells,
                    static fn (array $cell): bool => ($cell['header'] ?? false) === true,
                )),
                'dataCellCount' => count(array_filter(
                    $rowCells,
                    static fn (array $cell): bool => ($cell['header'] ?? false) !== true,
                )),
                'attributes' => self::elementAttributes($row),
            ];

            foreach ($rowCells as $cellReport) {
                foreach ($cellReport['diagnostics'] as $diagnostic) {
                    $diagnostics[] = [
                        'rowIndex' => $cellReport['rowIndex'],
                        'columnIndex' => $cellReport['columnIndex'],
                        'cellId' => $cellReport['id'],
                        'element' => $cellReport['element'],
                    ] + $diagnostic;
                }
                $cells[] = $cellReport;
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    private static function xhtmlTableCellReport(
        \DOMElement $cell,
        string $section,
        int $rowIndex,
        int $sectionRowIndex,
        int $columnIndex
    ): array {
        $rowspan = self::xhtmlPositiveIntegerAttribute($cell, 'rowspan');
        $colspan = self::xhtmlPositiveIntegerAttribute($cell, 'colspan');
        $diagnostics = [];
        foreach (['rowspan' => $rowspan, 'colspan' => $colspan] as $attribute => $span) {
            if (($span['valid'] ?? true) !== true) {
                $diagnostics[] = [
                    'type' => 'invalid-xhtml-table-cell-' . $attribute,
                    'attribute' => $attribute,
                    'value' => $span['raw'],
                    'message' => 'EPUB XHTML table cell span attributes must be positive integers',
                ];
            }
        }

        return [
            'section' => $section,
            'rowIndex' => $rowIndex,
            'sectionRowIndex' => $sectionRowIndex,
            'columnIndex' => $columnIndex,
            'element' => strtolower($cell->localName),
            'header' => strtolower($cell->localName) === 'th',
            'id' => self::nullableAttribute($cell, 'id'),
            'class' => self::nullableAttribute($cell, 'class'),
            'classes' => self::spaceDelimited($cell->getAttribute('class')),
            'scope' => self::nullableAttribute($cell, 'scope'),
            'headers' => self::spaceDelimited($cell->getAttribute('headers')),
            'abbr' => self::nullableAttribute($cell, 'abbr'),
            'rowspan' => $rowspan['value'],
            'rowspanRaw' => $rowspan['raw'],
            'rowspanValid' => $rowspan['valid'],
            'colspan' => $colspan['value'],
            'colspanRaw' => $colspan['raw'],
            'colspanValid' => $colspan['valid'],
            'text' => self::normalizedText($cell),
            'attributes' => self::elementAttributes($cell),
            'diagnostics' => $diagnostics,
        ];
    }

    /**
     * @return array{raw:?string, value:int, valid:bool}
     */
    private static function xhtmlPositiveIntegerAttribute(\DOMElement $element, string $name): array
    {
        $raw = self::nullableAttribute($element, $name);
        if ($raw === null) {
            return [
                'raw' => null,
                'value' => 1,
                'valid' => true,
            ];
        }

        if (preg_match('/^[1-9][0-9]*$/', $raw) === 1) {
            return [
                'raw' => $raw,
                'value' => (int) $raw,
                'valid' => true,
            ];
        }

        return [
            'raw' => $raw,
            'value' => 1,
            'valid' => false,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function xhtmlRubyReport(string $part, \DOMElement $ruby, int $index): array
    {
        $baseNodes = [];
        $annotations = [];
        $parentheses = [];
        $rtcCount = 0;
        $emptyRtcCount = 0;
        $diagnostics = [];

        foreach ($ruby->childNodes as $child) {
            if ($child instanceof \DOMText || $child instanceof \DOMCdataSection) {
                $text = self::normalizeWhitespace($child->textContent);
                if ($text !== '') {
                    $baseNodes[] = [
                        'index' => count($baseNodes),
                        'kind' => 'text',
                        'element' => null,
                        'id' => null,
                        'text' => $text,
                        'attributes' => [],
                    ];
                }
                continue;
            }

            if (!$child instanceof \DOMElement) {
                continue;
            }

            if ($child->namespaceURI !== self::XHTML_NS) {
                if (self::normalizedText($child) !== '') {
                    $baseNodes[] = self::xhtmlRubyBaseNodeReport($child, count($baseNodes), 'foreign');
                }
                continue;
            }

            $localName = strtolower($child->localName);
            if ($localName === 'rt') {
                $annotations[] = self::xhtmlRubyAnnotationReport($child, count($annotations), null);
                continue;
            }
            if ($localName === 'rtc') {
                $rtcIndex = $rtcCount;
                ++$rtcCount;
                $rtcAnnotations = 0;
                foreach (self::childElements($child, 'rt', self::XHTML_NS) as $rt) {
                    $annotations[] = self::xhtmlRubyAnnotationReport($rt, count($annotations), $rtcIndex);
                    ++$rtcAnnotations;
                }
                if ($rtcAnnotations === 0) {
                    ++$emptyRtcCount;
                    $diagnostics[] = [
                        'type' => 'empty-xhtml-ruby-annotation-container',
                        'rtcIndex' => $rtcIndex,
                        'message' => 'EPUB XHTML ruby annotation container does not contain any rt annotations',
                    ];
                }
                continue;
            }
            if ($localName === 'rp') {
                $parentheses[] = [
                    'index' => count($parentheses),
                    'id' => self::nullableAttribute($child, 'id'),
                    'text' => self::normalizedText($child),
                    'attributes' => self::elementAttributes($child),
                ];
                continue;
            }

            if (self::normalizedText($child) !== '') {
                $baseNodes[] = self::xhtmlRubyBaseNodeReport(
                    $child,
                    count($baseNodes),
                    in_array($localName, ['rb', 'rbc'], true) ? $localName : 'element'
                );
            }
        }

        $baseText = self::normalizeWhitespace(implode('', array_map(
            static fn (array $node): string => (string) ($node['text'] ?? ''),
            $baseNodes
        )));
        $annotationTexts = array_map(
            static fn (array $annotation): string => (string) ($annotation['text'] ?? ''),
            $annotations
        );
        $emptyAnnotations = array_values(array_filter(
            $annotations,
            static fn (array $annotation): bool => (string) ($annotation['text'] ?? '') === '',
        ));

        if ($baseText === '') {
            $diagnostics[] = [
                'type' => 'missing-xhtml-ruby-base',
                'message' => 'EPUB XHTML ruby element does not expose base text for annotation review',
            ];
        }
        if ($annotations === []) {
            $diagnostics[] = [
                'type' => 'missing-xhtml-ruby-annotation',
                'message' => 'EPUB XHTML ruby element does not contain an rt annotation',
            ];
        }
        if ($emptyAnnotations !== []) {
            $diagnostics[] = [
                'type' => 'empty-xhtml-ruby-annotation',
                'annotationIndexes' => array_map(
                    static fn (array $annotation): int => (int) ($annotation['index'] ?? 0),
                    $emptyAnnotations
                ),
                'message' => 'EPUB XHTML ruby annotation text is empty',
            ];
        }
        if (count($parentheses) % 2 === 1) {
            $diagnostics[] = [
                'type' => 'odd-xhtml-ruby-parenthesis-count',
                'parenthesisCount' => count($parentheses),
                'message' => 'EPUB XHTML ruby fallback parentheses are unbalanced',
            ];
        }

        return [
            'index' => $index,
            'sourcePart' => $part,
            'element' => 'ruby',
            'namespace' => $ruby->namespaceURI,
            'id' => self::nullableAttribute($ruby, 'id'),
            'class' => self::nullableAttribute($ruby, 'class'),
            'classes' => self::spaceDelimited($ruby->getAttribute('class')),
            'text' => self::normalizedText($ruby),
            'baseText' => $baseText,
            'baseNodeCount' => count($baseNodes),
            'baseNodes' => $baseNodes,
            'annotationCount' => count($annotations),
            'annotationTexts' => $annotationTexts,
            'annotations' => $annotations,
            'rtcCount' => $rtcCount,
            'emptyRtcCount' => $emptyRtcCount,
            'parenthesisCount' => count($parentheses),
            'parentheses' => $parentheses,
            'language' => self::xmlLang($ruby),
            'direction' => self::direction($ruby),
            'attributes' => self::elementAttributes($ruby),
            'valid' => $diagnostics === [],
            'diagnostics' => $diagnostics,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function xhtmlRubyBaseNodeReport(\DOMElement $element, int $index, string $kind): array
    {
        return [
            'index' => $index,
            'kind' => $kind,
            'element' => $element->localName,
            'id' => self::nullableAttribute($element, 'id'),
            'text' => self::normalizedText($element),
            'attributes' => self::elementAttributes($element),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function xhtmlRubyAnnotationReport(\DOMElement $element, int $index, ?int $rtcIndex): array
    {
        return [
            'index' => $index,
            'rtcIndex' => $rtcIndex,
            'id' => self::nullableAttribute($element, 'id'),
            'class' => self::nullableAttribute($element, 'class'),
            'classes' => self::spaceDelimited($element->getAttribute('class')),
            'text' => self::normalizedText($element),
            'language' => self::xmlLang($element),
            'direction' => self::direction($element),
            'attributes' => self::elementAttributes($element),
        ];
    }

    /**
     * @param list<string> $types
     * @param array<string, array<string, mixed>> $manifestByPart
     *
     * @return array<string, mixed>
     */
    private function xhtmlSemanticReport(
        ZipPackage $package,
        string $part,
        \DOMElement $element,
        array $types,
        array $manifestByPart,
        int $index
    ): array {
        $href = self::nullableAttribute($element, 'href');
        $reference = $href === null ? null : $this->packageReference(
            $package,
            $part,
            $href,
            $manifestByPart,
            'xhtml-semantic'
        );

        return [
            'index' => $index,
            'sourcePart' => $part,
            'element' => $element->localName,
            'namespace' => $element->namespaceURI,
            'id' => self::nullableAttribute($element, 'id'),
            'class' => self::nullableAttribute($element, 'class'),
            'classes' => self::spaceDelimited($element->getAttribute('class')),
            'types' => $types,
            'primaryType' => $types[0] ?? null,
            'text' => self::normalizedText($element),
            'language' => self::xmlLang($element),
            'direction' => self::direction($element),
            'href' => $href,
            'target' => is_array($reference) ? $reference['target'] : null,
            'part' => is_array($reference) ? $reference['part'] : null,
            'fragment' => is_array($reference) ? $reference['fragment'] : null,
            'fragmentKind' => is_array($reference) ? $reference['fragmentKind'] : null,
            'epubCfi' => is_array($reference) ? $reference['epubCfi'] : null,
            'mediaFragment' => is_array($reference) ? $reference['mediaFragment'] : null,
            'fragmentExists' => null,
            'external' => is_array($reference) ? $reference['external'] : false,
            'exists' => is_array($reference) ? $reference['exists'] : null,
            'byteLength' => is_array($reference) ? $reference['byteLength'] : null,
            'crc32' => is_array($reference) ? $reference['crc32'] : null,
            'manifestId' => is_array($reference) ? $reference['manifestId'] : null,
            'mediaType' => is_array($reference) ? $reference['mediaType'] : null,
            'encrypted' => is_array($reference) ? $reference['encrypted'] : false,
            'canExposeBytes' => is_array($reference) ? $reference['canExposeBytes'] : null,
            'attributes' => self::elementAttributes($element),
            'diagnostics' => is_array($reference) ? $reference['diagnostics'] : [],
        ];
    }

    /**
     * @param list<array<string, mixed>> $semantics
     * @param array<string, array<string, mixed>> $elementIds
     *
     * @return list<array<string, mixed>>
     */
    private static function xhtmlSemanticsWithElementResolution(array $semantics, array $elementIds): array
    {
        foreach ($semantics as $index => $semantic) {
            $fragment = is_string($semantic['fragment'] ?? null) ? $semantic['fragment'] : null;
            $part = is_string($semantic['part'] ?? null) ? $semantic['part'] : null;
            $sourcePart = is_string($semantic['sourcePart'] ?? null) ? $semantic['sourcePart'] : null;
            if (
                $fragment === null
                || $part === null
                || $sourcePart === null
                || $part !== $sourcePart
                || ($semantic['external'] ?? false) === true
                || ($semantic['fragmentKind'] ?? null) === 'epub-cfi'
                || ($semantic['fragmentKind'] ?? null) === 'media-fragment'
            ) {
                continue;
            }

            $targetExists = ($semantic['exists'] ?? false) === true;
            if (!$targetExists) {
                $semantics[$index]['fragmentExists'] = false;
                continue;
            }

            $fragmentExists = isset($elementIds[$fragment]);
            $semantics[$index]['fragmentExists'] = $fragmentExists;
            if (!$fragmentExists) {
                $semantics[$index]['diagnostics'][] = [
                    'type' => 'unresolved-xhtml-semantic-fragment',
                    'fragment' => $fragment,
                    'target' => $semantic['target'] ?? null,
                    'message' => 'EPUB XHTML semantic link fragment does not match an element id in the same content document',
                ];
            }
        }

        return $semantics;
    }

    /**
     * @param list<array<string, mixed>> $semantics
     *
     * @return list<string>
     */
    private static function xhtmlSemanticTypes(array $semantics): array
    {
        $types = [];
        foreach ($semantics as $semantic) {
            foreach (is_array($semantic['types'] ?? null) ? $semantic['types'] : [] as $type) {
                if (is_string($type) && $type !== '' && !in_array($type, $types, true)) {
                    $types[] = $type;
                }
            }
        }

        return $types;
    }

    /**
     * @param list<array<string, mixed>> $semantics
     *
     * @return array<string, list<array<string, mixed>>>
     */
    private static function xhtmlSemanticItemsByType(array $semantics): array
    {
        $items = [];
        foreach ($semantics as $semantic) {
            foreach (is_array($semantic['types'] ?? null) ? $semantic['types'] : [] as $type) {
                if (!is_string($type) || $type === '') {
                    continue;
                }

                $items[$type] ??= [];
                $items[$type][] = $semantic;
            }
        }

        return $items;
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
        \DOMElement $element,
        string $attribute,
        string $href,
        array $manifestByPart,
        int $index,
        array &$flags
    ): array {
        $reference = $this->packageReference($package, $part, $href, $manifestByPart, 'xhtml-content');
        $elementName = strtolower($element->localName);
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
            'element' => $elementName,
            'elementId' => self::nullableAttribute($element, 'id'),
            'elementClass' => self::nullableAttribute($element, 'class'),
            'elementClasses' => self::spaceDelimited($element->getAttribute('class')),
            'elementAttributes' => self::elementAttributes($element),
            'attribute' => $attribute,
            'href' => $href,
            'target' => $reference['target'],
            'part' => $reference['part'],
            'fragment' => $reference['fragment'],
            'fragmentKind' => $reference['fragmentKind'],
            'epubCfi' => $reference['epubCfi'],
            'mediaFragment' => $reference['mediaFragment'],
            'external' => $reference['external'],
            'exists' => $reference['exists'],
            'byteLength' => $reference['byteLength'],
            'crc32' => $reference['crc32'],
            'manifestId' => $reference['manifestId'],
            'mediaType' => $reference['mediaType'],
            'encrypted' => $reference['encrypted'],
            'canExposeBytes' => $reference['canExposeBytes'],
            'embeddedAuthoring' => self::xhtmlEmbeddedResourceAuthoringReport($element, $attribute),
            'diagnostics' => $diagnostics,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function xhtmlEmbeddedResourceAuthoringReport(\DOMElement $element, string $attribute): ?array
    {
        $elementName = strtolower($element->localName);
        $kind = self::xhtmlEmbeddedResourceKind([
            'element' => $elementName,
            'attribute' => $attribute,
        ]);
        if ($kind === null) {
            return null;
        }

        $diagnostics = [];
        $report = [
            'kind' => $kind,
            'element' => $elementName,
            'attribute' => $attribute,
            'id' => self::nullableAttribute($element, 'id'),
            'class' => self::nullableAttribute($element, 'class'),
            'classes' => self::spaceDelimited($element->getAttribute('class')),
            'language' => self::xmlLang($element),
            'direction' => self::direction($element),
            'attributes' => self::elementAttributes($element),
        ];

        if ($elementName === 'audio' || $elementName === 'video') {
            $autoplay = $element->hasAttribute('autoplay');
            $report += [
                'controls' => $element->hasAttribute('controls'),
                'autoplay' => $autoplay,
                'loop' => $element->hasAttribute('loop'),
                'muted' => $element->hasAttribute('muted'),
                'preload' => self::nullableAttribute($element, 'preload'),
            ];
            if ($elementName === 'video') {
                $report += [
                    'playsInline' => $element->hasAttribute('playsinline'),
                    'width' => self::nullableAttribute($element, 'width'),
                    'height' => self::nullableAttribute($element, 'height'),
                ];
            }
            if ($autoplay) {
                $diagnostics[] = [
                    'type' => 'autoplay-xhtml-media-resource',
                    'element' => $elementName,
                    'id' => self::nullableAttribute($element, 'id'),
                    'message' => 'EPUB XHTML embedded media declares autoplay; playback remains inert and requires review',
                ];
            }
        } elseif ($elementName === 'source') {
            $report += [
                'type' => self::nullableAttribute($element, 'type'),
                'media' => self::nullableAttribute($element, 'media'),
                'sizes' => self::nullableAttribute($element, 'sizes'),
            ];
        } elseif ($elementName === 'track') {
            $report += [
                'trackKind' => self::nullableAttribute($element, 'kind'),
                'srclang' => self::nullableAttribute($element, 'srclang'),
                'label' => self::nullableAttribute($element, 'label'),
                'default' => $element->hasAttribute('default'),
            ];
        } elseif ($elementName === 'object') {
            $report += [
                'type' => self::nullableAttribute($element, 'type'),
                'name' => self::nullableAttribute($element, 'name'),
                'width' => self::nullableAttribute($element, 'width'),
                'height' => self::nullableAttribute($element, 'height'),
                'form' => self::nullableAttribute($element, 'form'),
                'typeMustMatch' => $element->hasAttribute('typemustmatch'),
            ];
        } elseif ($elementName === 'embed') {
            $report += [
                'type' => self::nullableAttribute($element, 'type'),
                'width' => self::nullableAttribute($element, 'width'),
                'height' => self::nullableAttribute($element, 'height'),
            ];
        } elseif ($elementName === 'iframe') {
            $srcdoc = self::nullableAttribute($element, 'srcdoc');
            $report += [
                'name' => self::nullableAttribute($element, 'name'),
                'sandbox' => self::nullableAttribute($element, 'sandbox'),
                'sandboxTokens' => self::spaceDelimited($element->getAttribute('sandbox')),
                'allow' => self::nullableAttribute($element, 'allow'),
                'allowFullscreen' => $element->hasAttribute('allowfullscreen'),
                'referrerPolicy' => self::nullableAttribute($element, 'referrerpolicy'),
                'loading' => self::nullableAttribute($element, 'loading'),
                'srcdocPresent' => $srcdoc !== null,
                'srcdocLength' => $srcdoc === null ? 0 : strlen($srcdoc),
                'srcdocSha256' => $srcdoc === null ? null : hash('sha256', $srcdoc),
            ];
        }

        $report['diagnostics'] = $diagnostics;

        return $report;
    }

    /**
     * @return array{mathml:bool, svg:bool, scripted:bool, linkedResources:bool, inlineStyles:bool, switch:bool, trigger:bool, tables:bool, ruby:bool, sideEffects:bool, remoteResources:bool, missingReferences:bool, encryptedReferences:bool}
     */
    private static function emptyXhtmlContentResourceFlags(): array
    {
        return [
            'mathml' => false,
            'svg' => false,
            'scripted' => false,
            'linkedResources' => false,
            'inlineStyles' => false,
            'switch' => false,
            'trigger' => false,
            'tables' => false,
            'ruby' => false,
            'sideEffects' => false,
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
            'linkedResources' => 'linked-resources',
            'inlineStyles' => 'inline-styles',
            'switch' => 'switch',
            'trigger' => 'trigger',
            'tables' => 'tables',
            'ruby' => 'ruby',
            'sideEffects' => 'side-effects',
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
     * @return list<array<string, mixed>>
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
            'img' => ['src', 'srcset'],
            'link' => ['href'],
            'object' => ['data'],
            'script' => ['src'],
            'source' => ['src', 'srcset'],
            'track' => ['src'],
            'use' => ['href', 'xlink:href'],
            'video' => ['src', 'poster'],
        ][$localName] ?? [] as $attributeName) {
            $value = self::xhtmlAttributeValue($element, $attributeName);
            if ($value === null || trim($value) === '') {
                continue;
            }

            if ($attributeName === 'srcset') {
                foreach (self::xhtmlSrcsetReferenceAttributes($value) as $candidate) {
                    $attributes[] = $candidate;
                }
                continue;
            }

            $attributes[] = [
                'attribute' => $attributeName,
                'href' => $value,
            ];
        }

        return $attributes;
    }

    /**
     * @return list<array{attribute:string, href:string, srcsetCandidateIndex:int, srcsetCandidate:string, srcsetDescriptor:string|null}>
     */
    private static function xhtmlSrcsetReferenceAttributes(string $value): array
    {
        $attributes = [];
        foreach (self::splitXhtmlSrcsetCandidates($value) as $candidateIndex => $candidate) {
            $candidate = trim($candidate);
            if ($candidate === '') {
                continue;
            }

            $parts = preg_split('/[\x00-\x20]+/', $candidate);
            if (!is_array($parts) || $parts === []) {
                continue;
            }

            $href = trim((string) array_shift($parts));
            if ($href === '') {
                continue;
            }

            $descriptor = trim(implode(' ', $parts));
            $attributes[] = [
                'attribute' => 'srcset',
                'href' => $href,
                'srcsetCandidateIndex' => $candidateIndex,
                'srcsetCandidate' => $candidate,
                'srcsetDescriptor' => $descriptor === '' ? null : $descriptor,
            ];
        }

        return $attributes;
    }

    /**
     * @return list<string>
     */
    private static function splitXhtmlSrcsetCandidates(string $value): array
    {
        $candidates = [];
        $start = 0;
        $offset = 0;

        while (($comma = strpos($value, ',', $offset)) !== false) {
            $candidatePrefix = substr($value, $start, $comma - $start);
            if (self::isXhtmlDataUrlPayloadComma($candidatePrefix)) {
                $offset = $comma + 1;
                continue;
            }

            $candidates[] = $candidatePrefix;
            $start = $comma + 1;
            $offset = $start;
        }

        $candidates[] = substr($value, $start);

        return $candidates;
    }

    private static function isXhtmlDataUrlPayloadComma(string $candidatePrefix): bool
    {
        $trimmed = trim($candidatePrefix);
        if ($trimmed === '' || preg_match('/[\x00-\x20]/', $trimmed) === 1) {
            return false;
        }

        return preg_match('/^data:[^,]*$/i', $trimmed) === 1;
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
     * @param array<string, array<string, mixed>> $manifestById
     * @param array<string, mixed> $metadata
     * @param array<string, mixed> $encryption
     *
     * @return array<string, mixed>
     */
    private function assetReport(
        ZipPackage $package,
        string $opfPart,
        array $manifest,
        array $manifestById,
        array $metadata,
        array $encryption
    ): array {
        $assets = [];
        $manifestParts = [];
        foreach ($manifest as $item) {
            $part = $item['part'] ?? null;
            if (is_string($part) && $part !== '') {
                $manifestParts[$part] = true;
            }

            if (self::mediaTypeBaseEquals($item['mediaType'] ?? null, self::XHTML_MEDIA_TYPE)) {
                continue;
            }

            $isCoverImage = self::isCoverImageAsset($item, $metadata);
            $role = self::assetRole($item, $isCoverImage);
            $canExposeBytes = (bool) ($item['canExposeBytes'] ?? true);
            $exportCandidate = self::isExportCandidate($item, $role);
            $byteSha256 = null;
            $diagnostics = is_array($item['diagnostics'] ?? null) ? array_values($item['diagnostics']) : [];
            $fallback = $this->assetFallbackReport($package, $item, $manifestById);
            foreach ($fallback['diagnostics'] as $diagnostic) {
                $diagnostics[] = $diagnostic;
            }
            $fallbackStyle = $this->assetFallbackStyleReport($package, $item, $manifestById);
            foreach ($fallbackStyle['diagnostics'] as $diagnostic) {
                $diagnostics[] = $diagnostic;
            }
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
                'mediaTypeReport' => $item['mediaTypeReport'] ?? null,
                'mediaTypeReviewFlags' => $item['mediaTypeReviewFlags'] ?? [],
                'mediaTypeDiagnostics' => $item['mediaTypeDiagnostics'] ?? [],
                'properties' => $item['properties'],
                'manifestLanguage' => $item['language'] ?? null,
                'manifestDirection' => $item['direction'] ?? null,
                'manifestAttributes' => $item['attributes'] ?? [],
                'manifestCustomAttributes' => $item['customAttributes'] ?? [],
                'resourceFlags' => $item['resourceFlags'] ?? self::resourcePropertyFlags($item['properties'] ?? []),
                'resourceReviewFlags' => $item['resourceReviewFlags'] ?? [],
                'mediaOverlay' => $item['mediaOverlay'] ?? null,
                'mediaOverlayReference' => $item['mediaOverlayReference'] ?? null,
                'mediaOverlayDiagnostics' => $item['mediaOverlayDiagnostics'] ?? [],
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
                'fallback' => $fallback['fallbackId'],
                'fallbackId' => $fallback['fallbackId'],
                'fallbackChain' => $fallback['chain'],
                'fallbackDiagnostics' => $fallback['diagnostics'],
                'fallbackContentId' => $fallback['content']['id'] ?? null,
                'fallbackContentTarget' => $fallback['content']['target'] ?? null,
                'fallbackContentPart' => $fallback['content']['part'] ?? null,
                'fallbackContentMediaType' => $fallback['content']['mediaType'] ?? null,
                'fallbackAttachmentCandidate' => $fallback['content']['attachmentCandidate'] ?? false,
                'fallbackAttachmentRole' => $fallback['content']['attachmentRole'] ?? null,
                'fallbackByteSha256' => $fallback['content']['byteSha256'] ?? null,
                'fallbackStyle' => $fallbackStyle['fallbackStyleId'],
                'fallbackStyleId' => $fallbackStyle['fallbackStyleId'],
                'fallbackStyleChain' => $fallbackStyle['chain'],
                'fallbackStyleDiagnostics' => $fallbackStyle['diagnostics'],
                'fallbackStyleContentId' => $fallbackStyle['content']['id'] ?? null,
                'fallbackStyleContentTarget' => $fallbackStyle['content']['target'] ?? null,
                'fallbackStyleContentPart' => $fallbackStyle['content']['part'] ?? null,
                'fallbackStyleContentMediaType' => $fallbackStyle['content']['mediaType'] ?? null,
                'fallbackStyleByteSha256' => $fallbackStyle['content']['byteSha256'] ?? null,
                'diagnostics' => $diagnostics,
            ];
        }

        $coverImage = null;
        $coverImages = [];
        foreach ($assets as $asset) {
            if (($asset['isCoverImage'] ?? false) === true) {
                $coverImages[] = $asset;
                if ($coverImage === null) {
                    $coverImage = $asset;
                }
            }
        }
        $coverImageReport = self::coverImageReport($coverImages, $metadata, $coverImage, $assets);

        $attachmentCandidates = array_values(array_filter(
            $assets,
            static fn (array $asset): bool => ($asset['attachmentCandidate'] ?? false) === true,
        ));
        $fallbackItems = array_values(array_filter(
            $assets,
            static fn (array $asset): bool => is_string($asset['fallbackId'] ?? null) && $asset['fallbackId'] !== '',
        ));
        $fallbackStyleItems = array_values(array_filter(
            $assets,
            static fn (array $asset): bool => is_string($asset['fallbackStyleId'] ?? null) && $asset['fallbackStyleId'] !== '',
        ));
        $fallbackDiagnostics = [];
        foreach ($fallbackItems as $asset) {
            foreach (($asset['fallbackDiagnostics'] ?? []) as $diagnostic) {
                if (!is_array($diagnostic)) {
                    continue;
                }

                $fallbackDiagnostics[] = [
                    'id' => (string) $asset['id'],
                    'fallback' => $asset['fallbackId'],
                ] + $diagnostic;
            }
        }
        $fallbackStyleDiagnostics = [];
        foreach ($fallbackStyleItems as $asset) {
            foreach (($asset['fallbackStyleDiagnostics'] ?? []) as $diagnostic) {
                if (!is_array($diagnostic)) {
                    continue;
                }

                $fallbackStyleDiagnostics[] = [
                    'id' => (string) $asset['id'],
                    'fallbackStyle' => $asset['fallbackStyleId'],
                ] + $diagnostic;
            }
        }
        foreach (self::metadataLinkedParts($metadata) as $linkedPart) {
            $manifestParts[$linkedPart] = true;
        }

        $unmanifestedItems = $this->unmanifestedPackageAssets($package, $manifestParts, $opfPart, $encryption);
        $diagnostics = $coverImageReport['diagnostics'];
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
            'coverImageCount' => $coverImageReport['count'],
            'coverImages' => $coverImageReport['items'],
            'coverImageDiagnosticCount' => count($coverImageReport['diagnostics']),
            'coverImageDiagnostics' => $coverImageReport['diagnostics'],
            'attachmentCandidateCount' => count($attachmentCandidates),
            'attachmentCandidates' => $attachmentCandidates,
            'fallbackCount' => count($fallbackItems),
            'fallbackItems' => $fallbackItems,
            'fallbackDiagnosticCount' => count($fallbackDiagnostics),
            'fallbackDiagnostics' => $fallbackDiagnostics,
            'fallbackStyleCount' => count($fallbackStyleItems),
            'fallbackStyleItems' => $fallbackStyleItems,
            'fallbackStyleDiagnosticCount' => count($fallbackStyleDiagnostics),
            'fallbackStyleDiagnostics' => $fallbackStyleDiagnostics,
            'unmanifestedCount' => count($unmanifestedItems),
            'unmanifestedItems' => $unmanifestedItems,
            'diagnostics' => $diagnostics,
        ];
    }

    /**
     * @param list<array<string, mixed>> $coverImages
     * @param list<array<string, mixed>> $assets
     *
     * @return array{count:int, items:list<array<string, mixed>>, diagnostics:list<array<string, mixed>>}
     */
    private static function coverImageReport(array $coverImages, array $metadata, ?array $selected, array $assets): array
    {
        $ids = [];
        $manifestCoverIds = [];
        $metaCoverIds = [];
        $sourcesById = [];
        foreach ($coverImages as $asset) {
            $id = (string) ($asset['id'] ?? '');
            if ($id === '') {
                continue;
            }

            $ids[] = $id;
            $sources = is_array($asset['coverImageSources'] ?? null) ? array_values($asset['coverImageSources']) : [];
            $sourcesById[$id] = $sources;
            if (in_array('manifest-property-cover-image', $sources, true)) {
                $manifestCoverIds[] = $id;
            }
            if (in_array('meta-name-cover', $sources, true)) {
                $metaCoverIds[] = $id;
            }
        }

        $selectedId = is_array($selected) && is_string($selected['id'] ?? null) ? $selected['id'] : null;
        $metaCoverItemId = self::nullableManifestId($metadata['coverItemId'] ?? null);
        $metaCoverAsset = null;
        if ($metaCoverItemId !== null) {
            foreach ($assets as $asset) {
                if ((string) ($asset['id'] ?? '') === $metaCoverItemId) {
                    $metaCoverAsset = $asset;
                    break;
                }
            }
        }
        $diagnostics = [];

        if (count($coverImages) > 1) {
            $diagnostics[] = [
                'type' => 'multiple-cover-image-candidates',
                'selectedId' => $selectedId,
                'coverImageIds' => $ids,
                'manifestCoverImageIds' => $manifestCoverIds,
                'metaCoverImageIds' => $metaCoverIds,
                'metaCoverItemId' => $metaCoverItemId,
                'sourcesById' => $sourcesById,
                'message' => 'EPUB package exposes more than one cover image candidate; selected cover image remains the first package candidate for compatibility',
            ];
        }

        if ($metaCoverItemId !== null && !in_array($metaCoverItemId, $ids, true) && is_array($metaCoverAsset)) {
            $diagnostics[] = [
                'type' => 'invalid-meta-cover-image-media-type',
                'selectedId' => $selectedId,
                'coverImageIds' => $ids,
                'manifestCoverImageIds' => $manifestCoverIds,
                'metaCoverImageIds' => $metaCoverIds,
                'metaCoverItemId' => $metaCoverItemId,
                'metaCoverMediaType' => $metaCoverAsset['mediaType'] ?? null,
                'metaCoverPart' => $metaCoverAsset['part'] ?? null,
                'metaCoverProperties' => $metaCoverAsset['properties'] ?? [],
                'metaCoverRole' => $metaCoverAsset['role'] ?? null,
                'sourcesById' => $sourcesById,
                'message' => 'EPUB OPF legacy meta cover item id resolves to a non-image manifest item',
            ];
        } elseif ($metaCoverItemId !== null && !in_array($metaCoverItemId, $ids, true)) {
            $diagnostics[] = [
                'type' => 'missing-meta-cover-image',
                'selectedId' => $selectedId,
                'coverImageIds' => $ids,
                'manifestCoverImageIds' => $manifestCoverIds,
                'metaCoverImageIds' => $metaCoverIds,
                'metaCoverItemId' => $metaCoverItemId,
                'sourcesById' => $sourcesById,
                'message' => 'EPUB OPF legacy meta cover item id does not resolve to an importable cover image candidate',
            ];
        }

        return [
            'count' => count($coverImages),
            'items' => $coverImages,
            'diagnostics' => $diagnostics,
        ];
    }

    /**
     * @param array<string, mixed> $item
     * @param array<string, array<string, mixed>> $manifestById
     *
     * @return array{fallbackId:?string, chain:list<array<string, mixed>>, content:?array<string, mixed>, diagnostics:list<array<string, mixed>>}
     */
    private function assetFallbackReport(ZipPackage $package, array $item, array $manifestById): array
    {
        $fallbackId = self::nullableManifestId($item['fallback'] ?? null);
        if ($fallbackId === null) {
            return [
                'fallbackId' => null,
                'chain' => [],
                'content' => null,
                'diagnostics' => [],
            ];
        }

        $chain = [];
        $diagnostics = [];
        $visited = [(string) ($item['id'] ?? '') => true];
        $current = $item;
        $nextFallback = $fallbackId;

        while ($nextFallback !== null) {
            if (isset($visited[$nextFallback])) {
                $diagnostics[] = [
                    'type' => 'cyclic-asset-fallback-chain',
                    'id' => (string) ($current['id'] ?? ''),
                    'fallback' => $nextFallback,
                    'message' => 'EPUB manifest asset fallback chain cycles before reaching a terminal package resource',
                ];
                break;
            }

            if (!isset($manifestById[$nextFallback])) {
                $diagnostics[] = [
                    'type' => 'missing-asset-fallback-manifest-item',
                    'id' => (string) ($current['id'] ?? ''),
                    'fallback' => $nextFallback,
                    'message' => 'EPUB manifest asset fallback references an item id that is not in the OPF manifest',
                ];
                break;
            }

            $visited[$nextFallback] = true;
            $current = $manifestById[$nextFallback];
            $chainItem = $this->assetFallbackChainItem($package, $current);
            foreach ($chainItem['diagnostics'] as $diagnostic) {
                $diagnostics[] = $diagnostic;
            }
            $chain[] = $chainItem;
            $nextFallback = self::nullableManifestId($current['fallback'] ?? null);
        }

        return [
            'fallbackId' => $fallbackId,
            'chain' => $chain,
            'content' => $chain === [] ? null : $chain[count($chain) - 1],
            'diagnostics' => $diagnostics,
        ];
    }

    /**
     * @param array<string, mixed> $item
     *
     * @return array<string, mixed>
     */
    private function assetFallbackChainItem(ZipPackage $package, array $item): array
    {
        $mediaType = (string) ($item['mediaType'] ?? '');
        $part = is_string($item['part'] ?? null) ? (string) $item['part'] : null;
        $canExposeBytes = (bool) ($item['canExposeBytes'] ?? true);
        $exists = ($item['exists'] ?? false) === true;
        $encrypted = self::isEncryptedManifestItem($item);
        $attachmentRole = self::attachmentRole($mediaType, (string) $part, false);
        $attachmentCandidate = $exists
            && $canExposeBytes
            && !$encrypted
            && self::isAttachmentCandidate($mediaType, (string) $part, false);
        $byteSha256 = null;
        $diagnostics = [];

        if (($item['external'] ?? false) === true) {
            $diagnostics[] = [
                'type' => 'external-asset-fallback-resource',
                'id' => (string) ($item['id'] ?? ''),
                'href' => (string) ($item['href'] ?? ''),
                'message' => 'EPUB manifest asset fallback points outside the package and was not fetched',
            ];
        } elseif (!$exists) {
            $diagnostics[] = [
                'type' => 'missing-asset-fallback-part',
                'id' => (string) ($item['id'] ?? ''),
                'part' => $part,
                'message' => 'EPUB manifest asset fallback target is missing from the package',
            ];
        } elseif ($encrypted) {
            $diagnostics[] = [
                'type' => 'encrypted-asset-fallback-content',
                'id' => (string) ($item['id'] ?? ''),
                'part' => $part,
                'message' => 'EPUB manifest asset fallback target is encrypted and cannot expose package bytes',
            ];
        } elseif ($canExposeBytes && $part !== null && $part !== '') {
            try {
                $byteSha256 = hash('sha256', $package->read($part));
            } catch (\Throwable $exception) {
                $diagnostics[] = [
                    'type' => 'asset-fallback-bytes-unavailable',
                    'id' => (string) ($item['id'] ?? ''),
                    'part' => $part,
                    'message' => $exception->getMessage(),
                ];
            }
        }

        return [
            'id' => (string) ($item['id'] ?? ''),
            'href' => (string) ($item['href'] ?? ''),
            'target' => $item['target'] ?? null,
            'part' => $part,
            'external' => (bool) ($item['external'] ?? false),
            'mediaType' => $mediaType,
            'properties' => is_array($item['properties'] ?? null) ? $item['properties'] : [],
            'exists' => $exists,
            'byteLength' => $item['byteLength'] ?? null,
            'crc32' => $item['crc32'] ?? null,
            'byteSha256' => $byteSha256,
            'encrypted' => $encrypted,
            'canExposeBytes' => $canExposeBytes,
            'attachmentCandidate' => $attachmentCandidate,
            'attachmentRole' => $attachmentRole,
            'diagnostics' => $diagnostics,
        ];
    }

    /**
     * @param array<string, mixed> $item
     * @param array<string, array<string, mixed>> $manifestById
     *
     * @return array{fallbackStyleId:?string, chain:list<array<string, mixed>>, content:?array<string, mixed>, diagnostics:list<array<string, mixed>>}
     */
    private function assetFallbackStyleReport(ZipPackage $package, array $item, array $manifestById): array
    {
        $fallbackStyleId = self::nullableManifestId($item['fallbackStyle'] ?? null);
        if ($fallbackStyleId === null) {
            return [
                'fallbackStyleId' => null,
                'chain' => [],
                'content' => null,
                'diagnostics' => [],
            ];
        }

        $chain = [];
        $diagnostics = [];
        $visited = [(string) ($item['id'] ?? '') => true];
        $current = $item;
        $nextFallbackStyle = $fallbackStyleId;

        while ($nextFallbackStyle !== null) {
            if (isset($visited[$nextFallbackStyle])) {
                $diagnostics[] = [
                    'type' => 'cyclic-asset-fallback-style-chain',
                    'id' => (string) ($current['id'] ?? ''),
                    'fallbackStyle' => $nextFallbackStyle,
                    'message' => 'EPUB manifest asset fallback-style chain cycles before reaching a terminal CSS style resource',
                ];
                break;
            }

            if (!isset($manifestById[$nextFallbackStyle])) {
                $diagnostics[] = [
                    'type' => 'missing-asset-fallback-style-manifest-item',
                    'id' => (string) ($current['id'] ?? ''),
                    'fallbackStyle' => $nextFallbackStyle,
                    'message' => 'EPUB manifest asset fallback-style references an item id that is not in the OPF manifest',
                ];
                break;
            }

            $visited[$nextFallbackStyle] = true;
            $current = $manifestById[$nextFallbackStyle];
            $chainItem = $this->assetFallbackStyleChainItem($package, $current);
            foreach ($chainItem['diagnostics'] as $diagnostic) {
                $diagnostics[] = $diagnostic;
            }
            $chain[] = $chainItem;

            if (($chainItem['cssStyle'] ?? false) === true) {
                break;
            }

            $nextFallbackStyle = self::nullableManifestId($current['fallbackStyle'] ?? null);
            if ($nextFallbackStyle === null) {
                $diagnostics[] = [
                    'type' => 'non-css-asset-fallback-style',
                    'id' => (string) ($current['id'] ?? ''),
                    'mediaType' => (string) ($current['mediaType'] ?? ''),
                    'message' => 'EPUB manifest asset fallback-style should resolve to a CSS style resource',
                ];
                break;
            }
        }

        return [
            'fallbackStyleId' => $fallbackStyleId,
            'chain' => $chain,
            'content' => $chain === [] ? null : $chain[count($chain) - 1],
            'diagnostics' => $diagnostics,
        ];
    }

    /**
     * @param array<string, mixed> $item
     *
     * @return array<string, mixed>
     */
    private function assetFallbackStyleChainItem(ZipPackage $package, array $item): array
    {
        $mediaType = (string) ($item['mediaType'] ?? '');
        $part = is_string($item['part'] ?? null) ? (string) $item['part'] : null;
        $parts = self::mediaTypeParts($mediaType);
        $cssStyle = $parts['base'] === 'text/css';
        $canExposeBytes = (bool) ($item['canExposeBytes'] ?? true);
        $exists = ($item['exists'] ?? false) === true;
        $encrypted = self::isEncryptedManifestItem($item);
        $byteSha256 = null;
        $diagnostics = [];

        if (($item['external'] ?? false) === true) {
            $diagnostics[] = [
                'type' => 'external-asset-fallback-style-resource',
                'id' => (string) ($item['id'] ?? ''),
                'href' => (string) ($item['href'] ?? ''),
                'message' => 'EPUB manifest asset fallback-style points outside the package and was not fetched',
            ];
        } elseif (!$exists) {
            $diagnostics[] = [
                'type' => 'missing-asset-fallback-style-part',
                'id' => (string) ($item['id'] ?? ''),
                'part' => $part,
                'message' => 'EPUB manifest asset fallback-style target is missing from the package',
            ];
        } elseif ($encrypted) {
            $diagnostics[] = [
                'type' => 'encrypted-asset-fallback-style',
                'id' => (string) ($item['id'] ?? ''),
                'part' => $part,
                'message' => 'EPUB manifest asset fallback-style target is encrypted and cannot expose package bytes',
            ];
        } elseif ($canExposeBytes && $part !== null && $part !== '') {
            try {
                $byteSha256 = hash('sha256', $package->read($part));
            } catch (\Throwable $exception) {
                $diagnostics[] = [
                    'type' => 'asset-fallback-style-bytes-unavailable',
                    'id' => (string) ($item['id'] ?? ''),
                    'part' => $part,
                    'message' => $exception->getMessage(),
                ];
            }
        }

        return [
            'id' => (string) ($item['id'] ?? ''),
            'href' => (string) ($item['href'] ?? ''),
            'target' => $item['target'] ?? null,
            'part' => $part,
            'external' => (bool) ($item['external'] ?? false),
            'mediaType' => $mediaType,
            'properties' => is_array($item['properties'] ?? null) ? $item['properties'] : [],
            'exists' => $exists,
            'byteLength' => $item['byteLength'] ?? null,
            'crc32' => $item['crc32'] ?? null,
            'byteSha256' => $byteSha256,
            'encrypted' => $encrypted,
            'canExposeBytes' => $canExposeBytes,
            'cssStyle' => $cssStyle,
            'diagnostics' => $diagnostics,
        ];
    }

    private static function nullableManifestId(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    /**
     * @param array<string, bool> $manifestParts
     * @param array<string, mixed> $encryption
     *
     * @return list<array<string, mixed>>
     */
    private function unmanifestedPackageAssets(
        ZipPackage $package,
        array $manifestParts,
        string $opfPart,
        array $encryption
    ): array
    {
        $items = [];
        $encryptionByPart = self::encryptionItemsByPart($encryption);
        foreach ($package->entries() as $entry) {
            if ($entry->isDirectory()) {
                continue;
            }

            $part = OpcPackagePath::canonicalPartName($entry->name);
            if (isset($manifestParts[$part]) || self::isEpubStructuralPart($part, $opfPart)) {
                continue;
            }

            $mediaType = self::mediaTypeFromPart($part);
            $encryptionItems = $encryptionByPart[$part] ?? [];
            $encrypted = $encryptionItems !== [];
            $obfuscatedFont = self::containsObfuscatedFont($encryptionItems);
            $canExposeBytes = !$encrypted;
            $attachmentRole = self::attachmentRole($mediaType, $part, false);
            $attachmentCandidate = $canExposeBytes && self::isAttachmentCandidate($mediaType, $part, false);
            $diagnostics = [[
                'type' => 'unmanifested-package-resource',
                'part' => $part,
                'message' => 'EPUB package resource is present in ZIP but absent from the OPF manifest',
            ]];
            $byteSha256 = null;
            $encryptionReport = null;
            if ($encrypted) {
                $encryptionReport = [
                    'items' => $encryptionItems,
                    'algorithm' => $encryptionItems[0]['algorithm'] ?? null,
                    'role' => $encryptionItems[0]['role'] ?? self::encryptedResourceRole($mediaType, $part),
                    'obfuscatedFont' => $obfuscatedFont,
                    'canExposeBytes' => false,
                    'reviewPolicy' => $obfuscatedFont ? 'obfuscated-font-review' : 'encrypted-resource-review',
                    'byteExposurePolicy' => $obfuscatedFont ? 'obfuscated-font-bytes-blocked' : 'encrypted-resource-bytes-blocked',
                    'attachmentCandidateBlocked' => count(array_filter(
                        $encryptionItems,
                        static fn (array $item): bool => ($item['attachmentCandidateBlocked'] ?? false) === true,
                    )) > 0,
                ];
                $diagnostics[] = [
                    'type' => 'encrypted-unmanifested-package-resource',
                    'part' => $part,
                    'reviewPolicy' => $encryptionReport['reviewPolicy'],
                    'byteExposurePolicy' => $encryptionReport['byteExposurePolicy'],
                    'message' => 'EPUB package resource is encrypted and absent from the OPF manifest; package bytes remain metadata-only',
                ];
            } else {
                try {
                    $byteSha256 = hash('sha256', $package->read($part));
                } catch (\Throwable $exception) {
                    $diagnostics[] = [
                        'type' => 'unmanifested-package-resource-bytes-unavailable',
                        'part' => $part,
                        'message' => $exception->getMessage(),
                    ];
                }
            }

            $items[] = [
                'part' => $part,
                'mediaType' => $mediaType,
                'exists' => true,
                'byteLength' => $entry->uncompressedSize,
                'crc32' => $entry->crc32Hex(),
                'byteSha256' => $byteSha256,
                'encrypted' => $encrypted,
                'canExposeBytes' => $canExposeBytes,
                'encryption' => $encryptionReport,
                'attachmentCandidate' => $attachmentCandidate,
                'attachmentRole' => $attachmentRole,
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
            || self::isLegacyCoverImageAsset($item, $metadata);
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
        if (
            self::legacyCoverItemIdMatches($item, $metadata)
            && (in_array('cover-image', $item['properties'] ?? [], true) || self::isLegacyCoverImageAsset($item, $metadata))
        ) {
            $sources[] = 'meta-name-cover';
        }

        return $sources;
    }

    /**
     * @param array<string, mixed> $item
     * @param array<string, mixed> $metadata
     */
    private static function isLegacyCoverImageAsset(array $item, array $metadata): bool
    {
        if (!self::legacyCoverItemIdMatches($item, $metadata)) {
            return false;
        }

        $baseMediaType = self::baseMediaType(strtolower((string) ($item['mediaType'] ?? '')));

        return str_starts_with($baseMediaType, 'image/');
    }

    /**
     * @param array<string, mixed> $item
     * @param array<string, mixed> $metadata
     */
    private static function legacyCoverItemIdMatches(array $item, array $metadata): bool
    {
        $coverItemId = self::nullableManifestId($metadata['coverItemId'] ?? null);

        return $coverItemId !== null && (string) ($item['id'] ?? '') === $coverItemId;
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
        $baseMediaType = self::baseMediaType($mediaType);
        if ($baseMediaType === self::NCX_MEDIA_TYPE) {
            return 'navigation';
        }
        if ($baseMediaType === self::SMIL_MEDIA_TYPE) {
            return 'media-overlay';
        }
        if ($baseMediaType === 'text/css') {
            return 'stylesheet';
        }
        if (str_starts_with($baseMediaType, 'image/')) {
            return 'image';
        }
        if (str_starts_with($baseMediaType, 'audio/')) {
            return 'audio';
        }
        if (str_starts_with($baseMediaType, 'video/')) {
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
        $baseMediaType = self::baseMediaType($mediaType);

        return str_starts_with($baseMediaType, 'image/')
            || str_starts_with($baseMediaType, 'audio/')
            || str_starts_with($baseMediaType, 'video/')
            || self::isFontResource($mediaType, $part);
    }

    private static function attachmentRole(?string $mediaType, string $part, bool $isCoverImage): ?string
    {
        if ($isCoverImage) {
            return 'cover-image';
        }

        $mediaType = strtolower((string) $mediaType);
        $baseMediaType = self::baseMediaType($mediaType);
        if (str_starts_with($baseMediaType, 'image/')) {
            return 'image';
        }
        if (str_starts_with($baseMediaType, 'audio/')) {
            return 'audio';
        }
        if (str_starts_with($baseMediaType, 'video/')) {
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
     * @param array<string, mixed> $package
     * @param list<array<string, mixed>> $spine
     * @param array<string, mixed> $spineProperties
     * @param list<array<string, mixed>> $xhtmlAssets
     * @param array<string, mixed> $guide
     * @param list<array<string, mixed>> $collections
     * @param array<string, mixed> $bindings
     * @param array<string, mixed> $accessibility
     * @param array<string, mixed> $resourceProperties
     * @param array<string, mixed> $mediaTypes
     * @param array<string, mixed> $remoteResources
     * @param array<string, mixed> $mediaDurations
     * @param array<string, mixed> $mediaOverlayStyles
     * @param array<string, mixed> $pageBreaks
     * @param array<string, mixed> $spineContentProvenance
     * @param array<string, mixed> $navigation
     * @param array<string, mixed> $navigationOutline
     * @param array<string, mixed> $xhtmlResourceReport
     * @param array<string, mixed> $cssResourceReport
     * @param array<string, mixed> $assetReport
     * @param array<string, mixed> $renditions
     * @param array<string, mixed> $ocf
     */
    private function documentNode(
        array $metadata,
        array $mimetypeEntry,
        string $opfPart,
        array $package,
        array $spine,
        array $spineProperties,
        array $xhtmlAssets,
        array $guide,
        array $collections,
        array $bindings,
        array $accessibility,
        array $resourceProperties,
        array $mediaTypes,
        array $remoteResources,
        array $mediaDurations,
        array $mediaOverlayStyles,
        array $pageBreaks,
        array $spineContentProvenance,
        array $navigation,
        array $navigationOutline,
        array $xhtmlResourceReport,
        array $cssResourceReport,
        array $assetReport,
        array $renditions,
        array $ocf
    ): AstNode {
        $assetsByPart = [];
        foreach ($xhtmlAssets as $asset) {
            $assetsByPart[(string) $asset['part']] = $asset;
        }
        $spineContentByIndex = [];
        foreach (is_array($spineContentProvenance['items'] ?? null) ? $spineContentProvenance['items'] : [] as $provenance) {
            if (is_array($provenance) && is_int($provenance['spineIndex'] ?? null)) {
                $spineContentByIndex[(int) $provenance['spineIndex']] = $provenance;
            }
        }

        $children = [];
        foreach ($spine as $index => $item) {
            $contentMediaType = $item['contentMediaType'] ?? $item['mediaType'];
            if (!self::mediaTypeBaseEquals($contentMediaType, self::XHTML_MEDIA_TYPE)) {
                continue;
            }

            $contentPart = (string) ($item['contentPart'] ?? $item['part']);
            $asset = $assetsByPart[$contentPart] ?? null;
            if (!is_array($asset)) {
                continue;
            }

            $isFallback = (bool) ($item['contentIsFallback'] ?? false);
            $contentProvenance = $spineContentByIndex[(int) $index] ?? null;
            $attributes = [
                'format' => 'html',
                'html' => $asset['html'],
                'part' => $asset['part'],
                'contentProvenance' => $contentProvenance,
                'contentByteLength' => is_array($contentProvenance) ? $contentProvenance['byteLength'] : null,
                'contentCrc32' => is_array($contentProvenance) ? $contentProvenance['crc32'] : null,
                'contentByteSha256' => is_array($contentProvenance) ? $contentProvenance['byteSha256'] : null,
                'contentHtmlSha256' => is_array($contentProvenance) ? $contentProvenance['htmlSha256'] : null,
                'id' => $item['idref'],
                'spineItemId' => $item['id'] ?? null,
                'linear' => $item['linear'],
                'linearRaw' => $item['linearRaw'] ?? null,
                'linearSpecified' => $item['linearSpecified'] ?? false,
                'linearValid' => $item['linearValid'] ?? true,
                'spineItemLanguage' => $item['language'] ?? null,
                'spineItemDirection' => $item['direction'] ?? null,
                'spineItemAttributes' => $item['attributes'] ?? [],
                'spineItemCustomAttributes' => $item['customAttributes'] ?? [],
                'refinements' => $item['refinements'] ?? [],
                'linkedResources' => $item['linkedResources'] ?? [],
                'pageProgressionDirection' => $spineProperties['pageProgressionDirection'] ?? 'default',
                'pageSpread' => $item['pageSpread'] ?? null,
                'pageSpreadProperties' => $item['pageSpreadProperties'] ?? [],
                'flow' => $item['flow'] ?? null,
                'flowProperties' => $item['flowProperties'] ?? [],
                'alignX' => $item['alignX'] ?? null,
                'alignXProperties' => $item['alignXProperties'] ?? [],
                'layout' => $item['layout'] ?? null,
                'layoutProperties' => $item['layoutProperties'] ?? [],
                'orientation' => $item['orientation'] ?? null,
                'orientationProperties' => $item['orientationProperties'] ?? [],
                'spread' => $item['spread'] ?? null,
                'spreadProperties' => $item['spreadProperties'] ?? [],
                'spineItemProperties' => $item['spineItemProperties'] ?? [],
                'spineItemDiagnostics' => $item['spineItemDiagnostics'] ?? [],
                'effectiveRendition' => $item['effectiveRendition'] ?? [],
                'mediaOverlay' => $item['mediaOverlay'],
                'mediaOverlayReference' => $item['mediaOverlayReference'] ?? null,
                'mediaOverlayDiagnostics' => $item['mediaOverlayDiagnostics'] ?? [],
                'pageBreaks' => is_array($pageBreaks['itemsByPart'][$contentPart] ?? null)
                    ? array_values($pageBreaks['itemsByPart'][$contentPart])
                    : [],
                'pageBreakCount' => is_array($pageBreaks['itemsByPart'][$contentPart] ?? null)
                    ? count($pageBreaks['itemsByPart'][$contentPart])
                    : 0,
                'manifestLanguage' => $asset['manifestLanguage'] ?? null,
                'manifestDirection' => $asset['manifestDirection'] ?? null,
                'manifestAttributes' => $asset['manifestAttributes'] ?? [],
                'manifestCustomAttributes' => $asset['manifestCustomAttributes'] ?? [],
                'resourceFlags' => $asset['resourceFlags'] ?? [],
                'resourceReviewFlags' => $asset['resourceReviewFlags'] ?? [],
                'contentResourceFlags' => $asset['contentResourceFlags'] ?? [],
                'contentResourceReviewFlags' => $asset['contentResourceReviewFlags'] ?? [],
                'contentMetadata' => $asset['contentMetadata'] ?? [],
                'contentLanguage' => $asset['contentLanguage'] ?? null,
                'contentDirection' => $asset['contentDirection'] ?? null,
                'contentBodyEpubTypes' => $asset['contentBodyEpubTypes'] ?? [],
                'contentViewport' => $asset['contentViewport'] ?? [],
                'contentViewports' => $asset['contentViewports'] ?? [],
                'contentReferences' => $asset['contentReferences'] ?? [],
                'contentEmbeddedResources' => $asset['contentEmbeddedResources'] ?? [],
                'contentEmbeddedResourceDiagnostics' => $asset['contentEmbeddedResourceDiagnostics'] ?? [],
                'contentLinks' => $asset['contentLinks'] ?? [],
                'contentLinkDiagnostics' => $asset['contentLinkDiagnostics'] ?? [],
                'contentRefreshes' => $asset['contentRefreshes'] ?? [],
                'contentRefreshDiagnostics' => $asset['contentRefreshDiagnostics'] ?? [],
                'contentSideEffects' => $asset['contentSideEffects'] ?? [],
                'contentSideEffectDiagnostics' => $asset['contentSideEffectDiagnostics'] ?? [],
                'contentStyles' => $asset['contentStyles'] ?? [],
                'contentStyleDiagnostics' => $asset['contentStyleDiagnostics'] ?? [],
                'contentScripts' => $asset['contentScripts'] ?? [],
                'contentScriptEventHandlers' => $asset['contentScriptEventHandlers'] ?? [],
                'contentJavascriptReferences' => $asset['contentJavascriptReferences'] ?? [],
                'contentScriptDiagnostics' => $asset['contentScriptDiagnostics'] ?? [],
                'contentSwitches' => $asset['contentSwitches'] ?? [],
                'contentTriggers' => $asset['contentTriggers'] ?? [],
                'contentSemantics' => $asset['contentSemantics'] ?? [],
                'contentSemanticTypes' => $asset['contentSemanticTypes'] ?? [],
                'contentSemanticDiagnostics' => $asset['contentSemanticDiagnostics'] ?? [],
                'contentTables' => $asset['contentTables'] ?? [],
                'contentTableDiagnostics' => $asset['contentTableDiagnostics'] ?? [],
                'contentRubies' => $asset['contentRubies'] ?? [],
                'contentRubyDiagnostics' => $asset['contentRubyDiagnostics'] ?? [],
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
            'mimetypeEntry' => $mimetypeEntry,
            'opfPart' => $opfPart,
            'package' => $package,
            'metadata' => $metadata,
            'guide' => $guide,
            'collections' => $collections,
            'bindings' => $bindings,
            'accessibility' => $accessibility,
            'spineProperties' => $spineProperties,
            'resourceProperties' => $resourceProperties,
            'mediaTypes' => $mediaTypes,
            'remoteResources' => $remoteResources,
            'navigation' => $navigation,
            'navigationOutline' => $navigationOutline,
            'xhtmlResourceReport' => $xhtmlResourceReport,
            'cssResourceReport' => $cssResourceReport,
            'assets' => $assetReport,
            'mediaDurations' => $mediaDurations,
            'mediaOverlayStyles' => $mediaOverlayStyles,
            'pageBreaks' => $pageBreaks,
            'spineContentProvenance' => $spineContentProvenance,
            'renditions' => $renditions,
            'ocf' => $ocf,
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

    private static function isAbsoluteUrlWithFragment(string $reference): bool
    {
        return preg_match('/^[A-Za-z][A-Za-z0-9+.-]*:[^#\s]*#[^\s]+$/', $reference) === 1;
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
        return self::mediaTypeBaseEquals($item['mediaType'] ?? null, self::XHTML_MEDIA_TYPE)
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

    /**
     * @param list<string> $properties
     */
    private static function encryptedResourceRole(?string $mediaType, string $part, array $properties = []): string
    {
        if (in_array('cover-image', $properties, true)) {
            return 'cover-image';
        }

        $normalizedMediaType = strtolower(trim((string) ($mediaType ?? '')));
        if ($normalizedMediaType === '') {
            $normalizedMediaType = (string) (self::mediaTypeFromPart($part) ?? '');
        }
        $baseMediaType = self::baseMediaType($normalizedMediaType);

        if ($baseMediaType === self::XHTML_MEDIA_TYPE) {
            return 'xhtml';
        }
        if ($baseMediaType === self::NCX_MEDIA_TYPE) {
            return 'navigation';
        }
        if ($baseMediaType === self::SMIL_MEDIA_TYPE) {
            return 'media-overlay';
        }
        if ($baseMediaType === 'text/css') {
            return 'stylesheet';
        }
        if (str_starts_with($baseMediaType, 'image/')) {
            return 'image';
        }
        if (str_starts_with($baseMediaType, 'audio/')) {
            return 'audio';
        }
        if (str_starts_with($baseMediaType, 'video/')) {
            return 'video';
        }
        if (self::isFontResource($normalizedMediaType, $part)) {
            return 'font';
        }

        return 'asset';
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
        return self::normalizeWhitespace($element->textContent);
    }

    private static function normalizeWhitespace(string $text): string
    {
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return trim($text);
    }

    /**
     * @return array<string, string>
     */
    private static function elementAttributes(\DOMElement $element): array
    {
        $attributes = [];
        if (!$element->hasAttributes()) {
            return $attributes;
        }

        foreach ($element->attributes as $attribute) {
            if (!$attribute instanceof \DOMAttr) {
                continue;
            }

            $attributes[$attribute->name] = $attribute->value;
        }

        ksort($attributes);

        return $attributes;
    }

    private static function elementHidden(\DOMElement $element): bool
    {
        if ($element->hasAttribute('hidden')) {
            return true;
        }

        return strtolower(trim($element->getAttribute('aria-hidden'))) === 'true';
    }

    private static function nullableAttribute(\DOMElement $element, string $name): ?string
    {
        if (!$element->hasAttribute($name)) {
            return null;
        }

        $value = trim($element->getAttribute($name));

        return $value === '' ? null : $value;
    }

    private static function nullableNamespacedAttribute(
        \DOMElement $element,
        string $namespace,
        string $localName,
        string $prefixedName
    ): ?string {
        $value = trim($element->getAttributeNS($namespace, $localName));
        if ($value === '' && $element->hasAttribute($prefixedName)) {
            $value = trim($element->getAttribute($prefixedName));
        }

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

    private static function xmlBase(\DOMElement $element): ?string
    {
        $base = trim($element->getAttributeNS('http://www.w3.org/XML/1998/namespace', 'base'));
        if ($base === '') {
            $base = trim($element->getAttribute('xml:base'));
        }

        return $base === '' ? null : $base;
    }

    private static function direction(\DOMElement $element): ?string
    {
        $direction = trim($element->getAttribute('dir'));

        return $direction === '' ? null : $direction;
    }

    private static function loadXml(string $xml, string $label): \DOMDocument
    {
        return XmlHtmlDom::loadXmlDocument($xml, $label, preserveWhiteSpace: false);
    }
}
