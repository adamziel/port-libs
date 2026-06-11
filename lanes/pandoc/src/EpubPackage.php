<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class EpubPackage
{
    public const OCF_CONTAINER_NAMESPACE = 'urn:oasis:names:tc:opendocument:xmlns:container';
    public const OPF_NAMESPACE = 'http://www.idpf.org/2007/opf';
    public const DC_NAMESPACE = 'http://purl.org/dc/elements/1.1/';
    public const EPUB_OPS_NAMESPACE = 'http://www.idpf.org/2007/ops';
    public const EPUB_METADATA_NAMESPACE = 'http://www.idpf.org/2013/metadata';
    public const XHTML_NAMESPACE = 'http://www.w3.org/1999/xhtml';
    public const NCX_NAMESPACE = 'http://www.daisy.org/z3986/2005/ncx/';
    public const SMIL_NAMESPACE = 'http://www.w3.org/ns/SMIL';
    public const XMLENC_NAMESPACE = 'http://www.w3.org/2001/04/xmlenc#';
    public const EPUB_MIMETYPE = 'application/epub+zip';
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
    private const CORE_RESOURCE_PROPERTIES = [
        'nav' => 'nav',
        'cover-image' => 'coverImage',
        'mathml' => 'mathml',
        'svg' => 'svg',
        'remote-resources' => 'remoteResources',
        'scripted' => 'scripted',
        'switch' => 'switch',
    ];

    /**
     * @param list<array{fullPath:string, partName:string, mediaType:string}> $rootfiles
     * @param array<string, mixed> $metadata
     * @param list<array<string, mixed>> $containerLinks
     * @param list<array<string, mixed>> $packageLinks
     * @param array<string, array{id:string, href:string, partName:string, mediaType:string, properties:list<string>, fallback:?string, fallbackStyle:?string, mediaOverlay:?string}> $manifestById
     * @param list<array{id:string, href:string, partName:string, mediaType:string, properties:list<string>, fallback:?string, fallbackStyle:?string, mediaOverlay:?string}> $manifestItems
     * @param list<array<string, mixed>> $spine
     * @param array<string, mixed> $spineMetadata
     * @param list<array{type:?string, title:?string, href:?string, target:?string, partName:?string, external:bool, exists:bool, hrefHasQuery:bool, hrefQuery:?string, hrefHasFragment:bool, hrefFragment:?string}> $guideReferences
     * @param list<array<string, mixed>> $collections
     * @param array<string, mixed> $bindings
     * @param array<string, mixed> $mediaOverlays
     * @param array<string, mixed> $manifestFallbacks
     * @param array<string, mixed> $encryption
     * @param ?string $spineTocId
     * @param array{type:string, partName:string, entries:list<array{label:string, href:?string, target:?string, depth:int, playOrder:?int}>}|null $navigation
     * @param list<array{type:?string, types:list<string>, label:?string, partName:string, entries:list<array{label:string, href:?string, target:?string, depth:int, playOrder:?int}>}> $navigationSections
     */
    private function __construct(
        private readonly ZipPackage $package,
        private readonly array $rootfiles,
        private readonly array $containerLinks,
        private readonly string $opfPartName,
        private readonly array $metadata,
        private readonly array $packageLinks,
        private readonly array $manifestById,
        private readonly array $manifestItems,
        private readonly array $spine,
        private readonly array $spineMetadata,
        private readonly array $guideReferences,
        private readonly array $collections,
        private readonly array $bindings,
        private readonly array $mediaOverlays,
        private readonly array $manifestFallbacks,
        private readonly array $encryption,
        private readonly ?string $spineTocId,
        private readonly ?array $navigation,
        private readonly array $navigationSections,
    ) {
    }

    public static function fromString(string $bytes): self
    {
        return self::fromPackage(ZipPackage::fromString($bytes));
    }

    public static function fromPackage(ZipPackage $package): self
    {
        self::assertEpubMimetype($package);

        if (!$package->has('META-INF/container.xml')) {
            throw new \RuntimeException('EPUB package is missing META-INF/container.xml');
        }

        $rootfiles = self::parseContainerXml($package->read('META-INF/container.xml'));
        $opfPartName = null;
        foreach ($rootfiles as $rootfile) {
            if ($rootfile['mediaType'] === self::OPF_MEDIA_TYPE) {
                $opfPartName = $rootfile['partName'];
                break;
            }
        }

        if ($opfPartName === null) {
            throw new \RuntimeException('EPUB container does not declare an OPF rootfile');
        }

        if (!$package->has($opfPartName)) {
            throw new \RuntimeException("EPUB OPF package document not found: {$opfPartName}");
        }

        $opf = self::parseOpfXml($package->read($opfPartName), $opfPartName, $package);
        $containerLinks = self::parseContainerLinks($package, self::manifestByPart($opf['manifestById']));
        $navigation = self::loadNavigation($package, $opfPartName, $opf['manifestById'], $opf['spineTocId']);

        return new self(
            $package,
            $rootfiles,
            $containerLinks,
            $opfPartName,
            $opf['metadata'],
            $opf['packageLinks'],
            $opf['manifestById'],
            $opf['manifestItems'],
            $opf['spine'],
            $opf['spineMetadata'],
            $opf['guideReferences'],
            $opf['collections'],
            $opf['bindings'],
            $opf['mediaOverlays'],
            $opf['manifestFallbacks'],
            $opf['encryption'],
            $opf['spineTocId'],
            $navigation['navigation'],
            $navigation['sections'],
        );
    }

    public function package(): ZipPackage
    {
        return $this->package;
    }

    /**
     * @return list<array{fullPath:string, partName:string, mediaType:string}>
     */
    public function rootfiles(): array
    {
        return $this->rootfiles;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function containerLinks(): array
    {
        return $this->containerLinks;
    }

    public function opfPartName(): string
    {
        return $this->opfPartName;
    }

    /**
     * @return array<string, mixed>
     */
    public function metadata(): array
    {
        return $this->metadata;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function packageLinks(): array
    {
        return $this->packageLinks;
    }

    /**
     * @return list<array{id:string, href:string, partName:string, mediaType:string, properties:list<string>, fallback:?string, fallbackStyle:?string, mediaOverlay:?string}>
     */
    public function manifestItems(): array
    {
        return $this->manifestItems;
    }

    /**
     * @return array{id:string, href:string, partName:string, mediaType:string, properties:list<string>, fallback:?string, fallbackStyle:?string, mediaOverlay:?string}|null
     */
    public function manifestItem(string $id): ?array
    {
        return $this->manifestById[$id] ?? null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function spine(): array
    {
        return $this->spine;
    }

    /**
     * @return array<string, mixed>
     */
    public function spineMetadata(): array
    {
        return $this->spineMetadata;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function readingOrder(): array
    {
        return $this->spine;
    }

    /**
     * @return list<array{type:?string, title:?string, href:?string, target:?string, partName:?string, external:bool, exists:bool, hrefHasQuery:bool, hrefQuery:?string, hrefHasFragment:bool, hrefFragment:?string}>
     */
    public function guideReferences(): array
    {
        return $this->guideReferences;
    }

    /**
     * @return array<string, mixed>
     */
    public function guideReport(): array
    {
        return self::guideReferenceReport($this->guideReferences);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function collections(): array
    {
        return $this->collections;
    }

    /**
     * @return array<string, mixed>
     */
    public function bindings(): array
    {
        return $this->bindings;
    }

    /**
     * @return array<string, mixed>
     */
    public function mediaOverlays(): array
    {
        return $this->mediaOverlays;
    }

    /**
     * @return array<string, mixed>
     */
    public function manifestFallbacks(): array
    {
        return $this->manifestFallbacks;
    }

    /**
     * @return array<string, mixed>
     */
    public function encryption(): array
    {
        return $this->encryption;
    }

    /**
     * @return array<string, mixed>
     */
    public function resourceProperties(): array
    {
        $prefixBindings = is_array($this->metadata['prefixBindings'] ?? null)
            ? $this->metadata['prefixBindings']
            : [];

        return self::resourcePropertyReport($this->manifestItems, $prefixBindings);
    }

    /**
     * @return array{type:string, partName:string, entries:list<array{label:string, href:?string, target:?string, depth:int, playOrder:?int}>}|null
     */
    public function navigation(): ?array
    {
        return $this->navigation;
    }

    /**
     * @return list<array{type:?string, types:list<string>, label:?string, partName:string, entries:list<array{label:string, href:?string, target:?string, depth:int, playOrder:?int}>}>
     */
    public function navigationSections(): array
    {
        return $this->navigationSections;
    }

    /**
     * @return list<array{id:string, href:string, partName:string, mediaType:string, properties:list<string>, fallback:?string, mediaOverlay:?string}>
     */
    public function xhtmlAssets(): array
    {
        return array_values(array_filter(
            $this->manifestItems,
            static fn (array $item): bool => $item['mediaType'] === self::XHTML_MEDIA_TYPE,
        ));
    }

    /**
     * @return array{readingOrderParts:list<string>, xhtmlParts:list<string>, stylesheetParts:list<string>, imageParts:list<string>, coverImagePart:?string, navigationPart:?string, ncxPart:?string}
     */
    public function assetSummary(): array
    {
        $stylesheetParts = [];
        $imageParts = [];
        $xhtmlParts = [];
        $coverImagePart = null;
        $legacyCoverImageId = $this->metadata['coverImageId'] ?? null;
        $navigationType = $this->navigation['type'] ?? null;
        $navigationPart = $this->navigation['partName'] ?? null;

        foreach ($this->manifestItems as $item) {
            if ($item['mediaType'] === self::XHTML_MEDIA_TYPE) {
                $xhtmlParts[] = $item['partName'];
            }

            if ($item['mediaType'] === 'text/css') {
                $stylesheetParts[] = $item['partName'];
            }

            if (str_starts_with($item['mediaType'], 'image/')) {
                $imageParts[] = $item['partName'];
            }

            if (in_array('cover-image', $item['properties'], true) || ($legacyCoverImageId !== null && $item['id'] === $legacyCoverImageId)) {
                $coverImagePart ??= $item['partName'];
            }
        }

        return [
            'readingOrderParts' => array_values(array_map(
                static fn (array $item): string => $item['partName'],
                $this->spine,
            )),
            'xhtmlParts' => $xhtmlParts,
            'stylesheetParts' => $stylesheetParts,
            'imageParts' => $imageParts,
            'coverImagePart' => $coverImagePart,
            'navigationPart' => $navigationType === 'nav' ? $navigationPart : null,
            'ncxPart' => $navigationType === 'ncx' ? $navigationPart : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function remoteResourcePolicy(): array
    {
        return self::remoteResourcePolicyReport($this->packageLinks, $this->collections, $this->containerLinks);
    }

    /**
     * @return array<string, mixed>
     */
    public function validationReport(): array
    {
        return self::packageValidationReport(
            $this->package,
            $this->rootfiles,
            $this->opfPartName,
            $this->metadata,
            $this->manifestItems,
            $this->spine,
            $this->spineMetadata,
            $this->spineTocId,
            $this->navigation,
            $this->navigationSections,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function summary(): array
    {
        $assetSummary = $this->assetSummary();
        $navigationEntries = $this->navigation['entries'] ?? [];
        $containerLinkReport = self::collectionLinkReport($this->containerLinks);
        $packageLinkReport = self::collectionLinkReport($this->packageLinks);
        $packageLinkVocabulary = is_array($this->metadata['linkVocabulary'] ?? null)
            ? $this->metadata['linkVocabulary']
            : self::metadataLinkVocabularySummary($this->packageLinks);
        $remoteResourcePolicy = $this->remoteResourcePolicy();
        $mediaOverlayDiagnostics = self::mediaOverlayDiagnostics($this->mediaOverlays);
        $manifestFallbacks = $this->manifestFallbacks();
        $resourceProperties = $this->resourceProperties();
        $validationReport = $this->validationReport();
        $auxiliaryNavigation = self::auxiliaryNavigationReport($this->navigationSections);
        $spineMetadata = $this->spineMetadata();
        $guideReport = $this->guideReport();

        return [
            'opfPart' => $this->opfPartName,
            'rootfiles' => $this->rootfiles,
            'containerLinks' => $this->containerLinks,
            'containerLinksByRel' => $containerLinkReport['linksByRel'],
            'containerLinkRelCounts' => $containerLinkReport['relCounts'],
            'containerLinkDiagnostics' => $containerLinkReport['diagnostics'],
            'metadata' => $this->metadata,
            'packageLinks' => $this->packageLinks,
            'packageLinksByRel' => $packageLinkReport['linksByRel'],
            'packageLinkRelCounts' => $packageLinkReport['relCounts'],
            'packageLinkDiagnostics' => $packageLinkReport['diagnostics'],
            'packageLinkVocabulary' => $packageLinkVocabulary,
            'renditionLayout' => $this->metadata['renditionLayout'] ?? self::metadataRenditionLayoutReport([]),
            'accessibility' => $this->metadata['accessibility'] ?? self::accessibilityMetadataReport($this->metadata, $this->packageLinks),
            'manifest' => $this->manifestItems,
            'readingOrder' => $this->spine,
            'spineMetadata' => $spineMetadata,
            'guide' => $this->guideReferences,
            'guideReport' => $guideReport,
            'collections' => $this->collections,
            'bindings' => $this->bindings,
            'mediaOverlays' => $this->mediaOverlays,
            'manifestFallbacks' => $manifestFallbacks,
            'encryption' => $this->encryption,
            'resourceProperties' => $resourceProperties,
            'navigation' => $this->navigation,
            'navigationSections' => $this->navigationSections,
            'auxiliaryNavigation' => $auxiliaryNavigation,
            'assets' => $assetSummary,
            'remoteResourcePolicy' => $remoteResourcePolicy,
            'validation' => $validationReport,
            'wordpressImport' => [
                'title' => $this->metadata['title'],
                'creators' => $this->metadata['creators'],
                'language' => $this->metadata['language'],
                'metadataDetails' => [
                    'package' => $this->metadata['package'] ?? [],
                    'packageId' => $this->metadata['packageId'] ?? null,
                    'packageLanguage' => $this->metadata['packageLanguage'] ?? null,
                    'packageDirection' => $this->metadata['packageDirection'] ?? null,
                    'titleDetails' => $this->metadata['titleDetails'] ?? [],
                    'titlesByType' => $this->metadata['titlesByType'] ?? [],
                    'mainTitle' => $this->metadata['mainTitle'] ?? null,
                    'subtitle' => $this->metadata['subtitle'] ?? null,
                    'shortTitle' => $this->metadata['shortTitle'] ?? null,
                    'sortTitle' => $this->metadata['sortTitle'] ?? null,
                    'creatorDetails' => $this->metadata['creatorDetails'] ?? [],
                    'creatorsByRole' => $this->metadata['creatorsByRole'] ?? [],
                    'uniqueIdentifier' => $this->metadata['uniqueIdentifier'] ?? null,
                    'identifierDetails' => $this->metadata['identifierDetails'] ?? [],
                    'identifierSummary' => $this->metadata['identifierSummary'] ?? [],
                    'identifierDiagnostics' => $this->metadata['identifierDiagnostics'] ?? [],
                    'identifiersByType' => $this->metadata['identifiersByType'] ?? [],
                    'languageDetails' => $this->metadata['languageDetails'] ?? [],
                    'languagesByPrimarySubtag' => $this->metadata['languagesByPrimarySubtag'] ?? [],
                    'languageSummary' => $this->metadata['languageSummary'] ?? [],
                    'dateDetails' => $this->metadata['dateDetails'] ?? [],
                    'datesByEvent' => $this->metadata['datesByEvent'] ?? [],
                    'dateSummary' => $this->metadata['dateSummary'] ?? [],
                    'sourceDetails' => $this->metadata['sourceDetails'] ?? [],
                    'sourcesByType' => $this->metadata['sourcesByType'] ?? [],
                    'sourceSummary' => $this->metadata['sourceSummary'] ?? [],
                    'subjects' => $this->metadata['subjects'] ?? [],
                    'description' => $this->metadata['description'] ?? null,
                    'publisher' => $this->metadata['publisher'] ?? null,
                    'bibliographicDetails' => $this->metadata['bibliographicDetails'] ?? [],
                    'bibliographicDetailsByKind' => $this->metadata['bibliographicDetailsByKind'] ?? [],
                    'bibliographicSummary' => $this->metadata['bibliographicSummary'] ?? [],
                    'renditionLayout' => $this->metadata['renditionLayout'] ?? self::metadataRenditionLayoutReport([]),
                    'refinementsById' => $this->metadata['refinementsById'] ?? [],
                ],
                'readingOrderParts' => $assetSummary['readingOrderParts'],
                'spineMetadata' => $spineMetadata,
                'pageProgressionDirection' => $spineMetadata['pageProgressionDirection'],
                'readingProgression' => $spineMetadata['readingProgression'],
                'spinePackageDiagnostics' => $spineMetadata['diagnostics'],
                'spinePageSpreadItems' => $validationReport['spine']['pageSpreadItems'],
                'spineItemDiagnostics' => $validationReport['spine']['itemDiagnostics'],
                'navigationLabels' => array_values(array_map(
                    static fn (array $entry): string => $entry['label'],
                    $navigationEntries,
                )),
                'guideReferences' => $this->guideReferences,
                'guideReferenceReport' => $guideReport,
                'guideReferenceTargets' => $guideReport['targets'],
                'guideReferenceDiagnostics' => $guideReport['diagnostics'],
                'collections' => $this->collections,
                'containerLinks' => $this->containerLinks,
                'containerLinksByRel' => $containerLinkReport['linksByRel'],
                'containerLinkTargets' => self::packageLinkTargets($this->containerLinks),
                'containerLinkDiagnostics' => $containerLinkReport['diagnostics'],
                'collectionTitles' => self::collectionTitles($this->collections),
                'collectionLinkTargets' => self::collectionLinkTargets($this->collections),
                'collectionDiagnostics' => self::collectionDiagnostics($this->collections),
                'packageLinks' => $this->packageLinks,
                'packageLinksByRel' => $packageLinkReport['linksByRel'],
                'packageLinkTargets' => self::packageLinkTargets($this->packageLinks),
                'packageLinkDiagnostics' => $packageLinkReport['diagnostics'],
                'packageLinkVocabulary' => $packageLinkVocabulary,
                'packageLinkVocabularyDiagnostics' => $packageLinkVocabulary['diagnostics'],
                'accessibility' => $this->metadata['accessibility'] ?? self::accessibilityMetadataReport($this->metadata, $this->packageLinks),
                'remoteResourcePolicy' => $remoteResourcePolicy,
                'remoteResourceExternalTargets' => $remoteResourcePolicy['externalTargets'],
                'remoteResourcePolicyDiagnostics' => $remoteResourcePolicy['diagnostics'],
                'mediaTypeBindings' => $this->bindings['items'],
                'mediaTypeBindingDiagnostics' => $this->bindings['diagnostics'],
                'mediaOverlays' => $this->mediaOverlays,
                'mediaOverlayItems' => $this->mediaOverlays['items'],
                'mediaOverlayTargets' => $this->mediaOverlays['textTargets'],
                'mediaOverlayAudioTargets' => $this->mediaOverlays['audioTargets'],
                'mediaOverlayDiagnostics' => $mediaOverlayDiagnostics,
                'manifestFallbacks' => $manifestFallbacks,
                'manifestFallbackItems' => $manifestFallbacks['fallbackItems'],
                'manifestFallbackStyleItems' => $manifestFallbacks['fallbackStyleItems'],
                'manifestFallbackDiagnostics' => $manifestFallbacks['diagnostics'],
                'encryption' => $this->encryption,
                'encryptedResourceExposure' => $this->encryption['exposure'],
                'encryptedResourceDiagnostics' => $this->encryption['diagnostics'],
                'resourceProperties' => $resourceProperties,
                'resourcePropertySummary' => $resourceProperties['summary'],
                'resourcePropertyReviewItems' => $resourceProperties['reviewItems'],
                'resourcePropertyDiagnostics' => $resourceProperties['propertyVocabulary']['diagnostics'],
                'packageValidation' => $validationReport,
                'packageValidationDiagnostics' => $validationReport['diagnostics'],
                'navDocumentDiagnostics' => $validationReport['navigation']['documentDiagnostics'],
                'landmarkTargets' => self::navigationEntriesForSectionType($this->navigationSections, 'landmarks'),
                'pageListTargets' => self::navigationEntriesForSectionType($this->navigationSections, 'page-list'),
                'auxiliaryNavigation' => $auxiliaryNavigation,
                'auxiliaryNavigationSections' => $auxiliaryNavigation['sections'],
                'auxiliaryNavigationTargets' => $auxiliaryNavigation['items'],
                'coverImagePart' => $assetSummary['coverImagePart'],
                'stylesheetParts' => $assetSummary['stylesheetParts'],
                'imageParts' => $assetSummary['imageParts'],
            ],
        ];
    }

    /**
     * @param list<array{fullPath:string, partName:string, mediaType:string}> $rootfiles
     * @param array<string, mixed> $metadata
     * @param list<array<string, mixed>> $manifestItems
     * @param list<array<string, mixed>> $spine
     * @param array<string, mixed> $spineMetadata
     * @param ?string $spineTocId
     * @param array<string, mixed>|null $navigation
     * @param list<array<string, mixed>> $navigationSections
     *
     * @return array<string, mixed>
     */
    private static function packageValidationReport(
        ZipPackage $package,
        array $rootfiles,
        string $opfPartName,
        array $metadata,
        array $manifestItems,
        array $spine,
        array $spineMetadata,
        ?string $spineTocId,
        ?array $navigation,
        array $navigationSections
    ): array {
        $version = (string) ($metadata['version'] ?? '');
        $epub3 = preg_match('/^3(?:\.|$)/', trim($version)) === 1;
        $rootfileReport = self::packageRootfileValidationReport($package, $rootfiles, $opfPartName);
        $metadataReport = self::packageMetadataValidationReport($metadata, $epub3);
        $manifestReport = self::packageManifestValidationReport($manifestItems, $epub3);
        $spineReport = self::packageSpineValidationReport($spine, $spineMetadata);
        $ncxReport = self::packageNcxValidationReport($spineTocId, $manifestItems, $navigation);
        $navigationReport = self::packageNavigationValidationReport($package, $navigation, $navigationSections);
        $diagnostics = array_merge(
            $rootfileReport['diagnostics'],
            $metadataReport['diagnostics'],
            $manifestReport['diagnostics'],
            $spineReport['diagnostics'],
            $ncxReport['diagnostics'],
            $navigationReport['diagnostics'],
        );

        return [
            'valid' => $diagnostics === [],
            'packageVersion' => $version,
            'epub3' => $epub3,
            'opfPart' => $opfPartName,
            'rootfileCount' => count($rootfiles),
            'alternateRootfileCount' => max(0, count($rootfiles) - 1),
            'diagnosticCount' => count($diagnostics),
            'diagnostics' => $diagnostics,
            'rootfiles' => $rootfileReport,
            'metadata' => $metadataReport,
            'manifest' => $manifestReport,
            'spine' => $spineReport,
            'ncx' => $ncxReport,
            'navigation' => $navigationReport,
        ];
    }

    /**
     * @param list<array{fullPath:string, partName:string, mediaType:string}> $rootfiles
     *
     * @return array<string, mixed>
     */
    private static function packageRootfileValidationReport(ZipPackage $package, array $rootfiles, string $opfPartName): array
    {
        $items = [];
        $opfRootfiles = [];
        $alternateRootfiles = [];
        $missingRootfiles = [];
        $nonOpfRootfiles = [];
        $parts = [];
        $diagnostics = [];
        $selectedIndex = null;

        foreach ($rootfiles as $index => $rootfile) {
            $partName = (string) ($rootfile['partName'] ?? '');
            $mediaType = self::mediaTypeBase((string) ($rootfile['mediaType'] ?? ''));
            $exists = $partName !== '' && $package->has($partName);
            $selected = $selectedIndex === null && $partName === $opfPartName && $mediaType === self::OPF_MEDIA_TYPE;
            if ($selected) {
                $selectedIndex = $index;
            }

            $item = [
                'index' => $index,
                'fullPath' => (string) ($rootfile['fullPath'] ?? ''),
                'partName' => $partName,
                'mediaType' => $mediaType,
                'exists' => $exists,
                'selected' => $selected,
            ];
            $items[] = $item;

            if ($mediaType === self::OPF_MEDIA_TYPE) {
                $opfRootfiles[] = $item;
            } else {
                $nonOpfRootfiles[] = $item;
                $diagnostics[] = [
                    'type' => 'non-opf-container-rootfile',
                    'index' => $index,
                    'partName' => $partName,
                    'mediaType' => $mediaType,
                    'message' => 'EPUB container rootfile should identify an OPF package document',
                ];
            }

            if (!$selected) {
                $alternateRootfiles[] = $item;
            }

            if (!$exists) {
                $missingRootfiles[] = $item;
                $diagnostics[] = [
                    'type' => 'missing-rootfile-package-part',
                    'index' => $index,
                    'partName' => $partName,
                    'mediaType' => $mediaType,
                    'message' => 'EPUB container rootfile points at a package part that is not present in the ZIP',
                ];
            }

            if ($partName !== '') {
                $parts[$partName][] = $item;
            }
        }

        $duplicatePartItems = [];
        foreach ($parts as $partName => $matches) {
            if (count($matches) < 2) {
                continue;
            }

            $duplicate = [
                'partName' => $partName,
                'indexes' => array_column($matches, 'index'),
                'mediaTypes' => array_values(array_unique(array_column($matches, 'mediaType'))),
            ];
            $duplicatePartItems[] = $duplicate;
            $diagnostics[] = [
                'type' => 'duplicate-rootfile-package-part',
                'partName' => $partName,
                'indexes' => $duplicate['indexes'],
                'message' => 'EPUB container declares the same rootfile package part more than once',
            ];
        }

        return [
            'valid' => $diagnostics === [],
            'selectedIndex' => $selectedIndex,
            'selectedPart' => $opfPartName,
            'rootfileCount' => count($items),
            'opfRootfileCount' => count($opfRootfiles),
            'alternateRootfileCount' => count($alternateRootfiles),
            'missingRootfileCount' => count($missingRootfiles),
            'nonOpfRootfileCount' => count($nonOpfRootfiles),
            'duplicatePartCount' => count($duplicatePartItems),
            'items' => $items,
            'opfRootfiles' => $opfRootfiles,
            'alternateRootfiles' => $alternateRootfiles,
            'missingRootfiles' => $missingRootfiles,
            'nonOpfRootfiles' => $nonOpfRootfiles,
            'duplicatePartItems' => $duplicatePartItems,
            'diagnosticCount' => count($diagnostics),
            'diagnostics' => $diagnostics,
        ];
    }

    /**
     * @param array<string, mixed> $metadata
     *
     * @return array<string, mixed>
     */
    private static function packageMetadataValidationReport(array $metadata, bool $epub3): array
    {
        $titlePresent = trim((string) ($metadata['title'] ?? '')) !== '';
        $identifierPresent = trim((string) ($metadata['identifier'] ?? '')) !== ''
            || (is_array($metadata['identifiers'] ?? null) && $metadata['identifiers'] !== []);
        $languagePresent = trim((string) ($metadata['language'] ?? '')) !== '';
        $modifiedPresent = trim((string) ($metadata['modified'] ?? '')) !== '';
        $diagnostics = [];

        if (!$titlePresent) {
            $diagnostics[] = [
                'type' => 'missing-epub-metadata-title',
                'message' => 'EPUB OPF metadata should include a dc:title for import review',
            ];
        }

        if (!$identifierPresent) {
            $diagnostics[] = [
                'type' => 'missing-epub-metadata-identifier',
                'message' => 'EPUB OPF metadata should include a dc:identifier for source provenance',
            ];
        }

        if (!$languagePresent) {
            $diagnostics[] = [
                'type' => 'missing-epub-metadata-language',
                'message' => 'EPUB OPF metadata should include a dc:language for review handoff',
            ];
        }

        if ($epub3 && !$modifiedPresent) {
            $diagnostics[] = [
                'type' => 'missing-epub3-modified-metadata',
                'message' => 'EPUB 3 package metadata should include dcterms:modified',
            ];
        }

        return [
            'valid' => $diagnostics === [],
            'titlePresent' => $titlePresent,
            'identifierPresent' => $identifierPresent,
            'languagePresent' => $languagePresent,
            'modifiedPresent' => $modifiedPresent,
            'diagnosticCount' => count($diagnostics),
            'diagnostics' => $diagnostics,
        ];
    }

    /**
     * @param list<array<string, mixed>> $manifestItems
     *
     * @return array<string, mixed>
     */
    private static function packageManifestValidationReport(array $manifestItems, bool $epub3): array
    {
        $navItems = [];
        $usableNavItems = [];
        $invalidNavItems = [];
        $hrefSuffixItems = [];
        $missingItems = [];
        $parts = [];
        $ids = [];
        $diagnostics = [];

        foreach ($manifestItems as $index => $item) {
            $id = (string) ($item['id'] ?? '');
            $partName = (string) ($item['partName'] ?? '');
            $mediaType = self::mediaTypeBase((string) ($item['mediaType'] ?? ''));
            $properties = is_array($item['properties'] ?? null) ? array_values($item['properties']) : [];
            if ($id !== '') {
                $ids[$id][] = [
                    'index' => $index,
                    'id' => $id,
                    'href' => (string) ($item['href'] ?? ''),
                    'target' => (string) ($item['target'] ?? ''),
                    'partName' => $partName,
                    'mediaType' => $mediaType,
                ];
            }

            if (($item['exists'] ?? false) !== true) {
                $missingItem = [
                    'id' => $id,
                    'href' => (string) ($item['href'] ?? ''),
                    'target' => (string) ($item['target'] ?? ''),
                    'partName' => $partName,
                    'mediaType' => $mediaType,
                ];
                $missingItems[] = $missingItem;
                $diagnostics[] = [
                    'type' => 'missing-manifest-href-target',
                    'id' => $id,
                    'href' => $missingItem['href'],
                    'partName' => $partName,
                    'mediaType' => $mediaType,
                    'message' => 'EPUB OPF manifest href points at a package part that is not present in the ZIP',
                ];
            }

            $hasQuery = ($item['hrefHasQuery'] ?? false) === true;
            $hasFragment = ($item['hrefHasFragment'] ?? false) === true;
            if ($hasQuery || $hasFragment) {
                $suffixItem = [
                    'id' => $id,
                    'href' => (string) ($item['href'] ?? ''),
                    'target' => (string) ($item['target'] ?? ''),
                    'partName' => $partName,
                    'mediaType' => $mediaType,
                    'query' => is_string($item['hrefQuery'] ?? null) ? $item['hrefQuery'] : null,
                    'fragment' => is_string($item['hrefFragment'] ?? null) ? $item['hrefFragment'] : null,
                ];
                $hrefSuffixItems[] = $suffixItem;
                if ($hasQuery) {
                    $diagnostics[] = [
                        'type' => 'manifest-href-query-component',
                        'id' => $id,
                        'href' => $suffixItem['href'],
                        'partName' => $partName,
                        'query' => $suffixItem['query'],
                        'message' => 'EPUB OPF manifest href includes a query component; compact package ingestion loads the package part and preserves the suffix for review',
                    ];
                }
                if ($hasFragment) {
                    $diagnostics[] = [
                        'type' => 'manifest-href-fragment-component',
                        'id' => $id,
                        'href' => $suffixItem['href'],
                        'partName' => $partName,
                        'fragment' => $suffixItem['fragment'],
                        'message' => 'EPUB OPF manifest href includes a fragment component; compact package ingestion loads the package part and preserves the suffix for review',
                    ];
                }
            }

            if ($partName !== '') {
                $parts[$partName][] = [
                    'id' => $id,
                    'href' => (string) ($item['href'] ?? ''),
                    'mediaType' => $mediaType,
                ];
            }

            if (!in_array('nav', $properties, true)) {
                continue;
            }

            $navItem = [
                'id' => $id,
                'href' => (string) ($item['href'] ?? ''),
                'partName' => $partName,
                'mediaType' => $mediaType,
            ];
            $navItems[] = $navItem;
            if ($mediaType === self::XHTML_MEDIA_TYPE) {
                $usableNavItems[] = $navItem;
                continue;
            }

            $invalidNavItems[] = $navItem;
            $diagnostics[] = [
                'type' => 'nav-property-non-xhtml-manifest-item',
                'id' => $id,
                'partName' => $partName,
                'mediaType' => $mediaType,
                'message' => 'EPUB nav manifest property should be attached to an XHTML navigation document',
            ];
        }

        if ($epub3 && $usableNavItems === []) {
            $diagnostics[] = [
                'type' => 'missing-epub3-nav-document',
                'message' => 'EPUB 3 packages should declare an XHTML navigation document with the nav manifest property',
            ];
        }

        if (count($usableNavItems) > 1) {
            $diagnostics[] = [
                'type' => 'multiple-epub3-nav-documents',
                'ids' => array_column($usableNavItems, 'id'),
                'message' => 'EPUB package declares multiple XHTML nav manifest items; the first one is used for compact preflight',
            ];
        }

        $duplicateIdItems = [];
        foreach ($ids as $id => $items) {
            if (count($items) < 2) {
                continue;
            }

            $duplicate = [
                'id' => $id,
                'indexes' => array_column($items, 'index'),
                'hrefs' => array_column($items, 'href'),
                'targets' => array_column($items, 'target'),
                'partNames' => array_column($items, 'partName'),
                'mediaTypes' => array_values(array_unique(array_column($items, 'mediaType'))),
                'selectedIndex' => $items[0]['index'],
                'selectedPartName' => $items[0]['partName'],
            ];
            $duplicateIdItems[] = $duplicate;
            $diagnostics[] = [
                'type' => 'duplicate-manifest-item-id',
                'id' => $id,
                'indexes' => $duplicate['indexes'],
                'partNames' => $duplicate['partNames'],
                'selectedIndex' => $duplicate['selectedIndex'],
                'selectedPartName' => $duplicate['selectedPartName'],
                'message' => 'EPUB OPF manifest reuses an item id; compact package ingestion keeps the first item for idref resolution and exposes all occurrences for review',
            ];
        }

        $duplicatePartItems = [];
        foreach ($parts as $partName => $items) {
            if (count($items) < 2) {
                continue;
            }

            $duplicate = [
                'partName' => $partName,
                'ids' => array_column($items, 'id'),
                'hrefs' => array_column($items, 'href'),
                'mediaTypes' => array_values(array_unique(array_column($items, 'mediaType'))),
            ];
            $duplicatePartItems[] = $duplicate;
            $diagnostics[] = [
                'type' => 'duplicate-manifest-part-target',
                'partName' => $partName,
                'ids' => $duplicate['ids'],
                'message' => 'EPUB OPF manifest maps multiple item ids to the same package part',
            ];
        }

        return [
            'valid' => $diagnostics === [],
            'itemCount' => count($manifestItems),
            'navItemCount' => count($navItems),
            'usableNavItemCount' => count($usableNavItems),
            'invalidNavItemCount' => count($invalidNavItems),
            'missingItemCount' => count($missingItems),
            'duplicateIdCount' => count($duplicateIdItems),
            'duplicateManifestIdCount' => count($duplicateIdItems),
            'duplicatePartCount' => count($duplicatePartItems),
            'duplicateHrefTargetCount' => count($duplicatePartItems),
            'hrefSuffixCount' => count($hrefSuffixItems),
            'navItems' => $navItems,
            'usableNavItems' => $usableNavItems,
            'invalidNavItems' => $invalidNavItems,
            'missingItems' => $missingItems,
            'duplicateIdItems' => $duplicateIdItems,
            'duplicateManifestIdItems' => $duplicateIdItems,
            'duplicatePartItems' => $duplicatePartItems,
            'duplicateHrefTargetItems' => $duplicatePartItems,
            'hrefSuffixItems' => $hrefSuffixItems,
            'diagnosticCount' => count($diagnostics),
            'diagnostics' => $diagnostics,
        ];
    }

    /**
     * @param list<array<string, mixed>> $spine
     * @param array<string, mixed> $spineMetadata
     *
     * @return array<string, mixed>
     */
    private static function packageSpineValidationReport(array $spine, array $spineMetadata): array
    {
        $linearCount = 0;
        $nonLinearCount = 0;
        $missingManifestItems = [];
        $missingPackagePartItems = [];
        $nonContentDocumentItems = [];
        $invalidLinearItems = [];
        $pageSpreadItems = [];
        $pageSpreadCounts = [
            'left' => 0,
            'right' => 0,
            'center' => 0,
        ];
        $itemDiagnostics = [];
        $diagnostics = is_array($spineMetadata['diagnostics'] ?? null)
            ? array_values($spineMetadata['diagnostics'])
            : [];

        foreach ($spine as $index => $item) {
            if (($item['linear'] ?? true) === false) {
                ++$nonLinearCount;
            } else {
                ++$linearCount;
            }

            $itemProperties = is_array($item['spineItemProperties'] ?? null) ? $item['spineItemProperties'] : [];
            $pageSpread = is_string($item['pageSpread'] ?? null) ? $item['pageSpread'] : null;
            $pageSpreadProperties = is_array($item['pageSpreadProperties'] ?? null)
                ? array_values($item['pageSpreadProperties'])
                : [];
            if ($pageSpread !== null || $pageSpreadProperties !== []) {
                if ($pageSpread !== null && array_key_exists($pageSpread, $pageSpreadCounts)) {
                    ++$pageSpreadCounts[$pageSpread];
                }

                $pageSpreadItems[] = [
                    'index' => $index,
                    'id' => is_string($item['id'] ?? null) ? $item['id'] : null,
                    'idref' => (string) ($item['idref'] ?? ''),
                    'partName' => (string) ($item['partName'] ?? ''),
                    'placement' => $pageSpread,
                    'properties' => $pageSpreadProperties,
                    'conflicting' => (bool) ($itemProperties['pageSpread']['conflicting'] ?? false),
                ];
            }

            foreach (is_array($item['spineItemDiagnostics'] ?? null) ? $item['spineItemDiagnostics'] : [] as $itemDiagnostic) {
                if (!is_array($itemDiagnostic)) {
                    continue;
                }

                $diagnostic = [
                    'index' => $index,
                    'idref' => (string) ($item['idref'] ?? ''),
                ] + $itemDiagnostic;
                $itemDiagnostics[] = $diagnostic;
                if (($diagnostic['type'] ?? null) === 'invalid-spine-linear-value') {
                    $invalidLinearItems[] = [
                        'index' => $index,
                        'idref' => (string) ($item['idref'] ?? ''),
                        'value' => is_string($itemDiagnostic['value'] ?? null) ? $itemDiagnostic['value'] : null,
                        'linear' => ($item['linear'] ?? true) === true,
                        'linearRaw' => is_string($item['linearRaw'] ?? null) ? $item['linearRaw'] : null,
                    ];
                }
                $diagnostics[] = $diagnostic;
            }

            if (($item['manifestItemMissing'] ?? false) === true) {
                $diagnosticItem = [
                    'index' => $index,
                    'idref' => (string) ($item['idref'] ?? ''),
                ];
                $missingManifestItems[] = $diagnosticItem;
                $diagnostics[] = [
                    'type' => 'missing-spine-manifest-item',
                    'index' => $index,
                    'idref' => $diagnosticItem['idref'],
                    'message' => 'EPUB spine itemref references a manifest item id that is not present',
                ];
                continue;
            }

            if (($item['exists'] ?? true) !== true) {
                $diagnosticItem = [
                    'index' => $index,
                    'idref' => (string) ($item['idref'] ?? ''),
                    'partName' => (string) ($item['partName'] ?? ''),
                    'mediaType' => self::mediaTypeBase((string) ($item['mediaType'] ?? '')),
                ];
                $missingPackagePartItems[] = $diagnosticItem;
                $diagnostics[] = [
                    'type' => 'missing-spine-item-package-part',
                    'index' => $index,
                    'idref' => $diagnosticItem['idref'],
                    'partName' => $diagnosticItem['partName'],
                    'mediaType' => $diagnosticItem['mediaType'],
                    'message' => 'EPUB spine item points at a manifest package part that is not present in the ZIP',
                ];
            }

            $mediaType = self::mediaTypeBase((string) ($item['mediaType'] ?? ''));
            if (in_array($mediaType, [self::XHTML_MEDIA_TYPE, 'image/svg+xml'], true)) {
                continue;
            }

            $diagnosticItem = [
                'index' => $index,
                'idref' => (string) ($item['idref'] ?? ''),
                'partName' => (string) ($item['partName'] ?? ''),
                'mediaType' => $mediaType,
            ];
            $nonContentDocumentItems[] = $diagnosticItem;
            $diagnostics[] = [
                'type' => 'non-content-document-spine-item',
                'index' => $index,
                'idref' => $diagnosticItem['idref'],
                'partName' => $diagnosticItem['partName'],
                'mediaType' => $mediaType,
                'message' => 'EPUB spine item does not point to a compact preflight content-document media type',
            ];
        }

        return [
            'valid' => $diagnostics === [],
            'itemCount' => count($spine),
            'metadata' => $spineMetadata,
            'metadataValid' => ($spineMetadata['valid'] ?? true) === true,
            'linearCount' => $linearCount,
            'nonLinearCount' => $nonLinearCount,
            'invalidLinearCount' => count($invalidLinearItems),
            'missingManifestItemCount' => count($missingManifestItems),
            'missingPackagePartCount' => count($missingPackagePartItems),
            'nonContentDocumentCount' => count($nonContentDocumentItems),
            'pageSpreadCount' => count($pageSpreadItems),
            'pageSpreadLeftCount' => $pageSpreadCounts['left'],
            'pageSpreadRightCount' => $pageSpreadCounts['right'],
            'pageSpreadCenterCount' => $pageSpreadCounts['center'],
            'invalidLinearItems' => $invalidLinearItems,
            'pageSpreadItems' => $pageSpreadItems,
            'missingManifestItems' => $missingManifestItems,
            'missingPackagePartItems' => $missingPackagePartItems,
            'nonContentDocumentItems' => $nonContentDocumentItems,
            'itemDiagnosticCount' => count($itemDiagnostics),
            'itemDiagnostics' => $itemDiagnostics,
            'diagnosticCount' => count($diagnostics),
            'diagnostics' => $diagnostics,
        ];
    }

    /**
     * @param list<array<string, mixed>> $manifestItems
     * @param array<string, mixed>|null $navigation
     *
     * @return array<string, mixed>
     */
    private static function packageNcxValidationReport(?string $spineTocId, array $manifestItems, ?array $navigation): array
    {
        $manifestById = [];
        $ncxItems = [];
        foreach ($manifestItems as $item) {
            $id = (string) ($item['id'] ?? '');
            if ($id !== '') {
                $manifestById[$id] = $item;
            }

            if (self::mediaTypeBase((string) ($item['mediaType'] ?? '')) === self::NCX_MEDIA_TYPE) {
                $ncxItems[] = self::compactManifestBindingItem($item);
            }
        }

        $tocId = is_string($spineTocId) && trim($spineTocId) !== '' ? trim($spineTocId) : null;
        $tocItem = $tocId !== null && isset($manifestById[$tocId])
            ? self::compactManifestBindingItem($manifestById[$tocId])
            : null;
        $selectedPart = is_array($navigation) && ($navigation['type'] ?? null) === 'ncx'
            ? (string) ($navigation['partName'] ?? '')
            : null;
        $selectedItem = null;
        foreach ($ncxItems as $item) {
            if ($selectedPart !== null && $item['partName'] === $selectedPart) {
                $selectedItem = $item;
                break;
            }
        }

        $diagnostics = [];
        if ($tocId !== null && $tocItem === null) {
            $diagnostics[] = [
                'type' => 'missing-spine-toc-manifest-item',
                'tocId' => $tocId,
                'message' => 'EPUB spine toc attribute references a manifest item id that is not present',
            ];
        } elseif ($tocItem !== null && $tocItem['mediaType'] !== self::NCX_MEDIA_TYPE) {
            $diagnostics[] = [
                'type' => 'spine-toc-non-ncx-manifest-item',
                'tocId' => $tocId,
                'partName' => $tocItem['partName'],
                'mediaType' => $tocItem['mediaType'],
                'message' => 'EPUB spine toc attribute should reference an NCX manifest item',
            ];
        }

        $selectedBy = null;
        if ($selectedItem !== null) {
            $selectedBy = $tocItem !== null && $tocItem['id'] === $selectedItem['id'] && $tocItem['mediaType'] === self::NCX_MEDIA_TYPE
                ? 'spine-toc'
                : 'manifest-scan';
        }

        return [
            'valid' => $diagnostics === [],
            'tocSpecified' => $tocId !== null,
            'tocId' => $tocId,
            'tocItem' => $tocItem,
            'manifestNcxItemCount' => count($ncxItems),
            'manifestNcxItems' => $ncxItems,
            'selectedBy' => $selectedBy,
            'selectedItem' => $selectedItem,
            'diagnosticCount' => count($diagnostics),
            'diagnostics' => $diagnostics,
        ];
    }

    /**
     * @param array<string, mixed> $item
     *
     * @return array{id:string, href:string, partName:string, mediaType:string}
     */
    private static function compactManifestBindingItem(array $item): array
    {
        return [
            'id' => (string) ($item['id'] ?? ''),
            'href' => (string) ($item['href'] ?? ''),
            'partName' => (string) ($item['partName'] ?? ''),
            'mediaType' => self::mediaTypeBase((string) ($item['mediaType'] ?? '')),
        ];
    }

    /**
     * @param array<string, mixed>|null $navigation
     * @param list<array<string, mixed>> $navigationSections
     *
     * @return array<string, mixed>
     */
    private static function packageNavigationValidationReport(ZipPackage $package, ?array $navigation, array $navigationSections): array
    {
        $source = is_array($navigation) && is_string($navigation['type'] ?? null) ? $navigation['type'] : null;
        $sections = $navigationSections;
        if ($sections === [] && is_array($navigation) && is_array($navigation['entries'] ?? null)) {
            $sections = [[
                'type' => $source,
                'types' => $source === null ? [] : [$source],
                'entries' => $navigation['entries'],
            ]];
        }

        $entryCount = 0;
        $localTargetCount = 0;
        $missingTargetCount = 0;
        $externalTargetCount = 0;
        $diagnostics = [];
        $documentPart = null;

        foreach ($sections as $section) {
            if (is_string($section['partName'] ?? null) && $section['partName'] !== '') {
                $documentPart = $section['partName'];
                break;
            }
        }
        if ($documentPart === null && is_array($navigation) && is_string($navigation['partName'] ?? null)) {
            $documentPart = $navigation['partName'];
        }

        $documentSections = $source === 'nav' ? $sections : [];
        $documentDiagnostics = self::navDocumentDiagnosticReport(
            $documentSections,
            $documentPart,
            $source === 'nav'
        );
        array_push($diagnostics, ...$documentDiagnostics['diagnostics']);

        foreach ($sections as $sectionIndex => $section) {
            $entries = is_array($section['entries'] ?? null) ? array_values($section['entries']) : [];
            foreach ($entries as $entryIndex => $entry) {
                ++$entryCount;
                $target = is_string($entry['target'] ?? null) ? $entry['target'] : null;
                if ($target === null || $target === '') {
                    continue;
                }

                if (self::isAbsoluteUri($target)) {
                    ++$externalTargetCount;
                    $diagnostics[] = [
                        'type' => 'external-navigation-target',
                        'sectionIndex' => $sectionIndex,
                        'entryIndex' => $entryIndex,
                        'label' => is_string($entry['label'] ?? null) ? $entry['label'] : '',
                        'target' => $target,
                        'message' => 'EPUB navigation target points outside the package and was not fetched',
                    ];
                    continue;
                }

                ++$localTargetCount;
                $partName = OpcPackagePath::stripQueryAndFragment($target);
                if (!$package->has($partName)) {
                    ++$missingTargetCount;
                    $diagnostics[] = [
                        'type' => 'missing-navigation-target',
                        'sectionIndex' => $sectionIndex,
                        'entryIndex' => $entryIndex,
                        'label' => is_string($entry['label'] ?? null) ? $entry['label'] : '',
                        'target' => $target,
                        'partName' => $partName,
                        'message' => 'EPUB navigation target is not present in the package',
                    ];
                }
            }
        }

        return [
            'valid' => $diagnostics === [],
            'source' => $source,
            'sectionCount' => count($sections),
            'entryCount' => $entryCount,
            'localTargetCount' => $localTargetCount,
            'missingTargetCount' => $missingTargetCount,
            'externalTargetCount' => $externalTargetCount,
            'documentDiagnostics' => $documentDiagnostics,
            'documentDiagnosticCount' => $documentDiagnostics['diagnosticCount'],
            'diagnosticCount' => count($diagnostics),
            'diagnostics' => $diagnostics,
        ];
    }

    /**
     * @param list<array<string, mixed>> $sections
     *
     * @return array<string, mixed>
     */
    private static function navDocumentDiagnosticReport(array $sections, ?string $part, bool $documentPresent): array
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
        $missingEntryLabelCount = 0;
        $missingPrimaryItemLabelCount = 0;
        $missingOrderedListSectionCount = 0;
        $untypedSectionCount = 0;
        $missingItemLabelCount = 0;
        $emptyItemLabelCount = 0;
        $missingItemHrefCount = 0;
        $itemDiagnosticCount = 0;

        if ($documentPresent && $sections === []) {
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
            $sectionTitle = is_string($section['title'] ?? null)
                ? $section['title']
                : (is_string($section['label'] ?? null) ? $section['label'] : '');
            $itemCount = is_int($section['itemCount'] ?? null)
                ? $section['itemCount']
                : count(is_array($section['entries'] ?? null) ? $section['entries'] : []);
            $entries = is_array($section['entries'] ?? null) ? array_values($section['entries']) : [];

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
                foreach ($entries as $entryIndex => $entry) {
                    if (!is_array($entry)) {
                        continue;
                    }

                    $label = is_string($entry['label'] ?? null) ? trim($entry['label']) : '';
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
                        'entryIndex' => $entryIndex,
                        'label' => $label,
                        'href' => is_string($entry['href'] ?? null) ? $entry['href'] : null,
                        'target' => is_string($entry['target'] ?? null) ? $entry['target'] : null,
                        'depth' => is_int($entry['depth'] ?? null) ? $entry['depth'] : null,
                        'message' => 'EPUB primary navigation item has no text label for review handoff',
                    ];
                }
            } else {
                foreach ($entries as $entryIndex => $entry) {
                    if (!is_array($entry)) {
                        continue;
                    }

                    $label = is_string($entry['label'] ?? null) ? trim($entry['label']) : '';
                    if ($label !== '') {
                        continue;
                    }

                    $target = is_string($entry['target'] ?? null) ? $entry['target'] : null;
                    $href = is_string($entry['href'] ?? null) ? $entry['href'] : null;
                    if (($target === null || $target === '') && ($href === null || $href === '')) {
                        continue;
                    }

                    ++$missingEntryLabelCount;
                    $diagnostics[] = [
                        'type' => 'missing-nav-entry-label',
                        'part' => $part,
                        'sectionIndex' => $sectionIndex,
                        'entryIndex' => $entryIndex,
                        'sectionId' => $sectionId,
                        'sectionTypes' => $sectionTypes,
                        'href' => $href,
                        'target' => $target,
                        'depth' => is_int($entry['depth'] ?? null) ? $entry['depth'] : null,
                        'message' => 'EPUB navigation entry resolves a target without a reviewable text label',
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

            $missingItemLabelCount += is_int($section['missingItemLabelCount'] ?? null) ? $section['missingItemLabelCount'] : 0;
            $emptyItemLabelCount += is_int($section['emptyItemLabelCount'] ?? null) ? $section['emptyItemLabelCount'] : 0;
            $missingItemHrefCount += is_int($section['missingItemHrefCount'] ?? null) ? $section['missingItemHrefCount'] : 0;

            foreach (is_array($section['itemDiagnostics'] ?? null) ? $section['itemDiagnostics'] : [] as $itemDiagnostic) {
                if (!is_array($itemDiagnostic)) {
                    continue;
                }

                ++$itemDiagnosticCount;
                $diagnostics[] = [
                    'part' => $part,
                    'sectionIndex' => $sectionIndex,
                    'sectionId' => $sectionId,
                    'sectionTypes' => $sectionTypes,
                ] + $itemDiagnostic;
            }
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

        if ($documentPresent && $sections !== [] && $typeSections['toc'] === []) {
            $diagnostics[] = [
                'type' => 'missing-nav-toc-section',
                'part' => $part,
                'message' => 'EPUB navigation document is missing a toc nav section',
            ];
        }

        return [
            'present' => $documentPresent && $sections !== [],
            'part' => $part,
            'sectionCount' => count($sections),
            'primarySectionCount' => count($typeSections['toc']) + count($typeSections['landmarks']) + count($typeSections['page-list']),
            'tocSectionCount' => count($typeSections['toc']),
            'landmarksSectionCount' => count($typeSections['landmarks']),
            'pageListSectionCount' => count($typeSections['page-list']),
            'duplicatePrimaryTypeCount' => $duplicatePrimaryTypeCount,
            'emptySectionCount' => $emptySectionCount,
            'hiddenPrimarySectionCount' => $hiddenPrimarySectionCount,
            'missingHeadingSectionCount' => $missingHeadingSectionCount,
            'missingEntryLabelCount' => $missingEntryLabelCount,
            'missingPrimaryItemLabelCount' => $missingPrimaryItemLabelCount,
            'missingOrderedListSectionCount' => $missingOrderedListSectionCount,
            'untypedSectionCount' => $untypedSectionCount,
            'missingItemLabelCount' => $missingItemLabelCount,
            'emptyItemLabelCount' => $emptyItemLabelCount,
            'missingItemHrefCount' => $missingItemHrefCount,
            'itemDiagnosticCount' => $itemDiagnosticCount,
            'diagnosticCount' => count($diagnostics),
            'diagnostics' => $diagnostics,
        ];
    }

    private static function assertEpubMimetype(ZipPackage $package): void
    {
        if (!$package->has('mimetype')) {
            throw new \RuntimeException('EPUB package is missing the required mimetype entry');
        }

        $names = $package->names();
        if (($names[0] ?? null) !== 'mimetype') {
            throw new \RuntimeException('EPUB mimetype entry must be the first ZIP package entry');
        }

        $entry = $package->entry('mimetype');
        if ($entry->compressionMethod !== 0) {
            throw new \RuntimeException('EPUB mimetype entry must be stored without compression');
        }

        if ($package->read('mimetype') !== self::EPUB_MIMETYPE) {
            throw new \RuntimeException('EPUB mimetype entry must contain application/epub+zip');
        }
    }

    /**
     * @return list<array{fullPath:string, partName:string, mediaType:string}>
     */
    private static function parseContainerXml(string $xml): array
    {
        $dom = self::loadXml($xml, 'EPUB container.xml');
        $root = $dom->documentElement;
        if (!$root instanceof \DOMElement || $root->localName !== 'container' || $root->namespaceURI !== self::OCF_CONTAINER_NAMESPACE) {
            throw new \InvalidArgumentException('EPUB container.xml must use the OCF container namespace');
        }

        $rootfiles = [];
        foreach ($dom->getElementsByTagNameNS(self::OCF_CONTAINER_NAMESPACE, 'rootfile') as $rootfile) {
            if (!$rootfile instanceof \DOMElement) {
                continue;
            }

            $fullPath = $rootfile->getAttribute('full-path');
            $mediaType = $rootfile->getAttribute('media-type');
            if ($fullPath === '' || $mediaType === '') {
                throw new \InvalidArgumentException('EPUB rootfile full-path and media-type must be non-empty');
            }

            $rootfiles[] = [
                'fullPath' => $fullPath,
                'partName' => OpcPackagePath::canonicalPartName($fullPath),
                'mediaType' => $mediaType,
            ];
        }

        if ($rootfiles === []) {
            throw new \RuntimeException('EPUB container.xml does not declare any rootfiles');
        }

        return $rootfiles;
    }

    /**
     * @param array<string, array{id:string, href:string, partName:string, mediaType:string, properties:list<string>, fallback:?string, mediaOverlay:?string}> $manifestByPart
     *
     * @return list<array<string, mixed>>
     */
    private static function parseContainerLinks(ZipPackage $package, array $manifestByPart): array
    {
        $metadataPart = '/META-INF/metadata.xml';
        if (!$package->has($metadataPart)) {
            return [];
        }

        $dom = self::loadXml($package->read($metadataPart), 'EPUB OCF metadata.xml');
        $root = $dom->documentElement;
        if (!$root instanceof \DOMElement || $root->localName !== 'metadata' || $root->namespaceURI !== self::EPUB_METADATA_NAMESPACE) {
            throw new \InvalidArgumentException('EPUB metadata.xml must use the EPUB metadata namespace');
        }

        $links = [];
        foreach (self::childElements($root, 'link', self::EPUB_METADATA_NAMESPACE) as $index => $linkElement) {
            $links[] = self::parseContainerLink($linkElement, $index, $package, $manifestByPart);
        }

        return $links;
    }

    /**
     * @param array<string, array{id:string, href:string, partName:string, mediaType:string, properties:list<string>, fallback:?string, mediaOverlay:?string}> $manifestByPart
     *
     * @return array<string, mixed>
     */
    private static function parseContainerLink(
        \DOMElement $linkElement,
        int $index,
        ZipPackage $package,
        array $manifestByPart
    ): array {
        $href = self::emptyToNull($linkElement->getAttribute('href'));
        $rel = self::splitTokens($linkElement->getAttribute('rel'));
        $target = null;
        $partName = null;
        $external = false;
        $exists = false;
        $entry = null;
        $manifestItem = null;
        $hrefSuffix = [
            'hasQuery' => false,
            'query' => null,
            'hasFragment' => false,
            'fragment' => null,
        ];
        $diagnostics = [];

        if ($rel === []) {
            $diagnostics[] = [
                'type' => 'missing-container-link-rel',
                'message' => 'EPUB OCF container link is missing rel tokens for package preflight classification',
            ];
        }

        if ($href === null) {
            $diagnostics[] = [
                'type' => 'missing-container-link-href',
                'message' => 'EPUB OCF container link is missing href',
            ];
        } else {
            try {
                $target = self::resolvePackageHref('/', $href);
                $hrefSuffix = self::packageHrefSuffixReport($target);
                $external = self::isAbsoluteUri($target);
                if ($external) {
                    $diagnostics[] = [
                        'type' => 'external-container-link-target',
                        'href' => $href,
                        'message' => 'EPUB OCF container link points outside the package and was not fetched',
                    ];
                } else {
                    $partName = OpcPackagePath::stripQueryAndFragment($target);
                    $exists = $package->has($partName);
                    $entry = $exists ? $package->entry($partName) : null;
                    $manifestItem = $manifestByPart[$partName] ?? null;
                    if (!$exists) {
                        $diagnostics[] = [
                            'type' => 'missing-container-link-target',
                            'href' => $href,
                            'partName' => $partName,
                            'message' => 'EPUB OCF container link target is missing from the package',
                        ];
                    }
                }
            } catch (\InvalidArgumentException $exception) {
                $diagnostics[] = [
                    'type' => 'invalid-container-link-href',
                    'href' => $href,
                    'message' => $exception->getMessage(),
                ];
            }
        }

        return [
            'index' => $index,
            'id' => self::emptyToNull($linkElement->getAttribute('id')),
            'rel' => $rel,
            'href' => $href,
            'target' => $target,
            'partName' => $partName,
            'external' => $external,
            'exists' => $exists,
            'mediaType' => self::emptyToNull($linkElement->getAttribute('media-type')),
            'manifestId' => is_array($manifestItem) ? $manifestItem['id'] : null,
            'manifestMediaType' => is_array($manifestItem) ? $manifestItem['mediaType'] : null,
            'properties' => self::splitTokens($linkElement->getAttribute('properties')),
            'title' => self::emptyToNull($linkElement->getAttribute('title')),
            'hreflang' => self::emptyToNull($linkElement->getAttribute('hreflang')),
            'language' => self::metadataElementLanguage($linkElement),
            'direction' => self::metadataElementDirection($linkElement),
            'hrefHasQuery' => $hrefSuffix['hasQuery'],
            'hrefQuery' => $hrefSuffix['query'],
            'hrefHasFragment' => $hrefSuffix['hasFragment'],
            'hrefFragment' => $hrefSuffix['fragment'],
            'byteLength' => $entry instanceof ZipPackageEntry ? $entry->uncompressedSize : null,
            'crc32' => $entry instanceof ZipPackageEntry ? $entry->crc32Hex() : null,
            'diagnostics' => $diagnostics,
        ];
    }

    /**
     * @return array{
     *     metadata:array<string, mixed>,
     *     packageLinks:list<array<string, mixed>>,
     *     manifestById:array<string, array{id:string, href:string, partName:string, mediaType:string, properties:list<string>, fallback:?string, fallbackStyle:?string, mediaOverlay:?string}>,
     *     manifestItems:list<array{id:string, href:string, partName:string, mediaType:string, properties:list<string>, fallback:?string, fallbackStyle:?string, mediaOverlay:?string}>,
     *     spine:list<array<string, mixed>>,
     *     spineMetadata:array<string, mixed>,
     *     guideReferences:list<array{type:?string, title:?string, href:?string, target:?string, partName:?string, external:bool, exists:bool, hrefHasQuery:bool, hrefQuery:?string, hrefHasFragment:bool, hrefFragment:?string}>,
     *     collections:list<array<string, mixed>>,
     *     bindings:array<string, mixed>,
     *     manifestFallbacks:array<string, mixed>,
     *     encryption:array<string, mixed>,
     *     spineTocId:?string
     * }
     */
    private static function parseOpfXml(string $xml, string $opfPartName, ZipPackage $package): array
    {
        $dom = self::loadXml($xml, 'EPUB OPF package document');
        $root = $dom->documentElement;
        if (!$root instanceof \DOMElement || $root->localName !== 'package' || $root->namespaceURI !== self::OPF_NAMESPACE) {
            throw new \InvalidArgumentException('EPUB OPF package document must use the OPF namespace');
        }

        $metadataElement = self::firstChildElement($root, 'metadata', self::OPF_NAMESPACE);
        $manifestElement = self::firstChildElement($root, 'manifest', self::OPF_NAMESPACE);
        $spineElement = self::firstChildElement($root, 'spine', self::OPF_NAMESPACE);

        if (!$metadataElement instanceof \DOMElement || !$manifestElement instanceof \DOMElement || !$spineElement instanceof \DOMElement) {
            throw new \RuntimeException('EPUB OPF package document must include metadata, manifest, and spine');
        }

        $metadata = self::parseMetadata($metadataElement, $root);
        [$manifestById, $manifestItems] = self::parseManifest($manifestElement, $opfPartName, $package);
        $encryption = self::parseEncryption($package, $manifestById);
        $manifestById = self::attachEncryptionToManifest($manifestById, $encryption);
        $manifestItems = self::attachEncryptionToManifestItems($manifestItems, $encryption);
        $manifestByPart = self::manifestByPart($manifestById);
        $packageLinks = self::parsePackageLinks(
            $metadataElement,
            $opfPartName,
            $package,
            $manifestByPart,
            is_array($metadata['prefixBindings'] ?? null) ? $metadata['prefixBindings'] : [],
        );
        $packageLinkReport = self::collectionLinkReport($packageLinks);
        $metadata['links'] = $packageLinks;
        $metadata['linksByRel'] = $packageLinkReport['linksByRel'];
        $metadata['linkRelCounts'] = $packageLinkReport['relCounts'];
        $metadata['linkDiagnostics'] = $packageLinkReport['diagnostics'];
        $metadata['linkVocabulary'] = self::metadataLinkVocabularySummary($packageLinks);
        $metadata['accessibility'] = self::accessibilityMetadataReport($metadata, $packageLinks);
        $refinementsById = is_array($metadata['refinementsById'] ?? null) ? $metadata['refinementsById'] : [];
        $spineMetadata = self::parseSpineMetadata($spineElement);
        $spine = self::parseSpine($spineElement, $manifestById, $refinementsById);
        $guideReferences = self::parseGuide(
            self::firstChildElement($root, 'guide', self::OPF_NAMESPACE),
            $opfPartName,
            $package,
            $manifestByPart,
        );
        $collections = self::parseCollections($root, $opfPartName, $package, $manifestById);
        $bindings = self::parseBindings(self::firstChildElement($root, 'bindings', self::OPF_NAMESPACE), $manifestById, $package);
        $mediaOverlays = self::parseMediaOverlays($manifestById, $metadata, $package);
        $manifestFallbacks = self::manifestFallbackPreflight($manifestById, $package);

        return [
            'metadata' => $metadata,
            'packageLinks' => $packageLinks,
            'manifestById' => $manifestById,
            'manifestItems' => $manifestItems,
            'spine' => $spine,
            'spineMetadata' => $spineMetadata,
            'guideReferences' => $guideReferences,
            'collections' => $collections,
            'bindings' => $bindings,
            'mediaOverlays' => $mediaOverlays,
            'manifestFallbacks' => $manifestFallbacks,
            'encryption' => $encryption,
            'spineTocId' => $spineElement->hasAttribute('toc') ? $spineElement->getAttribute('toc') : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function parseMetadata(\DOMElement $metadataElement, \DOMElement $packageElement): array
    {
        $titles = [];
        $creators = [];
        $languages = [];
        $identifiers = [];
        $dc = [];
        $meta = [];
        $metaProperties = [];
        $propertyValues = [];
        $refinementsById = [];
        $coverImageId = null;
        $prefixReport = self::packagePrefixReport($packageElement->hasAttribute('prefix') ? $packageElement->getAttribute('prefix') : '');
        $prefixBindings = self::metadataVocabularyPrefixBindings($prefixReport['bindingsByPrefix']);
        $packageId = $packageElement->hasAttribute('id') ? self::emptyToNull($packageElement->getAttribute('id')) : null;
        $packageVersion = $packageElement->hasAttribute('version') ? $packageElement->getAttribute('version') : '';
        $packageLanguage = self::metadataElementLanguage($packageElement);
        $packageDirection = self::metadataElementDirection($packageElement);

        foreach (self::childElements($metadataElement) as $child) {
            if ($child->namespaceURI === self::DC_NAMESPACE) {
                $value = self::normalizeText($child->textContent);
                if ($value === '') {
                    continue;
                }

                $entry = [
                    'name' => $child->localName,
                    'text' => $value,
                    'id' => self::emptyToNull($child->getAttribute('id')),
                    'scheme' => self::metadataElementScheme($child),
                    'event' => self::emptyToNull($child->getAttribute('event')),
                    'language' => self::metadataElementLanguage($child),
                    'direction' => self::metadataElementDirection($child),
                    'refinements' => [],
                ];
                $dc[$child->localName][] = $entry;

                if ($child->localName === 'title') {
                    $titles[] = $value;
                    continue;
                }

                if ($child->localName === 'creator') {
                    $creators[] = $value;
                    continue;
                }

                if ($child->localName === 'language') {
                    $languages[] = $value;
                    continue;
                }

                if ($child->localName === 'identifier') {
                    $identifiers[] = [
                        'id' => $entry['id'],
                        'value' => $value,
                        'scheme' => $entry['scheme'],
                    ];
                }

                continue;
            }

            if ($child->namespaceURI !== self::OPF_NAMESPACE || $child->localName !== 'meta') {
                continue;
            }

            $property = $child->hasAttribute('property') ? $child->getAttribute('property') : null;
            $name = $child->hasAttribute('name') ? $child->getAttribute('name') : null;
            $content = $child->hasAttribute('content') ? $child->getAttribute('content') : self::normalizeText($child->textContent);
            $refines = $child->hasAttribute('refines') ? $child->getAttribute('refines') : null;
            $subjectId = self::metadataRefinementSubject($refines);
            $entry = [
                'property' => $property,
                'name' => $name,
                'content' => $content,
                'text' => self::normalizeText($child->textContent),
                'id' => self::emptyToNull($child->getAttribute('id')),
                'scheme' => self::emptyToNull($child->getAttribute('scheme')),
                'refines' => $refines,
                'subjectId' => $subjectId,
                'language' => self::metadataElementLanguage($child),
                'direction' => self::metadataElementDirection($child),
            ];
            $meta[] = $entry;

            if ($property !== null && $property !== '') {
                $propertyValues[$property][] = $content;
                $metaProperties[$property][] = $entry;
                if ($subjectId !== null) {
                    $refinementsById[$subjectId][$property][] = $entry;
                }
            }

            if ($name === 'cover' && $content !== '') {
                $coverImageId = $content;
            }
        }

        $dc = self::attachMetadataRefinements($dc, $refinementsById);
        $uniqueIdentifierId = $packageElement->hasAttribute('unique-identifier')
            ? $packageElement->getAttribute('unique-identifier')
            : null;
        $requiresUniqueIdentifier = $packageElement->localName === 'package' && $packageElement->namespaceURI === self::OPF_NAMESPACE;
        $titleDetails = self::metadataTitleDetails($dc['title'] ?? []);
        $mainTitle = self::firstMetadataTitleByType($titleDetails, 'main') ?? ($titleDetails[0] ?? null);
        $creatorDetails = self::metadataAgentDetails($dc['creator'] ?? [], 'creator');
        $contributorDetails = self::metadataAgentDetails($dc['contributor'] ?? [], 'contributor');
        $identifierDetails = self::metadataIdentifierDetails($dc['identifier'] ?? [], $uniqueIdentifierId);
        $uniqueIdentifier = self::metadataUniqueIdentifierReport($uniqueIdentifierId, $identifierDetails, $requiresUniqueIdentifier);
        $identifierSummary = self::metadataIdentifierSummary($identifierDetails, $uniqueIdentifier);
        $identifierDiagnostics = array_merge($uniqueIdentifier['diagnostics'], $identifierSummary['diagnostics']);
        $languageDetails = self::metadataLanguageDetails($dc['language'] ?? []);
        $dateDetails = self::metadataDateDetails($dc['date'] ?? []);
        $sourceDetails = self::metadataSourceDetails($dc['source'] ?? []);
        $bibliographicDetails = self::metadataBibliographicDetails($dc);
        $renditionLayout = self::metadataRenditionLayoutReport($metaProperties);
        $identifier = is_string($uniqueIdentifier['value'] ?? null) ? $uniqueIdentifier['value'] : '';
        $packageRefinements = $packageId !== null && isset($refinementsById[$packageId]) && is_array($refinementsById[$packageId])
            ? $refinementsById[$packageId]
            : [];

        return [
            'package' => [
                'id' => $packageId,
                'version' => $packageVersion,
                'uniqueIdentifierId' => $uniqueIdentifierId,
                'language' => $packageLanguage,
                'direction' => $packageDirection,
                'prefix' => $prefixReport['raw'],
                'prefixDeclarations' => $prefixReport['bindings'],
                'prefixBindings' => $prefixBindings,
                'refinements' => $packageRefinements,
            ],
            'packageId' => $packageId,
            'packageLanguage' => $packageLanguage,
            'packageDirection' => $packageDirection,
            'version' => $packageVersion,
            'uniqueIdentifierId' => $uniqueIdentifierId,
            'uniqueIdentifier' => $uniqueIdentifier,
            'identifier' => $identifier,
            'identifiers' => $identifiers,
            'identifierDetails' => $identifierDetails,
            'identifierSummary' => $identifierSummary,
            'identifierDiagnostics' => $identifierDiagnostics,
            'identifiersByType' => self::metadataDetailsByField($identifierDetails, 'identifierType'),
            'identifiersByScheme' => self::metadataDetailsByField($identifierDetails, 'scheme'),
            'title' => $titles[0] ?? '',
            'titles' => $titles,
            'titleDetails' => $titleDetails,
            'titlesByType' => self::metadataTitlesByType($titleDetails),
            'mainTitle' => $mainTitle,
            'subtitle' => self::firstMetadataTitleByType($titleDetails, 'subtitle'),
            'shortTitle' => self::firstMetadataTitleByType($titleDetails, 'short'),
            'sortTitle' => is_array($mainTitle) ? $mainTitle['fileAs'] : null,
            'creators' => $creators,
            'creatorDetails' => $creatorDetails,
            'creatorsByRole' => self::metadataAgentsByRole($creatorDetails),
            'contributors' => array_map(static fn (array $entry): string => $entry['text'], $dc['contributor'] ?? []),
            'contributorDetails' => $contributorDetails,
            'contributorsByRole' => self::metadataAgentsByRole($contributorDetails),
            'language' => $languages[0] ?? '',
            'languages' => $languages,
            'languageDetails' => $languageDetails,
            'languagesByPrimarySubtag' => self::metadataLanguageDetailsByPrimarySubtag($languageDetails),
            'languageSummary' => self::metadataLanguageSummary($languageDetails),
            'date' => $dateDetails[0]['text'] ?? null,
            'dates' => array_map(static fn (array $entry): string => (string) $entry['text'], $dc['date'] ?? []),
            'dateDetails' => $dateDetails,
            'datesByEvent' => self::metadataDetailsByField($dateDetails, 'event'),
            'dateSummary' => self::metadataDateSummary($dateDetails),
            'source' => $sourceDetails[0]['text'] ?? null,
            'sources' => array_map(static fn (array $entry): string => (string) $entry['text'], $dc['source'] ?? []),
            'sourceDetails' => $sourceDetails,
            'sourcesByType' => self::metadataSourcesByType($sourceDetails),
            'sourceSummary' => self::metadataSourceSummary($sourceDetails),
            'subjects' => array_map(static fn (array $entry): string => (string) $entry['text'], $dc['subject'] ?? []),
            'description' => $dc['description'][0]['text'] ?? null,
            'publisher' => $dc['publisher'][0]['text'] ?? null,
            'bibliographicDetails' => $bibliographicDetails,
            'bibliographicDetailsByKind' => self::metadataBibliographicDetailsByKind($bibliographicDetails),
            'bibliographicSummary' => self::metadataBibliographicSummary($bibliographicDetails),
            'renditionLayout' => $renditionLayout,
            'modified' => $propertyValues['dcterms:modified'][0] ?? null,
            'properties' => $propertyValues,
            'dc' => $dc,
            'metaProperties' => $metaProperties,
            'meta' => $meta,
            'refinementsById' => $refinementsById,
            'coverImageId' => $coverImageId,
            'prefix' => $prefixReport['raw'],
            'prefixDeclarations' => $prefixReport['bindings'],
            'prefixBindings' => $prefixBindings,
            'prefixDiagnostics' => $prefixReport['diagnostics'],
        ];
    }

    /**
     * @return array{raw:string, bindings:list<array{index:int, prefix:string, iri:string}>, bindingsByPrefix:array<string, string>, diagnostics:list<array<string, mixed>>}
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
            if (preg_match('/^([A-Za-z_][A-Za-z0-9._-]*):[ \t\r\n]+([^ \t\r\n]+)/', $segment, $match) !== 1) {
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
     * @param array<string, string> $prefixBindings
     *
     * @return array<string, string>
     */
    private static function metadataVocabularyPrefixBindings(array $prefixBindings): array
    {
        return array_replace(self::RESERVED_PACKAGE_PREFIXES, $prefixBindings);
    }

    /**
     * @param array<string, mixed> $metadata
     * @param list<array<string, mixed>> $links
     *
     * @return array<string, mixed>
     */
    private static function accessibilityMetadataReport(array $metadata, array $links): array
    {
        $entries = [];
        $entriesByProperty = [];
        foreach (($metadata['meta'] ?? []) as $metaEntry) {
            if (!is_array($metaEntry)) {
                continue;
            }

            $rawProperty = is_string($metaEntry['property'] ?? null) ? $metaEntry['property'] : null;
            $rawName = is_string($metaEntry['name'] ?? null) ? $metaEntry['name'] : null;
            $property = self::canonicalAccessibilityProperty($rawProperty);
            $source = 'property';
            if ($property === null) {
                $property = self::canonicalAccessibilityProperty($rawName);
                $source = 'name';
            }
            if ($property === null) {
                continue;
            }

            $text = self::metadataEntryValue($metaEntry);
            if ($text === '') {
                continue;
            }

            $entry = [
                'property' => $property,
                'source' => $source,
                'rawProperty' => $rawProperty,
                'rawName' => $rawName,
                'text' => $text,
                'content' => is_string($metaEntry['content'] ?? null) ? $metaEntry['content'] : null,
                'id' => is_string($metaEntry['id'] ?? null) ? $metaEntry['id'] : null,
                'refines' => is_string($metaEntry['refines'] ?? null) ? $metaEntry['refines'] : null,
                'subjectId' => is_string($metaEntry['subjectId'] ?? null) ? $metaEntry['subjectId'] : null,
                'scheme' => is_string($metaEntry['scheme'] ?? null) ? $metaEntry['scheme'] : null,
                'language' => is_string($metaEntry['language'] ?? null) ? $metaEntry['language'] : null,
                'direction' => is_string($metaEntry['direction'] ?? null) ? $metaEntry['direction'] : null,
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
                'direction' => $entry['direction'],
            ];
        }

        $linkedRecords = self::accessibilityLinkedRecords($links);

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
                'partName' => is_string($link['partName'] ?? null) ? $link['partName'] : null,
                'external' => (bool) ($link['external'] ?? false),
                'exists' => (bool) ($link['exists'] ?? false),
                'byteLength' => is_int($link['byteLength'] ?? null) ? $link['byteLength'] : null,
                'compressedByteLength' => is_int($link['compressedByteLength'] ?? null) ? $link['compressedByteLength'] : null,
                'compressionMethod' => is_int($link['compressionMethod'] ?? null) ? $link['compressionMethod'] : null,
                'compressionMethodName' => is_string($link['compressionMethodName'] ?? null) ? $link['compressionMethodName'] : null,
                'compressionSupported' => is_bool($link['compressionSupported'] ?? null) ? $link['compressionSupported'] : null,
                'crc32' => is_string($link['crc32'] ?? null) ? $link['crc32'] : null,
                'canExposeBytes' => (bool) ($link['canExposeBytes'] ?? false),
                'mediaType' => is_string($link['mediaType'] ?? null) ? $link['mediaType'] : null,
                'manifestId' => is_string($link['manifestId'] ?? null) ? $link['manifestId'] : null,
                'manifestMediaType' => is_string($link['manifestMediaType'] ?? null) ? $link['manifestMediaType'] : null,
                'properties' => $properties,
                'diagnostics' => is_array($link['diagnostics'] ?? null) ? array_values($link['diagnostics']) : [],
            ];
        }

        return $records;
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
            foreach ($entries as $index => $entry) {
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
            $refinements = is_array($entry['refinements'] ?? null) ? $entry['refinements'] : [];
            $details[] = [
                'kind' => 'title',
                'index' => $index,
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
            $type = $detail['titleType'] ?? null;
            if (is_string($type) && $type !== '') {
                $byType[$type][] = $detail;
            }
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
            $refinements = is_array($entry['refinements'] ?? null) ? $entry['refinements'] : [];
            $roles = self::metadataRefinementEntries($refinements, 'role');
            $roleValues = array_map(static fn (array $role): string => (string) $role['value'], $roles);
            $details[] = [
                'kind' => $kind,
                'index' => $index,
                'text' => (string) ($entry['text'] ?? ''),
                'id' => is_string($entry['id'] ?? null) ? $entry['id'] : null,
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
     * @param list<array<string, mixed>> $entries
     *
     * @return list<array<string, mixed>>
     */
    private static function metadataIdentifierDetails(array $entries, ?string $uniqueIdentifierId = null): array
    {
        $uniqueIdentifierId = is_string($uniqueIdentifierId) ? trim($uniqueIdentifierId) : '';
        $values = [];
        foreach ($entries as $index => $entry) {
            $text = (string) ($entry['text'] ?? '');
            if ($text === '') {
                continue;
            }

            $values[$text][] = [
                'index' => (int) $index,
                'id' => is_string($entry['id'] ?? null) ? $entry['id'] : null,
            ];
        }

        $details = [];
        foreach ($entries as $index => $entry) {
            $refinements = is_array($entry['refinements'] ?? null) ? $entry['refinements'] : [];
            $identifierTypes = self::metadataRefinementEntries($refinements, 'identifier-type');
            $value = (string) ($entry['text'] ?? '');
            $duplicateEntries = $value !== '' && count($values[$value] ?? []) > 1 ? $values[$value] : [];
            $details[] = [
                'kind' => 'identifier',
                'index' => $index,
                'value' => $value,
                'id' => is_string($entry['id'] ?? null) ? $entry['id'] : null,
                'scheme' => is_string($entry['scheme'] ?? null) ? $entry['scheme'] : null,
                'identifierType' => $identifierTypes[0]['value'] ?? null,
                'identifierTypes' => $identifierTypes,
                'selectedByUniqueIdentifier' => $uniqueIdentifierId !== ''
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
                'refinements' => $refinements,
            ];
        }

        return $details;
    }

    /**
     * @param list<array<string, mixed>> $identifierDetails
     *
     * @return array<string, mixed>
     */
    private static function metadataUniqueIdentifierReport(?string $uniqueIdentifierId, array $identifierDetails, bool $required): array
    {
        $id = is_string($uniqueIdentifierId) ? trim($uniqueIdentifierId) : '';
        $specified = $id !== '';
        $entries = [];
        foreach ($identifierDetails as $index => $detail) {
            $entries[] = [
                'index' => (int) ($detail['index'] ?? $index),
                'id' => is_string($detail['id'] ?? null) ? $detail['id'] : null,
                'value' => (string) ($detail['value'] ?? ''),
                'scheme' => is_string($detail['scheme'] ?? null) ? $detail['scheme'] : null,
                'identifierType' => is_string($detail['identifierType'] ?? null) ? $detail['identifierType'] : null,
                'duplicateValue' => (bool) ($detail['duplicateValue'] ?? false),
                'duplicateIds' => is_array($detail['duplicateIds'] ?? null) ? array_values($detail['duplicateIds']) : [],
                'duplicateIndexes' => is_array($detail['duplicateIndexes'] ?? null) ? array_values($detail['duplicateIndexes']) : [],
            ];
        }

        $matchedEntries = [];
        if ($specified) {
            foreach ($entries as $entry) {
                if (($entry['id'] ?? null) === $id) {
                    $matchedEntries[] = $entry;
                }
            }
        }

        $value = null;
        $selectedBy = null;
        if ($matchedEntries !== []) {
            $value = (string) $matchedEntries[0]['value'];
            $selectedBy = 'unique-identifier';
        } elseif ($entries !== []) {
            $value = (string) $entries[0]['value'];
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
        if ($required && $entries === []) {
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
                    static fn (array $entry): string => (string) $entry['value'],
                    $matchedEntries,
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
            'identifierCount' => count($entries),
            'matchCount' => count($matchedEntries),
            'duplicateMatchCount' => max(0, count($matchedEntries) - 1),
            'entries' => $entries,
            'matchedEntries' => $matchedEntries,
            'valid' => $diagnostics === [],
            'diagnostics' => $diagnostics,
        ];
    }

    /**
     * @param list<array<string, mixed>> $identifierDetails
     * @param array<string, mixed> $uniqueIdentifier
     *
     * @return array<string, mixed>
     */
    private static function metadataIdentifierSummary(array $identifierDetails, array $uniqueIdentifier): array
    {
        $schemes = [];
        $identifierTypes = [];
        $duplicatesByValue = [];
        $diagnostics = [];
        $selectedValue = is_string($uniqueIdentifier['value'] ?? null) ? $uniqueIdentifier['value'] : null;
        $selectedId = null;
        $selectedIndex = null;

        foreach ($identifierDetails as $detail) {
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

            $value = (string) ($detail['value'] ?? '');
            if ($value === '' || isset($duplicatesByValue[$value])) {
                continue;
            }

            $duplicateIds = is_array($detail['duplicateIds'] ?? null) ? array_values($detail['duplicateIds']) : [];
            $duplicateIndexes = is_array($detail['duplicateIndexes'] ?? null) ? array_values($detail['duplicateIndexes']) : [];
            $duplicatesByValue[$value] = [
                'value' => $value,
                'count' => count($duplicateIndexes),
                'ids' => $duplicateIds,
                'indexes' => $duplicateIndexes,
            ];
            $diagnostics[] = [
                'type' => 'duplicate-metadata-identifier-value',
                'value' => $value,
                'ids' => $duplicateIds,
                'indexes' => $duplicateIndexes,
                'message' => 'EPUB OPF metadata contains multiple dc:identifier entries with the same value',
            ];
        }

        if ($selectedIndex === null && $selectedValue !== null) {
            foreach ($identifierDetails as $detail) {
                if ((string) ($detail['value'] ?? '') !== $selectedValue) {
                    continue;
                }

                $selectedIndex = (int) ($detail['index'] ?? 0);
                $selectedId = is_string($detail['id'] ?? null) ? $detail['id'] : null;
                break;
            }
        }

        return [
            'present' => $identifierDetails !== [],
            'count' => count($identifierDetails),
            'typedCount' => count(array_filter(
                $identifierDetails,
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
            'duplicatesByValue' => array_values($duplicatesByValue),
            'valid' => $diagnostics === [],
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
            $refinements = is_array($entry['refinements'] ?? null) ? $entry['refinements'] : [];
            $eventAttribute = is_string($entry['event'] ?? null) && $entry['event'] !== ''
                ? $entry['event']
                : null;
            $eventRefinement = self::firstMetadataRefinementValue($refinements, 'event');
            $event = $eventAttribute ?? $eventRefinement;

            $details[] = [
                'kind' => 'date',
                'index' => $index,
                'text' => (string) ($entry['text'] ?? ''),
                'id' => is_string($entry['id'] ?? null) ? $entry['id'] : null,
                'scheme' => is_string($entry['scheme'] ?? null) ? $entry['scheme'] : null,
                'language' => is_string($entry['language'] ?? null) ? $entry['language'] : null,
                'direction' => is_string($entry['direction'] ?? null) ? $entry['direction'] : null,
                'event' => $event,
                'eventSource' => $eventAttribute !== null ? 'attribute' : ($eventRefinement !== null ? 'refinement' : null),
                'displaySeq' => self::firstMetadataRefinementValue($refinements, 'display-seq'),
                'alternateScripts' => self::metadataRefinementEntries($refinements, 'alternate-script'),
                'refinements' => $refinements,
            ];
        }

        return $details;
    }

    /**
     * @param list<array<string, mixed>> $dateDetails
     *
     * @return array<string, mixed>
     */
    private static function metadataDateSummary(array $dateDetails): array
    {
        $events = [];
        foreach ($dateDetails as $detail) {
            $event = is_string($detail['event'] ?? null) ? trim($detail['event']) : '';
            if ($event !== '') {
                $events[$event] = $event;
            }
        }

        return [
            'present' => $dateDetails !== [],
            'count' => count($dateDetails),
            'eventCount' => count($events),
            'events' => array_values($events),
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
     * @param list<array<string, mixed>> $entries
     *
     * @return list<array<string, mixed>>
     */
    private static function metadataSourceDetails(array $entries): array
    {
        $details = [];
        foreach ($entries as $index => $entry) {
            $refinements = is_array($entry['refinements'] ?? null) ? $entry['refinements'] : [];
            $sourceOf = self::metadataRefinementEntries($refinements, 'source-of');
            $sourceOfValues = array_map(static fn (array $source): string => (string) $source['value'], $sourceOf);
            $identifierTypes = self::metadataRefinementEntries($refinements, 'identifier-type');

            $details[] = [
                'kind' => 'source',
                'index' => $index,
                'text' => (string) ($entry['text'] ?? ''),
                'id' => is_string($entry['id'] ?? null) ? $entry['id'] : null,
                'scheme' => is_string($entry['scheme'] ?? null) ? $entry['scheme'] : null,
                'language' => is_string($entry['language'] ?? null) ? $entry['language'] : null,
                'direction' => is_string($entry['direction'] ?? null) ? $entry['direction'] : null,
                'sourceOf' => $sourceOfValues[0] ?? null,
                'sourceOfValues' => $sourceOfValues,
                'sourceOfEntries' => $sourceOf,
                'identifierType' => $identifierTypes[0]['value'] ?? null,
                'identifierTypes' => $identifierTypes,
                'displaySeq' => self::firstMetadataRefinementValue($refinements, 'display-seq'),
                'alternateScripts' => self::metadataRefinementEntries($refinements, 'alternate-script'),
                'refinements' => $refinements,
            ];
        }

        return $details;
    }

    /**
     * @param list<array<string, mixed>> $sourceDetails
     *
     * @return array<string, list<array<string, mixed>>>
     */
    private static function metadataSourcesByType(array $sourceDetails): array
    {
        $byType = [];
        foreach ($sourceDetails as $detail) {
            foreach ($detail['sourceOfValues'] ?? [] as $type) {
                if (is_string($type) && $type !== '') {
                    $byType[$type][] = $detail;
                }
            }
        }

        return $byType;
    }

    /**
     * @param list<array<string, mixed>> $sourceDetails
     *
     * @return array<string, mixed>
     */
    private static function metadataSourceSummary(array $sourceDetails): array
    {
        $schemes = [];
        $sourceOfValues = [];
        $identifierTypes = [];
        $typedCount = 0;

        foreach ($sourceDetails as $detail) {
            $scheme = is_string($detail['scheme'] ?? null) ? trim($detail['scheme']) : '';
            if ($scheme !== '') {
                $schemes[$scheme] = $scheme;
            }

            $sourceTypes = [];
            foreach ($detail['sourceOfValues'] ?? [] as $sourceOf) {
                if (is_string($sourceOf) && $sourceOf !== '') {
                    $sourceTypes[$sourceOf] = $sourceOf;
                    $sourceOfValues[$sourceOf] = $sourceOf;
                }
            }
            if ($sourceTypes !== []) {
                ++$typedCount;
            }

            foreach ($detail['identifierTypes'] ?? [] as $identifierType) {
                $value = is_string($identifierType['value'] ?? null) ? trim($identifierType['value']) : '';
                if ($value !== '') {
                    $identifierTypes[$value] = $value;
                }
            }
        }

        return [
            'present' => $sourceDetails !== [],
            'count' => count($sourceDetails),
            'typedCount' => $typedCount,
            'schemeCount' => count($schemes),
            'identifierTypeCount' => count($identifierTypes),
            'sourceOfValues' => array_values($sourceOfValues),
            'identifierTypes' => array_values($identifierTypes),
            'schemes' => array_values($schemes),
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
     * @return array<string, mixed>
     */
    private static function metadataRenditionLayoutReport(array $metaProperties): array
    {
        $layout = self::renditionMetadataScalarReport($metaProperties, 'layout', ['reflowable', 'pre-paginated']);
        $flow = self::renditionMetadataScalarReport($metaProperties, 'flow', ['auto', 'paginated', 'scrolled-continuous', 'scrolled-doc']);
        $orientation = self::renditionMetadataScalarReport($metaProperties, 'orientation', ['auto', 'landscape', 'portrait']);
        $spread = self::renditionMetadataScalarReport($metaProperties, 'spread', ['auto', 'none', 'both', 'landscape', 'portrait']);
        $viewportEntries = is_array($metaProperties['rendition:viewport'] ?? null) ? $metaProperties['rendition:viewport'] : [];
        $viewports = [];
        $diagnostics = array_merge($layout['diagnostics'], $flow['diagnostics'], $orientation['diagnostics'], $spread['diagnostics']);

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

        return [
            'present' => $layout['present'] || $flow['present'] || $orientation['present'] || $spread['present'] || $viewports !== [],
            'fixedLayout' => ($layout['value'] ?? null) === 'pre-paginated',
            'layout' => $layout['value'],
            'layoutRaw' => $layout['raw'],
            'layoutProperty' => $layout,
            'flow' => $flow['value'],
            'flowRaw' => $flow['raw'],
            'flowProperty' => $flow,
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

            $raw = self::metadataRefinementValue($entry);
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
        $raw = self::metadataRefinementValue($entry);
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
            'valid' => false,
            'diagnostics' => [],
        ];
    }

    /**
     * @param list<array<string, mixed>> $details
     *
     * @return array<string, list<array<string, mixed>>>
     */
    private static function metadataDetailsByField(array $details, string $field): array
    {
        $byField = [];
        foreach ($details as $detail) {
            $value = $detail[$field] ?? null;
            if (is_string($value) && $value !== '') {
                $byField[$value][] = $detail;
            }
        }

        return $byField;
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
                if (is_string($role) && $role !== '') {
                    $byRole[$role][] = $detail;
                }
            }
        }

        return $byRole;
    }

    /**
     * @param array<string, list<array<string, mixed>>> $refinements
     *
     * @return list<array<string, mixed>>
     */
    private static function metadataRefinementEntries(array $refinements, string $property): array
    {
        $entries = [];
        foreach ($refinements[$property] ?? [] as $entry) {
            $value = self::metadataRefinementValue($entry);
            if ($value === '') {
                continue;
            }

            $entries[] = [
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

        return $entries;
    }

    /**
     * @param array<string, list<array<string, mixed>>> $refinements
     */
    private static function firstMetadataRefinementValue(array $refinements, string $property): ?string
    {
        foreach ($refinements[$property] ?? [] as $entry) {
            $value = self::metadataRefinementValue($entry);
            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $entry
     */
    private static function metadataRefinementValue(array $entry): string
    {
        $content = $entry['content'] ?? null;
        if (is_string($content) && $content !== '') {
            return $content;
        }

        $text = $entry['text'] ?? null;

        return is_string($text) ? $text : '';
    }

    private static function metadataRefinementSubject(?string $refines): ?string
    {
        if ($refines === null) {
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

    private static function metadataElementScheme(\DOMElement $element): ?string
    {
        if ($element->hasAttributeNS(self::OPF_NAMESPACE, 'scheme')) {
            return self::emptyToNull($element->getAttributeNS(self::OPF_NAMESPACE, 'scheme'));
        }

        return self::emptyToNull($element->getAttribute('scheme'));
    }

    private static function metadataElementLanguage(\DOMElement $element): ?string
    {
        if ($element->hasAttributeNS('http://www.w3.org/XML/1998/namespace', 'lang')) {
            return self::emptyToNull($element->getAttributeNS('http://www.w3.org/XML/1998/namespace', 'lang'));
        }

        return self::emptyToNull($element->getAttribute('xml:lang'));
    }

    private static function metadataElementDirection(\DOMElement $element): ?string
    {
        return self::emptyToNull($element->getAttribute('dir'));
    }

    private static function emptyToNull(string $value): ?string
    {
        $value = trim($value);

        return $value === '' ? null : $value;
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
     * @return array{
     *     0:array<string, array{id:string, href:string, partName:string, mediaType:string, properties:list<string>, fallback:?string, fallbackStyle:?string, mediaOverlay:?string}>,
     *     1:list<array{id:string, href:string, partName:string, mediaType:string, properties:list<string>, fallback:?string, fallbackStyle:?string, mediaOverlay:?string}>
     * }
     */
    private static function parseManifest(\DOMElement $manifestElement, string $opfPartName, ZipPackage $package): array
    {
        $byId = [];
        $items = [];
        $manifestIdIndexes = [];

        foreach (self::childElements($manifestElement, 'item', self::OPF_NAMESPACE) as $itemElement) {
            $id = $itemElement->getAttribute('id');
            $href = $itemElement->getAttribute('href');
            $mediaType = $itemElement->getAttribute('media-type');
            if ($id === '' || $href === '' || $mediaType === '') {
                throw new \RuntimeException('EPUB manifest items must include id, href, and media-type');
            }

            $target = self::resolvePackageHref($opfPartName, $href);
            if (self::isAbsoluteUri($target)) {
                throw new \RuntimeException("EPUB manifest item {$id} must reference an internal package part");
            }

            $partName = OpcPackagePath::stripQueryAndFragment($target);
            $hrefSuffix = self::packageHrefSuffixReport($target);
            $exists = $package->has($partName);
            $entry = $exists ? $package->entry($partName) : null;

            $item = [
                'id' => $id,
                'href' => $href,
                'target' => $target,
                'partName' => $partName,
                'mediaType' => $mediaType,
                'exists' => $exists,
                'properties' => self::splitTokens($itemElement->getAttribute('properties')),
                'fallback' => $itemElement->hasAttribute('fallback') ? $itemElement->getAttribute('fallback') : null,
                'fallbackStyle' => $itemElement->hasAttribute('fallback-style') ? $itemElement->getAttribute('fallback-style') : null,
                'mediaOverlay' => $itemElement->hasAttribute('media-overlay') ? $itemElement->getAttribute('media-overlay') : null,
                'hrefHasQuery' => $hrefSuffix['hasQuery'],
                'hrefQuery' => $hrefSuffix['query'],
                'hrefHasFragment' => $hrefSuffix['hasFragment'],
                'hrefFragment' => $hrefSuffix['fragment'],
            ] + self::zipEntryProvenance($entry);

            $manifestIdIndexes[$id][] = count($items);
            $byId[$id] ??= $item;
            $items[] = $item;
        }

        if ($items === []) {
            throw new \RuntimeException('EPUB OPF manifest must contain at least one item');
        }

        foreach ($manifestIdIndexes as $id => $indexes) {
            if (count($indexes) < 2) {
                continue;
            }

            $selectedIndex = $indexes[0];
            foreach ($indexes as $ordinal => $index) {
                $items[$index]['duplicateManifestId'] = true;
                $items[$index]['duplicateManifestIdIndexes'] = $indexes;
                $items[$index]['duplicateManifestIdOrdinal'] = $ordinal;
                $items[$index]['duplicateManifestIdSelected'] = $index === $selectedIndex;
                if ($index === $selectedIndex) {
                    $byId[$id] = $items[$index];
                }
            }
        }

        return [$byId, $items];
    }

    /**
     * @return array<string, mixed>
     */
    private static function parseSpineMetadata(\DOMElement $spineElement): array
    {
        $tocSpecified = $spineElement->hasAttribute('toc');
        $pageProgressionSpecified = $spineElement->hasAttribute('page-progression-direction');
        $pageProgressionRaw = $pageProgressionSpecified
            ? self::emptyToNull($spineElement->getAttribute('page-progression-direction'))
            : null;
        $pageProgression = $pageProgressionRaw === null ? null : strtolower($pageProgressionRaw);
        $validPageProgressionValues = ['ltr', 'rtl', 'default'];
        $diagnostics = [];

        if ($pageProgression !== null && !in_array($pageProgression, $validPageProgressionValues, true)) {
            $diagnostics[] = [
                'type' => 'invalid-spine-page-progression-direction',
                'attribute' => 'page-progression-direction',
                'value' => $pageProgressionRaw,
                'message' => 'EPUB OPF spine page-progression-direction must be ltr, rtl, or default',
            ];
            $pageProgression = null;
        }

        return [
            'tocSpecified' => $tocSpecified,
            'tocId' => $tocSpecified ? self::emptyToNull($spineElement->getAttribute('toc')) : null,
            'pageProgressionDirectionSpecified' => $pageProgressionSpecified,
            'pageProgressionDirectionRaw' => $pageProgressionRaw,
            'pageProgressionDirection' => $pageProgression,
            'readingProgression' => match ($pageProgression) {
                'ltr' => 'left-to-right',
                'rtl' => 'right-to-left',
                'default' => 'default',
                default => null,
            },
            'leftToRight' => $pageProgression === 'ltr',
            'rightToLeft' => $pageProgression === 'rtl',
            'defaultProgression' => $pageProgression === 'default',
            'valid' => $diagnostics === [],
            'diagnosticCount' => count($diagnostics),
            'diagnostics' => $diagnostics,
        ];
    }

    /**
     * @param list<string> $properties
     *
     * @return array{pageSpread:array{placement:?string, properties:list<string>, matches:list<array{property:string, placement:string}>, placements:list<string>, conflicting:bool}, diagnostics:list<array<string, mixed>>}
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
                'placements' => $spreadPlacements,
                'conflicting' => $conflicting,
            ],
            'diagnostics' => $diagnostics,
        ];
    }

    /**
     * @param array<string, array{id:string, href:string, partName:string, mediaType:string, properties:list<string>, fallback:?string, fallbackStyle:?string, mediaOverlay:?string}> $manifestById
     * @param array<string, array<string, list<array<string, mixed>>>> $refinementsById
     *
     * @return list<array<string, mixed>>
     */
    private static function parseSpine(\DOMElement $spineElement, array $manifestById, array $refinementsById): array
    {
        $spine = [];
        foreach (self::childElements($spineElement, 'itemref', self::OPF_NAMESPACE) as $itemrefElement) {
            $idref = $itemrefElement->getAttribute('idref');
            if ($idref === '') {
                throw new \RuntimeException('EPUB spine itemref must include idref');
            }

            $linearReport = self::spineItemLinearReport($itemrefElement, $idref);
            $item = $manifestById[$idref] ?? null;
            if (!is_array($item)) {
                $id = $itemrefElement->hasAttribute('id')
                    ? self::emptyToNull($itemrefElement->getAttribute('id'))
                    : null;
                $properties = self::splitTokens($itemrefElement->getAttribute('properties'));
                $itemProperties = self::spineItemPropertyReport($properties);
                $spineItemDiagnostics = array_merge($itemProperties['diagnostics'], $linearReport['diagnostics']);
                $spine[] = [
                    'id' => $id,
                    'idref' => $idref,
                    'href' => '',
                    'partName' => '',
                    'mediaType' => '',
                    'exists' => false,
                    'manifestItemMissing' => true,
                    'linear' => $linearReport['linear'],
                    'linearRaw' => $linearReport['raw'],
                    'linearSpecified' => $linearReport['specified'],
                    'linearValid' => $linearReport['valid'],
                    'properties' => $properties,
                    'spineItemProperties' => $itemProperties,
                    'spineItemDiagnostics' => $spineItemDiagnostics,
                    'pageSpread' => $itemProperties['pageSpread']['placement'],
                    'pageSpreadProperties' => $itemProperties['pageSpread']['properties'],
                    'mediaOverlay' => null,
                    'refinements' => [],
                    'renditionViewportRefinements' => [],
                ];
                continue;
            }

            $id = $itemrefElement->hasAttribute('id')
                ? self::emptyToNull($itemrefElement->getAttribute('id'))
                : null;
            $refinements = $id !== null && isset($refinementsById[$id]) && is_array($refinementsById[$id])
                ? $refinementsById[$id]
                : [];
            $properties = self::splitTokens($itemrefElement->getAttribute('properties'));
            $itemProperties = self::spineItemPropertyReport($properties);
            $spineItemDiagnostics = array_merge($itemProperties['diagnostics'], $linearReport['diagnostics']);

            $spine[] = [
                'id' => $id,
                'idref' => $idref,
                'href' => $item['href'],
                'partName' => $item['partName'],
                'mediaType' => $item['mediaType'],
                'exists' => ($item['exists'] ?? false) === true,
                'manifestItemMissing' => false,
                'linear' => $linearReport['linear'],
                'linearRaw' => $linearReport['raw'],
                'linearSpecified' => $linearReport['specified'],
                'linearValid' => $linearReport['valid'],
                'properties' => $properties,
                'spineItemProperties' => $itemProperties,
                'spineItemDiagnostics' => $spineItemDiagnostics,
                'pageSpread' => $itemProperties['pageSpread']['placement'],
                'pageSpreadProperties' => $itemProperties['pageSpread']['properties'],
                'mediaOverlay' => $item['mediaOverlay'] ?? null,
                'refinements' => $refinements,
                'renditionViewportRefinements' => self::metadataRefinementEntries($refinements, 'rendition:viewport'),
            ];
        }

        if ($spine === []) {
            throw new \RuntimeException('EPUB spine must contain at least one itemref');
        }

        return $spine;
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
     * @param array<string, array{id:string, href:string, partName:string, mediaType:string, properties:list<string>, fallback:?string}> $manifestById
     *
     * @return array{
     *     navigation:array{type:string, partName:string, entries:list<array{label:string, href:?string, target:?string, depth:int, playOrder:?int}>}|null,
     *     sections:list<array{type:?string, types:list<string>, label:?string, partName:string, entries:list<array{label:string, href:?string, target:?string, depth:int, playOrder:?int}>}>
     * }
     */
    private static function loadNavigation(ZipPackage $package, string $opfPartName, array $manifestById, ?string $spineTocId): array
    {
        foreach ($manifestById as $item) {
            if ($item['mediaType'] === self::XHTML_MEDIA_TYPE && in_array('nav', $item['properties'], true)) {
                if (($item['exists'] ?? false) !== true || !$package->has($item['partName'])) {
                    continue;
                }

                $report = self::parseNavDocument($package->read($item['partName']), $item['partName']);

                return [
                    'navigation' => [
                        'type' => 'nav',
                        'partName' => $item['partName'],
                        'entries' => $report['primaryEntries'],
                    ],
                    'sections' => $report['sections'],
                ];
            }
        }

        $ncxItem = null;
        if ($spineTocId !== null && isset($manifestById[$spineTocId]) && self::mediaTypeBase($manifestById[$spineTocId]['mediaType']) === self::NCX_MEDIA_TYPE) {
            $ncxItem = $manifestById[$spineTocId];
        } else {
            foreach ($manifestById as $item) {
                if (self::mediaTypeBase($item['mediaType']) === self::NCX_MEDIA_TYPE) {
                    $ncxItem = $item;
                    break;
                }
            }
        }

        if (is_array($ncxItem)) {
            if (($ncxItem['exists'] ?? false) !== true || !$package->has($ncxItem['partName'])) {
                return [
                    'navigation' => null,
                    'sections' => [],
                ];
            }

            $entries = self::parseNcxDocument($package->read($ncxItem['partName']), $ncxItem['partName']);

            return [
                'navigation' => [
                    'type' => 'ncx',
                    'partName' => $ncxItem['partName'],
                    'entries' => $entries,
                ],
                'sections' => [[
                    'type' => 'toc',
                    'types' => ['toc'],
                    'label' => null,
                    'partName' => $ncxItem['partName'],
                    'entries' => $entries,
                ]],
            ];
        }

        return [
            'navigation' => null,
            'sections' => [],
        ];
    }

    /**
     * @return array{
     *     primaryEntries:list<array{label:string, href:?string, target:?string, depth:int, playOrder:?int}>,
     *     sections:list<array{type:?string, types:list<string>, label:?string, partName:string, entries:list<array{label:string, href:?string, target:?string, depth:int, playOrder:?int}>}>
     * }
     */
    private static function parseNavDocument(string $xml, string $navPartName): array
    {
        $dom = self::loadXml($xml, 'EPUB navigation document');
        $xpath = new \DOMXPath($dom);
        $nodes = $xpath->query('//*[local-name() = "nav"]');
        if (!$nodes instanceof \DOMNodeList || $nodes->length === 0) {
            throw new \RuntimeException('EPUB navigation document does not contain a nav element');
        }

        $primaryEntries = null;
        $fallbackEntries = null;
        $sections = [];
        foreach ($nodes as $node) {
            if (!$node instanceof \DOMElement) {
                continue;
            }

            $types = self::epubTypes($node);
            $directList = self::firstChildElement($node, 'ol');
            $list = $directList ?? self::firstDescendantElement($node, 'ol');
            $listReport = $list instanceof \DOMElement
                ? self::parseNavListReport($list, $navPartName, 1)
                : self::emptyNavListReport();
            $entries = $listReport['entries'];
            $label = self::navSectionLabel($node);
            $section = [
                'id' => self::emptyToNull($node->getAttribute('id')),
                'type' => $types[0] ?? null,
                'types' => $types,
                'label' => $label,
                'title' => $label,
                'hidden' => self::elementHidden($node),
                'hasOrderedList' => $directList instanceof \DOMElement,
                'itemCount' => count($entries),
                'rawItemCount' => $listReport['rawItemCount'],
                'missingItemLabelCount' => $listReport['missingLabelCount'],
                'emptyItemLabelCount' => $listReport['emptyLabelCount'],
                'missingItemHrefCount' => $listReport['missingHrefCount'],
                'itemDiagnostics' => $listReport['diagnostics'],
                'itemDiagnosticCount' => count($listReport['diagnostics']),
                'partName' => $navPartName,
                'entries' => $entries,
            ];

            $sections[] = $section;
            $fallbackEntries ??= $entries;
            if (in_array('toc', $types, true) && $primaryEntries === null) {
                $primaryEntries = $entries;
            }
        }

        if ($sections === []) {
            throw new \RuntimeException('EPUB navigation document does not contain a usable nav element');
        }

        return [
            'primaryEntries' => $primaryEntries ?? $fallbackEntries ?? [],
            'sections' => $sections,
        ];
    }

    /**
     * @return list<array{label:string, href:?string, target:?string, depth:int, playOrder:?int}>
     */
    private static function parseNavList(\DOMElement $list, string $navPartName, int $depth): array
    {
        return self::parseNavListReport($list, $navPartName, $depth)['entries'];
    }

    /**
     * @return array{
     *     entries:list<array{label:string, href:?string, target:?string, depth:int, playOrder:?int}>,
     *     rawItemCount:int,
     *     missingLabelCount:int,
     *     emptyLabelCount:int,
     *     missingHrefCount:int,
     *     diagnostics:list<array<string, mixed>>
     * }
     */
    private static function emptyNavListReport(): array
    {
        return [
            'entries' => [],
            'rawItemCount' => 0,
            'missingLabelCount' => 0,
            'emptyLabelCount' => 0,
            'missingHrefCount' => 0,
            'diagnostics' => [],
        ];
    }

    /**
     * @return array{
     *     entries:list<array{label:string, href:?string, target:?string, depth:int, playOrder:?int}>,
     *     rawItemCount:int,
     *     missingLabelCount:int,
     *     emptyLabelCount:int,
     *     missingHrefCount:int,
     *     diagnostics:list<array<string, mixed>>
     * }
     */
    private static function parseNavListReport(\DOMElement $list, string $navPartName, int $depth, int &$itemIndex = 0): array
    {
        $entries = [];
        $rawItemCount = 0;
        $missingLabelCount = 0;
        $emptyLabelCount = 0;
        $missingHrefCount = 0;
        $diagnostics = [];

        foreach (self::childElements($list, 'li') as $li) {
            $labelElement = self::firstChildElement($li, 'a') ?? self::firstChildElement($li, 'span');
            $currentItemIndex = $itemIndex++;
            ++$rawItemCount;

            if ($labelElement instanceof \DOMElement) {
                $href = $labelElement->localName === 'a' && $labelElement->hasAttribute('href')
                    ? $labelElement->getAttribute('href')
                    : null;
                $label = self::normalizeText($labelElement->textContent);

                if ($label === '') {
                    ++$emptyLabelCount;
                    $diagnostics[] = [
                        'type' => 'empty-nav-item-label',
                        'itemIndex' => $currentItemIndex,
                        'depth' => $depth,
                        'itemId' => self::emptyToNull($li->getAttribute('id')),
                        'labelElement' => $labelElement->localName,
                        'labelId' => self::emptyToNull($labelElement->getAttribute('id')),
                        'href' => $href,
                        'message' => 'EPUB navigation list item label is empty',
                    ];
                }
                if ($labelElement->localName === 'a' && ($href === null || trim($href) === '')) {
                    ++$missingHrefCount;
                    $diagnostics[] = [
                        'type' => 'missing-nav-item-href',
                        'itemIndex' => $currentItemIndex,
                        'depth' => $depth,
                        'itemId' => self::emptyToNull($li->getAttribute('id')),
                        'label' => $label,
                        'labelId' => self::emptyToNull($labelElement->getAttribute('id')),
                        'message' => 'EPUB navigation link item is missing an href target',
                    ];
                }

                if ($label !== '' || ($href !== null && $href !== '')) {
                    $entries[] = [
                        'label' => $label,
                        'href' => $href,
                        'target' => $href === null || $href === '' ? null : self::resolveReadingHref($navPartName, $href),
                        'depth' => $depth,
                        'playOrder' => null,
                    ];
                }
            } else {
                ++$missingLabelCount;
                $diagnostics[] = [
                    'type' => 'missing-nav-item-label',
                    'itemIndex' => $currentItemIndex,
                    'depth' => $depth,
                    'itemId' => self::emptyToNull($li->getAttribute('id')),
                    'message' => 'EPUB navigation list item is missing a direct a or span label',
                ];
            }

            foreach (self::childElements($li, 'ol') as $nestedList) {
                $nestedReport = self::parseNavListReport($nestedList, $navPartName, $depth + 1, $itemIndex);
                array_push($entries, ...$nestedReport['entries']);
                $rawItemCount += $nestedReport['rawItemCount'];
                $missingLabelCount += $nestedReport['missingLabelCount'];
                $emptyLabelCount += $nestedReport['emptyLabelCount'];
                $missingHrefCount += $nestedReport['missingHrefCount'];
                array_push($diagnostics, ...$nestedReport['diagnostics']);
            }
        }

        return [
            'entries' => $entries,
            'rawItemCount' => $rawItemCount,
            'missingLabelCount' => $missingLabelCount,
            'emptyLabelCount' => $emptyLabelCount,
            'missingHrefCount' => $missingHrefCount,
            'diagnostics' => $diagnostics,
        ];
    }

    /**
     * @param array<string, array<string, mixed>> $manifestByPart
     *
     * @return list<array{type:?string, title:?string, href:?string, target:?string, partName:?string, external:bool, exists:bool, hrefHasQuery:bool, hrefQuery:?string, hrefHasFragment:bool, hrefFragment:?string}>
     */
    private static function parseGuide(
        ?\DOMElement $guideElement,
        string $opfPartName,
        ZipPackage $package,
        array $manifestByPart
    ): array {
        if (!$guideElement instanceof \DOMElement) {
            return [];
        }

        $references = [];
        foreach (self::childElements($guideElement, 'reference', self::OPF_NAMESPACE) as $index => $reference) {
            $references[] = self::parseGuideReference($reference, $index, $opfPartName, $package, $manifestByPart);
        }

        return $references;
    }

    /**
     * @param array<string, array<string, mixed>> $manifestByPart
     *
     * @return array<string, mixed>
     */
    private static function parseGuideReference(
        \DOMElement $reference,
        int $index,
        string $opfPartName,
        ZipPackage $package,
        array $manifestByPart
    ): array {
        $href = self::emptyToNull($reference->getAttribute('href'));
        $target = null;
        $partName = null;
        $external = false;
        $exists = false;
        $entry = null;
        $manifestItem = null;
        $hrefSuffix = [
            'hasQuery' => false,
            'query' => null,
            'hasFragment' => false,
            'fragment' => null,
        ];
        $diagnostics = [];

        if ($href === null) {
            $diagnostics[] = [
                'type' => 'missing-guide-reference-href',
                'message' => 'EPUB OPF guide reference is missing href',
            ];
        } else {
            try {
                $target = self::resolvePackageHref($opfPartName, $href);
                $hrefSuffix = self::packageHrefSuffixReport($target);
                $external = self::isAbsoluteUri($target);
                if ($external) {
                    $diagnostics[] = [
                        'type' => 'external-guide-reference-target',
                        'href' => $href,
                        'message' => 'EPUB OPF guide reference points outside the package and was not fetched',
                    ];
                } else {
                    $partName = OpcPackagePath::stripQueryAndFragment($target);
                    $exists = $package->has($partName);
                    $entry = $exists ? $package->entry($partName) : null;
                    $manifestItem = $manifestByPart[$partName] ?? null;

                    if (!$exists) {
                        $diagnostics[] = [
                            'type' => 'missing-guide-reference-target',
                            'href' => $href,
                            'partName' => $partName,
                            'message' => 'EPUB OPF guide reference target is missing from the package',
                        ];
                    } elseif (!is_array($manifestItem)) {
                        $diagnostics[] = [
                            'type' => 'guide-reference-target-not-in-manifest',
                            'href' => $href,
                            'partName' => $partName,
                            'message' => 'EPUB OPF guide reference target is present in the ZIP but not declared in the OPF manifest',
                        ];
                    }
                }
            } catch (\InvalidArgumentException $exception) {
                $diagnostics[] = [
                    'type' => 'invalid-guide-reference-href',
                    'href' => $href,
                    'message' => $exception->getMessage(),
                ];
            }
        }

        return [
            'index' => $index,
            'type' => self::emptyToNull($reference->getAttribute('type')),
            'title' => self::emptyToNull($reference->getAttribute('title')),
            'href' => $href,
            'target' => $target,
            'partName' => $partName,
            'external' => $external,
            'exists' => $exists,
            'manifestId' => is_array($manifestItem) ? (string) ($manifestItem['id'] ?? '') : null,
            'manifestMediaType' => is_array($manifestItem) ? (string) ($manifestItem['mediaType'] ?? '') : null,
            'manifestProperties' => is_array($manifestItem) && is_array($manifestItem['properties'] ?? null)
                ? array_values($manifestItem['properties'])
                : [],
            'hrefHasQuery' => $hrefSuffix['hasQuery'],
            'hrefQuery' => $hrefSuffix['query'],
            'hrefHasFragment' => $hrefSuffix['hasFragment'],
            'hrefFragment' => $hrefSuffix['fragment'],
            'diagnostics' => $diagnostics,
        ] + self::zipEntryProvenance($entry);
    }

    /**
     * @param list<array<string, mixed>> $references
     *
     * @return array<string, mixed>
     */
    private static function guideReferenceReport(array $references): array
    {
        $typeCounts = [];
        $targets = [];
        $localTargets = [];
        $externalTargets = [];
        $missingTargets = [];
        $manifestLinkedTargets = [];
        $diagnostics = [];
        $missingHrefCount = 0;
        $invalidTargetCount = 0;

        foreach ($references as $index => $reference) {
            $referenceType = is_string($reference['type'] ?? null) ? $reference['type'] : null;
            if ($referenceType !== null) {
                $typeCounts[$referenceType] = ($typeCounts[$referenceType] ?? 0) + 1;
            }

            $target = is_string($reference['target'] ?? null) ? $reference['target'] : null;
            if ($target !== null) {
                $targets[] = $target;
            }

            if (($reference['external'] ?? false) === true) {
                if ($target !== null) {
                    $externalTargets[] = $target;
                }
            } elseif (($reference['exists'] ?? false) === true) {
                if ($target !== null) {
                    $localTargets[] = $target;
                }
            } elseif ($target !== null) {
                $missingTargets[] = $target;
            }

            if (is_string($reference['manifestId'] ?? null) && $reference['manifestId'] !== '') {
                $manifestLinkedTargets[] = [
                    'index' => $index,
                    'type' => $referenceType,
                    'target' => $target,
                    'partName' => is_string($reference['partName'] ?? null) ? $reference['partName'] : null,
                    'manifestId' => $reference['manifestId'],
                    'manifestMediaType' => is_string($reference['manifestMediaType'] ?? null) ? $reference['manifestMediaType'] : null,
                ];
            }

            foreach (is_array($reference['diagnostics'] ?? null) ? $reference['diagnostics'] : [] as $diagnostic) {
                $diagnosticType = is_string($diagnostic['type'] ?? null) ? $diagnostic['type'] : '';
                if ($diagnosticType === 'missing-guide-reference-href') {
                    ++$missingHrefCount;
                }
                if ($diagnosticType === 'invalid-guide-reference-href') {
                    ++$invalidTargetCount;
                }

                $diagnostics[] = [
                    'index' => $index,
                    'guideType' => $referenceType,
                    'href' => is_string($reference['href'] ?? null) ? $reference['href'] : null,
                ] + $diagnostic;
            }
        }

        return [
            'present' => $references !== [],
            'referenceCount' => count($references),
            'typeCount' => count($typeCounts),
            'types' => array_keys($typeCounts),
            'typeCounts' => $typeCounts,
            'targetCount' => count($targets),
            'localTargetCount' => count($localTargets),
            'externalTargetCount' => count($externalTargets),
            'missingTargetCount' => count($missingTargets),
            'missingHrefCount' => $missingHrefCount,
            'invalidTargetCount' => $invalidTargetCount,
            'manifestLinkedTargetCount' => count($manifestLinkedTargets),
            'targets' => $targets,
            'localTargets' => $localTargets,
            'externalTargets' => $externalTargets,
            'missingTargets' => $missingTargets,
            'manifestLinkedTargets' => $manifestLinkedTargets,
            'diagnosticCount' => count($diagnostics),
            'diagnostics' => $diagnostics,
            'items' => $references,
        ];
    }

    /**
     * @param array<string, array{id:string, href:string, partName:string, mediaType:string, properties:list<string>, fallback:?string, mediaOverlay:?string}> $manifestById
     *
     * @return array<string, array{id:string, href:string, partName:string, mediaType:string, properties:list<string>, fallback:?string, mediaOverlay:?string}>
     */
    private static function manifestByPart(array $manifestById): array
    {
        $byPart = [];
        foreach ($manifestById as $item) {
            $byPart[$item['partName']] = $item;
        }

        return $byPart;
    }

    /**
     * @param array<string, array{id:string, href:string, partName:string, mediaType:string, properties:list<string>, fallback:?string, mediaOverlay:?string}> $manifestByPart
     *
     * @return list<array<string, mixed>>
     */
    private static function parsePackageLinks(
        \DOMElement $metadataElement,
        string $opfPartName,
        ZipPackage $package,
        array $manifestByPart,
        array $prefixBindings = []
    ): array {
        $links = [];
        foreach (self::childElements($metadataElement, 'link', self::OPF_NAMESPACE) as $index => $linkElement) {
            $links[] = self::parsePackageLink($linkElement, $index, $opfPartName, $package, $manifestByPart, $prefixBindings);
        }

        return $links;
    }

    /**
     * @param array<string, array{id:string, href:string, partName:string, mediaType:string, properties:list<string>, fallback:?string, mediaOverlay:?string}> $manifestByPart
     *
     * @return array<string, mixed>
     */
    private static function parsePackageLink(
        \DOMElement $linkElement,
        int $index,
        string $opfPartName,
        ZipPackage $package,
        array $manifestByPart,
        array $prefixBindings = []
    ): array {
        $href = self::emptyToNull($linkElement->getAttribute('href'));
        $rel = self::splitTokens($linkElement->getAttribute('rel'));
        $properties = self::splitTokens($linkElement->getAttribute('properties'));
        $target = null;
        $partName = null;
        $external = false;
        $exists = false;
        $entry = null;
        $manifestItem = null;
        $hrefSuffix = [
            'hasQuery' => false,
            'query' => null,
            'hasFragment' => false,
            'fragment' => null,
        ];
        $diagnostics = [];

        if ($rel === []) {
            $diagnostics[] = [
                'type' => 'missing-package-link-rel',
                'message' => 'EPUB OPF metadata link is missing rel tokens for package preflight classification',
            ];
        }

        if ($href === null) {
            $diagnostics[] = [
                'type' => 'missing-package-link-href',
                'message' => 'EPUB OPF metadata link is missing href',
            ];
        } else {
            try {
                $target = self::resolvePackageHref($opfPartName, $href);
                $hrefSuffix = self::packageHrefSuffixReport($target);
                $external = self::isAbsoluteUri($target);
                if ($external) {
                    $diagnostics[] = [
                        'type' => 'external-package-link-target',
                        'href' => $href,
                        'message' => 'EPUB OPF metadata link points outside the package and was not fetched',
                    ];
                } else {
                    $partName = OpcPackagePath::stripQueryAndFragment($target);
                    $exists = $package->has($partName);
                    $entry = $exists ? $package->entry($partName) : null;
                    $manifestItem = $manifestByPart[$partName] ?? null;
                    if (!$exists) {
                        $diagnostics[] = [
                            'type' => 'missing-package-link-target',
                            'href' => $href,
                            'partName' => $partName,
                            'message' => 'EPUB OPF metadata link target is missing from the package',
                        ];
                    }
                }
            } catch (\InvalidArgumentException $exception) {
                $diagnostics[] = [
                    'type' => 'invalid-package-link-href',
                    'href' => $href,
                    'message' => $exception->getMessage(),
                ];
            }
        }

        $refines = self::emptyToNull($linkElement->getAttribute('refines'));
        $provenance = self::zipEntryProvenance($entry);
        if (is_array($manifestItem) && ($manifestItem['canExposeBytes'] ?? false) !== true) {
            $provenance['canExposeBytes'] = false;
        }

        return [
            'index' => $index,
            'id' => self::emptyToNull($linkElement->getAttribute('id')),
            'rel' => $rel,
            'href' => $href,
            'target' => $target,
            'partName' => $partName,
            'external' => $external,
            'exists' => $exists,
            'mediaType' => self::emptyToNull($linkElement->getAttribute('media-type')),
            'manifestId' => is_array($manifestItem) ? $manifestItem['id'] : null,
            'manifestMediaType' => is_array($manifestItem) ? $manifestItem['mediaType'] : null,
            'properties' => $properties,
            'relVocabulary' => self::metadataLinkTokenReport($rel, $prefixBindings, 'rel', $index),
            'propertyVocabulary' => self::metadataLinkTokenReport($properties, $prefixBindings, 'properties', $index),
            'title' => self::emptyToNull($linkElement->getAttribute('title')),
            'hreflang' => self::emptyToNull($linkElement->getAttribute('hreflang')),
            'language' => self::metadataElementLanguage($linkElement),
            'direction' => self::metadataElementDirection($linkElement),
            'refines' => $refines,
            'subjectId' => self::metadataRefinementSubject($refines),
            'hrefHasQuery' => $hrefSuffix['hasQuery'],
            'hrefQuery' => $hrefSuffix['query'],
            'hrefHasFragment' => $hrefSuffix['hasFragment'],
            'hrefFragment' => $hrefSuffix['fragment'],
            'diagnostics' => $diagnostics,
        ] + $provenance;
    }

    /**
     * @param array<string, array{id:string, href:string, partName:string, mediaType:string, properties:list<string>, fallback:?string, mediaOverlay:?string}> $manifestById
     *
     * @return list<array<string, mixed>>
     */
    private static function parseCollections(
        \DOMElement $packageElement,
        string $opfPartName,
        ZipPackage $package,
        array $manifestById
    ): array {
        $manifestByPart = self::manifestByPart($manifestById);
        $collections = [];
        foreach (self::childElements($packageElement, 'collection', self::OPF_NAMESPACE) as $index => $collectionElement) {
            $collections[] = self::parseCollection($collectionElement, $index, $opfPartName, $package, $manifestByPart);
        }

        return $collections;
    }

    /**
     * @param array<string, array{id:string, href:string, partName:string, mediaType:string, properties:list<string>, fallback:?string}> $manifestByPart
     *
     * @return array<string, mixed>
     */
    private static function parseCollection(
        \DOMElement $collectionElement,
        int $index,
        string $opfPartName,
        ZipPackage $package,
        array $manifestByPart
    ): array {
        $links = [];
        foreach (self::childElements($collectionElement, 'link', self::OPF_NAMESPACE) as $linkIndex => $linkElement) {
            $links[] = self::parseCollectionLink($linkElement, $linkIndex, $opfPartName, $package, $manifestByPart);
        }

        $children = [];
        foreach (self::childElements($collectionElement, 'collection', self::OPF_NAMESPACE) as $childIndex => $childElement) {
            $children[] = self::parseCollection($childElement, $childIndex, $opfPartName, $package, $manifestByPart);
        }

        $metadataElement = self::firstChildElement($collectionElement, 'metadata', self::OPF_NAMESPACE);
        $metadata = $metadataElement instanceof \DOMElement
            ? self::parseMetadata($metadataElement, $collectionElement)
            : [];
        $role = self::emptyToNull($collectionElement->getAttribute('role'));
        $roleTokens = self::splitTokens($role ?? '');
        $report = self::collectionLinkReport($links);

        return [
            'index' => $index,
            'id' => self::emptyToNull($collectionElement->getAttribute('id')),
            'role' => $role,
            'roleTokens' => $roleTokens,
            'primaryRole' => $roleTokens[0] ?? null,
            'language' => self::metadataElementLanguage($collectionElement),
            'direction' => self::metadataElementDirection($collectionElement),
            'metadata' => $metadata,
            'links' => $links,
            'linkCount' => $report['count'],
            'localLinkCount' => $report['localCount'],
            'externalLinkCount' => $report['externalCount'],
            'missingLinkCount' => $report['missingCount'],
            'linkRelTokens' => $report['relTokens'],
            'linkRelCounts' => $report['relCounts'],
            'linksByRel' => $report['linksByRel'],
            'diagnosticCount' => count($report['diagnostics']),
            'diagnostics' => $report['diagnostics'],
            'children' => $children,
        ];
    }

    /**
     * @param array<string, array{id:string, href:string, partName:string, mediaType:string, properties:list<string>, fallback:?string}> $manifestByPart
     *
     * @return array<string, mixed>
     */
    private static function parseCollectionLink(
        \DOMElement $linkElement,
        int $index,
        string $opfPartName,
        ZipPackage $package,
        array $manifestByPart
    ): array {
        $href = self::emptyToNull($linkElement->getAttribute('href'));
        $target = null;
        $partName = null;
        $external = false;
        $exists = false;
        $entry = null;
        $manifestItem = null;
        $hrefSuffix = [
            'hasQuery' => false,
            'query' => null,
            'hasFragment' => false,
            'fragment' => null,
        ];
        $diagnostics = [];

        if ($href === null) {
            $diagnostics[] = [
                'type' => 'missing-collection-link-href',
                'message' => 'EPUB OPF collection link is missing href',
            ];
        } else {
            try {
                $target = self::resolvePackageHref($opfPartName, $href);
                $hrefSuffix = self::packageHrefSuffixReport($target);
                $external = self::isAbsoluteUri($target);
                if ($external) {
                    $diagnostics[] = [
                        'type' => 'external-collection-link-target',
                        'href' => $href,
                        'message' => 'EPUB OPF collection link points outside the package and was not fetched',
                    ];
                } else {
                    $partName = OpcPackagePath::stripQueryAndFragment($target);
                    $exists = $package->has($partName);
                    $entry = $exists ? $package->entry($partName) : null;
                    $manifestItem = $manifestByPart[$partName] ?? null;
                    if (!$exists) {
                        $diagnostics[] = [
                            'type' => 'missing-collection-link-target',
                            'href' => $href,
                            'partName' => $partName,
                            'message' => 'EPUB OPF collection link target is missing from the package',
                        ];
                    }
                }
            } catch (\InvalidArgumentException $exception) {
                $diagnostics[] = [
                    'type' => 'invalid-collection-link-href',
                    'href' => $href,
                    'message' => $exception->getMessage(),
                ];
            }
        }

        $provenance = self::zipEntryProvenance($entry);
        if (is_array($manifestItem) && ($manifestItem['canExposeBytes'] ?? false) !== true) {
            $provenance['canExposeBytes'] = false;
        }

        return [
            'index' => $index,
            'id' => self::emptyToNull($linkElement->getAttribute('id')),
            'rel' => self::splitTokens($linkElement->getAttribute('rel')),
            'href' => $href,
            'target' => $target,
            'partName' => $partName,
            'external' => $external,
            'exists' => $exists,
            'mediaType' => self::emptyToNull($linkElement->getAttribute('media-type')),
            'manifestId' => is_array($manifestItem) ? $manifestItem['id'] : null,
            'manifestMediaType' => is_array($manifestItem) ? $manifestItem['mediaType'] : null,
            'properties' => self::splitTokens($linkElement->getAttribute('properties')),
            'title' => self::emptyToNull($linkElement->getAttribute('title')),
            'refines' => self::emptyToNull($linkElement->getAttribute('refines')),
            'hrefHasQuery' => $hrefSuffix['hasQuery'],
            'hrefQuery' => $hrefSuffix['query'],
            'hrefHasFragment' => $hrefSuffix['hasFragment'],
            'hrefFragment' => $hrefSuffix['fragment'],
            'diagnostics' => $diagnostics,
        ] + $provenance;
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
        $diagnostics = [];
        $localCount = 0;
        $externalCount = 0;
        $missingCount = 0;

        foreach ($links as $linkIndex => $link) {
            if (($link['external'] ?? false) === true) {
                ++$externalCount;
            } elseif (is_string($link['partName'] ?? null)) {
                ++$localCount;
            }

            if (($link['external'] ?? false) !== true && ($link['exists'] ?? false) !== true) {
                ++$missingCount;
            }

            foreach (is_array($link['rel'] ?? null) ? $link['rel'] : [] as $rel) {
                $rel = (string) $rel;
                $relCounts[$rel] = ($relCounts[$rel] ?? 0) + 1;
                $linksByRel[$rel][] = $link;
            }

            foreach (is_array($link['diagnostics'] ?? null) ? $link['diagnostics'] : [] as $diagnostic) {
                $diagnostics[] = ['index' => $linkIndex, 'id' => $link['id'] ?? null] + $diagnostic;
            }
        }

        return [
            'count' => count($links),
            'localCount' => $localCount,
            'externalCount' => $externalCount,
            'missingCount' => $missingCount,
            'relTokens' => array_keys($relCounts),
            'relCounts' => $relCounts,
            'linksByRel' => $linksByRel,
            'diagnostics' => $diagnostics,
        ];
    }

    /**
     * @param list<string> $tokens
     * @param array<string, string> $prefixBindings
     *
     * @return array<string, mixed>
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
            $looksAbsolute = self::isAbsoluteUri($value);
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
     * @return array<string, mixed>
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
     * @param list<array<string, mixed>> $collections
     *
     * @return list<string>
     */
    private static function collectionTitles(array $collections): array
    {
        $titles = [];
        foreach ($collections as $collection) {
            $title = $collection['metadata']['title'] ?? null;
            if (is_string($title) && $title !== '') {
                $titles[] = $title;
            }

            array_push($titles, ...self::collectionTitles(
                is_array($collection['children'] ?? null) ? $collection['children'] : [],
            ));
        }

        return $titles;
    }

    /**
     * @param list<array<string, mixed>> $collections
     *
     * @return list<string>
     */
    private static function collectionLinkTargets(array $collections): array
    {
        $targets = [];
        foreach ($collections as $collection) {
            foreach (is_array($collection['links'] ?? null) ? $collection['links'] : [] as $link) {
                $target = $link['target'] ?? null;
                if (is_string($target) && $target !== '') {
                    $targets[] = $target;
                }
            }

            array_push($targets, ...self::collectionLinkTargets(
                is_array($collection['children'] ?? null) ? $collection['children'] : [],
            ));
        }

        return $targets;
    }

    /**
     * @param list<array<string, mixed>> $collections
     *
     * @return list<array<string, mixed>>
     */
    private static function collectionDiagnostics(array $collections): array
    {
        $diagnostics = [];
        foreach ($collections as $collectionIndex => $collection) {
            foreach (is_array($collection['diagnostics'] ?? null) ? $collection['diagnostics'] : [] as $diagnostic) {
                $diagnostics[] = [
                    'collectionIndex' => $collectionIndex,
                    'collectionId' => $collection['id'] ?? null,
                ] + $diagnostic;
            }

            array_push($diagnostics, ...self::collectionDiagnostics(
                is_array($collection['children'] ?? null) ? $collection['children'] : [],
            ));
        }

        return $diagnostics;
    }

    /**
     * @param list<array<string, mixed>> $links
     *
     * @return list<string>
     */
    private static function packageLinkTargets(array $links): array
    {
        $targets = [];
        foreach ($links as $link) {
            $target = $link['target'] ?? null;
            if (is_string($target) && $target !== '') {
                $targets[] = $target;
            }
        }

        return $targets;
    }

    /**
     * @param list<array<string, mixed>> $packageLinks
     * @param list<array<string, mixed>> $collections
     * @param list<array<string, mixed>> $containerLinks
     *
     * @return array<string, mixed>
     */
    private static function remoteResourcePolicyReport(array $packageLinks, array $collections, array $containerLinks = []): array
    {
        $items = [];
        foreach ($containerLinks as $linkIndex => $link) {
            $items[] = self::remoteResourcePolicyItem($link, 'container-link', $linkIndex, null, []);
        }

        foreach ($packageLinks as $linkIndex => $link) {
            $items[] = self::remoteResourcePolicyItem($link, 'package-link', $linkIndex, null, []);
        }

        self::appendCollectionRemoteResourcePolicyItems($collections, [], $items);

        $policyCounts = [];
        $itemsByPolicy = [];
        $localTargets = [];
        $externalTargets = [];
        $missingTargets = [];
        $diagnostics = [];
        $containerLinkCount = 0;
        $packageLinkCount = 0;
        $collectionLinkCount = 0;

        foreach ($items as $item) {
            $policy = (string) $item['policy'];
            $policyCounts[$policy] = ($policyCounts[$policy] ?? 0) + 1;
            $itemsByPolicy[$policy][] = $item;

            if ($item['source'] === 'container-link') {
                ++$containerLinkCount;
            } elseif ($item['source'] === 'package-link') {
                ++$packageLinkCount;
            } elseif ($item['source'] === 'collection-link') {
                ++$collectionLinkCount;
            }

            $target = is_string($item['target'] ?? null) ? $item['target'] : null;
            if ($target !== null && $target !== '') {
                if ($policy === 'local-package') {
                    $localTargets[] = $target;
                } elseif ($policy === 'remote-no-fetch') {
                    $externalTargets[] = $target;
                } elseif ($policy === 'missing-package') {
                    $missingTargets[] = $target;
                }
            }

            foreach (is_array($item['diagnostics'] ?? null) ? $item['diagnostics'] : [] as $diagnostic) {
                if (!is_array($diagnostic)) {
                    continue;
                }

                $diagnostics[] = [
                    'source' => $item['source'],
                    'sourceIndex' => $item['sourceIndex'],
                    'collectionPath' => $item['collectionPath'],
                    'collectionId' => $item['collectionId'],
                    'id' => $item['id'],
                    'policy' => $policy,
                ] + $diagnostic;
            }
        }

        return [
            'present' => $items !== [],
            'itemCount' => count($items),
            'containerLinkCount' => $containerLinkCount,
            'packageLinkCount' => $packageLinkCount,
            'collectionLinkCount' => $collectionLinkCount,
            'localTargetCount' => $policyCounts['local-package'] ?? 0,
            'externalTargetCount' => $policyCounts['remote-no-fetch'] ?? 0,
            'remoteNoFetchCount' => $policyCounts['remote-no-fetch'] ?? 0,
            'missingTargetCount' => $policyCounts['missing-package'] ?? 0,
            'unresolvedTargetCount' => $policyCounts['unresolved'] ?? 0,
            'policyCounts' => $policyCounts,
            'localTargets' => $localTargets,
            'externalTargets' => $externalTargets,
            'missingTargets' => $missingTargets,
            'items' => $items,
            'itemsByPolicy' => $itemsByPolicy,
            'diagnostics' => $diagnostics,
        ];
    }

    /**
     * @param list<array<string, mixed>> $collections
     * @param list<int> $collectionPath
     * @param list<array<string, mixed>> $items
     */
    private static function appendCollectionRemoteResourcePolicyItems(
        array $collections,
        array $collectionPath,
        array &$items
    ): void {
        foreach ($collections as $collectionIndex => $collection) {
            if (!is_array($collection)) {
                continue;
            }

            $currentPath = array_merge($collectionPath, [$collectionIndex]);
            foreach (is_array($collection['links'] ?? null) ? $collection['links'] : [] as $linkIndex => $link) {
                if (!is_array($link)) {
                    continue;
                }

                $items[] = self::remoteResourcePolicyItem(
                    $link,
                    'collection-link',
                    $linkIndex,
                    $collection,
                    $currentPath,
                );
            }

            self::appendCollectionRemoteResourcePolicyItems(
                is_array($collection['children'] ?? null) ? $collection['children'] : [],
                $currentPath,
                $items,
            );
        }
    }

    /**
     * @param array<string, mixed> $link
     * @param array<string, mixed>|null $collection
     * @param list<int> $collectionPath
     *
     * @return array<string, mixed>
     */
    private static function remoteResourcePolicyItem(
        array $link,
        string $source,
        int $sourceIndex,
        ?array $collection,
        array $collectionPath
    ): array {
        $external = ($link['external'] ?? false) === true;
        $exists = ($link['exists'] ?? false) === true;
        $partName = is_string($link['partName'] ?? null) ? $link['partName'] : null;
        $target = is_string($link['target'] ?? null) ? $link['target'] : null;
        $href = is_string($link['href'] ?? null) ? $link['href'] : null;
        $policy = 'unresolved';

        if ($external) {
            $policy = 'remote-no-fetch';
        } elseif ($exists && $partName !== null) {
            $policy = 'local-package';
        } elseif ($target !== null || $partName !== null || $href !== null) {
            $policy = 'missing-package';
        }

        return [
            'source' => $source,
            'sourceIndex' => $sourceIndex,
            'collectionPath' => $source === 'collection-link' ? $collectionPath : null,
            'collectionId' => is_array($collection) && is_string($collection['id'] ?? null)
                ? $collection['id']
                : null,
            'collectionRole' => is_array($collection) && is_string($collection['role'] ?? null)
                ? $collection['role']
                : null,
            'id' => is_string($link['id'] ?? null) ? $link['id'] : null,
            'rel' => is_array($link['rel'] ?? null) ? array_values($link['rel']) : [],
            'href' => $href,
            'target' => $target,
            'partName' => $partName,
            'external' => $external,
            'exists' => $exists,
            'mediaType' => is_string($link['mediaType'] ?? null) ? $link['mediaType'] : null,
            'manifestId' => is_string($link['manifestId'] ?? null) ? $link['manifestId'] : null,
            'manifestMediaType' => is_string($link['manifestMediaType'] ?? null)
                ? $link['manifestMediaType']
                : null,
            'properties' => is_array($link['properties'] ?? null) ? array_values($link['properties']) : [],
            'policy' => $policy,
            'diagnostics' => is_array($link['diagnostics'] ?? null) ? array_values($link['diagnostics']) : [],
        ];
    }

    /**
     * @param array<string, array<string, mixed>> $manifestById
     * @param array<string, mixed> $metadata
     *
     * @return array<string, mixed>
     */
    private static function parseMediaOverlays(array $manifestById, array $metadata, ZipPackage $package): array
    {
        $manifestByPart = self::manifestByPart($manifestById);
        $durationReport = self::mediaOverlayDurationReport($metadata);
        $referencedByOverlay = [];
        foreach ($manifestById as $item) {
            $mediaOverlay = self::emptyToNull((string) ($item['mediaOverlay'] ?? ''));
            if ($mediaOverlay === null) {
                continue;
            }

            $referencedByOverlay[$mediaOverlay][] = [
                'id' => (string) ($item['id'] ?? ''),
                'href' => (string) ($item['href'] ?? ''),
                'partName' => (string) ($item['partName'] ?? ''),
                'mediaType' => (string) ($item['mediaType'] ?? ''),
                'linear' => null,
            ];
        }

        $items = [];
        $itemsById = [];
        $textTargets = [];
        $audioTargets = [];
        $diagnostics = $durationReport['diagnostics'];

        foreach ($referencedByOverlay as $overlayId => $referencedBy) {
            $overlay = $manifestById[$overlayId] ?? null;
            if (!is_array($overlay)) {
                $item = [
                    'id' => $overlayId,
                    'href' => null,
                    'partName' => null,
                    'mediaType' => null,
                    'exists' => false,
                    'byteLength' => null,
                    'crc32' => null,
                    'referencedBy' => $referencedBy,
                    'referencedByIds' => array_values(array_map(
                        static fn (array $reference): string => (string) ($reference['id'] ?? ''),
                        $referencedBy,
                    )),
                    'duration' => $durationReport['overlaysById'][$overlayId]['duration'] ?? null,
                    'durationSeconds' => $durationReport['overlaysById'][$overlayId]['durationSeconds'] ?? null,
                    'items' => [],
                    'itemCount' => 0,
                    'textTargets' => [],
                    'audioTargets' => [],
                    'diagnostics' => [[
                        'type' => 'missing-media-overlay-manifest-item',
                        'id' => $overlayId,
                        'message' => 'EPUB OPF media-overlay attribute references an item id that is not in the manifest',
                    ]],
                ];
                $items[] = $item;
                $itemsById[$overlayId] = $item;
                array_push($diagnostics, ...self::mediaOverlayItemDiagnostics($item));
                continue;
            }

            $item = self::parseMediaOverlayItem(
                $overlay,
                $referencedBy,
                is_array($durationReport['overlaysById'][$overlayId] ?? null) ? $durationReport['overlaysById'][$overlayId] : null,
                $package,
                $manifestByPart,
            );
            $items[] = $item;
            $itemsById[$overlayId] = $item;
            array_push($textTargets, ...$item['textTargets']);
            array_push($audioTargets, ...$item['audioTargets']);
            array_push($diagnostics, ...self::mediaOverlayItemDiagnostics($item));
        }

        return [
            'present' => $items !== [],
            'overlayCount' => count($items),
            'referencedContentItemCount' => array_sum(array_map(
                static fn (array $item): int => count(is_array($item['referencedBy'] ?? null) ? $item['referencedBy'] : []),
                $items,
            )),
            'resolvedOverlayCount' => count(array_filter($items, static fn (array $item): bool => ($item['exists'] ?? false) === true)),
            'missingOverlayCount' => count(array_filter($items, static fn (array $item): bool => ($item['exists'] ?? false) !== true)),
            'durationCount' => count($durationReport['items']),
            'items' => $items,
            'itemsById' => $itemsById,
            'textTargets' => array_values(array_unique($textTargets)),
            'audioTargets' => array_values(array_unique($audioTargets)),
            'durations' => $durationReport,
            'diagnostics' => $diagnostics,
        ];
    }

    /**
     * @param array<string, mixed> $metadata
     *
     * @return array{present:bool, items:list<array<string, mixed>>, overlaysById:array<string, array<string, mixed>>, diagnostics:list<array<string, mixed>>}
     */
    private static function mediaOverlayDurationReport(array $metadata): array
    {
        $entries = is_array($metadata['metaProperties']['media:duration'] ?? null)
            ? array_values($metadata['metaProperties']['media:duration'])
            : [];
        $items = [];
        $overlaysById = [];
        $diagnostics = [];

        foreach ($entries as $index => $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $duration = (string) ($entry['content'] ?? $entry['text'] ?? '');
            $seconds = self::smilClockSeconds($duration);
            $subjectId = is_string($entry['subjectId'] ?? null) ? $entry['subjectId'] : null;
            $itemDiagnostics = [];
            if ($seconds === null) {
                $itemDiagnostics[] = [
                    'type' => 'invalid-media-duration-clock',
                    'duration' => $duration,
                    'message' => 'EPUB media:duration must be a bounded SMIL clock value',
                ];
            }

            $item = [
                'index' => $index,
                'scope' => $subjectId === null ? 'publication' : 'media-overlay',
                'subjectId' => $subjectId,
                'refines' => is_string($entry['refines'] ?? null) ? $entry['refines'] : null,
                'duration' => $duration,
                'durationSeconds' => $seconds,
                'validClock' => $seconds !== null,
                'diagnostics' => $itemDiagnostics,
            ];

            if ($subjectId !== null && !isset($overlaysById[$subjectId])) {
                $overlaysById[$subjectId] = $item;
            }

            foreach ($itemDiagnostics as $diagnostic) {
                $diagnostics[] = ['index' => $index] + $diagnostic;
            }
            $items[] = $item;
        }

        return [
            'present' => $items !== [],
            'items' => $items,
            'overlaysById' => $overlaysById,
            'diagnostics' => $diagnostics,
        ];
    }

    /**
     * @param array<string, mixed> $overlay
     * @param list<array<string, mixed>> $referencedBy
     * @param array<string, mixed>|null $duration
     * @param array<string, array<string, mixed>> $manifestByPart
     *
     * @return array<string, mixed>
     */
    private static function parseMediaOverlayItem(
        array $overlay,
        array $referencedBy,
        ?array $duration,
        ZipPackage $package,
        array $manifestByPart
    ): array {
        $partName = (string) ($overlay['partName'] ?? '');
        $mediaType = (string) ($overlay['mediaType'] ?? '');
        $entry = $partName !== '' && $package->has($partName) ? $package->entry($partName) : null;
        $diagnostics = [];
        $timelineItems = [];

        if ($mediaType !== self::SMIL_MEDIA_TYPE) {
            $diagnostics[] = [
                'type' => 'unexpected-media-overlay-type',
                'id' => (string) ($overlay['id'] ?? ''),
                'mediaType' => $mediaType,
                'message' => 'EPUB media-overlay manifest item should use application/smil+xml',
            ];
        }

        if (!$entry instanceof ZipPackageEntry) {
            $diagnostics[] = [
                'type' => 'missing-media-overlay-part',
                'id' => (string) ($overlay['id'] ?? ''),
                'partName' => $partName,
                'message' => 'EPUB media-overlay SMIL part is missing from the package',
            ];
        } elseif ($mediaType === self::SMIL_MEDIA_TYPE) {
            try {
                $timelineItems = self::parseMediaOverlaySmil($package->read($partName), $partName, $package, $manifestByPart);
            } catch (\Throwable $exception) {
                $diagnostics[] = [
                    'type' => 'media-overlay-scan-failed',
                    'id' => (string) ($overlay['id'] ?? ''),
                    'partName' => $partName,
                    'message' => $exception->getMessage(),
                ];
            }
        }

        $textTargets = [];
        $audioTargets = [];
        foreach ($timelineItems as $item) {
            $textTarget = $item['textTarget'] ?? null;
            if (is_string($textTarget) && $textTarget !== '') {
                $textTargets[] = $textTarget;
            }
            $audioTarget = $item['audioTarget'] ?? null;
            if (is_string($audioTarget) && $audioTarget !== '') {
                $audioTargets[] = $audioTarget;
            }
        }
        $provenance = self::zipEntryProvenance($entry);
        if (($overlay['canExposeBytes'] ?? false) !== true) {
            $provenance['canExposeBytes'] = false;
        }

        return [
            'id' => (string) ($overlay['id'] ?? ''),
            'href' => (string) ($overlay['href'] ?? ''),
            'partName' => $partName,
            'mediaType' => $mediaType,
            'exists' => $entry instanceof ZipPackageEntry,
            'referencedBy' => $referencedBy,
            'referencedByIds' => array_values(array_map(
                static fn (array $reference): string => (string) ($reference['id'] ?? ''),
                $referencedBy,
            )),
            'duration' => is_array($duration) ? $duration['duration'] : null,
            'durationSeconds' => is_array($duration) ? $duration['durationSeconds'] : null,
            'items' => $timelineItems,
            'itemCount' => count($timelineItems),
            'textTargets' => array_values(array_unique($textTargets)),
            'audioTargets' => array_values(array_unique($audioTargets)),
            'diagnostics' => $diagnostics,
        ] + $provenance;
    }

    /**
     * @param array<string, array<string, mixed>> $manifestByPart
     *
     * @return list<array<string, mixed>>
     */
    private static function parseMediaOverlaySmil(
        string $xml,
        string $smilPartName,
        ZipPackage $package,
        array $manifestByPart
    ): array {
        $dom = self::loadXml($xml, 'EPUB media-overlay SMIL document');
        $root = $dom->documentElement;
        if (!$root instanceof \DOMElement || $root->localName !== 'smil' || $root->namespaceURI !== self::SMIL_NAMESPACE) {
            throw new \InvalidArgumentException('EPUB media-overlay SMIL document must use the SMIL namespace');
        }

        $items = [];
        foreach ($root->getElementsByTagNameNS(self::SMIL_NAMESPACE, 'par') as $par) {
            if (!$par instanceof \DOMElement) {
                continue;
            }

            $text = self::firstChildElement($par, 'text', self::SMIL_NAMESPACE);
            $audio = self::firstChildElement($par, 'audio', self::SMIL_NAMESPACE);
            $textSrc = $text instanceof \DOMElement ? self::emptyToNull($text->getAttribute('src')) : null;
            $audioSrc = $audio instanceof \DOMElement ? self::emptyToNull($audio->getAttribute('src')) : null;
            $textReference = self::mediaOverlayReference($textSrc, $smilPartName, $package, $manifestByPart, 'text');
            $audioReference = self::mediaOverlayReference($audioSrc, $smilPartName, $package, $manifestByPart, 'audio');
            $clipBegin = $audio instanceof \DOMElement ? self::emptyToNull($audio->getAttribute('clipBegin')) : null;
            $clipEnd = $audio instanceof \DOMElement ? self::emptyToNull($audio->getAttribute('clipEnd')) : null;
            $clip = self::mediaOverlayClipTiming($clipBegin, $clipEnd);

            $items[] = [
                'index' => count($items),
                'id' => self::emptyToNull($par->getAttribute('id')),
                'textSrc' => $textSrc,
                'textTarget' => $textReference['target'],
                'textPartName' => $textReference['partName'],
                'textExists' => $textReference['exists'],
                'textManifestId' => $textReference['manifestId'],
                'audioSrc' => $audioSrc,
                'audioTarget' => $audioReference['target'],
                'audioPartName' => $audioReference['partName'],
                'audioExists' => $audioReference['exists'],
                'audioExternal' => $audioReference['external'],
                'audioManifestId' => $audioReference['manifestId'],
                'clipBegin' => $clipBegin,
                'clipBeginSeconds' => $clip['clipBeginSeconds'],
                'clipEnd' => $clipEnd,
                'clipEndSeconds' => $clip['clipEndSeconds'],
                'clipDurationSeconds' => $clip['clipDurationSeconds'],
                'clipValid' => $clip['valid'],
                'diagnostics' => array_merge($textReference['diagnostics'], $audioReference['diagnostics'], $clip['diagnostics']),
            ];
        }

        return $items;
    }

    /**
     * @param array<string, array<string, mixed>> $manifestByPart
     *
     * @return array{target:?string, partName:?string, exists:bool, external:bool, manifestId:?string, diagnostics:list<array<string, mixed>>}
     */
    private static function mediaOverlayReference(
        ?string $href,
        string $smilPartName,
        ZipPackage $package,
        array $manifestByPart,
        string $kind
    ): array {
        if ($href === null) {
            return [
                'target' => null,
                'partName' => null,
                'exists' => false,
                'external' => false,
                'manifestId' => null,
                'diagnostics' => [[
                    'type' => 'missing-media-overlay-' . $kind . '-reference',
                    'message' => 'EPUB media-overlay par is missing a ' . $kind . ' source reference',
                ]],
            ];
        }

        try {
            $target = self::resolvePackageHref($smilPartName, $href);
        } catch (\InvalidArgumentException $exception) {
            return [
                'target' => null,
                'partName' => null,
                'exists' => false,
                'external' => false,
                'manifestId' => null,
                'diagnostics' => [[
                    'type' => 'invalid-media-overlay-' . $kind . '-reference',
                    'href' => $href,
                    'message' => $exception->getMessage(),
                ]],
            ];
        }

        if (self::isAbsoluteUri($target)) {
            return [
                'target' => $target,
                'partName' => null,
                'exists' => false,
                'external' => true,
                'manifestId' => null,
                'diagnostics' => [[
                    'type' => 'external-media-overlay-' . $kind . '-reference',
                    'href' => $href,
                    'message' => 'EPUB media-overlay ' . $kind . ' reference points outside the package and was not fetched',
                ]],
            ];
        }

        $partName = OpcPackagePath::stripQueryAndFragment($target);
        $exists = $package->has($partName);
        $manifestItem = $manifestByPart[$partName] ?? null;
        $diagnostics = [];
        if (!$exists) {
            $diagnostics[] = [
                'type' => 'missing-media-overlay-' . $kind . '-reference',
                'href' => $href,
                'partName' => $partName,
                'message' => 'EPUB media-overlay ' . $kind . ' reference targets a missing package part',
            ];
        }

        return [
            'target' => $target,
            'partName' => $partName,
            'exists' => $exists,
            'external' => false,
            'manifestId' => is_array($manifestItem) ? (string) ($manifestItem['id'] ?? '') : null,
            'diagnostics' => $diagnostics,
        ];
    }

    /**
     * @return array{clipBeginSeconds:?float, clipEndSeconds:?float, clipDurationSeconds:?float, valid:bool, diagnostics:list<array<string, mixed>>}
     */
    private static function mediaOverlayClipTiming(?string $clipBegin, ?string $clipEnd): array
    {
        $beginSeconds = $clipBegin === null ? null : self::smilClockSeconds($clipBegin);
        $endSeconds = $clipEnd === null ? null : self::smilClockSeconds($clipEnd);
        $diagnostics = [];

        if ($clipBegin !== null && $beginSeconds === null) {
            $diagnostics[] = [
                'type' => 'invalid-media-overlay-clip-begin',
                'clipBegin' => $clipBegin,
                'message' => 'EPUB media-overlay clipBegin must be a bounded SMIL clock value',
            ];
        }
        if ($clipEnd !== null && $endSeconds === null) {
            $diagnostics[] = [
                'type' => 'invalid-media-overlay-clip-end',
                'clipEnd' => $clipEnd,
                'message' => 'EPUB media-overlay clipEnd must be a bounded SMIL clock value',
            ];
        }
        if ($beginSeconds !== null && $endSeconds !== null && $endSeconds < $beginSeconds) {
            $diagnostics[] = [
                'type' => 'media-overlay-clip-end-before-begin',
                'clipBegin' => $clipBegin,
                'clipEnd' => $clipEnd,
                'clipBeginSeconds' => $beginSeconds,
                'clipEndSeconds' => $endSeconds,
                'message' => 'EPUB media-overlay clipEnd must not be earlier than clipBegin',
            ];
        }

        return [
            'clipBeginSeconds' => $beginSeconds,
            'clipEndSeconds' => $endSeconds,
            'clipDurationSeconds' => $beginSeconds !== null && $endSeconds !== null && $endSeconds >= $beginSeconds
                ? $endSeconds - $beginSeconds
                : null,
            'valid' => $diagnostics === [],
            'diagnostics' => $diagnostics,
        ];
    }

    private static function smilClockSeconds(string $clock): ?float
    {
        $value = trim($clock);
        if ($value === '') {
            return null;
        }

        if (preg_match('/^\d+(?:\.\d+)?ms$/', $value) === 1) {
            return (float) substr($value, 0, -2) / 1000;
        }
        if (preg_match('/^\d+(?:\.\d+)?s$/', $value) === 1) {
            return (float) substr($value, 0, -1);
        }
        if (preg_match('/^\d+(?:\.\d+)?$/', $value) === 1) {
            return (float) $value;
        }
        if (preg_match('/^(\d+):([0-5]?\d):([0-5]?\d(?:\.\d+)?)$/', $value, $match) === 1) {
            return ((int) $match[1] * 3600) + ((int) $match[2] * 60) + (float) $match[3];
        }
        if (preg_match('/^(\d+):([0-5]?\d(?:\.\d+)?)$/', $value, $match) === 1) {
            return ((int) $match[1] * 60) + (float) $match[2];
        }

        return null;
    }

    /**
     * @param array<string, mixed> $mediaOverlays
     *
     * @return list<array<string, mixed>>
     */
    private static function mediaOverlayDiagnostics(array $mediaOverlays): array
    {
        $diagnostics = [];
        foreach (is_array($mediaOverlays['diagnostics'] ?? null) ? $mediaOverlays['diagnostics'] : [] as $diagnostic) {
            if (is_array($diagnostic)) {
                $diagnostics[] = $diagnostic;
            }
        }

        return $diagnostics;
    }

    /**
     * @param array<string, mixed> $item
     *
     * @return list<array<string, mixed>>
     */
    private static function mediaOverlayItemDiagnostics(array $item): array
    {
        $diagnostics = [];
        foreach (is_array($item['diagnostics'] ?? null) ? $item['diagnostics'] : [] as $diagnostic) {
            if (is_array($diagnostic)) {
                $diagnostics[] = [
                    'overlayId' => $item['id'] ?? null,
                ] + $diagnostic;
            }
        }
        foreach (is_array($item['items'] ?? null) ? $item['items'] : [] as $par) {
            foreach (is_array($par['diagnostics'] ?? null) ? $par['diagnostics'] : [] as $diagnostic) {
                if (!is_array($diagnostic)) {
                    continue;
                }

                $diagnostics[] = [
                    'overlayId' => $item['id'] ?? null,
                    'itemIndex' => $par['index'] ?? null,
                    'itemId' => $par['id'] ?? null,
                ] + $diagnostic;
            }
        }

        return $diagnostics;
    }

    /**
     * @param array<string, array<string, mixed>> $manifestById
     *
     * @return array<string, mixed>
     */
    private static function manifestFallbackPreflight(array $manifestById, ZipPackage $package): array
    {
        $items = [];
        $itemsById = [];
        $fallbackItems = [];
        $fallbackStyleItems = [];
        $missingFallbackItems = [];
        $diagnostics = [];
        $fallbackDiagnosticCount = 0;
        $fallbackStyleDiagnosticCount = 0;
        $missingFallbackDiagnosticCount = 0;

        foreach ($manifestById as $item) {
            $fallbackId = self::nullableManifestId($item['fallback'] ?? null);
            $fallbackStyleId = self::nullableManifestId($item['fallbackStyle'] ?? null);
            $requiresFallbackReview = $fallbackId === null
                && $fallbackStyleId === null
                && self::manifestItemRequiresFallbackReview($item);
            if ($fallbackId === null && $fallbackStyleId === null && !$requiresFallbackReview) {
                continue;
            }

            $report = self::manifestFallbackItemPreflight($item, $manifestById, $package);
            $items[] = $report;
            $itemsById[$report['id']] = $report;

            if ($fallbackId !== null) {
                $fallbackItems[] = $report;
                $fallbackDiagnosticCount += count($report['fallbackDiagnostics']);
            }

            if ($fallbackStyleId !== null) {
                $fallbackStyleItems[] = $report;
                $fallbackStyleDiagnosticCount += count($report['fallbackStyleDiagnostics']);
            }

            if ($requiresFallbackReview) {
                $missingFallbackItems[] = $report;
                $missingFallbackDiagnosticCount += count($report['fallbackDiagnostics']);
            }

            foreach ($report['fallbackDiagnostics'] as $diagnostic) {
                $diagnostics[] = [
                    'id' => $report['id'],
                    'kind' => 'fallback',
                ] + $diagnostic;
            }

            foreach ($report['fallbackStyleDiagnostics'] as $diagnostic) {
                $diagnostics[] = [
                    'id' => $report['id'],
                    'kind' => 'fallback-style',
                ] + $diagnostic;
            }
        }

        return [
            'present' => $items !== [],
            'itemCount' => count($items),
            'fallbackCount' => count($fallbackItems),
            'resolvedFallbackCount' => count(array_filter(
                $fallbackItems,
                static fn (array $item): bool => ($item['fallbackResolved'] ?? false) === true,
            )),
            'usableFallbackCount' => count(array_filter(
                $fallbackItems,
                static fn (array $item): bool => ($item['fallbackUsable'] ?? false) === true,
            )),
            'fallbackDiagnosticCount' => $fallbackDiagnosticCount,
            'fallbackStyleCount' => count($fallbackStyleItems),
            'resolvedFallbackStyleCount' => count(array_filter(
                $fallbackStyleItems,
                static fn (array $item): bool => ($item['fallbackStyleResolved'] ?? false) === true,
            )),
            'fallbackStyleDiagnosticCount' => $fallbackStyleDiagnosticCount,
            'missingFallbackCount' => count($missingFallbackItems),
            'missingFallbackDiagnosticCount' => $missingFallbackDiagnosticCount,
            'items' => $items,
            'itemsById' => $itemsById,
            'fallbackItems' => $fallbackItems,
            'fallbackStyleItems' => $fallbackStyleItems,
            'missingFallbackItems' => $missingFallbackItems,
            'diagnostics' => $diagnostics,
        ];
    }

    /**
     * @param array<string, mixed> $item
     * @param array<string, array<string, mixed>> $manifestById
     *
     * @return array<string, mixed>
     */
    private static function manifestFallbackItemPreflight(
        array $item,
        array $manifestById,
        ZipPackage $package
    ): array {
        $fallback = self::manifestFallbackChainReport($item, $manifestById, $package, 'fallback');
        $fallbackStyle = self::manifestFallbackChainReport($item, $manifestById, $package, 'fallbackStyle');
        if (
            $fallback['id'] === null
            && $fallbackStyle['id'] === null
            && self::manifestItemRequiresFallbackReview($item)
        ) {
            $fallback['diagnostics'][] = [
                'type' => 'missing-manifest-fallback-for-non-core-media-type',
                'id' => (string) ($item['id'] ?? ''),
                'partName' => (string) ($item['partName'] ?? ''),
                'mediaType' => (string) ($item['mediaType'] ?? ''),
                'message' => 'EPUB OPF manifest non-core media type resource declares no fallback item for reviewer handoff',
            ];
        }

        return [
            'id' => (string) ($item['id'] ?? ''),
            'href' => (string) ($item['href'] ?? ''),
            'partName' => (string) ($item['partName'] ?? ''),
            'mediaType' => (string) ($item['mediaType'] ?? ''),
            'properties' => is_array($item['properties'] ?? null) ? array_values($item['properties']) : [],
            'fallbackId' => $fallback['id'],
            'fallbackResolved' => $fallback['resolved'],
            'fallbackUsable' => $fallback['usable'],
            'fallbackTerminalId' => $fallback['terminalId'],
            'fallbackTerminalPartName' => $fallback['terminalPartName'],
            'fallbackTerminalMediaType' => $fallback['terminalMediaType'],
            'fallbackTerminalCoreMediaType' => $fallback['terminalCoreMediaType'],
            'fallbackTerminalEpubContentDocument' => $fallback['terminalEpubContentDocument'],
            'fallbackChain' => $fallback['chain'],
            'fallbackDiagnostics' => $fallback['diagnostics'],
            'fallbackStyleId' => $fallbackStyle['id'],
            'fallbackStyleResolved' => $fallbackStyle['resolved'],
            'fallbackStyleUsable' => $fallbackStyle['usable'],
            'fallbackStyleTerminalId' => $fallbackStyle['terminalId'],
            'fallbackStyleTerminalPartName' => $fallbackStyle['terminalPartName'],
            'fallbackStyleTerminalMediaType' => $fallbackStyle['terminalMediaType'],
            'fallbackStyleTerminalCssStyle' => $fallbackStyle['terminalCssStyle'],
            'fallbackStyleChain' => $fallbackStyle['chain'],
            'fallbackStyleDiagnostics' => $fallbackStyle['diagnostics'],
        ];
    }

    /**
     * @param array<string, mixed> $item
     */
    private static function manifestItemRequiresFallbackReview(array $item): bool
    {
        $mediaType = (string) ($item['mediaType'] ?? '');
        if (trim($mediaType) === '') {
            return false;
        }

        return self::coreMediaTypeKind($mediaType) === null;
    }

    /**
     * @param array<string, mixed> $item
     * @param array<string, array<string, mixed>> $manifestById
     *
     * @return array{id:?string, resolved:bool, usable:bool, chain:list<array<string, mixed>>, terminalId:?string, terminalPartName:?string, terminalMediaType:?string, terminalCoreMediaType:?bool, terminalEpubContentDocument:?bool, terminalCssStyle:?bool, diagnostics:list<array<string, mixed>>}
     */
    private static function manifestFallbackChainReport(
        array $item,
        array $manifestById,
        ZipPackage $package,
        string $attribute
    ): array {
        $fallbackId = self::nullableManifestId($item[$attribute] ?? null);
        if ($fallbackId === null) {
            return [
                'id' => null,
                'resolved' => false,
                'usable' => false,
                'chain' => [],
                'terminalId' => null,
                'terminalPartName' => null,
                'terminalMediaType' => null,
                'terminalCoreMediaType' => null,
                'terminalEpubContentDocument' => null,
                'terminalCssStyle' => null,
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
        $isStyle = $attribute === 'fallbackStyle';
        $diagnosticNames = $isStyle
            ? [
                'cyclic' => 'cyclic-manifest-fallback-style-chain',
                'missing' => 'missing-manifest-fallback-style-item',
                'unsupported' => 'non-css-manifest-fallback-style',
                'key' => 'fallbackStyle',
                'cycleMessage' => 'EPUB OPF manifest fallback-style chain cycles before reaching a CSS resource',
                'missingMessage' => 'EPUB OPF manifest fallback-style references an item id that is not in the OPF manifest',
                'unsupportedMessage' => 'EPUB OPF manifest fallback-style chain terminates at a non-CSS resource',
            ]
            : [
                'cyclic' => 'cyclic-manifest-fallback-chain',
                'missing' => 'missing-manifest-fallback-item',
                'unsupported' => 'unsupported-manifest-fallback-terminal',
                'key' => 'fallback',
                'cycleMessage' => 'EPUB OPF manifest fallback chain cycles before reaching a core media type',
                'missingMessage' => 'EPUB OPF manifest fallback references an item id that is not in the OPF manifest',
                'unsupportedMessage' => 'EPUB OPF manifest fallback chain terminates at another non-core media type',
            ];

        while ($next !== null) {
            if (isset($visited[$next])) {
                $diagnostics[] = [
                    'type' => $diagnosticNames['cyclic'],
                    'id' => (string) ($current['id'] ?? ''),
                    $diagnosticNames['key'] => $next,
                    'chainIds' => array_map(static fn (array $chainItem): string => (string) $chainItem['id'], $chain),
                    'message' => $diagnosticNames['cycleMessage'],
                ];
                break;
            }

            $fallbackItem = $manifestById[$next] ?? null;
            if (!is_array($fallbackItem)) {
                $diagnostics[] = [
                    'type' => $diagnosticNames['missing'],
                    'id' => (string) ($current['id'] ?? ''),
                    $diagnosticNames['key'] => $next,
                    'message' => $diagnosticNames['missingMessage'],
                ];
                break;
            }

            $visited[$next] = true;
            $current = $fallbackItem;
            $chainItem = self::manifestFallbackChainItem($fallbackItem, $package);
            $chain[] = $chainItem;

            if ($isStyle && ($chainItem['cssStyle'] ?? false) === true) {
                break;
            }

            $next = self::nullableManifestId($fallbackItem[$attribute] ?? null);
        }

        $terminal = $chain === [] ? null : $chain[count($chain) - 1];
        if ($diagnostics === [] && is_array($terminal)) {
            if ($isStyle && ($terminal['cssStyle'] ?? false) !== true) {
                $diagnostics[] = [
                    'type' => $diagnosticNames['unsupported'],
                    'id' => (string) ($item['id'] ?? ''),
                    'fallbackStyle' => $fallbackId,
                    'terminalId' => (string) ($terminal['id'] ?? ''),
                    'terminalMediaType' => (string) ($terminal['mediaType'] ?? ''),
                    'message' => $diagnosticNames['unsupportedMessage'],
                ];
            } elseif (!$isStyle && ($terminal['coreMediaType'] ?? false) !== true) {
                $diagnostics[] = [
                    'type' => $diagnosticNames['unsupported'],
                    'id' => (string) ($item['id'] ?? ''),
                    'fallback' => $fallbackId,
                    'terminalId' => (string) ($terminal['id'] ?? ''),
                    'terminalMediaType' => (string) ($terminal['mediaType'] ?? ''),
                    'message' => $diagnosticNames['unsupportedMessage'],
                ];
            }
        }

        return [
            'id' => $fallbackId,
            'resolved' => $diagnostics === [] && $chain !== [],
            'usable' => $diagnostics === [] && $chain !== [],
            'chain' => $chain,
            'terminalId' => is_array($terminal) ? (string) $terminal['id'] : null,
            'terminalPartName' => is_array($terminal) ? (string) $terminal['partName'] : null,
            'terminalMediaType' => is_array($terminal) ? (string) $terminal['mediaType'] : null,
            'terminalCoreMediaType' => is_array($terminal) ? (bool) $terminal['coreMediaType'] : null,
            'terminalEpubContentDocument' => is_array($terminal) ? (bool) $terminal['epubContentDocument'] : null,
            'terminalCssStyle' => is_array($terminal) ? (bool) $terminal['cssStyle'] : null,
            'diagnostics' => $diagnostics,
        ];
    }

    /**
     * @param array<string, mixed> $item
     *
     * @return array<string, mixed>
     */
    private static function manifestFallbackChainItem(array $item, ZipPackage $package): array
    {
        $mediaType = (string) ($item['mediaType'] ?? '');
        $partName = (string) ($item['partName'] ?? '');
        $entry = $partName !== '' && $package->has($partName) ? $package->entry($partName) : null;
        $baseMediaType = self::mediaTypeBase($mediaType);
        $coreKind = self::coreMediaTypeKind($mediaType);
        $provenance = self::zipEntryProvenance($entry);
        if (($item['canExposeBytes'] ?? false) !== true) {
            $provenance['canExposeBytes'] = false;
        }

        return [
            'id' => (string) ($item['id'] ?? ''),
            'href' => (string) ($item['href'] ?? ''),
            'partName' => $partName,
            'mediaType' => $mediaType,
            'baseMediaType' => $baseMediaType,
            'properties' => is_array($item['properties'] ?? null) ? array_values($item['properties']) : [],
            'exists' => $entry instanceof ZipPackageEntry,
            'coreMediaType' => $coreKind !== null,
            'coreMediaTypeKind' => $coreKind,
            'epubContentDocument' => in_array($baseMediaType, [self::XHTML_MEDIA_TYPE, 'image/svg+xml'], true),
            'cssStyle' => $baseMediaType === 'text/css',
            'fallbackId' => self::nullableManifestId($item['fallback'] ?? null),
            'fallbackStyleId' => self::nullableManifestId($item['fallbackStyle'] ?? null),
        ] + $provenance;
    }

    /**
     * @param array<string, array<string, mixed>> $manifestById
     *
     * @return array<string, mixed>
     */
    private static function parseEncryption(ZipPackage $package, array $manifestById): array
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

        $dom = self::loadXml($package->read($encryptionPart), 'EPUB OCF encryption.xml');
        $root = $dom->documentElement;
        if (!$root instanceof \DOMElement || $root->localName !== 'encryption' || $root->namespaceURI !== self::OCF_CONTAINER_NAMESPACE) {
            throw new \InvalidArgumentException('EPUB encryption.xml must use the OCF container namespace');
        }

        $manifestByPart = self::manifestByPart($manifestById);
        $items = [];
        $diagnostics = [];

        foreach (self::encryptedDataElements($dom) as $index => $encryptedData) {
            $method = self::firstChildElement($encryptedData, 'EncryptionMethod', self::XMLENC_NAMESPACE);
            $cipherData = self::firstChildElement($encryptedData, 'CipherData', self::XMLENC_NAMESPACE);
            $cipherReference = $cipherData instanceof \DOMElement
                ? self::firstChildElement($cipherData, 'CipherReference', self::XMLENC_NAMESPACE)
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
                $partName = self::encryptionCipherPart($uri);
            } catch (\InvalidArgumentException $exception) {
                $diagnostics[] = [
                    'type' => 'invalid-cipher-reference',
                    'index' => $index,
                    'uri' => $uri,
                    'message' => $exception->getMessage(),
                ];
                continue;
            }

            $manifestItem = $manifestByPart[$partName] ?? null;
            $mediaType = is_array($manifestItem) ? (string) ($manifestItem['mediaType'] ?? '') : null;
            $properties = is_array($manifestItem) && is_array($manifestItem['properties'] ?? null)
                ? array_values($manifestItem['properties'])
                : [];
            $algorithm = $method instanceof \DOMElement ? self::emptyToNull($method->getAttribute('Algorithm')) : null;
            $obfuscatedFont = self::isObfuscatedFont($algorithm, $mediaType, $partName);
            $isCoverImage = in_array('cover-image', $properties, true);
            $item = [
                'index' => $index,
                'uri' => $uri,
                'partName' => $partName,
                'algorithm' => $algorithm,
                'manifestId' => is_array($manifestItem) ? (string) ($manifestItem['id'] ?? '') : null,
                'mediaType' => $mediaType,
                'role' => self::encryptedResourceRole($mediaType, $partName, $properties),
                'exists' => $package->has($partName),
                'obfuscatedFont' => $obfuscatedFont,
                'canExposeBytes' => false,
                'reviewPolicy' => $obfuscatedFont ? 'obfuscated-font-review' : 'encrypted-resource-review',
                'byteExposurePolicy' => $obfuscatedFont ? 'obfuscated-font-bytes-blocked' : 'encrypted-resource-bytes-blocked',
                'attachmentCandidateBlocked' => self::isAttachmentCandidate($mediaType, $partName, $isCoverImage),
            ];

            if (!is_array($manifestItem)) {
                $diagnostics[] = [
                    'type' => 'encrypted-resource-not-in-manifest',
                    'index' => $index,
                    'partName' => $partName,
                    'message' => 'Encrypted OCF resource is not listed in the OPF manifest',
                ];
            }

            if ($item['exists'] !== true) {
                $diagnostics[] = [
                    'type' => 'encrypted-resource-missing',
                    'index' => $index,
                    'partName' => $partName,
                    'message' => 'Encrypted OCF resource is missing from the ZIP package',
                ];
            }

            $items[] = $item;
        }

        return [
            'present' => true,
            'part' => $encryptionPart,
            'items' => $items,
            'encryptedParts' => array_map(static fn (array $item): string => (string) $item['partName'], $items),
            'obfuscatedFonts' => array_values(array_filter(
                $items,
                static fn (array $item): bool => ($item['obfuscatedFont'] ?? false) === true,
            )),
            'exposure' => self::encryptionExposureReport($items),
            'diagnostics' => $diagnostics,
        ];
    }

    /**
     * @return list<\DOMElement>
     */
    private static function encryptedDataElements(\DOMDocument $dom): array
    {
        $elements = [];
        foreach ($dom->getElementsByTagNameNS(self::XMLENC_NAMESPACE, 'EncryptedData') as $element) {
            if ($element instanceof \DOMElement) {
                $elements[] = $element;
            }
        }

        return $elements;
    }

    private static function encryptionCipherPart(string $uri): string
    {
        if (self::isAbsoluteUri($uri) || str_starts_with($uri, '//')) {
            throw new \InvalidArgumentException('EPUB encryption CipherReference URI must be package-relative');
        }

        if (str_contains($uri, '?') || str_contains($uri, '#')) {
            throw new \InvalidArgumentException('EPUB encryption CipherReference URI must identify a package part without query or fragment');
        }

        return OpcPackagePath::canonicalPartName($uri);
    }

    /**
     * @param array<string, array<string, mixed>> $manifestById
     * @param array<string, mixed> $encryption
     *
     * @return array<string, array<string, mixed>>
     */
    private static function attachEncryptionToManifest(array $manifestById, array $encryption): array
    {
        $encryptionByPart = self::encryptionItemsByPart($encryption);

        foreach ($manifestById as $id => $item) {
            $manifestById[$id] = self::attachEncryptionToManifestItem($item, $encryptionByPart);
        }

        return $manifestById;
    }

    /**
     * @param list<array<string, mixed>> $manifestItems
     * @param array<string, mixed> $encryption
     *
     * @return list<array<string, mixed>>
     */
    private static function attachEncryptionToManifestItems(array $manifestItems, array $encryption): array
    {
        $encryptionByPart = self::encryptionItemsByPart($encryption);

        foreach ($manifestItems as $index => $item) {
            $manifestItems[$index] = self::attachEncryptionToManifestItem($item, $encryptionByPart);
        }

        return $manifestItems;
    }

    /**
     * @param array<string, mixed> $encryption
     *
     * @return array<string, list<array<string, mixed>>>
     */
    private static function encryptionItemsByPart(array $encryption): array
    {
        $encryptionByPart = [];
        foreach (is_array($encryption['items'] ?? null) ? $encryption['items'] : [] as $item) {
            if (!is_array($item)) {
                continue;
            }

            $partName = $item['partName'] ?? null;
            if (!is_string($partName) || $partName === '') {
                continue;
            }

            $encryptionByPart[$partName][] = $item;
        }

        return $encryptionByPart;
    }

    /**
     * @param array<string, mixed> $item
     * @param array<string, list<array<string, mixed>>> $encryptionByPart
     *
     * @return array<string, mixed>
     */
    private static function attachEncryptionToManifestItem(array $item, array $encryptionByPart): array
    {
        $entries = $encryptionByPart[(string) ($item['partName'] ?? '')] ?? [];
        if ($entries === []) {
            return $item;
        }

        $obfuscatedFont = self::containsObfuscatedFont($entries);
        $item['encrypted'] = true;
        $item['canExposeBytes'] = false;
        $item['encryption'] = [
            'items' => $entries,
            'algorithm' => $entries[0]['algorithm'] ?? null,
            'role' => $entries[0]['role'] ?? self::encryptedResourceRole(
                is_string($item['mediaType'] ?? null) ? $item['mediaType'] : null,
                (string) ($item['partName'] ?? ''),
                is_array($item['properties'] ?? null) ? array_values($item['properties']) : [],
            ),
            'obfuscatedFont' => $obfuscatedFont,
            'canExposeBytes' => false,
            'reviewPolicy' => $obfuscatedFont ? 'obfuscated-font-review' : 'encrypted-resource-review',
            'byteExposurePolicy' => $obfuscatedFont ? 'obfuscated-font-bytes-blocked' : 'encrypted-resource-bytes-blocked',
            'attachmentCandidateBlocked' => count(array_filter(
                $entries,
                static fn (array $entry): bool => ($entry['attachmentCandidateBlocked'] ?? false) === true,
            )) > 0,
        ];

        return $item;
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
            $partName = is_string($item['partName'] ?? null) ? $item['partName'] : null;
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
            if ($partName !== null && $partName !== '') {
                if ($obfuscatedFont) {
                    $obfuscatedFontParts[] = $partName;
                } else {
                    $nonObfuscatedEncryptedParts[] = $partName;
                }
            }

            $reportItems[] = [
                'index' => (int) ($item['index'] ?? 0),
                'uri' => is_string($item['uri'] ?? null) ? $item['uri'] : null,
                'partName' => $partName,
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
     * @param list<string> $properties
     */
    private static function encryptedResourceRole(?string $mediaType, string $partName, array $properties = []): string
    {
        if (in_array('cover-image', $properties, true)) {
            return 'cover-image';
        }

        $baseMediaType = $mediaType === null || trim($mediaType) === '' ? '' : self::mediaTypeBase($mediaType);
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
        if (self::isFontResource($baseMediaType, $partName)) {
            return 'font';
        }

        return 'asset';
    }

    private static function isObfuscatedFont(?string $algorithm, ?string $mediaType, string $partName): bool
    {
        if ($algorithm !== self::IDPF_FONT_OBFUSCATION_ALGORITHM) {
            return false;
        }

        return self::isFontResource($mediaType, $partName);
    }

    private static function isFontResource(?string $mediaType, string $partName): bool
    {
        $baseMediaType = $mediaType === null ? '' : self::mediaTypeBase($mediaType);
        if (in_array($baseMediaType, [
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

        return in_array(strtolower(pathinfo($partName, PATHINFO_EXTENSION)), ['otf', 'ttf', 'woff', 'woff2'], true);
    }

    private static function isAttachmentCandidate(?string $mediaType, string $partName, bool $isCoverImage): bool
    {
        if ($isCoverImage) {
            return true;
        }

        $baseMediaType = $mediaType === null ? '' : self::mediaTypeBase($mediaType);

        return str_starts_with($baseMediaType, 'image/')
            || str_starts_with($baseMediaType, 'audio/')
            || str_starts_with($baseMediaType, 'video/')
            || self::isFontResource($baseMediaType, $partName);
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

    private static function nullableManifestId(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        return self::emptyToNull($value);
    }

    private static function mediaTypeBase(string $mediaType): string
    {
        return strtolower(trim(explode(';', $mediaType, 2)[0]));
    }

    /**
     * @return array{hasQuery:bool, query:?string, hasFragment:bool, fragment:?string}
     */
    private static function packageHrefSuffixReport(string $target): array
    {
        $fragmentOffset = strpos($target, '#');
        $withoutFragment = $fragmentOffset === false ? $target : substr($target, 0, $fragmentOffset);
        $fragment = $fragmentOffset === false ? null : substr($target, $fragmentOffset + 1);
        $queryOffset = strpos($withoutFragment, '?');
        $query = $queryOffset === false ? null : substr($withoutFragment, $queryOffset + 1);

        return [
            'hasQuery' => $queryOffset !== false,
            'query' => $query,
            'hasFragment' => $fragmentOffset !== false,
            'fragment' => $fragment,
        ];
    }

    private static function coreMediaTypeKind(string $mediaType): ?string
    {
        return self::CORE_MEDIA_TYPE_KINDS[self::mediaTypeBase($mediaType)] ?? null;
    }

    /**
     * @param list<array<string, mixed>> $manifest
     * @param array<string, string> $prefixBindings
     *
     * @return array<string, mixed>
     */
    private static function resourcePropertyReport(array $manifest, array $prefixBindings): array
    {
        $propertyVocabulary = self::manifestPropertyVocabularySummary($manifest, $prefixBindings);
        $items = [];
        $itemsById = [];
        $itemsByProperty = [];
        foreach (array_keys(self::CORE_RESOURCE_PROPERTIES) as $property) {
            $itemsByProperty[$property] = [];
        }
        $reviewItems = [];

        foreach ($manifest as $item) {
            $properties = array_values(array_filter(
                is_array($item['properties'] ?? null) ? $item['properties'] : [],
                static fn (mixed $property): bool => is_string($property) && $property !== '',
            ));
            $recognized = array_values(array_unique(array_filter(
                $properties,
                static fn (string $property): bool => array_key_exists($property, self::CORE_RESOURCE_PROPERTIES),
            )));
            if ($recognized === []) {
                continue;
            }

            $flags = self::resourcePropertyFlags($properties);
            $reviewFlags = self::resourceReviewFlags($flags);
            $reportItem = [
                'id' => (string) ($item['id'] ?? ''),
                'href' => (string) ($item['href'] ?? ''),
                'target' => is_string($item['partName'] ?? null) ? $item['partName'] : null,
                'partName' => is_string($item['partName'] ?? null) ? $item['partName'] : null,
                'mediaType' => (string) ($item['mediaType'] ?? ''),
                'exists' => true,
                'properties' => $recognized,
                'allProperties' => $properties,
                'propertyVocabulary' => self::manifestItemPropertyVocabularyReport(
                    $properties,
                    $prefixBindings,
                    (string) ($item['id'] ?? ''),
                ),
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
        ];
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
     * @param list<array<string, mixed>> $manifest
     * @param array<string, string> $prefixBindings
     *
     * @return array<string, mixed>
     */
    private static function manifestPropertyVocabularySummary(array $manifest, array $prefixBindings): array
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
            $properties = array_values(array_filter(
                is_array($item['properties'] ?? null) ? $item['properties'] : [],
                static fn (mixed $property): bool => is_string($property) && $property !== '',
            ));
            if ($properties === []) {
                continue;
            }

            $report = self::manifestItemPropertyVocabularyReport(
                $properties,
                $prefixBindings,
                (string) ($item['id'] ?? ''),
            );
            $manifestId = (string) ($item['id'] ?? '');
            $summaryItem = [
                'id' => $manifestId,
                'href' => (string) ($item['href'] ?? ''),
                'target' => is_string($item['partName'] ?? null) ? $item['partName'] : null,
                'partName' => is_string($item['partName'] ?? null) ? $item['partName'] : null,
                'mediaType' => (string) ($item['mediaType'] ?? ''),
                'properties' => $properties,
                'propertyVocabulary' => $report,
            ];
            $items[] = $summaryItem;
            if ($manifestId !== '') {
                $itemsById[$manifestId] = $summaryItem;
            }

            foreach ($report['items'] as $propertyItem) {
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
                }

                foreach ($vocabulary['diagnostics'] ?? [] as $diagnostic) {
                    if (!is_array($diagnostic)) {
                        continue;
                    }

                    $diagnostics[] = [
                        'manifestId' => $manifestId,
                        'href' => (string) ($item['href'] ?? ''),
                        'index' => (int) ($propertyItem['index'] ?? 0),
                        'property' => (string) ($propertyItem['property'] ?? ''),
                    ] + $diagnostic;
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
     * @param list<string> $properties
     * @param array<string, string> $prefixBindings
     *
     * @return array<string, mixed>
     */
    private static function manifestItemPropertyVocabularyReport(
        array $properties,
        array $prefixBindings,
        string $manifestId
    ): array {
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
     * @param array<string, string> $prefixBindings
     *
     * @return array<string, mixed>|null
     */
    private static function manifestPropertyVocabulary(?string $property, array $prefixBindings): ?array
    {
        if ($property === null) {
            return null;
        }

        $raw = trim($property);
        if ($raw === '') {
            return null;
        }

        $prefixBindings = self::metadataVocabularyPrefixBindings($prefixBindings);
        $diagnostics = [];

        if (preg_match('/^([A-Za-z_][A-Za-z0-9_.-]*):([A-Za-z_][A-Za-z0-9_.-]*)$/', $raw, $matches) === 1) {
            $prefix = $matches[1];
            $localName = $matches[2];
            $bindingIri = isset($prefixBindings[$prefix]) ? (string) $prefixBindings[$prefix] : null;
            if ($bindingIri === null || $bindingIri === '') {
                $diagnostics[] = [
                    'type' => 'unknown-manifest-property-prefix',
                    'prefix' => $prefix,
                    'property' => $raw,
                    'message' => 'EPUB OPF manifest item property uses a prefix that is not declared on the package element',
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
                'valid' => $diagnostics === [],
                'diagnostics' => $diagnostics,
            ];
        }

        if (preg_match('/^[A-Za-z_][A-Za-z0-9_.-]*$/', $raw) !== 1) {
            return [
                'raw' => $raw,
                'prefixed' => false,
                'prefix' => null,
                'name' => $raw,
                'localName' => $raw,
                'bindingIri' => null,
                'iri' => null,
                'resolved' => false,
                'valid' => false,
                'diagnostics' => [[
                    'type' => 'invalid-manifest-property-token',
                    'property' => $raw,
                    'message' => 'EPUB OPF manifest item property must be an unprefixed token or a prefixed name',
                ]],
            ];
        }

        return [
            'raw' => $raw,
            'prefixed' => false,
            'prefix' => null,
            'name' => $raw,
            'localName' => $raw,
            'bindingIri' => null,
            'iri' => null,
            'resolved' => false,
            'valid' => true,
            'diagnostics' => [],
        ];
    }

    /**
     * @param array<string, array{id:string, href:string, partName:string, mediaType:string, properties:list<string>, fallback:?string}> $manifestById
     *
     * @return array<string, mixed>
     */
    private static function parseBindings(?\DOMElement $bindingsElement, array $manifestById, ZipPackage $package): array
    {
        if (!$bindingsElement instanceof \DOMElement) {
            return [
                'present' => false,
                'itemCount' => 0,
                'boundMediaTypes' => [],
                'items' => [],
                'diagnostics' => [],
            ];
        }

        $items = [];
        $diagnostics = [];
        $boundMediaTypes = [];

        foreach (self::childElements($bindingsElement, 'mediaType', self::OPF_NAMESPACE) as $index => $mediaTypeElement) {
            $mediaType = trim($mediaTypeElement->getAttribute('media-type'));
            $handlerId = trim($mediaTypeElement->getAttribute('handler'));
            $handler = $handlerId === '' ? null : ($manifestById[$handlerId] ?? null);
            $itemDiagnostics = [];

            if ($mediaType === '') {
                $itemDiagnostics[] = [
                    'type' => 'missing-binding-media-type',
                    'message' => 'EPUB OPF binding mediaType entry is missing media-type',
                ];
            } else {
                $boundMediaTypes[] = $mediaType;
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
            } elseif (($handler['encrypted'] ?? false) === true) {
                $handlerEncryption = is_array($handler['encryption'] ?? null) ? $handler['encryption'] : [];
                $itemDiagnostics[] = [
                    'type' => 'encrypted-binding-handler',
                    'mediaType' => $mediaType === '' ? null : $mediaType,
                    'handlerId' => $handlerId,
                    'handlerPartName' => (string) ($handler['partName'] ?? ''),
                    'handlerMediaType' => (string) ($handler['mediaType'] ?? ''),
                    'reviewPolicy' => is_string($handlerEncryption['reviewPolicy'] ?? null) ? $handlerEncryption['reviewPolicy'] : null,
                    'byteExposurePolicy' => is_string($handlerEncryption['byteExposurePolicy'] ?? null) ? $handlerEncryption['byteExposurePolicy'] : null,
                    'message' => 'EPUB OPF binding handler is encrypted and cannot expose review bytes',
                ];
            }

            foreach ($itemDiagnostics as $diagnostic) {
                $diagnostics[] = ['index' => $index] + $diagnostic;
            }

            $handlerPartName = is_array($handler) ? (string) $handler['partName'] : null;
            $entry = $handlerPartName !== null && $package->has($handlerPartName)
                ? $package->entry($handlerPartName)
                : null;
            $handlerEncrypted = is_array($handler) && ($handler['encrypted'] ?? false) === true;
            $handlerProvenance = self::zipEntryProvenance($entry);
            if ($handlerEncrypted) {
                $handlerProvenance['canExposeBytes'] = false;
            }

            $items[] = [
                'index' => $index,
                'mediaType' => $mediaType === '' ? null : $mediaType,
                'handlerId' => $handlerId === '' ? null : $handlerId,
                'handlerHref' => is_array($handler) ? (string) $handler['href'] : null,
                'handlerPartName' => $handlerPartName,
                'handlerMediaType' => is_array($handler) ? (string) $handler['mediaType'] : null,
                'handlerProperties' => is_array($handler) ? $handler['properties'] : [],
                'handlerExists' => $entry instanceof ZipPackageEntry,
                'handlerEncrypted' => $handlerEncrypted,
                'handlerCanExposeBytes' => $handlerProvenance['canExposeBytes'],
                'handlerEncryption' => is_array($handler) && is_array($handler['encryption'] ?? null) ? $handler['encryption'] : null,
                'handlerByteLength' => $handlerProvenance['byteLength'],
                'handlerCompressedByteLength' => $handlerProvenance['compressedByteLength'],
                'handlerCompressionMethod' => $handlerProvenance['compressionMethod'],
                'handlerCompressionMethodName' => $handlerProvenance['compressionMethodName'],
                'handlerCompressionSupported' => $handlerProvenance['compressionSupported'],
                'handlerCrc32' => $handlerProvenance['crc32'],
                'diagnostics' => $itemDiagnostics,
            ];
        }

        return [
            'present' => true,
            'itemCount' => count($items),
            'boundMediaTypes' => array_values(array_unique($boundMediaTypes)),
            'items' => $items,
            'diagnostics' => $diagnostics,
        ];
    }

    /**
     * @return list<array{label:string, href:?string, target:?string, depth:int, playOrder:?int}>
     */
    private static function parseNcxDocument(string $xml, string $ncxPartName): array
    {
        $dom = self::loadXml($xml, 'EPUB NCX document');
        $root = $dom->documentElement;
        if (!$root instanceof \DOMElement || $root->localName !== 'ncx') {
            throw new \InvalidArgumentException('EPUB NCX document must use an ncx root element');
        }

        $navMap = self::firstDescendantElement($root, 'navMap');
        if (!$navMap instanceof \DOMElement) {
            return [];
        }

        return self::parseNcxNavPoints($navMap, $ncxPartName, 1);
    }

    /**
     * @return list<string>
     */
    private static function epubTypes(\DOMElement $element): array
    {
        $type = $element->getAttributeNS(self::EPUB_OPS_NAMESPACE, 'type')
            ?: $element->getAttribute('epub:type')
            ?: $element->getAttribute('type');

        return self::splitTokens($type);
    }

    private static function navSectionLabel(\DOMElement $nav): ?string
    {
        foreach (self::childElements($nav) as $child) {
            if (in_array($child->localName, ['h1', 'h2', 'h3', 'h4', 'h5', 'h6'], true)) {
                $label = self::normalizeText($child->textContent);

                return $label === '' ? null : $label;
            }
        }

        return null;
    }

    private static function elementHidden(\DOMElement $element): bool
    {
        if ($element->hasAttribute('hidden')) {
            return true;
        }

        return strtolower(trim($element->getAttribute('aria-hidden'))) === 'true';
    }

    /**
     * @param list<array{type:?string, types:list<string>, label:?string, partName:string, entries:list<array{label:string, href:?string, target:?string, depth:int, playOrder:?int}>}> $sections
     *
     * @return list<array{label:string, href:?string, target:?string, depth:int, playOrder:?int}>
     */
    private static function navigationEntriesForSectionType(array $sections, string $type): array
    {
        $entries = [];
        foreach ($sections as $section) {
            if (!in_array($type, $section['types'], true)) {
                continue;
            }

            array_push($entries, ...$section['entries']);
        }

        return $entries;
    }

    /**
     * @param list<array<string, mixed>> $sections
     *
     * @return array<string, mixed>
     */
    private static function auxiliaryNavigationReport(array $sections): array
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

            $entries = is_array($section['entries'] ?? null) ? array_values($section['entries']) : [];
            $summary = [
                'sectionIndex' => $sectionIndex,
                'id' => is_string($section['id'] ?? null) ? $section['id'] : null,
                'type' => $auxiliaryTypes[0],
                'types' => $sectionTypes,
                'auxiliaryTypes' => $auxiliaryTypes,
                'title' => is_string($section['title'] ?? null)
                    ? $section['title']
                    : (is_string($section['label'] ?? null) ? $section['label'] : null),
                'hidden' => (bool) ($section['hidden'] ?? false),
                'partName' => is_string($section['partName'] ?? null) ? $section['partName'] : null,
                'itemCount' => count($entries),
                'entries' => $entries,
            ];

            $reportedSections[] = $summary;
            foreach ($auxiliaryTypes as $type) {
                $types[$type] = true;
                $sectionsByType[$type][] = $summary;
            }

            foreach ($entries as $entryIndex => $entry) {
                if (!is_array($entry)) {
                    continue;
                }

                $items[] = [
                    'sectionIndex' => $sectionIndex,
                    'sectionId' => $summary['id'],
                    'sectionType' => $summary['type'],
                    'sectionTypes' => $auxiliaryTypes,
                    'sectionTitle' => $summary['title'],
                    'entryIndex' => $entryIndex,
                    'depth' => is_int($entry['depth'] ?? null) ? $entry['depth'] : 0,
                ] + $entry;
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
     * @return list<array{label:string, href:?string, target:?string, depth:int, playOrder:?int}>
     */
    private static function parseNcxNavPoints(\DOMElement $parent, string $ncxPartName, int $depth): array
    {
        $entries = [];
        foreach (self::childElements($parent, 'navPoint') as $navPoint) {
            $labelContainer = self::firstChildElement($navPoint, 'navLabel');
            $labelElement = $labelContainer instanceof \DOMElement
                ? self::firstDescendantElement($labelContainer, 'text')
                : null;
            $contentElement = self::firstChildElement($navPoint, 'content');
            $href = $contentElement instanceof \DOMElement && $contentElement->hasAttribute('src')
                ? $contentElement->getAttribute('src')
                : null;
            $playOrder = $navPoint->hasAttribute('playOrder') && ctype_digit($navPoint->getAttribute('playOrder'))
                ? (int) $navPoint->getAttribute('playOrder')
                : null;

            $entries[] = [
                'label' => $labelElement instanceof \DOMElement ? self::normalizeText($labelElement->textContent) : '',
                'href' => $href,
                'target' => $href === null || $href === '' ? null : self::resolveReadingHref($ncxPartName, $href),
                'depth' => $depth,
                'playOrder' => $playOrder,
            ];

            array_push($entries, ...self::parseNcxNavPoints($navPoint, $ncxPartName, $depth + 1));
        }

        return $entries;
    }

    private static function resolvePackageHref(string $sourcePartName, string $href): string
    {
        if ($href === '') {
            throw new \InvalidArgumentException('EPUB package href must not be empty');
        }

        if (str_contains($href, "\0") || str_contains($href, '\\')) {
            throw new \InvalidArgumentException('EPUB package href must use slash-separated paths');
        }

        if (self::isAbsoluteUri($href)) {
            return $href;
        }

        if (str_starts_with($href, '#')) {
            return OpcPackagePath::canonicalPartName($sourcePartName) . $href;
        }

        return OpcPackagePath::resolveInternalTarget($sourcePartName, $href);
    }

    private static function resolveReadingHref(string $sourcePartName, string $href): string
    {
        return self::resolvePackageHref($sourcePartName, $href);
    }

    private static function isAbsoluteUri(string $value): bool
    {
        return preg_match('/^[A-Za-z][A-Za-z0-9+.-]*:/', $value) === 1;
    }

    private static function isAbsoluteUrlWithFragment(string $value): bool
    {
        return preg_match('/^[A-Za-z][A-Za-z0-9+.-]*:[^#\s]*#[^\s]+$/', $value) === 1;
    }

    /**
     * @return list<string>
     */
    private static function splitTokens(string $value): array
    {
        $value = trim($value);
        if ($value === '') {
            return [];
        }

        $tokens = preg_split('/\s+/', $value, -1, PREG_SPLIT_NO_EMPTY);

        return is_array($tokens) ? array_values($tokens) : [];
    }

    private static function normalizeText(string $text): string
    {
        $normalized = preg_replace('/\s+/u', ' ', trim($text));

        return $normalized === null ? trim($text) : $normalized;
    }

    /**
     * @return list<\DOMElement>
     */
    private static function childElements(\DOMNode $node, ?string $localName = null, ?string $namespaceUri = null): array
    {
        $children = [];
        foreach ($node->childNodes as $child) {
            if (!$child instanceof \DOMElement) {
                continue;
            }

            if ($localName !== null && $child->localName !== $localName) {
                continue;
            }

            if ($namespaceUri !== null && $child->namespaceURI !== $namespaceUri) {
                continue;
            }

            $children[] = $child;
        }

        return $children;
    }

    private static function firstChildElement(\DOMNode $node, ?string $localName = null, ?string $namespaceUri = null): ?\DOMElement
    {
        foreach (self::childElements($node, $localName, $namespaceUri) as $child) {
            return $child;
        }

        return null;
    }

    private static function firstDescendantElement(\DOMElement $element, string $localName): ?\DOMElement
    {
        foreach ($element->getElementsByTagName($localName) as $candidate) {
            if ($candidate instanceof \DOMElement && $candidate->localName === $localName) {
                return $candidate;
            }
        }

        return null;
    }

    private static function loadXml(string $xml, string $label): \DOMDocument
    {
        $previous = libxml_use_internal_errors(true);
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->resolveExternals = false;
        $dom->substituteEntities = false;
        $loaded = $dom->loadXML($xml, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (!$loaded) {
            throw new \InvalidArgumentException("Unable to parse {$label}");
        }

        return $dom;
    }
}
