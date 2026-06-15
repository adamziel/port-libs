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
    public const XMLDSIG_NAMESPACE = 'http://www.w3.org/2000/09/xmldsig#';
    public const ODF_MANIFEST_NAMESPACE = 'urn:oasis:names:tc:opendocument:xmlns:manifest:1.0';
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
    private const OCF_PACKAGE_SIDECARS = [
        'metadata' => [
            'partName' => '/META-INF/metadata.xml',
            'expectedRootName' => 'metadata',
            'expectedRootNamespace' => self::EPUB_METADATA_NAMESPACE,
            'reviewPolicy' => 'ocf-metadata-sidecar-review',
        ],
        'manifest' => [
            'partName' => '/META-INF/manifest.xml',
            'expectedRootName' => 'manifest',
            'expectedRootNamespace' => self::ODF_MANIFEST_NAMESPACE,
            'reviewPolicy' => 'ocf-manifest-sidecar-review',
        ],
        'rights' => [
            'partName' => '/META-INF/rights.xml',
            'expectedRootName' => 'rights',
            'expectedRootNamespace' => self::OCF_CONTAINER_NAMESPACE,
            'reviewPolicy' => 'ocf-rights-sidecar-review',
        ],
        'signatures' => [
            'partName' => '/META-INF/signatures.xml',
            'expectedRootName' => 'signatures',
            'expectedRootNamespace' => self::OCF_CONTAINER_NAMESPACE,
            'reviewPolicy' => 'ocf-signatures-sidecar-review',
        ],
    ];
    private const OPF_PACKAGE_STRUCTURAL_ATTRIBUTES = [
        'dir' => true,
        'id' => true,
        'prefix' => true,
        'unique-identifier' => true,
        'version' => true,
        'xml:base' => true,
        'xml:lang' => true,
    ];
    private const OPF_MANIFEST_ITEM_STRUCTURAL_ATTRIBUTES = [
        'dir' => true,
        'fallback' => true,
        'fallback-style' => true,
        'href' => true,
        'id' => true,
        'media-overlay' => true,
        'media-type' => true,
        'properties' => true,
        'xml:lang' => true,
    ];
    private const OPF_SPINE_ITEMREF_STRUCTURAL_ATTRIBUTES = [
        'dir' => true,
        'id' => true,
        'idref' => true,
        'linear' => true,
        'properties' => true,
        'xml:lang' => true,
    ];

    /**
     * @param list<array<string, mixed>> $rootfiles
     * @param array<string, mixed> $mimetypeEntry
     * @param array<string, mixed> $renditions
     * @param array<string, mixed> $metadata
     * @param list<array<string, mixed>> $containerLinks
     * @param array<string, mixed> $ocfSidecars
     * @param list<array<string, mixed>> $packageLinks
     * @param array<string, array{id:string, href:string, target:string, partName:?string, external:bool, mediaType:string, properties:list<string>, fallback:?string, fallbackStyle:?string, mediaOverlay:?string}> $manifestById
     * @param list<array{id:string, href:string, target:string, partName:?string, external:bool, mediaType:string, properties:list<string>, fallback:?string, fallbackStyle:?string, mediaOverlay:?string}> $manifestItems
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
        private readonly array $mimetypeEntry,
        private readonly array $rootfiles,
        private readonly array $renditions,
        private readonly array $containerLinks,
        private readonly array $ocfSidecars,
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
        $mimetypeEntry = self::assertEpubMimetype($package);

        if (!$package->has('META-INF/container.xml')) {
            throw new \RuntimeException('EPUB package is missing META-INF/container.xml');
        }

        $rootfiles = self::parseContainerXml($package->read('META-INF/container.xml'));
        $opfPartName = null;
        foreach ($rootfiles as $rootfile) {
            $rootfileMediaTypeBase = is_string($rootfile['mediaTypeBase'] ?? null)
                ? $rootfile['mediaTypeBase']
                : self::mediaTypeBase((string) ($rootfile['mediaType'] ?? ''));
            if ($rootfileMediaTypeBase === self::OPF_MEDIA_TYPE) {
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
        $renditions = self::summarizeRenditions($package, $rootfiles, $opfPartName, $opf);
        $containerLinks = self::parseContainerLinks($package, self::manifestByPart($opf['manifestById']));
        $ocfSidecars = self::summarizeOcfSidecars($package);
        $navigation = self::loadNavigation($package, $opfPartName, $opf['manifestById'], $opf['spineTocId']);

        return new self(
            $package,
            $mimetypeEntry,
            $rootfiles,
            $renditions,
            $containerLinks,
            $ocfSidecars,
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
     * @return array<string, mixed>
     */
    public function mimetypeEntry(): array
    {
        return $this->mimetypeEntry;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function rootfiles(): array
    {
        return $this->rootfiles;
    }

    /**
     * @return array<string, mixed>
     */
    public function renditions(): array
    {
        return $this->renditions;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function containerLinks(): array
    {
        return $this->containerLinks;
    }

    /**
     * @return array<string, mixed>
     */
    public function ocfSidecars(): array
    {
        return $this->ocfSidecars;
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
     * @return array<string, mixed>
     */
    public function manifestResourceKinds(): array
    {
        return self::manifestResourceKindReport($this->manifestItems);
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
            static fn (array $item): bool => self::mediaTypeBase((string) ($item['mediaType'] ?? '')) === self::XHTML_MEDIA_TYPE,
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
            $mediaType = self::mediaTypeBase((string) ($item['mediaType'] ?? ''));
            $partName = is_string($item['partName'] ?? null) && $item['partName'] !== ''
                ? $item['partName']
                : null;

            if ($partName === null) {
                continue;
            }

            if ($mediaType === self::XHTML_MEDIA_TYPE) {
                $xhtmlParts[] = $partName;
            }

            if ($mediaType === 'text/css') {
                $stylesheetParts[] = $partName;
            }

            if (str_starts_with($mediaType, 'image/')) {
                $imageParts[] = $partName;
            }

            if (in_array('cover-image', $item['properties'], true) || ($legacyCoverImageId !== null && $item['id'] === $legacyCoverImageId)) {
                $coverImagePart ??= $partName;
            }
        }

        return [
            'readingOrderParts' => array_values(array_filter(
                array_map(
                    static fn (array $item): ?string => is_string($item['partName'] ?? null) && $item['partName'] !== ''
                        ? $item['partName']
                        : null,
                    $this->spine,
                ),
                static fn (?string $partName): bool => $partName !== null,
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
        $ncxAudioLabelReport = self::ncxAudioLabelReport($this->navigation);
        $containerLinkReport = self::collectionLinkReport($this->containerLinks);
        $containerLinkVocabulary = self::metadataLinkVocabularySummary($this->containerLinks);
        $packageLinkReport = self::collectionLinkReport($this->packageLinks);
        $packageLinkVocabulary = is_array($this->metadata['linkVocabulary'] ?? null)
            ? $this->metadata['linkVocabulary']
            : self::metadataLinkVocabularySummary($this->packageLinks);
        $metaPropertyVocabulary = is_array($this->metadata['metaPropertyVocabulary'] ?? null)
            ? $this->metadata['metaPropertyVocabulary']
            : self::metadataMetaPropertyVocabularySummary([]);
        $metadataRefinementTargets = is_array($this->metadata['refinementTargets'] ?? null)
            ? $this->metadata['refinementTargets']
            : self::metadataRefinementTargetReport([], [], [], [], []);
        $collectionLinkVocabulary = self::collectionLinkVocabularySummary($this->collections);
        $collectionRoleVocabulary = self::collectionRoleVocabularySummary($this->collections);
        $collectionHierarchy = self::collectionHierarchyReport($this->collections);
        $remoteResourcePolicy = $this->remoteResourcePolicy();
        $mediaOverlayDiagnostics = self::mediaOverlayDiagnostics($this->mediaOverlays);
        $manifestFallbacks = $this->manifestFallbacks();
        $resourceProperties = $this->resourceProperties();
        $manifestResourceKinds = $this->manifestResourceKinds();
        $validationReport = $this->validationReport();
        $auxiliaryNavigation = self::auxiliaryNavigationReport($this->navigationSections);
        $spineMetadata = $this->spineMetadata();
        $packageAuthoring = self::packageAuthoringReport($this->metadata);
        $manifestAuthoring = self::manifestItemAuthoringReport($this->manifestItems);
        $spineAuthoring = self::spineItemrefAuthoringReport($this->spine);
        $guideReport = $this->guideReport();
        $ocfSidecars = $this->ocfSidecars();
        $packageInventory = self::packageInventoryReport(
            $this->package,
            $this->opfPartName,
            $this->rootfiles,
            $this->manifestItems,
            $this->spine,
            $ocfSidecars,
            $this->encryption,
        );
        $readingOrderInventory = self::readingOrderInventoryReport($this->spine, $packageInventory);
        $compactPackageReport = self::compactPackageReport(
            $this->metadata,
            $this->packageLinks,
            $this->guideReferences,
            $guideReport,
            $this->collections,
            $this->mediaOverlays,
            $manifestFallbacks,
            $this->encryption,
            $manifestResourceKinds,
            $resourceProperties,
            $ocfSidecars,
            $this->navigation,
            $this->navigationSections,
            $validationReport,
        );

        return [
            'opfPart' => $this->opfPartName,
            'mimetypeEntry' => $this->mimetypeEntry,
            'packageInventory' => $packageInventory,
            'readingOrderInventory' => $readingOrderInventory,
            'rootfiles' => $this->rootfiles,
            'renditions' => $this->renditions,
            'containerLinks' => $this->containerLinks,
            'containerLinksByRel' => $containerLinkReport['linksByRel'],
            'containerLinkRelCounts' => $containerLinkReport['relCounts'],
            'containerLinkDiagnostics' => $containerLinkReport['diagnostics'],
            'containerLinkVocabulary' => $containerLinkVocabulary,
            'ocfSidecars' => $ocfSidecars,
            'ocfSidecarDiagnostics' => $ocfSidecars['diagnostics'],
            'metadata' => $this->metadata,
            'packageAuthoring' => $packageAuthoring,
            'metaPropertyVocabulary' => $metaPropertyVocabulary,
            'metadataRefinementTargets' => $metadataRefinementTargets,
            'packageLinks' => $this->packageLinks,
            'packageLinksByRel' => $packageLinkReport['linksByRel'],
            'packageLinkRelCounts' => $packageLinkReport['relCounts'],
            'packageLinkDiagnostics' => $packageLinkReport['diagnostics'],
            'packageLinkVocabulary' => $packageLinkVocabulary,
            'renditionLayout' => $this->metadata['renditionLayout'] ?? self::metadataRenditionLayoutReport([]),
            'accessibility' => $this->metadata['accessibility'] ?? self::accessibilityMetadataReport($this->metadata, $this->packageLinks),
            'manifest' => $this->manifestItems,
            'manifestAuthoring' => $manifestAuthoring,
            'manifestResourceKinds' => $manifestResourceKinds,
            'readingOrder' => $this->spine,
            'spineMetadata' => $spineMetadata,
            'spineAuthoring' => $spineAuthoring,
            'guide' => $this->guideReferences,
            'guideReport' => $guideReport,
            'collections' => $this->collections,
            'collectionHierarchy' => $collectionHierarchy,
            'collectionLinkVocabulary' => $collectionLinkVocabulary,
            'collectionRoleVocabulary' => $collectionRoleVocabulary,
            'bindings' => $this->bindings,
            'mediaOverlays' => $this->mediaOverlays,
            'manifestFallbacks' => $manifestFallbacks,
            'encryption' => $this->encryption,
            'resourceProperties' => $resourceProperties,
            'navigation' => $this->navigation,
            'ncxAudioLabelReport' => $ncxAudioLabelReport,
            'ncxAudioLabelDiagnostics' => $ncxAudioLabelReport['diagnostics'],
            'navigationSections' => $this->navigationSections,
            'auxiliaryNavigation' => $auxiliaryNavigation,
            'assets' => $assetSummary,
            'remoteResourcePolicy' => $remoteResourcePolicy,
            'validation' => $validationReport,
            'compactPackageReport' => $compactPackageReport,
            'wordpressImport' => [
                'mimetypeEntry' => $this->mimetypeEntry,
                'packageInventory' => $packageInventory,
                'readingOrderInventory' => $readingOrderInventory,
                'compactPackageReport' => $compactPackageReport,
                'compactPackageReportCases' => $compactPackageReport['cases'],
                'compactPackageReportPresentCaseIds' => $compactPackageReport['presentCaseIds'],
                'compactPackageReportDiagnostics' => $compactPackageReport['diagnostics'],
                'title' => $this->metadata['title'],
                'creators' => $this->metadata['creators'],
                'language' => $this->metadata['language'],
                'metadataDetails' => [
                    'package' => $this->metadata['package'] ?? [],
                    'packageId' => $this->metadata['packageId'] ?? null,
                    'packageBase' => $this->metadata['packageBase'] ?? null,
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
                    'contributorDetails' => $this->metadata['contributorDetails'] ?? [],
                    'contributorsByRole' => $this->metadata['contributorsByRole'] ?? [],
                    'agentDisplayOrder' => $this->metadata['agentDisplayOrder'] ?? self::metadataAgentDisplayOrderReport([], []),
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
                    'subjectDetails' => $this->metadata['subjectDetails'] ?? [],
                    'subjectsByScheme' => $this->metadata['subjectsByScheme'] ?? [],
                    'subjectsByAuthority' => $this->metadata['subjectsByAuthority'] ?? [],
                    'subjectsByTerm' => $this->metadata['subjectsByTerm'] ?? [],
                    'subjectSummary' => $this->metadata['subjectSummary'] ?? self::metadataSubjectSummary([]),
                    'rights' => $this->metadata['rights'] ?? [],
                    'rightsDetails' => $this->metadata['rightsDetails'] ?? [],
                    'rightsSummary' => $this->metadata['rightsSummary'] ?? self::metadataRightsSummary([]),
                    'description' => $this->metadata['description'] ?? null,
                    'publisher' => $this->metadata['publisher'] ?? null,
                    'bibliographicDetails' => $this->metadata['bibliographicDetails'] ?? [],
                    'bibliographicDetailsByKind' => $this->metadata['bibliographicDetailsByKind'] ?? [],
                    'bibliographicSummary' => $this->metadata['bibliographicSummary'] ?? [],
                    'meta' => $this->metadata['meta'] ?? [],
                    'metaPropertyVocabulary' => $metaPropertyVocabulary,
                    'metaPropertyDiagnostics' => $metaPropertyVocabulary['diagnostics'],
                    'renditionLayout' => $this->metadata['renditionLayout'] ?? self::metadataRenditionLayoutReport([]),
                    'refinementsById' => $this->metadata['refinementsById'] ?? [],
                    'refinementTargets' => $metadataRefinementTargets,
                ],
                'packageAuthoring' => $packageAuthoring,
                'metadataPropertyVocabulary' => $metaPropertyVocabulary,
                'metadataPropertyDiagnostics' => $metaPropertyVocabulary['diagnostics'],
                'metadataRefinementTargets' => $metadataRefinementTargets,
                'metadataRefinementTargetDiagnostics' => $metadataRefinementTargets['diagnostics'],
                'readingOrderParts' => $assetSummary['readingOrderParts'],
                'renditions' => $this->renditions,
                'renditionDiagnostics' => $this->renditions['diagnostics'],
                'spineMetadata' => $spineMetadata,
                'pageProgressionDirection' => $spineMetadata['pageProgressionDirection'],
                'readingProgression' => $spineMetadata['readingProgression'],
                'spinePackageDiagnostics' => $spineMetadata['diagnostics'],
                'spinePageSpreadItems' => $validationReport['spine']['pageSpreadItems'],
                'spineItemDiagnostics' => $validationReport['spine']['itemDiagnostics'],
                'spineMissingRequiredAttributeItems' => $validationReport['spine']['missingRequiredAttributeItems'],
                'spineMissingRequiredAttributeNames' => $validationReport['spine']['missingRequiredAttributeNames'],
                'navigationLabels' => array_values(array_map(
                    static fn (array $entry): string => $entry['label'],
                    $navigationEntries,
                )),
                'ncxAudioLabels' => $ncxAudioLabelReport['items'],
                'ncxAudioLabelReport' => $ncxAudioLabelReport,
                'ncxAudioLabelDiagnostics' => $ncxAudioLabelReport['diagnostics'],
                'guideReferences' => $this->guideReferences,
                'guideReferenceReport' => $guideReport,
                'guideReferenceTargets' => $guideReport['targets'],
                'guideReferenceDiagnostics' => $guideReport['diagnostics'],
                'guideReferenceManifestMediaTypeParameterItems' => $guideReport['manifestMediaTypeParameterItems'],
                'guideReferenceManifestMediaTypeParameterNames' => $guideReport['manifestMediaTypeParameterNames'],
                'guideReferenceManifestMediaTypeDiagnostics' => $guideReport['manifestMediaTypeDiagnostics'],
                'collections' => $this->collections,
                'collectionHierarchy' => $collectionHierarchy,
                'collectionHierarchyItems' => $collectionHierarchy['items'],
                'collectionHierarchyDiagnostics' => $collectionHierarchy['diagnostics'],
                'containerLinks' => $this->containerLinks,
                'containerLinksByRel' => $containerLinkReport['linksByRel'],
                'containerLinkTargets' => self::packageLinkTargets($this->containerLinks),
                'containerLinkDiagnostics' => $containerLinkReport['diagnostics'],
                'containerLinkVocabulary' => $containerLinkVocabulary,
                'containerLinkVocabularyDiagnostics' => $containerLinkVocabulary['diagnostics'],
                'ocfSidecars' => $ocfSidecars,
                'ocfSidecarItems' => $ocfSidecars['items'],
                'ocfSidecarDiagnostics' => $ocfSidecars['diagnostics'],
                'collectionTitles' => self::collectionTitles($this->collections),
                'collectionLinkTargets' => self::collectionLinkTargets($this->collections),
                'collectionDiagnostics' => self::collectionDiagnostics($this->collections),
                'collectionLinkVocabulary' => $collectionLinkVocabulary,
                'collectionLinkVocabularyDiagnostics' => $collectionLinkVocabulary['diagnostics'],
                'collectionRoleVocabulary' => $collectionRoleVocabulary,
                'collectionRoleVocabularyDiagnostics' => $collectionRoleVocabulary['diagnostics'],
                'packageLinks' => $this->packageLinks,
                'packageLinksByRel' => $packageLinkReport['linksByRel'],
                'packageLinkTargets' => self::packageLinkTargets($this->packageLinks),
                'packageLinkDiagnostics' => $packageLinkReport['diagnostics'],
                'packageLinkVocabulary' => $packageLinkVocabulary,
                'packageLinkVocabularyDiagnostics' => $packageLinkVocabulary['diagnostics'],
                'accessibility' => $this->metadata['accessibility'] ?? self::accessibilityMetadataReport($this->metadata, $this->packageLinks),
                'manifestAuthoring' => $manifestAuthoring,
                'manifestAuthoringItems' => $manifestAuthoring['items'],
                'remoteResourcePolicy' => $remoteResourcePolicy,
                'remoteResourceExternalTargets' => $remoteResourcePolicy['externalTargets'],
                'remoteResourcePolicyDiagnostics' => $remoteResourcePolicy['diagnostics'],
                'mediaTypeBindings' => $this->bindings['items'],
                'mediaTypeBindingDiagnostics' => $this->bindings['diagnostics'],
                'mediaOverlays' => $this->mediaOverlays,
                'mediaOverlayItems' => $this->mediaOverlays['items'],
                'mediaOverlayTargets' => $this->mediaOverlays['textTargets'],
                'mediaOverlayAudioTargets' => $this->mediaOverlays['audioTargets'],
                'mediaOverlayTextLocalTargets' => $this->mediaOverlays['textLocalTargets'] ?? [],
                'mediaOverlayTextExternalTargets' => $this->mediaOverlays['textExternalTargets'] ?? [],
                'mediaOverlayTextMissingTargets' => $this->mediaOverlays['textMissingTargets'] ?? [],
                'mediaOverlayAudioLocalTargets' => $this->mediaOverlays['audioLocalTargets'] ?? [],
                'mediaOverlayAudioExternalTargets' => $this->mediaOverlays['audioExternalTargets'] ?? [],
                'mediaOverlayAudioMissingTargets' => $this->mediaOverlays['audioMissingTargets'] ?? [],
                'mediaOverlayDiagnostics' => $mediaOverlayDiagnostics,
                'manifestFallbacks' => $manifestFallbacks,
                'manifestFallbackItems' => $manifestFallbacks['fallbackItems'],
                'manifestFallbackStyleItems' => $manifestFallbacks['fallbackStyleItems'],
                'manifestFallbackDiagnostics' => $manifestFallbacks['diagnostics'],
                'encryption' => $this->encryption,
                'encryptedResourceExposure' => $this->encryption['exposure'],
                'encryptedResourceDiagnostics' => $this->encryption['diagnostics'],
                'manifestResourceKinds' => $manifestResourceKinds,
                'manifestResourceKindSummary' => $manifestResourceKinds['summary'],
                'manifestResourceKindItems' => $manifestResourceKinds['items'],
                'manifestResourceKindCounts' => $manifestResourceKinds['kindCounts'],
                'resourceProperties' => $resourceProperties,
                'resourcePropertySummary' => $resourceProperties['summary'],
                'resourcePropertyReviewItems' => $resourceProperties['reviewItems'],
                'resourcePropertyDiagnostics' => $resourceProperties['propertyVocabulary']['diagnostics'],
                'packageValidation' => $validationReport,
                'packageValidationDiagnostics' => $validationReport['diagnostics'],
                'spineAuthoring' => $spineAuthoring,
                'spineAuthoringItems' => $spineAuthoring['items'],
                'manifestMediaTypeParameterItems' => $validationReport['manifest']['mediaTypeParameterItems'],
                'manifestMediaTypeParameterNames' => $validationReport['manifest']['mediaTypeParameterNames'],
                'manifestMediaTypeDiagnostics' => $validationReport['manifest']['mediaTypeDiagnostics'],
                'manifestExternalItems' => $validationReport['manifest']['externalItems'],
                'manifestItemDiagnostics' => $validationReport['manifest']['itemDiagnostics'],
                'manifestMissingRequiredAttributeItems' => $validationReport['manifest']['missingRequiredAttributeItems'],
                'manifestMissingRequiredAttributeNames' => $validationReport['manifest']['missingRequiredAttributeNames'],
                'manifestInvalidHrefItems' => $validationReport['manifest']['invalidHrefItems'],
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
     * @param array<string, mixed> $metadata
     * @param list<array<string, mixed>> $packageLinks
     * @param list<array<string, mixed>> $guideReferences
     * @param array<string, mixed> $guideReport
     * @param list<array<string, mixed>> $collections
     * @param array<string, mixed> $mediaOverlays
     * @param array<string, mixed> $manifestFallbacks
     * @param array<string, mixed> $encryption
     * @param array<string, mixed> $manifestResourceKinds
     * @param array<string, mixed> $resourceProperties
     * @param array<string, mixed> $ocfSidecars
     * @param array<string, mixed>|null $navigation
     * @param list<array<string, mixed>> $navigationSections
     * @param array<string, mixed> $validationReport
     *
     * @return array<string, mixed>
     */
    private static function compactPackageReport(
        array $metadata,
        array $packageLinks,
        array $guideReferences,
        array $guideReport,
        array $collections,
        array $mediaOverlays,
        array $manifestFallbacks,
        array $encryption,
        array $manifestResourceKinds,
        array $resourceProperties,
        array $ocfSidecars,
        ?array $navigation,
        array $navigationSections,
        array $validationReport
    ): array {
        $cases = [];
        $allDiagnostics = [];
        $appendCase = static function (
            string $id,
            string $domain,
            string $label,
            int $itemCount,
            array $diagnostics,
            array $extra = []
        ) use (&$cases, &$allDiagnostics): void {
            $caseDiagnostics = [];
            foreach ($diagnostics as $diagnostic) {
                if (!is_array($diagnostic)) {
                    continue;
                }
                $caseDiagnostics[] = [
                    'caseId' => $id,
                    'domain' => $domain,
                ] + $diagnostic;
            }

            array_push($allDiagnostics, ...$caseDiagnostics);
            $cases[] = array_merge([
                'id' => $id,
                'domain' => $domain,
                'label' => $label,
                'present' => $itemCount > 0,
                'itemCount' => $itemCount,
                'diagnosticCount' => count($caseDiagnostics),
                'diagnosticTypes' => self::compactDiagnosticTypes($caseDiagnostics),
                'reviewRequired' => $caseDiagnostics !== [],
                'diagnostics' => $caseDiagnostics,
            ], $extra);
        };

        $refinementsById = is_array($metadata['refinementsById'] ?? null)
            ? $metadata['refinementsById']
            : [];
        $refinementTargets = is_array($metadata['refinementTargets'] ?? null)
            ? $metadata['refinementTargets']
            : [];
        $refinementCount = 0;
        foreach ($refinementsById as $entries) {
            if (is_array($entries)) {
                $refinementCount += count($entries);
            }
        }
        $appendCase(
            'metadata-refinements',
            'metadata',
            'OPF metadata refinements',
            $refinementCount,
            self::compactDiagnosticList($refinementTargets['diagnostics'] ?? []),
            [
                'targetCount' => count($refinementsById),
                'targetIds' => array_keys($refinementsById),
                'packageLinkCount' => count($packageLinks),
            ],
        );

        $navigationValidation = is_array($validationReport['navigation'] ?? null)
            ? $validationReport['navigation']
            : [];
        $navigationTypeCounts = [];
        foreach ($navigationSections as $section) {
            $type = is_string($section['type'] ?? null) && $section['type'] !== ''
                ? $section['type']
                : 'unknown';
            $navigationTypeCounts[$type] = ($navigationTypeCounts[$type] ?? 0) + 1;
        }
        $appendCase(
            'navigation-sections',
            'navigation',
            'EPUB navigation sections',
            count($navigationSections),
            self::compactDiagnosticList($navigationValidation['diagnostics'] ?? []),
            [
                'navigationType' => is_array($navigation) && is_string($navigation['type'] ?? null) ? $navigation['type'] : null,
                'entryCount' => is_array($navigation) && is_array($navigation['entries'] ?? null) ? count($navigation['entries']) : 0,
                'sectionTypes' => array_keys($navigationTypeCounts),
                'sectionTypeCounts' => $navigationTypeCounts,
            ],
        );

        $appendCase(
            'guide-references',
            'guide',
            'OPF guide references',
            count($guideReferences),
            self::compactDiagnosticList($guideReport['diagnostics'] ?? []),
            [
                'targets' => is_array($guideReport['targets'] ?? null) ? array_values($guideReport['targets']) : [],
            ],
        );

        $collectionHierarchy = self::collectionHierarchyReport($collections);
        $appendCase(
            'collections',
            'collections',
            'OPF collections',
            self::compactCollectionCount($collections),
            self::collectionDiagnostics($collections),
            [
                'titles' => self::collectionTitles($collections),
                'linkTargets' => self::collectionLinkTargets($collections),
                'pathKeys' => array_column($collectionHierarchy['items'], 'pathKey'),
                'maxDepth' => $collectionHierarchy['maxDepth'],
                'leafCollectionCount' => $collectionHierarchy['leafCollectionCount'],
                'roleCounts' => $collectionHierarchy['roleCounts'],
                'primaryRoleCounts' => $collectionHierarchy['primaryRoleCounts'],
                'linkRelCounts' => $collectionHierarchy['linkRelCounts'],
            ],
        );

        $appendCase(
            'media-overlays',
            'media-overlays',
            'EPUB media overlays',
            (int) ($mediaOverlays['overlayCount'] ?? 0),
            self::compactDiagnosticList($mediaOverlays['diagnostics'] ?? []),
            [
                'textTargets' => is_array($mediaOverlays['textTargets'] ?? null) ? array_values($mediaOverlays['textTargets']) : [],
                'audioTargets' => is_array($mediaOverlays['audioTargets'] ?? null) ? array_values($mediaOverlays['audioTargets']) : [],
            ],
        );

        $appendCase(
            'manifest-fallbacks',
            'manifest',
            'OPF manifest fallback chains',
            (int) ($manifestFallbacks['itemCount'] ?? 0),
            self::compactDiagnosticList($manifestFallbacks['diagnostics'] ?? []),
            [
                'fallbackCount' => (int) ($manifestFallbacks['fallbackCount'] ?? 0),
                'fallbackStyleCount' => (int) ($manifestFallbacks['fallbackStyleCount'] ?? 0),
            ],
        );

        $manifestResourceKindMissingCount = (int) ($manifestResourceKinds['missingItemCount'] ?? 0);
        $manifestResourceKindExternalCount = (int) ($manifestResourceKinds['externalItemCount'] ?? 0);
        $appendCase(
            'manifest-resource-kinds',
            'manifest',
            'OPF manifest resource kind readiness',
            (int) ($manifestResourceKinds['itemCount'] ?? 0),
            [],
            [
                'reviewRequired' => $manifestResourceKindMissingCount > 0 || $manifestResourceKindExternalCount > 0,
                'kindCount' => (int) ($manifestResourceKinds['kindCount'] ?? 0),
                'kinds' => is_array($manifestResourceKinds['kinds'] ?? null) ? array_values($manifestResourceKinds['kinds']) : [],
                'kindCounts' => is_array($manifestResourceKinds['kindCounts'] ?? null) ? $manifestResourceKinds['kindCounts'] : [],
                'kindPartNames' => is_array($manifestResourceKinds['kindPartNames'] ?? null) ? $manifestResourceKinds['kindPartNames'] : [],
                'mediaTypeBaseCounts' => is_array($manifestResourceKinds['mediaTypeBaseCounts'] ?? null) ? $manifestResourceKinds['mediaTypeBaseCounts'] : [],
                'existingItemCount' => (int) ($manifestResourceKinds['existingItemCount'] ?? 0),
                'missingItemCount' => $manifestResourceKindMissingCount,
                'externalItemCount' => $manifestResourceKindExternalCount,
                'exposableItemCount' => (int) ($manifestResourceKinds['exposableItemCount'] ?? 0),
            ],
        );

        $resourcePropertySummary = is_array($resourceProperties['summary'] ?? null) ? $resourceProperties['summary'] : [];
        $resourcePropertyVocabulary = is_array($resourceProperties['propertyVocabulary'] ?? null) ? $resourceProperties['propertyVocabulary'] : [];
        $resourcePropertyDiagnostics = self::compactDiagnosticList($resourcePropertyVocabulary['diagnostics'] ?? []);
        $resourcePropertyReviewRequiredCount = (int) ($resourcePropertySummary['reviewRequiredCount'] ?? 0);
        $resourcePropertyBlockedCount = (int) ($resourcePropertySummary['blockedByteExposureCount'] ?? 0);
        $appendCase(
            'manifest-resource-properties',
            'manifest',
            'OPF manifest resource property byte policy',
            is_array($resourceProperties['items'] ?? null) ? count($resourceProperties['items']) : 0,
            $resourcePropertyDiagnostics,
            [
                'reviewRequired' => $resourcePropertyReviewRequiredCount > 0 || $resourcePropertyBlockedCount > 0 || $resourcePropertyDiagnostics !== [],
                'propertySummary' => $resourcePropertySummary,
                'byteExposurePolicyCounts' => is_array($resourceProperties['byteExposurePolicyCounts'] ?? null) ? $resourceProperties['byteExposurePolicyCounts'] : [],
                'reviewRequiredCount' => $resourcePropertyReviewRequiredCount,
                'blockedByteExposureCount' => $resourcePropertyBlockedCount,
                'missingItemCount' => (int) ($resourcePropertySummary['missingItemCount'] ?? 0),
                'externalItemCount' => (int) ($resourcePropertySummary['externalItemCount'] ?? 0),
                'encryptedItemCount' => (int) ($resourcePropertySummary['encryptedItemCount'] ?? 0),
                'unsupportedCompressionItemCount' => (int) ($resourcePropertySummary['unsupportedCompressionItemCount'] ?? 0),
                'reviewItemIds' => is_array($resourceProperties['reviewItems'] ?? null) ? array_values(array_filter(
                    array_column($resourceProperties['reviewItems'], 'id'),
                    static fn (mixed $id): bool => is_string($id) && $id !== '',
                )) : [],
                'blockedByteExposureItemIds' => is_array($resourceProperties['blockedByteExposureItems'] ?? null) ? array_values(array_filter(
                    array_column($resourceProperties['blockedByteExposureItems'], 'id'),
                    static fn (mixed $id): bool => is_string($id) && $id !== '',
                )) : [],
            ],
        );

        $encryptionExposure = is_array($encryption['exposure'] ?? null) ? $encryption['exposure'] : [];
        $encryptionDiagnostics = array_merge(
            self::compactDiagnosticList($encryption['diagnostics'] ?? []),
            self::compactDiagnosticList($encryptionExposure['diagnostics'] ?? []),
        );
        $appendCase(
            'encrypted-resources',
            'encryption',
            'OCF encrypted resources',
            (int) ($encryptionExposure['itemCount'] ?? 0),
            $encryptionDiagnostics,
            [
                'encryptedParts' => is_array($encryption['encryptedParts'] ?? null) ? array_values($encryption['encryptedParts']) : [],
                'blockedByteExposureCount' => (int) ($encryptionExposure['blockedByteExposureCount'] ?? 0),
                'obfuscatedFontCount' => (int) ($encryptionExposure['obfuscatedFontCount'] ?? 0),
            ],
        );

        $appendCase(
            'ocf-sidecars',
            'ocf',
            'OCF sidecar documents',
            (int) ($ocfSidecars['sidecarCount'] ?? 0),
            self::compactDiagnosticList($ocfSidecars['diagnostics'] ?? []),
            [
                'kinds' => is_array($ocfSidecars['kinds'] ?? null) ? array_values($ocfSidecars['kinds']) : [],
                'referenceCount' => (int) ($ocfSidecars['referenceCount'] ?? 0),
            ],
        );

        $presentCaseIds = [];
        $diagnosticCaseIds = [];
        $reviewRequiredCaseIds = [];
        $caseCounts = [];
        $domainCounts = [];
        foreach ($cases as $case) {
            $id = (string) $case['id'];
            $domain = (string) $case['domain'];
            $caseCounts[$id] = (int) $case['itemCount'];
            $domainCounts[$domain] = ($domainCounts[$domain] ?? 0) + (int) $case['itemCount'];
            if (($case['present'] ?? false) === true) {
                $presentCaseIds[] = $id;
            }
            if ((int) ($case['diagnosticCount'] ?? 0) > 0) {
                $diagnosticCaseIds[] = $id;
            }
            if (($case['reviewRequired'] ?? false) === true) {
                $reviewRequiredCaseIds[] = $id;
            }
        }

        return [
            'present' => $presentCaseIds !== [],
            'caseCount' => count($cases),
            'presentCaseCount' => count($presentCaseIds),
            'diagnosticCaseCount' => count($diagnosticCaseIds),
            'reviewRequiredCaseCount' => count($reviewRequiredCaseIds),
            'caseIds' => array_column($cases, 'id'),
            'presentCaseIds' => $presentCaseIds,
            'diagnosticCaseIds' => $diagnosticCaseIds,
            'reviewRequiredCaseIds' => $reviewRequiredCaseIds,
            'caseCounts' => $caseCounts,
            'domainCounts' => $domainCounts,
            'diagnosticCount' => count($allDiagnostics),
            'diagnosticTypes' => self::compactDiagnosticTypes($allDiagnostics),
            'diagnosticTypeCounts' => self::compactDiagnosticTypeCounts($allDiagnostics),
            'diagnostics' => $allDiagnostics,
            'cases' => $cases,
            'casesById' => self::compactCasesById($cases),
        ];
    }

    /**
     * @param mixed $diagnostics
     * @return list<array<string, mixed>>
     */
    private static function compactDiagnosticList(mixed $diagnostics): array
    {
        if (!is_array($diagnostics)) {
            return [];
        }

        return array_values(array_filter($diagnostics, static fn (mixed $diagnostic): bool => is_array($diagnostic)));
    }

    /**
     * @param list<array<string, mixed>> $diagnostics
     * @return list<string>
     */
    private static function compactDiagnosticTypes(array $diagnostics): array
    {
        return array_keys(self::compactDiagnosticTypeCounts($diagnostics));
    }

    /**
     * @param list<array<string, mixed>> $diagnostics
     * @return array<string, int>
     */
    private static function compactDiagnosticTypeCounts(array $diagnostics): array
    {
        $counts = [];
        foreach ($diagnostics as $diagnostic) {
            if (!is_array($diagnostic) || !is_string($diagnostic['type'] ?? null) || $diagnostic['type'] === '') {
                continue;
            }
            $counts[$diagnostic['type']] = ($counts[$diagnostic['type']] ?? 0) + 1;
        }

        return $counts;
    }

    /**
     * @param list<array<string, mixed>> $collections
     */
    private static function compactCollectionCount(array $collections): int
    {
        $count = 0;
        foreach ($collections as $collection) {
            if (!is_array($collection)) {
                continue;
            }
            ++$count;
            $children = is_array($collection['children'] ?? null) ? $collection['children'] : [];
            $count += self::compactCollectionCount($children);
        }

        return $count;
    }

    /**
     * @param list<array<string, mixed>> $cases
     * @return array<string, array<string, mixed>>
     */
    private static function compactCasesById(array $cases): array
    {
        $byId = [];
        foreach ($cases as $case) {
            if (!is_array($case) || !is_string($case['id'] ?? null) || $case['id'] === '') {
                continue;
            }
            $byId[$case['id']] = $case;
        }

        return $byId;
    }

    /**
     * @param list<array<string, mixed>> $rootfiles
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
     * @param list<array<string, mixed>> $rootfiles
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
        $existingRootfiles = [];
        $rootfileParts = [];
        $opfRootfileParts = [];
        $alternateRootfileParts = [];
        $missingRootfileParts = [];
        $nonOpfRootfileParts = [];
        $existingRootfileParts = [];
        $rootfileMediaTypeCounts = [];
        $rootfilePartsByMediaType = [];
        $parts = [];
        $diagnostics = [];
        $selectedIndex = null;
        $selectedRootfile = null;

        foreach ($rootfiles as $index => $rootfile) {
            $partName = (string) ($rootfile['partName'] ?? '');
            $rawMediaType = (string) ($rootfile['mediaType'] ?? '');
            $mediaType = is_string($rootfile['mediaTypeBase'] ?? null)
                ? $rootfile['mediaTypeBase']
                : self::mediaTypeBase($rawMediaType);
            $mediaTypeParameters = is_array($rootfile['mediaTypeParameters'] ?? null)
                ? array_values($rootfile['mediaTypeParameters'])
                : [];
            $mediaTypeParameterMap = is_array($rootfile['mediaTypeParameterMap'] ?? null)
                ? $rootfile['mediaTypeParameterMap']
                : [];
            $mediaTypeDiagnostics = is_array($rootfile['mediaTypeDiagnostics'] ?? null)
                ? array_values($rootfile['mediaTypeDiagnostics'])
                : [];
            $hasQuery = ($rootfile['fullPathHasQuery'] ?? false) === true;
            $hasFragment = ($rootfile['fullPathHasFragment'] ?? false) === true;
            $exists = $partName !== '' && $package->has($partName);
            $entry = $exists ? $package->entry($partName) : null;
            $provenance = self::zipEntryProvenance($entry);
            $selected = $selectedIndex === null && $partName === $opfPartName && $mediaType === self::OPF_MEDIA_TYPE;
            if ($selected) {
                $selectedIndex = $index;
            }

            $item = [
                'index' => $index,
                'fullPath' => (string) ($rootfile['fullPath'] ?? ''),
                'target' => is_string($rootfile['target'] ?? null) ? $rootfile['target'] : $partName,
                'partName' => $partName,
                'mediaType' => $rawMediaType,
                'normalizedMediaType' => is_string($rootfile['normalizedMediaType'] ?? null) ? $rootfile['normalizedMediaType'] : $mediaType,
                'mediaTypeBase' => $mediaType,
                'mediaTypeHasParameters' => $mediaTypeParameters !== [],
                'mediaTypeParameterCount' => count($mediaTypeParameters),
                'mediaTypeParameters' => $mediaTypeParameters,
                'mediaTypeParameterMap' => $mediaTypeParameterMap,
                'mediaTypeSyntaxValid' => $mediaTypeDiagnostics === [],
                'mediaTypeDiagnostics' => $mediaTypeDiagnostics,
                'fullPathHasQuery' => $hasQuery,
                'fullPathQuery' => is_string($rootfile['fullPathQuery'] ?? null) ? $rootfile['fullPathQuery'] : null,
                'fullPathHasFragment' => $hasFragment,
                'fullPathFragment' => is_string($rootfile['fullPathFragment'] ?? null) ? $rootfile['fullPathFragment'] : null,
                'exists' => $exists,
                'selected' => $selected,
                'byteLength' => $provenance['byteLength'],
                'compressedByteLength' => $provenance['compressedByteLength'],
                'compressionMethod' => $provenance['compressionMethod'],
                'compressionMethodName' => $provenance['compressionMethodName'],
                'compressionSupported' => $provenance['compressionSupported'],
                'crc32' => $provenance['crc32'],
                'canExposeBytes' => $provenance['canExposeBytes'],
            ];
            $items[] = $item;
            $rootfileParts[] = $partName;
            $rootfileMediaTypeCounts[$mediaType] = ($rootfileMediaTypeCounts[$mediaType] ?? 0) + 1;
            $rootfilePartsByMediaType[$mediaType][] = $partName;

            if ($hasQuery) {
                $diagnostics[] = [
                    'type' => 'rootfile-full-path-query-component',
                    'index' => $index,
                    'fullPath' => $item['fullPath'],
                    'target' => $item['target'],
                    'partName' => $partName,
                    'query' => $item['fullPathQuery'],
                    'message' => 'EPUB container rootfile full-path includes a query component; compact package ingestion loads the package part and preserves the suffix for review',
                ];
            }
            if ($hasFragment) {
                $diagnostics[] = [
                    'type' => 'rootfile-full-path-fragment-component',
                    'index' => $index,
                    'fullPath' => $item['fullPath'],
                    'target' => $item['target'],
                    'partName' => $partName,
                    'fragment' => $item['fullPathFragment'],
                    'message' => 'EPUB container rootfile full-path includes a fragment component; compact package ingestion loads the package part and preserves the suffix for review',
                ];
            }

            if ($mediaType === self::OPF_MEDIA_TYPE) {
                $opfRootfiles[] = $item;
                $opfRootfileParts[] = $partName;
            } else {
                $nonOpfRootfiles[] = $item;
                $nonOpfRootfileParts[] = $partName;
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
                $alternateRootfileParts[] = $partName;
            } else {
                $selectedRootfile = $item;
            }

            if (!$exists) {
                $missingRootfiles[] = $item;
                $missingRootfileParts[] = $partName;
                $diagnostics[] = [
                    'type' => 'missing-rootfile-package-part',
                    'index' => $index,
                    'partName' => $partName,
                    'mediaType' => $mediaType,
                    'message' => 'EPUB container rootfile points at a package part that is not present in the ZIP',
                ];
            } else {
                $existingRootfiles[] = $item;
                $existingRootfileParts[] = $partName;
            }

            if ($partName !== '') {
                $parts[$partName][] = $item;
            }
        }

        $fullPathSuffixItems = [];
        foreach ($items as $item) {
            if (($item['fullPathHasQuery'] ?? false) !== true && ($item['fullPathHasFragment'] ?? false) !== true) {
                continue;
            }

            $fullPathSuffixItems[] = [
                'index' => $item['index'],
                'fullPath' => $item['fullPath'],
                'target' => $item['target'],
                'partName' => $item['partName'],
                'mediaType' => $item['mediaTypeBase'],
                'query' => $item['fullPathQuery'],
                'fragment' => $item['fullPathFragment'],
            ];
        }

        $mediaTypeParameterItems = [];
        $mediaTypeParameterNames = [];
        $mediaTypeParameterCount = 0;
        $rootfileMediaTypeDiagnostics = [];
        foreach ($items as $item) {
            $parameters = is_array($item['mediaTypeParameters'] ?? null)
                ? array_values($item['mediaTypeParameters'])
                : [];
            if ($parameters !== []) {
                $parameterNames = [];
                foreach ($parameters as $parameter) {
                    if (!is_array($parameter)) {
                        continue;
                    }

                    $name = is_string($parameter['name'] ?? null) ? $parameter['name'] : '';
                    if ($name !== '') {
                        $parameterNames[] = $name;
                        $mediaTypeParameterNames[$name] = true;
                    }
                }

                $mediaTypeParameterCount += count($parameters);
                $mediaTypeParameterItems[] = [
                    'index' => $item['index'],
                    'fullPath' => $item['fullPath'],
                    'partName' => $item['partName'],
                    'mediaType' => $item['mediaType'],
                    'mediaTypeBase' => $item['mediaTypeBase'],
                    'parameterCount' => count($parameters),
                    'parameterNames' => array_values(array_unique($parameterNames)),
                    'parameters' => $parameters,
                    'parameterMap' => is_array($item['mediaTypeParameterMap'] ?? null)
                        ? $item['mediaTypeParameterMap']
                        : [],
                ];
            }

            foreach (is_array($item['mediaTypeDiagnostics'] ?? null) ? $item['mediaTypeDiagnostics'] : [] as $mediaTypeDiagnostic) {
                if (!is_array($mediaTypeDiagnostic)) {
                    continue;
                }

                $rootfileMediaTypeDiagnostics[] = [
                    'index' => $item['index'],
                    'fullPath' => $item['fullPath'],
                    'partName' => $item['partName'],
                    'mediaType' => $item['mediaType'],
                ] + $mediaTypeDiagnostic;
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
            'selectedRootfile' => $selectedRootfile,
            'rootfileCount' => count($items),
            'opfRootfileCount' => count($opfRootfiles),
            'alternateRootfileCount' => count($alternateRootfiles),
            'existingRootfileCount' => count($existingRootfiles),
            'missingRootfileCount' => count($missingRootfiles),
            'nonOpfRootfileCount' => count($nonOpfRootfiles),
            'duplicatePartCount' => count($duplicatePartItems),
            'rootfileParts' => $rootfileParts,
            'opfRootfileParts' => $opfRootfileParts,
            'alternateRootfileParts' => $alternateRootfileParts,
            'existingRootfileParts' => $existingRootfileParts,
            'missingRootfileParts' => $missingRootfileParts,
            'nonOpfRootfileParts' => $nonOpfRootfileParts,
            'mediaTypeCounts' => $rootfileMediaTypeCounts,
            'partsByMediaType' => $rootfilePartsByMediaType,
            'fullPathSuffixCount' => count($fullPathSuffixItems),
            'fullPathSuffixItems' => $fullPathSuffixItems,
            'mediaTypeParameterItemCount' => count($mediaTypeParameterItems),
            'mediaTypeParameterCount' => $mediaTypeParameterCount,
            'mediaTypeParameterNames' => array_keys($mediaTypeParameterNames),
            'mediaTypeDiagnosticCount' => count($rootfileMediaTypeDiagnostics),
            'items' => $items,
            'opfRootfiles' => $opfRootfiles,
            'alternateRootfiles' => $alternateRootfiles,
            'existingRootfiles' => $existingRootfiles,
            'missingRootfiles' => $missingRootfiles,
            'nonOpfRootfiles' => $nonOpfRootfiles,
            'duplicatePartItems' => $duplicatePartItems,
            'mediaTypeParameterItems' => $mediaTypeParameterItems,
            'mediaTypeDiagnostics' => $rootfileMediaTypeDiagnostics,
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
        $prefixDiagnostics = [];
        foreach (is_array($metadata['prefixDiagnostics'] ?? null) ? $metadata['prefixDiagnostics'] : [] as $diagnostic) {
            if (is_array($diagnostic)) {
                $prefixDiagnostics[] = $diagnostic;
            }
        }
        $identifierDiagnostics = [];
        foreach (is_array($metadata['identifierDiagnostics'] ?? null) ? $metadata['identifierDiagnostics'] : [] as $diagnostic) {
            if (is_array($diagnostic)) {
                $identifierDiagnostics[] = $diagnostic;
            }
        }
        $metaPropertyDiagnostics = [];
        $metaPropertyVocabulary = is_array($metadata['metaPropertyVocabulary'] ?? null)
            ? $metadata['metaPropertyVocabulary']
            : self::metadataMetaPropertyVocabularySummary([]);
        $refinementTargets = is_array($metadata['refinementTargets'] ?? null)
            ? $metadata['refinementTargets']
            : self::metadataRefinementTargetReport([], [], [], [], []);
        foreach (is_array($metaPropertyVocabulary['diagnostics'] ?? null) ? $metaPropertyVocabulary['diagnostics'] : [] as $diagnostic) {
            if (is_array($diagnostic)) {
                $metaPropertyDiagnostics[] = $diagnostic;
            }
        }
        $refinementTargetDiagnostics = [];
        foreach (is_array($refinementTargets['diagnostics'] ?? null) ? $refinementTargets['diagnostics'] : [] as $diagnostic) {
            if (is_array($diagnostic)) {
                $refinementTargetDiagnostics[] = $diagnostic;
            }
        }
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

        array_push(
            $diagnostics,
            ...$identifierDiagnostics,
            ...$prefixDiagnostics,
            ...$metaPropertyDiagnostics,
            ...$refinementTargetDiagnostics,
        );

        return [
            'valid' => $diagnostics === [],
            'titlePresent' => $titlePresent,
            'identifierPresent' => $identifierPresent,
            'languagePresent' => $languagePresent,
            'modifiedPresent' => $modifiedPresent,
            'identifierValid' => $identifierDiagnostics === [],
            'identifierDiagnosticCount' => count($identifierDiagnostics),
            'identifierDiagnostics' => $identifierDiagnostics,
            'prefixValid' => $prefixDiagnostics === [],
            'prefixDiagnosticCount' => count($prefixDiagnostics),
            'prefixDiagnostics' => $prefixDiagnostics,
            'metaPropertyValid' => $metaPropertyDiagnostics === [],
            'metaPropertyDiagnosticCount' => count($metaPropertyDiagnostics),
            'metaPropertyDiagnostics' => $metaPropertyDiagnostics,
            'refinementTargetValid' => $refinementTargetDiagnostics === [],
            'refinementTargetDiagnosticCount' => count($refinementTargetDiagnostics),
            'refinementTargetDiagnostics' => $refinementTargetDiagnostics,
            'refinementTargets' => $refinementTargets,
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
        $mediaTypeItems = [];
        $invalidMediaTypeItems = [];
        $duplicateMediaTypeParameterItems = [];
        $missingItems = [];
        $externalItems = [];
        $parts = [];
        $ids = [];
        $mediaTypeParameterItems = [];
        $mediaTypeParameterNames = [];
        $mediaTypeParameterCount = 0;
        $mediaTypeDiagnostics = [];
        $itemDiagnostics = [];
        $missingRequiredAttributeItems = [];
        $missingRequiredAttributeNames = [];
        $missingRequiredAttributeCount = 0;
        $invalidHrefItems = [];
        $diagnostics = [];

        foreach ($manifestItems as $index => $item) {
            $id = (string) ($item['id'] ?? '');
            $partName = is_string($item['partName'] ?? null) ? $item['partName'] : '';
            $mediaTypeReport = self::manifestMediaTypeItemReport($item, $index);
            $mediaType = $mediaTypeReport['baseMediaType'];
            $mediaTypeItems[] = $mediaTypeReport;
            $mediaTypeParameterCount += $mediaTypeReport['parameterCount'];
            if ($mediaTypeReport['parameterCount'] > 0) {
                $mediaTypeParameterItems[] = $mediaTypeReport;
                foreach ($mediaTypeReport['parameterNames'] as $parameterName) {
                    $mediaTypeParameterNames[$parameterName] = true;
                }
            }
            if (!$mediaTypeReport['valid']) {
                $invalidMediaTypeItems[] = $mediaTypeReport;
            }
            if ($mediaTypeReport['duplicateParameterCount'] > 0) {
                $duplicateMediaTypeParameterItems[] = $mediaTypeReport;
            }
            array_push($mediaTypeDiagnostics, ...$mediaTypeReport['diagnostics']);
            array_push($diagnostics, ...$mediaTypeReport['diagnostics']);
            $properties = is_array($item['properties'] ?? null) ? array_values($item['properties']) : [];
            $external = ($item['external'] ?? false) === true;
            $missingRequiredAttributes = is_array($item['missingRequiredAttributes'] ?? null)
                ? array_values(array_filter(
                    $item['missingRequiredAttributes'],
                    static fn (mixed $attribute): bool => is_string($attribute) && $attribute !== ''
                ))
                : [];
            if ($missingRequiredAttributes !== []) {
                $missingRequiredAttributeCount += count($missingRequiredAttributes);
                foreach ($missingRequiredAttributes as $attribute) {
                    $missingRequiredAttributeNames[$attribute] = true;
                }

                $missingRequiredAttributeItems[] = [
                    'index' => $index,
                    'id' => $id === '' ? null : $id,
                    'href' => (string) ($item['href'] ?? ''),
                    'target' => (string) ($item['target'] ?? ''),
                    'partName' => $partName === '' ? null : $partName,
                    'mediaType' => (string) ($item['mediaType'] ?? ''),
                    'missingAttributes' => $missingRequiredAttributes,
                ];
            }

            foreach (is_array($item['diagnostics'] ?? null) ? $item['diagnostics'] : [] as $itemDiagnostic) {
                if (!is_array($itemDiagnostic)) {
                    continue;
                }

                $diagnostic = [
                    'id' => $id,
                    'href' => (string) ($item['href'] ?? ''),
                    'target' => (string) ($item['target'] ?? ''),
                    'partName' => $partName === '' ? null : $partName,
                    'mediaType' => (string) ($item['mediaType'] ?? ''),
                ] + $itemDiagnostic;
                $itemDiagnostics[] = $diagnostic;
                $diagnostics[] = $diagnostic;

                if (($diagnostic['type'] ?? null) === 'invalid-manifest-href-target') {
                    $invalidHrefItems[] = [
                        'index' => $index,
                        'id' => $id === '' ? null : $id,
                        'href' => (string) ($item['href'] ?? ''),
                        'target' => (string) ($item['target'] ?? ''),
                        'partName' => $partName === '' ? null : $partName,
                        'mediaType' => (string) ($item['mediaType'] ?? ''),
                        'message' => is_string($diagnostic['message'] ?? null) ? $diagnostic['message'] : '',
                    ];
                }
            }

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

            if ($external) {
                $externalItems[] = [
                    'id' => $id,
                    'href' => (string) ($item['href'] ?? ''),
                    'target' => (string) ($item['target'] ?? ''),
                    'partName' => null,
                    'mediaType' => $mediaType,
                ];
            } elseif ($partName !== '' && ($item['exists'] ?? false) !== true) {
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
            'externalItemCount' => count($externalItems),
            'duplicateIdCount' => count($duplicateIdItems),
            'duplicateManifestIdCount' => count($duplicateIdItems),
            'duplicatePartCount' => count($duplicatePartItems),
            'duplicateHrefTargetCount' => count($duplicatePartItems),
            'hrefSuffixCount' => count($hrefSuffixItems),
            'mediaTypeParameterItemCount' => count($mediaTypeParameterItems),
            'mediaTypeParameterizedItemCount' => count($mediaTypeParameterItems),
            'mediaTypeParameterCount' => $mediaTypeParameterCount,
            'mediaTypeParameterNames' => array_keys($mediaTypeParameterNames),
            'mediaTypeDiagnosticCount' => count($mediaTypeDiagnostics),
            'itemDiagnosticCount' => count($itemDiagnostics),
            'missingRequiredAttributeItemCount' => count($missingRequiredAttributeItems),
            'missingRequiredAttributeCount' => $missingRequiredAttributeCount,
            'missingRequiredAttributeNames' => array_keys($missingRequiredAttributeNames),
            'invalidHrefItemCount' => count($invalidHrefItems),
            'invalidMediaTypeCount' => count($invalidMediaTypeItems),
            'duplicateMediaTypeParameterCount' => count($duplicateMediaTypeParameterItems),
            'navItems' => $navItems,
            'usableNavItems' => $usableNavItems,
            'invalidNavItems' => $invalidNavItems,
            'missingItems' => $missingItems,
            'externalItems' => $externalItems,
            'duplicateIdItems' => $duplicateIdItems,
            'duplicateManifestIdItems' => $duplicateIdItems,
            'duplicatePartItems' => $duplicatePartItems,
            'duplicateHrefTargetItems' => $duplicatePartItems,
            'hrefSuffixItems' => $hrefSuffixItems,
            'mediaTypeItems' => $mediaTypeItems,
            'mediaTypeParameterItems' => $mediaTypeParameterItems,
            'mediaTypeDiagnostics' => $mediaTypeDiagnostics,
            'itemDiagnostics' => $itemDiagnostics,
            'missingRequiredAttributeItems' => $missingRequiredAttributeItems,
            'invalidHrefItems' => $invalidHrefItems,
            'invalidMediaTypeItems' => $invalidMediaTypeItems,
            'duplicateMediaTypeParameterItems' => $duplicateMediaTypeParameterItems,
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
        $pageSpreadItems = [];
        $pageSpreadCounts = [
            'left' => 0,
            'right' => 0,
            'center' => 0,
        ];
        $itemDiagnostics = [];
        $missingRequiredAttributeItems = [];
        $missingRequiredAttributeNames = [];
        $missingRequiredAttributeCount = 0;
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

            $missingRequiredAttributes = is_array($item['missingRequiredAttributes'] ?? null)
                ? array_values(array_filter(
                    $item['missingRequiredAttributes'],
                    static fn (mixed $attribute): bool => is_string($attribute) && $attribute !== ''
                ))
                : [];
            if ($missingRequiredAttributes !== []) {
                $missingRequiredAttributeCount += count($missingRequiredAttributes);
                foreach ($missingRequiredAttributes as $attribute) {
                    $missingRequiredAttributeNames[$attribute] = true;
                }

                $missingRequiredAttributeItems[] = [
                    'index' => $index,
                    'id' => is_string($item['id'] ?? null) ? $item['id'] : null,
                    'idref' => (string) ($item['idref'] ?? ''),
                    'missingAttributes' => $missingRequiredAttributes,
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
                $diagnostics[] = $diagnostic;
            }

            if ($missingRequiredAttributes !== []) {
                continue;
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
            'missingManifestItemCount' => count($missingManifestItems),
            'missingPackagePartCount' => count($missingPackagePartItems),
            'nonContentDocumentCount' => count($nonContentDocumentItems),
            'missingRequiredAttributeItemCount' => count($missingRequiredAttributeItems),
            'missingRequiredAttributeCount' => $missingRequiredAttributeCount,
            'missingRequiredAttributeNames' => array_keys($missingRequiredAttributeNames),
            'pageSpreadCount' => count($pageSpreadItems),
            'pageSpreadLeftCount' => $pageSpreadCounts['left'],
            'pageSpreadRightCount' => $pageSpreadCounts['right'],
            'pageSpreadCenterCount' => $pageSpreadCounts['center'],
            'pageSpreadItems' => $pageSpreadItems,
            'missingManifestItems' => $missingManifestItems,
            'missingPackagePartItems' => $missingPackagePartItems,
            'nonContentDocumentItems' => $nonContentDocumentItems,
            'missingRequiredAttributeItems' => $missingRequiredAttributeItems,
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
        $sourceDiagnostics = [];
        if (is_array($navigation) && is_array($navigation['diagnostics'] ?? null)) {
            foreach ($navigation['diagnostics'] as $diagnostic) {
                if (is_array($diagnostic)) {
                    $sourceDiagnostics[] = $diagnostic;
                }
            }
        }
        $sections = $navigationSections;
        if ($sections === [] && $sourceDiagnostics === [] && is_array($navigation) && is_array($navigation['entries'] ?? null)) {
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
            $source === 'nav',
            $source === 'nav' ? $sourceDiagnostics : []
        );
        array_push($diagnostics, ...$documentDiagnostics['diagnostics']);
        if ($source !== 'nav') {
            array_push($diagnostics, ...$sourceDiagnostics);
        }

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
    private static function navDocumentDiagnosticReport(
        array $sections,
        ?string $part,
        bool $documentPresent,
        array $documentParseDiagnostics = []
    ): array
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

        $documentParseDiagnostics = array_values(array_filter(
            $documentParseDiagnostics,
            static fn (mixed $diagnostic): bool => is_array($diagnostic),
        ));
        array_push($diagnostics, ...$documentParseDiagnostics);

        if ($documentPresent && $sections === [] && $documentParseDiagnostics === []) {
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
            'present' => $documentPresent && ($sections !== [] || $documentParseDiagnostics !== []),
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
            'documentParseDiagnosticCount' => count($documentParseDiagnostics),
            'documentParseDiagnostics' => $documentParseDiagnostics,
            'diagnosticCount' => count($diagnostics),
            'diagnostics' => $diagnostics,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function assertEpubMimetype(ZipPackage $package): array
    {
        return $package->assertStoredFirstEntry('mimetype', self::EPUB_MIMETYPE, 'EPUB mimetype entry');
    }

    /**
     * @return list<array<string, mixed>>
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

            $targetSuffixOffset = strcspn($fullPath, '?#');
            $targetSuffix = substr($fullPath, $targetSuffixOffset);
            $partName = OpcPackagePath::canonicalPartName(OpcPackagePath::stripQueryAndFragment($fullPath));
            $target = $partName . $targetSuffix;
            $hrefSuffix = self::packageHrefSuffixReport($target);
            $mediaTypeReport = self::mediaTypeReport($mediaType);
            $rootfiles[] = [
                'fullPath' => $fullPath,
                'target' => $target,
                'partName' => $partName,
                'mediaType' => $mediaType,
                'normalizedMediaType' => $mediaTypeReport['normalizedMediaType'],
                'mediaTypeBase' => $mediaTypeReport['mediaTypeBase'],
                'mediaTypeHasParameters' => $mediaTypeReport['mediaTypeHasParameters'],
                'mediaTypeParameterCount' => $mediaTypeReport['mediaTypeParameterCount'],
                'mediaTypeParameters' => $mediaTypeReport['mediaTypeParameters'],
                'mediaTypeParameterMap' => $mediaTypeReport['mediaTypeParameterMap'],
                'mediaTypeSyntaxValid' => $mediaTypeReport['mediaTypeSyntaxValid'],
                'mediaTypeDiagnostics' => $mediaTypeReport['mediaTypeDiagnostics'],
                'fullPathHasQuery' => $hrefSuffix['hasQuery'],
                'fullPathQuery' => $hrefSuffix['query'],
                'fullPathHasFragment' => $hrefSuffix['hasFragment'],
                'fullPathFragment' => $hrefSuffix['fragment'],
            ];
        }

        if ($rootfiles === []) {
            throw new \RuntimeException('EPUB container.xml does not declare any rootfiles');
        }

        return $rootfiles;
    }

    /**
     * @param list<array<string, mixed>> $rootfiles
     * @param array<string, mixed> $selectedOpf
     *
     * @return array<string, mixed>
     */
    private static function summarizeRenditions(ZipPackage $package, array $rootfiles, string $selectedOpfPart, array $selectedOpf): array
    {
        $items = [];
        $diagnostics = [];
        $selectedIndex = null;

        foreach ($rootfiles as $index => $rootfile) {
            $partName = (string) ($rootfile['partName'] ?? '');
            $mediaTypeBase = is_string($rootfile['mediaTypeBase'] ?? null)
                ? $rootfile['mediaTypeBase']
                : self::mediaTypeBase((string) ($rootfile['mediaType'] ?? ''));
            if ($mediaTypeBase !== self::OPF_MEDIA_TYPE) {
                continue;
            }

            $selected = $selectedIndex === null && $partName === $selectedOpfPart;
            $item = $selected
                ? self::renditionSummaryFromParsedOpf($package, $rootfile, $index, $selectedOpf, true)
                : self::alternateRenditionSummary($package, $rootfile, $index);

            if ($selected) {
                $selectedIndex = count($items);
            }

            foreach (is_array($item['diagnostics'] ?? null) ? $item['diagnostics'] : [] as $diagnostic) {
                if (!is_array($diagnostic)) {
                    continue;
                }

                $diagnostics[] = [
                    'index' => $item['index'],
                    'path' => $item['partName'],
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
            'diagnosticCount' => count($diagnostics),
            'diagnostics' => $diagnostics,
            'items' => $items,
        ];
    }

    /**
     * @param array<string, mixed> $rootfile
     * @param array<string, mixed> $opf
     *
     * @return array<string, mixed>
     */
    private static function renditionSummaryFromParsedOpf(
        ZipPackage $package,
        array $rootfile,
        int $index,
        array $opf,
        bool $selected
    ): array {
        $metadata = is_array($opf['metadata'] ?? null) ? $opf['metadata'] : [];
        $packageSummary = is_array($metadata['package'] ?? null) ? $metadata['package'] : null;
        if (is_array($packageSummary)) {
            $packageSummary['opfPart'] = (string) ($rootfile['partName'] ?? '');
        }

        return self::baseRenditionSummary($package, $rootfile, $index, $selected) + [
            'package' => $packageSummary,
            'metadata' => self::renditionMetadataSummary($metadata),
            'renditionProperties' => self::renditionProperties($metadata),
            'renditionLayout' => is_array($metadata['renditionLayout'] ?? null)
                ? $metadata['renditionLayout']
                : self::metadataRenditionLayoutReport(is_array($metadata['metaProperties'] ?? null) ? $metadata['metaProperties'] : []),
            'manifestCount' => is_array($opf['manifestItems'] ?? null) ? count($opf['manifestItems']) : null,
            'spineCount' => is_array($opf['spine'] ?? null) ? count($opf['spine']) : null,
            'diagnostics' => [],
        ];
    }

    /**
     * @param array<string, mixed> $rootfile
     *
     * @return array<string, mixed>
     */
    private static function alternateRenditionSummary(ZipPackage $package, array $rootfile, int $index): array
    {
        $summary = self::baseRenditionSummary($package, $rootfile, $index, false) + [
            'package' => null,
            'metadata' => self::renditionMetadataSummary([]),
            'renditionProperties' => [],
            'renditionLayout' => self::metadataRenditionLayoutReport([]),
            'manifestCount' => null,
            'spineCount' => null,
            'diagnostics' => [],
        ];

        if (($summary['exists'] ?? false) !== true) {
            $summary['diagnostics'][] = [
                'type' => 'missing-alternate-rendition-rootfile',
                'message' => 'EPUB alternate rendition OPF rootfile is missing from the package',
            ];

            return $summary;
        }

        try {
            $dom = self::loadXml($package->read((string) $summary['partName']), 'EPUB alternate rendition OPF package document');
        } catch (\Throwable $exception) {
            $summary['diagnostics'][] = [
                'type' => 'invalid-alternate-rendition-opf',
                'message' => $exception->getMessage(),
            ];

            return $summary;
        }

        $root = $dom->documentElement;
        if (!$root instanceof \DOMElement || $root->localName !== 'package' || $root->namespaceURI !== self::OPF_NAMESPACE) {
            $summary['diagnostics'][] = [
                'type' => 'invalid-alternate-rendition-opf',
                'message' => 'EPUB alternate rendition root must be an OPF package element',
            ];

            return $summary;
        }

        $metadataElement = self::firstChildElement($root, 'metadata', self::OPF_NAMESPACE);
        $manifestElement = self::firstChildElement($root, 'manifest', self::OPF_NAMESPACE);
        $spineElement = self::firstChildElement($root, 'spine', self::OPF_NAMESPACE);
        $metadata = [];

        if ($metadataElement instanceof \DOMElement) {
            $metadata = self::parseMetadata($metadataElement, $root);
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

        $packageSummary = is_array($metadata['package'] ?? null)
            ? $metadata['package']
            : [
                'id' => self::emptyToNull($root->getAttribute('id')),
                'version' => $root->getAttribute('version'),
                'uniqueIdentifierId' => self::emptyToNull($root->getAttribute('unique-identifier')),
                'language' => self::metadataElementLanguage($root),
                'direction' => self::metadataElementDirection($root),
                'prefix' => $root->hasAttribute('prefix') ? $root->getAttribute('prefix') : '',
                'prefixDeclarations' => [],
                'prefixBindings' => [],
                'refinements' => [],
            ];
        $packageSummary['opfPart'] = (string) $summary['partName'];

        $summary['package'] = $packageSummary;
        $summary['metadata'] = self::renditionMetadataSummary($metadata);
        $summary['renditionProperties'] = self::renditionProperties($metadata);
        $summary['renditionLayout'] = is_array($metadata['renditionLayout'] ?? null)
            ? $metadata['renditionLayout']
            : self::metadataRenditionLayoutReport(is_array($metadata['metaProperties'] ?? null) ? $metadata['metaProperties'] : []);
        $summary['manifestCount'] = $manifestElement instanceof \DOMElement
            ? count(self::childElements($manifestElement, 'item', self::OPF_NAMESPACE))
            : null;
        $summary['spineCount'] = $spineElement instanceof \DOMElement
            ? count(self::childElements($spineElement, 'itemref', self::OPF_NAMESPACE))
            : null;

        return $summary;
    }

    /**
     * @param array<string, mixed> $rootfile
     *
     * @return array<string, mixed>
     */
    private static function baseRenditionSummary(ZipPackage $package, array $rootfile, int $index, bool $selected): array
    {
        $partName = (string) ($rootfile['partName'] ?? '');
        $exists = $partName !== '' && $package->has($partName);
        $entry = $exists ? $package->entry($partName) : null;

        return [
            'index' => $index,
            'fullPath' => (string) ($rootfile['fullPath'] ?? ''),
            'target' => is_string($rootfile['target'] ?? null) ? $rootfile['target'] : $partName,
            'path' => $partName,
            'partName' => $partName,
            'mediaType' => (string) ($rootfile['mediaType'] ?? ''),
            'normalizedMediaType' => is_string($rootfile['normalizedMediaType'] ?? null)
                ? $rootfile['normalizedMediaType']
                : self::mediaTypeReport((string) ($rootfile['mediaType'] ?? ''))['normalizedMediaType'],
            'mediaTypeBase' => is_string($rootfile['mediaTypeBase'] ?? null)
                ? $rootfile['mediaTypeBase']
                : self::mediaTypeBase((string) ($rootfile['mediaType'] ?? '')),
            'mediaTypeHasParameters' => (bool) ($rootfile['mediaTypeHasParameters'] ?? false),
            'mediaTypeParameterCount' => is_int($rootfile['mediaTypeParameterCount'] ?? null)
                ? $rootfile['mediaTypeParameterCount']
                : count(is_array($rootfile['mediaTypeParameters'] ?? null) ? $rootfile['mediaTypeParameters'] : []),
            'mediaTypeParameters' => is_array($rootfile['mediaTypeParameters'] ?? null)
                ? $rootfile['mediaTypeParameters']
                : [],
            'mediaTypeParameterMap' => is_array($rootfile['mediaTypeParameterMap'] ?? null)
                ? $rootfile['mediaTypeParameterMap']
                : [],
            'mediaTypeSyntaxValid' => (bool) ($rootfile['mediaTypeSyntaxValid'] ?? true),
            'mediaTypeDiagnostics' => is_array($rootfile['mediaTypeDiagnostics'] ?? null)
                ? array_values($rootfile['mediaTypeDiagnostics'])
                : [],
            'fullPathHasQuery' => ($rootfile['fullPathHasQuery'] ?? false) === true,
            'fullPathQuery' => is_string($rootfile['fullPathQuery'] ?? null) ? $rootfile['fullPathQuery'] : null,
            'fullPathHasFragment' => ($rootfile['fullPathHasFragment'] ?? false) === true,
            'fullPathFragment' => is_string($rootfile['fullPathFragment'] ?? null) ? $rootfile['fullPathFragment'] : null,
            'exists' => $exists,
            'selected' => $selected,
        ] + self::zipEntryProvenance($entry);
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

                $value = trim((string) ($entry['content'] ?? ''));
                if ($value === '') {
                    $value = trim((string) ($entry['text'] ?? ''));
                }
                if ($value === '') {
                    continue;
                }

                $properties[$key] = $value;
                break;
            }
        }

        ksort($properties);

        return $properties;
    }

    /**
     * @return array<string, mixed>
     */
    private static function summarizeOcfSidecars(ZipPackage $package): array
    {
        $items = [];
        $itemsByKind = [];
        $diagnostics = [];

        foreach (self::OCF_PACKAGE_SIDECARS as $kind => $definition) {
            $partName = (string) $definition['partName'];
            if (!$package->has($partName)) {
                continue;
            }

            $entry = $package->entry($partName);
            $provenance = self::zipEntryProvenance($entry);
            $itemDiagnostics = [];
            $expectedRootNamespace = (string) $definition['expectedRootNamespace'];
            $rootReport = ($provenance['compressionSupported'] ?? false) === true
                ? self::ocfSidecarRootReport(
                    $package,
                    $kind,
                    $partName,
                    (string) $definition['expectedRootName'],
                    $expectedRootNamespace,
                )
                : self::emptyOcfSidecarRootReport();
            $manifestReport = $kind === 'manifest'
                ? self::ocfManifestSidecarReport($package, $partName, $rootReport, $provenance)
                : [];

            if (($provenance['compressionSupported'] ?? false) !== true) {
                $itemDiagnostics[] = [
                    'type' => 'ocf-sidecar-unsupported-compression-method',
                    'kind' => $kind,
                    'partName' => $partName,
                    'compressionMethod' => $provenance['compressionMethod'],
                    'compressionMethodName' => $provenance['compressionMethodName'],
                    'message' => 'EPUB OCF sidecar uses a ZIP compression method that native package ingestion cannot expose as bytes',
                ];
            }
            array_push($itemDiagnostics, ...$rootReport['diagnostics']);
            if (is_array($manifestReport['diagnostics'] ?? null)) {
                array_push($itemDiagnostics, ...$manifestReport['diagnostics']);
            }

            $item = [
                'kind' => $kind,
                'part' => $partName,
                'partName' => $partName,
                'packagePath' => ltrim($partName, '/'),
                'exists' => true,
                'expectedRootName' => (string) $definition['expectedRootName'],
                'expectedRootNamespace' => $expectedRootNamespace,
                'reviewPolicy' => (string) $definition['reviewPolicy'],
                'byteExposurePolicy' => 'ocf-sidecar-metadata-only',
                'canExposeBytes' => false,
                'xmlRootChecked' => $rootReport['checked'],
                'xmlWellFormed' => $rootReport['wellFormed'],
                'rootName' => $rootReport['rootName'],
                'rootNamespace' => $rootReport['rootNamespace'],
                'rootValid' => $rootReport['valid'],
                'rootReport' => $rootReport,
                'rootDiagnostics' => $rootReport['diagnostics'],
                'diagnosticCount' => count($itemDiagnostics),
                'diagnostics' => $itemDiagnostics,
            ] + $manifestReport + $provenance;

            $items[] = $item;
            $itemsByKind[$kind] = $item;
            array_push($diagnostics, ...$itemDiagnostics);
        }

        return [
            'present' => $items !== [],
            'sidecarCount' => count($items),
            'count' => count($items),
            'metadataPresent' => isset($itemsByKind['metadata']),
            'manifestPresent' => isset($itemsByKind['manifest']),
            'rightsPresent' => isset($itemsByKind['rights']),
            'signaturesPresent' => isset($itemsByKind['signatures']),
            'kinds' => array_keys($itemsByKind),
            'items' => $items,
            'itemsByKind' => $itemsByKind,
            'referenceCount' => array_sum(array_map(static fn (array $item): int => (int) ($item['referenceCount'] ?? 0), $items)),
            'localReferenceCount' => array_sum(array_map(static fn (array $item): int => (int) ($item['localReferenceCount'] ?? 0), $items)),
            'externalReferenceCount' => array_sum(array_map(static fn (array $item): int => (int) ($item['externalReferenceCount'] ?? 0), $items)),
            'missingReferenceCount' => array_sum(array_map(static fn (array $item): int => (int) ($item['missingReferenceCount'] ?? 0), $items)),
            'diagnosticCount' => count($diagnostics),
            'diagnostics' => $diagnostics,
        ];
    }

    /**
     * @return array{checked:bool, wellFormed:?bool, rootName:?string, rootNamespace:?string, valid:?bool, diagnostics:list<array<string, mixed>>}
     */
    private static function emptyOcfSidecarRootReport(): array
    {
        return [
            'checked' => false,
            'wellFormed' => null,
            'rootName' => null,
            'rootNamespace' => null,
            'valid' => null,
            'diagnostics' => [],
        ];
    }

    /**
     * @return array{checked:bool, wellFormed:?bool, rootName:?string, rootNamespace:?string, valid:?bool, diagnostics:list<array<string, mixed>>}
     */
    private static function ocfSidecarRootReport(
        ZipPackage $package,
        string $kind,
        string $partName,
        string $expectedRootName,
        string $expectedRootNamespace
    ): array {
        try {
            $dom = self::loadXml($package->read($partName), "EPUB OCF sidecar {$partName}");
        } catch (\RuntimeException | \InvalidArgumentException $exception) {
            return [
                'checked' => true,
                'wellFormed' => false,
                'rootName' => null,
                'rootNamespace' => null,
                'valid' => false,
                'diagnostics' => [[
                    'type' => 'invalid-ocf-sidecar-xml',
                    'kind' => $kind,
                    'partName' => $partName,
                    'error' => $exception->getMessage(),
                    'message' => 'EPUB OCF sidecar XML could not be parsed for bounded package review',
                ]],
            ];
        }

        $root = $dom->documentElement;
        $rootName = $root instanceof \DOMElement ? $root->localName : null;
        $rootNamespace = $root instanceof \DOMElement ? $root->namespaceURI : null;
        $diagnostics = [];
        if ($rootName !== $expectedRootName || $rootNamespace !== $expectedRootNamespace) {
            $diagnostics[] = [
                'type' => 'unexpected-ocf-sidecar-root',
                'kind' => $kind,
                'partName' => $partName,
                'expectedRootName' => $expectedRootName,
                'expectedRootNamespace' => $expectedRootNamespace,
                'rootName' => $rootName,
                'rootNamespace' => $rootNamespace,
                'message' => 'EPUB OCF sidecar root element does not match the expected container sidecar element',
            ];
        }

        return [
            'checked' => true,
            'wellFormed' => true,
            'rootName' => $rootName,
            'rootNamespace' => $rootNamespace,
            'valid' => $diagnostics === [],
            'diagnostics' => $diagnostics,
        ];
    }

    /**
     * @param array{checked:bool, wellFormed:?bool, rootName:?string, rootNamespace:?string, valid:?bool, diagnostics:list<array<string, mixed>>} $rootReport
     * @param array<string, mixed> $provenance
     *
     * @return array<string, mixed>
     */
    private static function ocfManifestSidecarReport(
        ZipPackage $package,
        string $partName,
        array $rootReport,
        array $provenance
    ): array {
        $report = [
            'format' => null,
            'odfCompatible' => null,
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

        if (($provenance['compressionSupported'] ?? false) !== true || ($rootReport['valid'] ?? false) !== true) {
            return $report;
        }

        $report['format'] = 'odf-manifest';
        $report['odfCompatible'] = true;

        try {
            $dom = self::loadXml($package->read($partName), 'EPUB OCF manifest XML');
        } catch (\RuntimeException | \InvalidArgumentException $exception) {
            $report['format'] = 'xml';
            $report['odfCompatible'] = false;
            $report['diagnostics'][] = [
                'type' => 'invalid-ocf-manifest-xml',
                'partName' => $partName,
                'message' => $exception->getMessage(),
            ];

            return $report;
        }

        $root = $dom->documentElement;
        if (!$root instanceof \DOMElement) {
            $report['format'] = 'xml';
            $report['odfCompatible'] = false;

            return $report;
        }

        $report['version'] = self::nullableNamespacedAttribute($root, self::ODF_MANIFEST_NAMESPACE, 'version', 'manifest:version');
        $items = [];
        $itemsByPart = [];
        foreach (self::childElements($root, 'file-entry', self::ODF_MANIFEST_NAMESPACE) as $index => $entryElement) {
            $item = self::ocfManifestFileEntryReport($package, $entryElement, $index);
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
            static fn (array $item): bool => is_string($item['fullPath'] ?? null) && $item['fullPath'] !== '',
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
    private static function ocfManifestFileEntryReport(ZipPackage $package, \DOMElement $entryElement, int $index): array
    {
        $fullPath = self::nullableNamespacedAttribute($entryElement, self::ODF_MANIFEST_NAMESPACE, 'full-path', 'manifest:full-path');
        $mediaType = self::nullableNamespacedAttribute($entryElement, self::ODF_MANIFEST_NAMESPACE, 'media-type', 'manifest:media-type');
        $version = self::nullableNamespacedAttribute($entryElement, self::ODF_MANIFEST_NAMESPACE, 'version', 'manifest:version');
        $size = self::nullableNamespacedAttribute($entryElement, self::ODF_MANIFEST_NAMESPACE, 'size', 'manifest:size');
        $encrypted = self::firstChildElement($entryElement, 'encryption-data', self::ODF_MANIFEST_NAMESPACE) instanceof \DOMElement;
        $reference = null;
        $diagnostics = [];

        if ($fullPath === null) {
            $diagnostics[] = [
                'type' => 'missing-ocf-manifest-full-path',
                'message' => 'EPUB OCF manifest file-entry is missing manifest:full-path',
            ];
        } else {
            $reference = self::ocfManifestEntryReference($package, $fullPath);
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
        $canExposeBytes = is_array($reference)
            && ($reference['exists'] ?? false) === true
            && ($reference['canExposeBytes'] ?? false) === true
            && $part !== null
            && !$directory
            && !$encrypted;
        $byteSha256 = null;
        if ($canExposeBytes) {
            try {
                $byteSha256 = hash('sha256', $package->read($part));
            } catch (\RuntimeException | \InvalidArgumentException $exception) {
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
            'compressedByteLength' => is_array($reference) && is_int($reference['compressedByteLength'] ?? null) ? $reference['compressedByteLength'] : null,
            'compressionMethod' => is_array($reference) && is_int($reference['compressionMethod'] ?? null) ? $reference['compressionMethod'] : null,
            'compressionMethodName' => is_array($reference) && is_string($reference['compressionMethodName'] ?? null) ? $reference['compressionMethodName'] : null,
            'compressionSupported' => is_array($reference) && is_bool($reference['compressionSupported'] ?? null) ? $reference['compressionSupported'] : null,
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
    private static function ocfManifestEntryReference(ZipPackage $package, string $fullPath): array
    {
        $fullPath = trim($fullPath);
        if ($fullPath === '') {
            return self::missingOcfReference('manifest');
        }

        if (self::isAbsoluteUri($fullPath) || str_starts_with($fullPath, '//')) {
            return [
                'target' => $fullPath,
                'part' => null,
                'external' => true,
                'exists' => false,
                'diagnostics' => [[
                    'type' => 'ocf-manifest-external-reference',
                    'fullPath' => $fullPath,
                    'message' => 'EPUB OCF manifest file-entry points outside the package and was not fetched',
                ]],
            ] + self::zipEntryProvenance(null);
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
                'diagnostics' => [[
                    'type' => 'ocf-manifest-invalid-reference',
                    'fullPath' => $fullPath,
                    'message' => $exception->getMessage(),
                ]],
            ] + self::zipEntryProvenance(null);
        }

        if ($part === '/') {
            return [
                'target' => '/',
                'part' => null,
                'external' => false,
                'exists' => true,
                'diagnostics' => [],
            ] + self::zipEntryProvenance(null);
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
            'diagnostics' => $diagnostics,
        ] + self::zipEntryProvenance($entry);
    }

    /**
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
     * @return array<string, mixed>
     */
    private static function missingOcfReference(string $context): array
    {
        return [
            'target' => null,
            'part' => null,
            'external' => false,
            'exists' => false,
            'diagnostics' => [[
                'type' => 'ocf-' . $context . '-missing-reference',
                'message' => 'EPUB OCF ' . $context . ' reference is missing a URI',
            ]],
        ] + self::zipEntryProvenance(null);
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

        $prefixReport = self::packagePrefixReport($root->hasAttribute('prefix') ? $root->getAttribute('prefix') : '');
        $prefixBindings = $prefixReport['bindingsByPrefix'];
        $links = [];
        foreach (self::childElements($root, 'link', self::EPUB_METADATA_NAMESPACE) as $index => $linkElement) {
            $links[] = self::parseContainerLink($linkElement, $index, $package, $manifestByPart, $prefixBindings);
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
            'hrefHasQuery' => $hrefSuffix['hasQuery'],
            'hrefQuery' => $hrefSuffix['query'],
            'hrefHasFragment' => $hrefSuffix['hasFragment'],
            'hrefFragment' => $hrefSuffix['fragment'],
            'diagnostics' => $diagnostics,
        ] + $provenance;
    }

    /**
     * @return array{
     *     metadata:array<string, mixed>,
     *     packageLinks:list<array<string, mixed>>,
     *     manifestById:array<string, array{id:string, href:string, target:string, partName:?string, external:bool, mediaType:string, properties:list<string>, fallback:?string, fallbackStyle:?string, mediaOverlay:?string}>,
     *     manifestItems:list<array{id:string, href:string, target:string, partName:?string, external:bool, mediaType:string, properties:list<string>, fallback:?string, fallbackStyle:?string, mediaOverlay:?string}>,
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
        $prefixBindings = is_array($metadata['prefixBindings'] ?? null) ? $metadata['prefixBindings'] : [];
        $packageLinks = self::parsePackageLinks(
            $metadataElement,
            $opfPartName,
            $package,
            $manifestByPart,
            $prefixBindings,
        );
        $packageLinkReport = self::collectionLinkReport($packageLinks);
        $metadata['links'] = $packageLinks;
        $metadata['linksByRel'] = $packageLinkReport['linksByRel'];
        $metadata['linkRelCounts'] = $packageLinkReport['relCounts'];
        $metadata['linkDiagnostics'] = $packageLinkReport['diagnostics'];
        $metadata['linkVocabulary'] = self::metadataLinkVocabularySummary($packageLinks);
        $metadata = self::attachPackageLinksToMetadata($metadata, $packageLinks);
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
        $collections = self::parseCollections($root, $opfPartName, $package, $manifestById, $prefixBindings);
        $bindings = self::parseBindings(self::firstChildElement($root, 'bindings', self::OPF_NAMESPACE), $manifestById, $package);
        $mediaOverlays = self::parseMediaOverlays($manifestById, $metadata, $package);
        $manifestFallbacks = self::manifestFallbackPreflight($manifestById, $package);
        $metadata['refinementTargets'] = self::metadataRefinementTargetReport(
            $metadata,
            $manifestItems,
            $spine,
            $packageLinks,
            $collections,
        );

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
        $packageBase = self::metadataElementBase($packageElement);
        $packageLanguage = self::metadataElementLanguage($packageElement);
        $packageDirection = self::metadataElementDirection($packageElement);
        $packageAttributes = self::elementAttributes($packageElement);
        $packageCustomAttributes = self::packageCustomAttributes($packageAttributes);

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
            $entry['propertyVocabulary'] = self::metadataMetaPropertyTokenReport(
                $property,
                $prefixBindings,
                count($meta),
            );
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
        $agentDisplayOrder = self::metadataAgentDisplayOrderReport($creatorDetails, $contributorDetails);
        $identifierDetails = self::metadataIdentifierDetails($dc['identifier'] ?? [], $uniqueIdentifierId);
        $uniqueIdentifier = self::metadataUniqueIdentifierReport($uniqueIdentifierId, $identifierDetails, $requiresUniqueIdentifier);
        $identifierSummary = self::metadataIdentifierSummary($identifierDetails, $uniqueIdentifier);
        $identifierDiagnostics = array_merge($uniqueIdentifier['diagnostics'], $identifierSummary['diagnostics']);
        $languageDetails = self::metadataLanguageDetails($dc['language'] ?? []);
        $dateDetails = self::metadataDateDetails($dc['date'] ?? []);
        $sourceDetails = self::metadataSourceDetails($dc['source'] ?? []);
        $subjectDetails = self::metadataSubjectDetails($dc['subject'] ?? []);
        $bibliographicDetails = self::metadataBibliographicDetails($dc);
        $bibliographicDetailsByKind = self::metadataBibliographicDetailsByKind($bibliographicDetails);
        $rightsDetails = $bibliographicDetailsByKind['rights'] ?? [];
        $rightsSummary = self::metadataRightsSummary($rightsDetails);
        $renditionLayout = self::metadataRenditionLayoutReport($metaProperties);
        $metaPropertyVocabulary = self::metadataMetaPropertyVocabularySummary($meta);
        $identifier = is_string($uniqueIdentifier['value'] ?? null) ? $uniqueIdentifier['value'] : '';
        $packageRefinements = $packageId !== null && isset($refinementsById[$packageId]) && is_array($refinementsById[$packageId])
            ? $refinementsById[$packageId]
            : [];

        return [
            'package' => [
                'id' => $packageId,
                'version' => $packageVersion,
                'uniqueIdentifierId' => $uniqueIdentifierId,
                'base' => $packageBase,
                'language' => $packageLanguage,
                'direction' => $packageDirection,
                'prefix' => $prefixReport['raw'],
                'prefixDeclarations' => $prefixReport['bindings'],
                'prefixBindings' => $prefixBindings,
                'refinements' => $packageRefinements,
                'attributes' => $packageAttributes,
                'attributeCount' => count($packageAttributes),
                'customAttributes' => $packageCustomAttributes,
                'customAttributeCount' => count($packageCustomAttributes),
            ],
            'packageId' => $packageId,
            'packageBase' => $packageBase,
            'packageLanguage' => $packageLanguage,
            'packageDirection' => $packageDirection,
            'packageAttributes' => $packageAttributes,
            'packageCustomAttributes' => $packageCustomAttributes,
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
            'agentDisplayOrder' => $agentDisplayOrder,
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
            'subjectDetails' => $subjectDetails,
            'subjectsByScheme' => self::metadataDetailsByField($subjectDetails, 'scheme'),
            'subjectsByAuthority' => self::metadataDetailsByField($subjectDetails, 'authority'),
            'subjectsByTerm' => self::metadataDetailsByField($subjectDetails, 'term'),
            'subjectSummary' => self::metadataSubjectSummary($subjectDetails),
            'rights' => array_map(static fn (array $entry): string => (string) $entry['text'], $dc['rights'] ?? []),
            'rightsDetails' => $rightsDetails,
            'rightsSummary' => $rightsSummary,
            'description' => $dc['description'][0]['text'] ?? null,
            'publisher' => $dc['publisher'][0]['text'] ?? null,
            'bibliographicDetails' => $bibliographicDetails,
            'bibliographicDetailsByKind' => $bibliographicDetailsByKind,
            'bibliographicSummary' => self::metadataBibliographicSummary($bibliographicDetails),
            'renditionLayout' => $renditionLayout,
            'modified' => $propertyValues['dcterms:modified'][0] ?? null,
            'properties' => $propertyValues,
            'dc' => $dc,
            'metaProperties' => $metaProperties,
            'metaPropertyVocabulary' => $metaPropertyVocabulary,
            'metaPropertyDiagnostics' => $metaPropertyVocabulary['diagnostics'],
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
     * @param array<string, string> $prefixBindings
     *
     * @return array<string, mixed>
     */
    private static function metadataMetaPropertyTokenReport(?string $property, array $prefixBindings, int $metaIndex): array
    {
        $property = is_string($property) ? trim($property) : '';
        $tokens = $property !== '' ? [$property] : [];
        $report = self::linkVocabularyTokenReport(
            $tokens,
            $prefixBindings,
            'property',
            $metaIndex,
            'metadata-meta',
            'EPUB OPF metadata meta',
        );
        $diagnostics = [];
        foreach ($report['diagnostics'] as $diagnostic) {
            if (!is_array($diagnostic)) {
                continue;
            }

            $diagnostics[] = ['metaIndex' => $metaIndex, 'property' => $property] + $diagnostic;
        }

        $items = [];
        foreach ($report['items'] as $item) {
            if (!is_array($item)) {
                continue;
            }

            $items[] = ['metaIndex' => $metaIndex, 'property' => $property] + $item;
        }

        $report['metaIndex'] = $metaIndex;
        $report['property'] = $property !== '' ? $property : null;
        $report['present'] = $property !== '';
        $report['valid'] = $diagnostics === [];
        $report['diagnosticCount'] = count($diagnostics);
        $report['items'] = $items;
        $report['diagnostics'] = $diagnostics;

        return $report;
    }

    /**
     * @param list<array<string, mixed>> $meta
     *
     * @return array<string, mixed>
     */
    private static function metadataMetaPropertyVocabularySummary(array $meta): array
    {
        $items = [];
        $invalidItems = [];
        $diagnostics = [];
        $propertyCounts = [];
        $validCount = 0;
        $resolvedCount = 0;
        $absoluteUrlCount = 0;

        foreach ($meta as $metaIndex => $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $property = is_string($entry['property'] ?? null) ? trim($entry['property']) : '';
            if ($property === '') {
                continue;
            }

            $report = is_array($entry['propertyVocabulary'] ?? null)
                ? $entry['propertyVocabulary']
                : self::metadataMetaPropertyTokenReport($property, [], (int) $metaIndex);
            $reportItems = is_array($report['items'] ?? null) ? array_values($report['items']) : [];
            $tokenItem = is_array($reportItems[0] ?? null) ? $reportItems[0] : [];
            $itemDiagnostics = is_array($report['diagnostics'] ?? null) ? array_values($report['diagnostics']) : [];
            $item = [
                'metaIndex' => (int) $metaIndex,
                'property' => $property,
                'id' => is_string($entry['id'] ?? null) ? $entry['id'] : null,
                'refines' => is_string($entry['refines'] ?? null) ? $entry['refines'] : null,
                'subjectId' => is_string($entry['subjectId'] ?? null) ? $entry['subjectId'] : null,
                'value' => is_string($entry['content'] ?? null) ? $entry['content'] : '',
                'kind' => is_string($tokenItem['kind'] ?? null) ? $tokenItem['kind'] : null,
                'prefix' => is_string($tokenItem['prefix'] ?? null) ? $tokenItem['prefix'] : null,
                'localName' => is_string($tokenItem['localName'] ?? null) ? $tokenItem['localName'] : null,
                'iri' => is_string($tokenItem['iri'] ?? null) ? $tokenItem['iri'] : null,
                'resolved' => ($tokenItem['resolved'] ?? false) === true,
                'absoluteUrlWithFragment' => ($tokenItem['absoluteUrlWithFragment'] ?? false) === true,
                'valid' => $itemDiagnostics === [],
                'diagnostics' => $itemDiagnostics,
            ];

            $propertyCounts[$property] = ($propertyCounts[$property] ?? 0) + 1;
            $items[] = $item;
            if ($item['valid']) {
                ++$validCount;
            } else {
                $invalidItems[] = $item;
            }
            if ($item['resolved']) {
                ++$resolvedCount;
            }
            if ($item['absoluteUrlWithFragment']) {
                ++$absoluteUrlCount;
            }
            foreach ($itemDiagnostics as $diagnostic) {
                if (is_array($diagnostic)) {
                    $diagnostics[] = $diagnostic;
                }
            }
        }

        ksort($propertyCounts, SORT_STRING);

        return [
            'present' => $items !== [],
            'propertyCount' => count($items),
            'validCount' => $validCount,
            'diagnosticPropertyCount' => count($invalidItems),
            'resolvedCount' => $resolvedCount,
            'absoluteUrlCount' => $absoluteUrlCount,
            'duplicatePropertyCount' => count(array_filter(
                $propertyCounts,
                static fn (int $count): bool => $count > 1,
            )),
            'properties' => array_keys($propertyCounts),
            'propertyCounts' => $propertyCounts,
            'items' => $items,
            'diagnosticItems' => $invalidItems,
            'diagnosticCount' => count($diagnostics),
            'diagnostics' => $diagnostics,
        ];
    }

    /**
     * @param array<string, mixed> $metadata
     * @param list<array<string, mixed>> $manifestItems
     * @param list<array<string, mixed>> $spine
     * @param list<array<string, mixed>> $packageLinks
     * @param list<array<string, mixed>> $collections
     *
     * @return array<string, mixed>
     */
    private static function metadataRefinementTargetReport(
        array $metadata,
        array $manifestItems,
        array $spine,
        array $packageLinks,
        array $collections
    ): array {
        $targetItems = [];
        $targetsById = [];
        $targetKindCounts = [];

        $addTarget = static function (?string $id, string $kind, array $context = []) use (&$targetItems, &$targetsById, &$targetKindCounts): void {
            $id = is_string($id) ? trim($id) : '';
            if ($id === '') {
                return;
            }

            $item = [
                'id' => $id,
                'kind' => $kind,
            ] + $context;
            $targetItems[] = $item;
            $targetsById[$id][] = $item;
            $targetKindCounts[$kind] = ($targetKindCounts[$kind] ?? 0) + 1;
        };

        $addTarget(is_string($metadata['packageId'] ?? null) ? $metadata['packageId'] : null, 'package');

        foreach (is_array($metadata['dc'] ?? null) ? $metadata['dc'] : [] as $name => $entries) {
            if (!is_array($entries)) {
                continue;
            }

            foreach ($entries as $index => $entry) {
                if (!is_array($entry)) {
                    continue;
                }

                $addTarget(
                    is_string($entry['id'] ?? null) ? $entry['id'] : null,
                    'dc-metadata',
                    [
                        'name' => is_string($name) ? $name : (string) $name,
                        'index' => (int) $index,
                    ],
                );
            }
        }

        foreach (is_array($metadata['meta'] ?? null) ? $metadata['meta'] : [] as $index => $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $addTarget(
                is_string($entry['id'] ?? null) ? $entry['id'] : null,
                'metadata-meta',
                [
                    'index' => (int) $index,
                    'property' => is_string($entry['property'] ?? null) ? $entry['property'] : null,
                    'name' => is_string($entry['name'] ?? null) ? $entry['name'] : null,
                ],
            );
        }

        foreach ($packageLinks as $index => $link) {
            if (!is_array($link)) {
                continue;
            }

            $addTarget(
                is_string($link['id'] ?? null) ? $link['id'] : null,
                'metadata-link',
                [
                    'index' => (int) $index,
                    'href' => is_string($link['href'] ?? null) ? $link['href'] : null,
                ],
            );
        }

        foreach ($manifestItems as $index => $item) {
            if (!is_array($item)) {
                continue;
            }

            $addTarget(
                is_string($item['id'] ?? null) ? $item['id'] : null,
                'manifest-item',
                [
                    'index' => (int) $index,
                    'href' => is_string($item['href'] ?? null) ? $item['href'] : null,
                    'partName' => is_string($item['partName'] ?? null) ? $item['partName'] : null,
                    'mediaType' => is_string($item['mediaType'] ?? null) ? $item['mediaType'] : null,
                ],
            );
        }

        foreach ($spine as $index => $item) {
            if (!is_array($item)) {
                continue;
            }

            $addTarget(
                is_string($item['id'] ?? null) ? $item['id'] : null,
                'spine-itemref',
                [
                    'index' => (int) $index,
                    'idref' => is_string($item['idref'] ?? null) ? $item['idref'] : null,
                    'partName' => is_string($item['partName'] ?? null) ? $item['partName'] : null,
                ],
            );
        }

        $appendCollectionTargets = static function (array $items, array $path = []) use (&$appendCollectionTargets, $addTarget): void {
            foreach ($items as $index => $collection) {
                if (!is_array($collection)) {
                    continue;
                }

                $currentPath = array_merge($path, [(int) $index]);
                $addTarget(
                    is_string($collection['id'] ?? null) ? $collection['id'] : null,
                    'collection',
                    [
                        'index' => (int) $index,
                        'path' => $currentPath,
                        'role' => is_string($collection['role'] ?? null) ? $collection['role'] : null,
                    ],
                );

                foreach (is_array($collection['links'] ?? null) ? $collection['links'] : [] as $linkIndex => $link) {
                    if (!is_array($link)) {
                        continue;
                    }

                    $addTarget(
                        is_string($link['id'] ?? null) ? $link['id'] : null,
                        'collection-link',
                        [
                            'index' => (int) $linkIndex,
                            'collectionPath' => $currentPath,
                            'href' => is_string($link['href'] ?? null) ? $link['href'] : null,
                        ],
                    );
                }

                $collectionMetadata = is_array($collection['metadata'] ?? null) ? $collection['metadata'] : [];
                foreach (is_array($collectionMetadata['dc'] ?? null) ? $collectionMetadata['dc'] : [] as $name => $entries) {
                    if (!is_array($entries)) {
                        continue;
                    }

                    foreach ($entries as $entryIndex => $entry) {
                        if (!is_array($entry)) {
                            continue;
                        }

                        $addTarget(
                            is_string($entry['id'] ?? null) ? $entry['id'] : null,
                            'collection-dc-metadata',
                            [
                                'name' => is_string($name) ? $name : (string) $name,
                                'index' => (int) $entryIndex,
                                'collectionPath' => $currentPath,
                            ],
                        );
                    }
                }

                foreach (is_array($collectionMetadata['meta'] ?? null) ? $collectionMetadata['meta'] : [] as $entryIndex => $entry) {
                    if (!is_array($entry)) {
                        continue;
                    }

                    $addTarget(
                        is_string($entry['id'] ?? null) ? $entry['id'] : null,
                        'collection-metadata-meta',
                        [
                            'index' => (int) $entryIndex,
                            'collectionPath' => $currentPath,
                            'property' => is_string($entry['property'] ?? null) ? $entry['property'] : null,
                        ],
                    );
                }

                $appendCollectionTargets(
                    is_array($collection['children'] ?? null) ? $collection['children'] : [],
                    $currentPath,
                );
            }
        };
        $appendCollectionTargets($collections);

        ksort($targetKindCounts, SORT_STRING);
        $duplicateTargetItems = [];
        foreach ($targetsById as $id => $targets) {
            if (count($targets) < 2) {
                continue;
            }

            $duplicateTargetItems[] = [
                'id' => $id,
                'targetCount' => count($targets),
                'targetKinds' => array_values(array_unique(array_map(
                    static fn (array $target): string => (string) ($target['kind'] ?? ''),
                    $targets,
                ))),
                'targets' => array_values($targets),
            ];
        }

        $items = [];
        $resolvedItems = [];
        $unresolvedItems = [];
        $externalItems = [];
        $packageRelativeItems = [];
        $diagnostics = [];

        foreach (is_array($metadata['meta'] ?? null) ? $metadata['meta'] : [] as $index => $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $refines = is_string($entry['refines'] ?? null) ? trim($entry['refines']) : '';
            if ($refines === '') {
                continue;
            }

            $subjectId = is_string($entry['subjectId'] ?? null)
                ? trim($entry['subjectId'])
                : (self::metadataRefinementSubject($refines) ?? '');
            $targetLocal = str_starts_with($refines, '#');
            $targetExternal = !$targetLocal && self::isAbsoluteUri($refines);
            $targetPackageRelative = !$targetLocal && !$targetExternal && str_contains($refines, '#');
            $targets = $targetLocal && $subjectId !== '' && isset($targetsById[$subjectId])
                ? array_values($targetsById[$subjectId])
                : [];
            $targetKinds = array_values(array_unique(array_map(
                static fn (array $target): string => (string) ($target['kind'] ?? ''),
                $targets,
            )));
            $itemDiagnostics = [];
            if ($subjectId === '') {
                $itemDiagnostics[] = [
                    'type' => 'invalid-metadata-refinement-target',
                    'metaIndex' => (int) $index,
                    'id' => is_string($entry['id'] ?? null) ? $entry['id'] : null,
                    'property' => is_string($entry['property'] ?? null) ? $entry['property'] : null,
                    'refines' => $refines,
                    'message' => 'EPUB OPF metadata refinement target must include a fragment identifier',
                ];
            } elseif ($targetLocal && $targets === []) {
                $itemDiagnostics[] = [
                    'type' => 'unresolved-metadata-refinement-target',
                    'metaIndex' => (int) $index,
                    'id' => is_string($entry['id'] ?? null) ? $entry['id'] : null,
                    'property' => is_string($entry['property'] ?? null) ? $entry['property'] : null,
                    'refines' => $refines,
                    'subjectId' => $subjectId,
                    'message' => 'EPUB OPF metadata refinement points at a local package subject id that was not found in the compact handoff target inventory',
                ];
            }

            $item = [
                'metaIndex' => (int) $index,
                'id' => is_string($entry['id'] ?? null) ? $entry['id'] : null,
                'property' => is_string($entry['property'] ?? null) ? $entry['property'] : null,
                'refines' => $refines,
                'subjectId' => $subjectId === '' ? null : $subjectId,
                'value' => self::metadataEntryValue($entry),
                'targetLocal' => $targetLocal,
                'targetExternal' => $targetExternal,
                'targetPackageRelative' => $targetPackageRelative,
                'resolved' => $targets !== [],
                'targetCount' => count($targets),
                'targetKinds' => $targetKinds,
                'targets' => $targets,
                'diagnostics' => $itemDiagnostics,
            ];
            $items[] = $item;

            if ($item['resolved']) {
                $resolvedItems[] = $item;
            } else {
                $unresolvedItems[] = $item;
            }

            if ($targetExternal) {
                $externalItems[] = $item;
            } elseif ($targetPackageRelative) {
                $packageRelativeItems[] = $item;
            }

            array_push($diagnostics, ...$itemDiagnostics);
        }

        return [
            'present' => $items !== [],
            'targetIdCount' => count($targetsById),
            'targetCount' => count($targetItems),
            'targetKindCounts' => $targetKindCounts,
            'targetItems' => $targetItems,
            'targetsById' => $targetsById,
            'duplicateTargetIdCount' => count($duplicateTargetItems),
            'duplicateTargetItems' => $duplicateTargetItems,
            'refinementCount' => count($items),
            'localRefinementCount' => count(array_filter(
                $items,
                static fn (array $item): bool => ($item['targetLocal'] ?? false) === true,
            )),
            'externalRefinementCount' => count($externalItems),
            'packageRelativeRefinementCount' => count($packageRelativeItems),
            'resolvedRefinementCount' => count($resolvedItems),
            'unresolvedRefinementCount' => count($unresolvedItems),
            'items' => $items,
            'resolvedItems' => $resolvedItems,
            'unresolvedItems' => $unresolvedItems,
            'externalItems' => $externalItems,
            'packageRelativeItems' => $packageRelativeItems,
            'diagnosticCount' => count($diagnostics),
            'diagnostics' => $diagnostics,
        ];
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
     * @param list<array<string, mixed>> $entries
     *
     * @return list<array<string, mixed>>
     */
    private static function metadataSubjectDetails(array $entries): array
    {
        $details = [];

        foreach ($entries as $index => $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $refinements = is_array($entry['refinements'] ?? null) ? $entry['refinements'] : [];
            $authorityEntries = self::metadataRefinementEntries($refinements, 'authority');
            $termEntries = self::metadataRefinementEntries($refinements, 'term');
            $displaySeq = self::firstMetadataRefinementValue($refinements, 'display-seq');
            $displaySeqNumber = self::metadataDisplaySeqNumber($displaySeq);
            $diagnostics = [];

            if ($displaySeq !== null && $displaySeqNumber === null) {
                $diagnostics[] = [
                    'type' => 'invalid-subject-display-seq',
                    'id' => is_string($entry['id'] ?? null) ? $entry['id'] : null,
                    'index' => (int) $index,
                    'value' => $displaySeq,
                    'message' => 'EPUB OPF subject display-seq metadata should be a positive integer',
                ];
            }

            $details[] = [
                'kind' => 'subject',
                'index' => (int) $index,
                'text' => (string) ($entry['text'] ?? ''),
                'id' => is_string($entry['id'] ?? null) ? $entry['id'] : null,
                'scheme' => is_string($entry['scheme'] ?? null) ? $entry['scheme'] : null,
                'language' => is_string($entry['language'] ?? null) ? $entry['language'] : null,
                'direction' => is_string($entry['direction'] ?? null) ? $entry['direction'] : null,
                'displaySeq' => $displaySeq,
                'displaySeqNumber' => $displaySeqNumber,
                'displaySeqValid' => $displaySeq === null || $displaySeqNumber !== null,
                'fileAs' => self::firstMetadataRefinementValue($refinements, 'file-as'),
                'alternateScripts' => self::metadataRefinementEntries($refinements, 'alternate-script'),
                'authority' => is_array($authorityEntries[0] ?? null) ? (string) $authorityEntries[0]['value'] : null,
                'authorityEntries' => $authorityEntries,
                'term' => is_array($termEntries[0] ?? null) ? (string) $termEntries[0]['value'] : null,
                'termEntries' => $termEntries,
                'linkedResources' => [],
                'linkedResourceCount' => 0,
                'localLinkedResourceCount' => 0,
                'externalLinkedResourceCount' => 0,
                'missingLinkedResourceCount' => 0,
                'refinements' => $refinements,
                'diagnostics' => $diagnostics,
            ];
        }

        return $details;
    }

    /**
     * @param list<array<string, mixed>> $subjectDetails
     *
     * @return array<string, mixed>
     */
    private static function metadataSubjectSummary(array $subjectDetails): array
    {
        $schemes = [];
        $authorities = [];
        $terms = [];
        $sequencedCount = 0;
        $invalidDisplaySeqCount = 0;
        $alternateScriptCount = 0;
        $linkedResourceCount = 0;
        $localLinkedResourceCount = 0;
        $externalLinkedResourceCount = 0;
        $missingLinkedResourceCount = 0;
        $diagnostics = [];

        foreach ($subjectDetails as $detail) {
            if (!is_array($detail)) {
                continue;
            }

            $scheme = is_string($detail['scheme'] ?? null) ? trim($detail['scheme']) : '';
            if ($scheme !== '') {
                $schemes[$scheme] = $scheme;
            }

            $authority = is_string($detail['authority'] ?? null) ? trim($detail['authority']) : '';
            if ($authority !== '') {
                $authorities[$authority] = $authority;
            }

            $term = is_string($detail['term'] ?? null) ? trim($detail['term']) : '';
            if ($term !== '') {
                $terms[$term] = $term;
            }

            if (is_int($detail['displaySeqNumber'] ?? null)) {
                ++$sequencedCount;
            } elseif (is_string($detail['displaySeq'] ?? null) && $detail['displaySeq'] !== '') {
                ++$invalidDisplaySeqCount;
            }

            $alternateScripts = is_array($detail['alternateScripts'] ?? null) ? $detail['alternateScripts'] : [];
            $alternateScriptCount += count($alternateScripts);

            $linkedResources = is_array($detail['linkedResources'] ?? null) ? $detail['linkedResources'] : [];
            $linkedResourceCount += count($linkedResources);
            $localLinkedResourceCount += (int) ($detail['localLinkedResourceCount'] ?? 0);
            $externalLinkedResourceCount += (int) ($detail['externalLinkedResourceCount'] ?? 0);
            $missingLinkedResourceCount += (int) ($detail['missingLinkedResourceCount'] ?? 0);

            foreach (($detail['diagnostics'] ?? []) as $diagnostic) {
                if (is_array($diagnostic)) {
                    $diagnostics[] = $diagnostic;
                }
            }
        }

        return [
            'present' => $subjectDetails !== [],
            'count' => count($subjectDetails),
            'schemeCount' => count($schemes),
            'authorityCount' => count($authorities),
            'termCount' => count($terms),
            'sequencedCount' => $sequencedCount,
            'invalidDisplaySeqCount' => $invalidDisplaySeqCount,
            'alternateScriptCount' => $alternateScriptCount,
            'linkedResourceCount' => $linkedResourceCount,
            'localLinkedResourceCount' => $localLinkedResourceCount,
            'externalLinkedResourceCount' => $externalLinkedResourceCount,
            'missingLinkedResourceCount' => $missingLinkedResourceCount,
            'schemes' => array_values($schemes),
            'authorities' => array_values($authorities),
            'terms' => array_values($terms),
            'diagnostics' => $diagnostics,
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
     * @param array<string, mixed> $metadata
     * @param list<array<string, mixed>> $packageLinks
     *
     * @return array<string, mixed>
     */
    private static function attachPackageLinksToMetadata(array $metadata, array $packageLinks): array
    {
        $linksBySubjectId = [];
        foreach ($packageLinks as $link) {
            if (!is_array($link)) {
                continue;
            }

            $subjectId = is_string($link['subjectId'] ?? null) ? trim($link['subjectId']) : '';
            if ($subjectId === '') {
                continue;
            }

            $linksBySubjectId[$subjectId][] = $link;
        }

        if (is_array($metadata['bibliographicDetails'] ?? null)) {
            $metadata['bibliographicDetails'] = self::attachLinkedResourcesToMetadataDetails(
                $metadata['bibliographicDetails'],
                $linksBySubjectId,
            );
            $metadata['bibliographicDetailsByKind'] = self::metadataBibliographicDetailsByKind($metadata['bibliographicDetails']);
            $metadata['bibliographicSummary'] = self::metadataBibliographicSummary($metadata['bibliographicDetails']);
            $metadata['rightsDetails'] = $metadata['bibliographicDetailsByKind']['rights'] ?? [];
            $metadata['rightsSummary'] = self::metadataRightsSummary($metadata['rightsDetails']);
        }

        if (is_array($metadata['subjectDetails'] ?? null)) {
            $metadata['subjectDetails'] = self::attachLinkedResourcesToMetadataDetails(
                $metadata['subjectDetails'],
                $linksBySubjectId,
            );
            $metadata['subjectsByScheme'] = self::metadataDetailsByField($metadata['subjectDetails'], 'scheme');
            $metadata['subjectsByAuthority'] = self::metadataDetailsByField($metadata['subjectDetails'], 'authority');
            $metadata['subjectsByTerm'] = self::metadataDetailsByField($metadata['subjectDetails'], 'term');
            $metadata['subjectSummary'] = self::metadataSubjectSummary($metadata['subjectDetails']);
        }

        return $metadata;
    }

    /**
     * @param list<array<string, mixed>> $details
     * @param array<string, list<array<string, mixed>>> $linksBySubjectId
     *
     * @return list<array<string, mixed>>
     */
    private static function attachLinkedResourcesToMetadataDetails(array $details, array $linksBySubjectId): array
    {
        foreach ($details as $index => $detail) {
            if (!is_array($detail)) {
                continue;
            }

            $id = is_string($detail['id'] ?? null) ? trim($detail['id']) : '';
            $linkedResources = $id !== '' && isset($linksBySubjectId[$id])
                ? array_values($linksBySubjectId[$id])
                : [];

            $details[$index]['linkedResources'] = $linkedResources;
            $details[$index]['linkedResourceCount'] = count($linkedResources);
            $details[$index]['localLinkedResourceCount'] = count(array_filter(
                $linkedResources,
                static fn (array $link): bool => ($link['external'] ?? false) !== true
                    && is_string($link['partName'] ?? null),
            ));
            $details[$index]['externalLinkedResourceCount'] = count(array_filter(
                $linkedResources,
                static fn (array $link): bool => ($link['external'] ?? false) === true,
            ));
            $details[$index]['missingLinkedResourceCount'] = count(array_filter(
                $linkedResources,
                static fn (array $link): bool => ($link['external'] ?? false) !== true
                    && ($link['exists'] ?? false) !== true,
            ));
        }

        return $details;
    }

    /**
     * @param list<array<string, mixed>> $rightsDetails
     *
     * @return array<string, mixed>
     */
    private static function metadataRightsSummary(array $rightsDetails): array
    {
        $authorities = [];
        $terms = [];
        $relCounts = [];
        $diagnostics = [];
        $linkedResourceCount = 0;
        $localLinkedResourceCount = 0;
        $externalLinkedResourceCount = 0;
        $missingLinkedResourceCount = 0;

        foreach ($rightsDetails as $detail) {
            if (!is_array($detail)) {
                continue;
            }

            if (is_string($detail['authority'] ?? null) && $detail['authority'] !== '') {
                $authorities[$detail['authority']] = $detail['authority'];
            }
            if (is_string($detail['term'] ?? null) && $detail['term'] !== '') {
                $terms[$detail['term']] = $detail['term'];
            }

            $linkedResources = is_array($detail['linkedResources'] ?? null) ? $detail['linkedResources'] : [];
            $linkedResourceCount += count($linkedResources);
            foreach ($linkedResources as $link) {
                if (!is_array($link)) {
                    continue;
                }

                if (($link['external'] ?? false) === true) {
                    ++$externalLinkedResourceCount;
                } elseif (is_string($link['partName'] ?? null)) {
                    ++$localLinkedResourceCount;
                }

                if (($link['external'] ?? false) !== true && ($link['exists'] ?? false) !== true) {
                    ++$missingLinkedResourceCount;
                }

                foreach (is_array($link['rel'] ?? null) ? $link['rel'] : [] as $rel) {
                    if (!is_string($rel) || $rel === '') {
                        continue;
                    }

                    $relCounts[$rel] = ($relCounts[$rel] ?? 0) + 1;
                }

                foreach (is_array($link['diagnostics'] ?? null) ? $link['diagnostics'] : [] as $diagnostic) {
                    if (!is_array($diagnostic)) {
                        continue;
                    }

                    $diagnostics[] = [
                        'rightsIndex' => is_int($detail['index'] ?? null) ? $detail['index'] : null,
                        'rightsId' => is_string($detail['id'] ?? null) ? $detail['id'] : null,
                        'linkIndex' => is_int($link['index'] ?? null) ? $link['index'] : null,
                        'linkId' => is_string($link['id'] ?? null) ? $link['id'] : null,
                    ] + $diagnostic;
                }
            }
        }

        return [
            'present' => $rightsDetails !== [],
            'count' => count($rightsDetails),
            'authorityCount' => count($authorities),
            'authorities' => array_values($authorities),
            'termCount' => count($terms),
            'terms' => array_values($terms),
            'linkedResourceCount' => $linkedResourceCount,
            'localLinkedResourceCount' => $localLinkedResourceCount,
            'externalLinkedResourceCount' => $externalLinkedResourceCount,
            'missingLinkedResourceCount' => $missingLinkedResourceCount,
            'linkedResourceRelCounts' => $relCounts,
            'diagnosticCount' => count($diagnostics),
            'diagnostics' => $diagnostics,
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
                    if (is_string($role) && $role !== '') {
                        $roles[$role] = $role;
                    }
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
                if (is_string($role) && $role !== '') {
                    $byRole[$role][] = $item;
                }
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

    private static function metadataElementBase(\DOMElement $element): ?string
    {
        if ($element->hasAttributeNS('http://www.w3.org/XML/1998/namespace', 'base')) {
            return self::emptyToNull($element->getAttributeNS('http://www.w3.org/XML/1998/namespace', 'base'));
        }

        return self::emptyToNull($element->getAttribute('xml:base'));
    }

    private static function metadataElementDirection(\DOMElement $element): ?string
    {
        return self::emptyToNull($element->getAttribute('dir'));
    }

    /**
     * @param array<string, string> $attributes
     *
     * @return array<string, string>
     */
    private static function packageCustomAttributes(array $attributes): array
    {
        $custom = [];
        foreach ($attributes as $name => $value) {
            if (!is_string($name) || !is_string($value)) {
                continue;
            }
            if (isset(self::OPF_PACKAGE_STRUCTURAL_ATTRIBUTES[$name]) || $name === 'xmlns' || str_starts_with($name, 'xmlns:')) {
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
    private static function manifestItemCustomAttributes(array $attributes): array
    {
        $custom = [];
        foreach ($attributes as $name => $value) {
            if (!is_string($name) || !is_string($value)) {
                continue;
            }
            if (isset(self::OPF_MANIFEST_ITEM_STRUCTURAL_ATTRIBUTES[$name])) {
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
     * @param array<string, mixed> $metadata
     *
     * @return array<string, mixed>
     */
    private static function packageAuthoringReport(array $metadata): array
    {
        $package = is_array($metadata['package'] ?? null) ? $metadata['package'] : [];
        $attributes = [];
        foreach (is_array($package['attributes'] ?? null) ? $package['attributes'] : [] as $name => $value) {
            if (is_string($name) && is_string($value)) {
                $attributes[$name] = $value;
            }
        }

        $customAttributes = [];
        foreach (is_array($package['customAttributes'] ?? null) ? $package['customAttributes'] : self::packageCustomAttributes($attributes) as $name => $value) {
            if (is_string($name) && is_string($value)) {
                $customAttributes[$name] = $value;
            }
        }

        return [
            'present' => $attributes !== [],
            'id' => is_string($package['id'] ?? null) ? $package['id'] : null,
            'version' => is_string($package['version'] ?? null) ? $package['version'] : '',
            'uniqueIdentifierId' => is_string($package['uniqueIdentifierId'] ?? null) ? $package['uniqueIdentifierId'] : null,
            'base' => is_string($package['base'] ?? null) ? $package['base'] : null,
            'language' => is_string($package['language'] ?? null) ? $package['language'] : null,
            'direction' => is_string($package['direction'] ?? null) ? $package['direction'] : null,
            'prefix' => is_string($package['prefix'] ?? null) ? $package['prefix'] : '',
            'attributes' => $attributes,
            'attributeCount' => count($attributes),
            'customAttributes' => $customAttributes,
            'customAttributeCount' => count($customAttributes),
            'hasBase' => is_string($package['base'] ?? null) && $package['base'] !== '',
            'hasLanguage' => is_string($package['language'] ?? null) && $package['language'] !== '',
            'hasDirection' => is_string($package['direction'] ?? null) && $package['direction'] !== '',
            'hasCustomAttributes' => $customAttributes !== [],
        ];
    }

    /**
     * @param list<array<string, mixed>> $manifestItems
     *
     * @return array<string, mixed>
     */
    private static function manifestItemAuthoringReport(array $manifestItems): array
    {
        $items = [];
        $itemsById = [];
        $languageItems = [];
        $directionItems = [];
        $customAttributeItems = [];

        foreach ($manifestItems as $item) {
            $attributes = is_array($item['attributes'] ?? null) ? $item['attributes'] : [];
            $customAttributes = is_array($item['customAttributes'] ?? null)
                ? $item['customAttributes']
                : self::manifestItemCustomAttributes($attributes);
            $summary = [
                'id' => (string) ($item['id'] ?? ''),
                'href' => (string) ($item['href'] ?? ''),
                'target' => is_string($item['target'] ?? null) ? $item['target'] : null,
                'partName' => is_string($item['partName'] ?? null) ? $item['partName'] : null,
                'mediaType' => is_string($item['mediaType'] ?? null) ? $item['mediaType'] : null,
                'language' => is_string($item['language'] ?? null) ? $item['language'] : null,
                'direction' => is_string($item['direction'] ?? null) ? $item['direction'] : null,
                'attributes' => $attributes,
                'attributeCount' => count($attributes),
                'customAttributes' => $customAttributes,
                'customAttributeCount' => count($customAttributes),
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
            'customAttributeItemCount' => count($customAttributeItems),
            'customAttributeItems' => $customAttributeItems,
        ];
    }

    /**
     * @param list<array<string, mixed>> $manifestItems
     *
     * @return array<string, mixed>
     */
    private static function manifestResourceKindReport(array $manifestItems): array
    {
        $items = [];
        $itemsById = [];
        $itemsByKind = [];
        $kindCounts = [];
        $kindPartNames = [];
        $mediaTypeBaseCounts = [];
        $existingCount = 0;
        $missingCount = 0;
        $externalCount = 0;
        $exposableCount = 0;

        foreach ($manifestItems as $index => $item) {
            $mediaType = is_string($item['mediaType'] ?? null) ? $item['mediaType'] : '';
            $baseMediaType = is_string($item['mediaTypeBase'] ?? null)
                ? $item['mediaTypeBase']
                : self::mediaTypeBase($mediaType);
            $properties = is_array($item['properties'] ?? null) ? array_values(array_filter(
                $item['properties'],
                static fn (mixed $property): bool => is_string($property) && $property !== '',
            )) : [];
            $partName = is_string($item['partName'] ?? null) ? $item['partName'] : null;
            $target = is_string($item['target'] ?? null) ? $item['target'] : null;
            $href = is_string($item['href'] ?? null) ? $item['href'] : '';
            $packagePath = self::packageInventoryEntryName($partName ?? $target ?? $href) ?? $href;
            $kind = self::packageInventoryResourceKind($mediaType, $packagePath, $properties);
            $exists = ($item['exists'] ?? false) === true;
            $external = ($item['external'] ?? false) === true;
            $canExposeBytes = ($item['canExposeBytes'] ?? false) === true;

            $reviewItem = [
                'index' => (int) $index,
                'id' => is_string($item['id'] ?? null) ? $item['id'] : '',
                'href' => $href,
                'target' => $target,
                'partName' => $partName,
                'mediaType' => $mediaType,
                'mediaTypeBase' => $baseMediaType,
                'properties' => $properties,
                'resourceKind' => $kind,
                'exists' => $exists,
                'external' => $external,
                'canExposeBytes' => $canExposeBytes,
                'byteLength' => is_int($item['byteLength'] ?? null) ? $item['byteLength'] : null,
                'compressedByteLength' => is_int($item['compressedByteLength'] ?? null) ? $item['compressedByteLength'] : null,
                'compressionMethod' => is_int($item['compressionMethod'] ?? null) ? $item['compressionMethod'] : null,
                'compressionMethodName' => is_string($item['compressionMethodName'] ?? null) ? $item['compressionMethodName'] : null,
            ];

            $items[] = $reviewItem;
            if ($reviewItem['id'] !== '') {
                $itemsById[$reviewItem['id']] = $reviewItem;
            }
            $itemsByKind[$kind][] = $reviewItem;
            $kindCounts[$kind] = ($kindCounts[$kind] ?? 0) + 1;
            if ($partName !== null && $partName !== '') {
                $kindPartNames[$kind][$partName] = $partName;
            }
            if ($baseMediaType !== '') {
                $mediaTypeBaseCounts[$baseMediaType] = ($mediaTypeBaseCounts[$baseMediaType] ?? 0) + 1;
            }
            if ($exists) {
                ++$existingCount;
            } else {
                ++$missingCount;
            }
            if ($external) {
                ++$externalCount;
            }
            if ($canExposeBytes) {
                ++$exposableCount;
            }
        }

        ksort($itemsById, SORT_STRING);
        ksort($itemsByKind, SORT_STRING);
        ksort($kindCounts, SORT_STRING);
        ksort($kindPartNames, SORT_STRING);
        ksort($mediaTypeBaseCounts, SORT_STRING);

        foreach ($kindPartNames as $kind => $partNames) {
            $kindPartNames[$kind] = array_values($partNames);
            sort($kindPartNames[$kind], SORT_STRING);
        }

        return [
            'present' => $items !== [],
            'itemCount' => count($items),
            'kindCount' => count($kindCounts),
            'kinds' => array_keys($kindCounts),
            'kindCounts' => $kindCounts,
            'kindPartNames' => $kindPartNames,
            'mediaTypeBaseCounts' => $mediaTypeBaseCounts,
            'existingItemCount' => $existingCount,
            'missingItemCount' => $missingCount,
            'externalItemCount' => $externalCount,
            'exposableItemCount' => $exposableCount,
            'summary' => [
                'itemCount' => count($items),
                'kindCount' => count($kindCounts),
                'kinds' => array_keys($kindCounts),
                'kindCounts' => $kindCounts,
                'existingItemCount' => $existingCount,
                'missingItemCount' => $missingCount,
                'externalItemCount' => $externalCount,
                'exposableItemCount' => $exposableCount,
            ],
            'items' => $items,
            'itemsById' => $itemsById,
            'itemsByKind' => $itemsByKind,
        ];
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
            $index = count($items);
            $attributes = is_array($item['attributes'] ?? null) ? $item['attributes'] : [];
            $customAttributes = is_array($item['customAttributes'] ?? null)
                ? $item['customAttributes']
                : self::spineItemrefCustomAttributes($attributes);
            $summary = [
                'index' => $index,
                'id' => is_string($item['id'] ?? null) ? $item['id'] : null,
                'idref' => (string) ($item['idref'] ?? ''),
                'href' => is_string($item['href'] ?? null) ? $item['href'] : null,
                'partName' => is_string($item['partName'] ?? null) ? $item['partName'] : null,
                'mediaType' => is_string($item['mediaType'] ?? null) ? $item['mediaType'] : null,
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
     * @param list<array<string, mixed>> $rootfiles
     * @param list<array<string, mixed>> $manifestItems
     * @param list<array<string, mixed>> $spine
     * @param array<string, mixed> $ocfSidecars
     * @param array<string, mixed> $encryption
     *
     * @return array<string, mixed>
     */
    private static function packageInventoryReport(
        ZipPackage $package,
        string $opfPartName,
        array $rootfiles,
        array $manifestItems,
        array $spine,
        array $ocfSidecars,
        array $encryption
    ): array {
        $manifestByPackagePath = [];
        $manifestDeclaredPartNames = [];
        $missingManifestDeclaredPartNames = [];
        foreach ($manifestItems as $index => $item) {
            $packagePath = self::packageInventoryEntryName($item['partName'] ?? null);
            if ($packagePath === null) {
                continue;
            }

            $manifestByPackagePath[$packagePath][] = ['index' => $index] + $item;
            $partName = self::packageInventoryPartName($packagePath);
            $manifestDeclaredPartNames[$partName] = true;
            if (($item['exists'] ?? false) !== true) {
                $missingManifestDeclaredPartNames[$partName] = true;
            }
        }

        $rootfilesByPackagePath = [];
        foreach ($rootfiles as $index => $rootfile) {
            $packagePath = self::packageInventoryEntryName($rootfile['partName'] ?? null);
            if ($packagePath !== null) {
                $rootfilesByPackagePath[$packagePath][] = ['index' => $index] + $rootfile;
            }
        }

        $spineByPackagePath = [];
        foreach ($spine as $index => $item) {
            $packagePath = self::packageInventoryEntryName($item['partName'] ?? null);
            if ($packagePath !== null) {
                $spineByPackagePath[$packagePath][] = ['index' => $index] + $item;
            }
        }

        $sidecarsByPackagePath = [];
        foreach (is_array($ocfSidecars['items'] ?? null) ? $ocfSidecars['items'] : [] as $sidecar) {
            if (!is_array($sidecar)) {
                continue;
            }

            $packagePath = self::packageInventoryEntryName($sidecar['partName'] ?? $sidecar['part'] ?? null);
            if ($packagePath !== null) {
                $sidecarsByPackagePath[$packagePath][] = $sidecar;
            }
        }

        $encryptionPackagePath = ($encryption['present'] ?? false) === true
            ? self::packageInventoryEntryName($encryption['part'] ?? null)
            : null;
        $encryptedResourcesByPackagePath = [];
        foreach (is_array($encryption['items'] ?? null) ? $encryption['items'] : [] as $item) {
            if (!is_array($item)) {
                continue;
            }

            $packagePath = self::packageInventoryEntryName($item['partName'] ?? null);
            if ($packagePath !== null) {
                $encryptedResourcesByPackagePath[$packagePath][] = $item;
            }
        }

        $opfPackagePath = self::packageInventoryEntryName($opfPartName);
        $localOrderByName = [];
        foreach ($package->localNames() as $index => $name) {
            $localOrderByName[$name] = $index;
        }

        $entries = [];
        $byPackagePath = [];
        $roleCounts = [];
        $roleByteLengths = [];
        $roleCompressedByteLengths = [];
        $resourceKindCounts = [];
        $resourceKindByteLengths = [];
        $resourceKindCompressedByteLengths = [];
        $undeclaredPartNames = [];
        $unsupportedCompressionPartNames = [];
        $encryptedPartNames = [];
        $obfuscatedFontPartNames = [];
        $spinePartNames = [];
        $opfManifestDeclaredEntryCount = 0;
        $spineEntryCount = 0;
        $encryptedEntryCount = 0;
        $obfuscatedFontEntryCount = 0;
        $unsupportedCompressionMethodCount = 0;
        $directoryEntryCount = 0;
        $totalByteLength = 0;
        $totalCompressedByteLength = 0;
        $exposableEntryCount = 0;
        $blockedEntryCount = 0;
        $exposableByteLength = 0;
        $exposableCompressedByteLength = 0;
        $blockedByteLength = 0;
        $blockedCompressedByteLength = 0;
        $unsupportedCompressionByteLength = 0;
        $unsupportedCompressionCompressedByteLength = 0;

        foreach ($package->entries() as $index => $entry) {
            $packagePath = $entry->name;
            $partName = self::packageInventoryPartName($packagePath);
            $manifestMatches = $manifestByPackagePath[$packagePath] ?? [];
            $rootfileMatches = $rootfilesByPackagePath[$packagePath] ?? [];
            $spineMatches = $spineByPackagePath[$packagePath] ?? [];
            $sidecarMatches = $sidecarsByPackagePath[$packagePath] ?? [];
            $encryptedMatches = $encryptedResourcesByPackagePath[$packagePath] ?? [];
            $declaredInOpfManifest = $manifestMatches !== [];
            $inSpine = $spineMatches !== [];
            $encrypted = $encryptedMatches !== [];
            $obfuscatedFont = count(array_filter(
                $encryptedMatches,
                static fn (array $item): bool => ($item['obfuscatedFont'] ?? false) === true
            )) > 0;
            $manifestItem = $manifestMatches[0] ?? null;
            $manifestIds = array_values(array_filter(
                array_map(
                    static fn (array $item): ?string => is_string($item['id'] ?? null) && $item['id'] !== '' ? $item['id'] : null,
                    $manifestMatches
                ),
                static fn (?string $id): bool => $id !== null
            ));
            $spineIndexes = array_map(
                static fn (array $item): int => (int) ($item['index'] ?? 0),
                $spineMatches
            );
            $sidecarKinds = array_values(array_filter(
                array_map(
                    static fn (array $item): ?string => is_string($item['kind'] ?? null) && $item['kind'] !== '' ? $item['kind'] : null,
                    $sidecarMatches
                ),
                static fn (?string $kind): bool => $kind !== null
            ));
            $mediaType = is_array($manifestItem) && is_string($manifestItem['mediaType'] ?? null)
                ? $manifestItem['mediaType']
                : null;
            $mediaTypeBase = $mediaType === null ? null : self::mediaTypeBase($mediaType);
            $properties = is_array($manifestItem['properties'] ?? null) ? array_values($manifestItem['properties']) : [];
            $resourceKind = is_array($manifestItem)
                ? self::packageInventoryResourceKind($mediaType, $packagePath, $properties)
                : null;
            $isMimetype = $packagePath === 'mimetype';
            $isContainer = $packagePath === 'META-INF/container.xml';
            $isOpfPackage = $opfPackagePath !== null && $packagePath === $opfPackagePath;
            $isRootfile = $rootfileMatches !== [];
            $isEncryptionSidecar = $encryptionPackagePath !== null && $packagePath === $encryptionPackagePath;
            $isSidecar = $sidecarMatches !== [];
            $declaredPackageEntry = $isMimetype
                || $isContainer
                || $isRootfile
                || $isOpfPackage
                || $isSidecar
                || $isEncryptionSidecar
                || $declaredInOpfManifest;
            $undeclared = !$declaredPackageEntry;
            $roles = [];
            $addRole = static function (string $role) use (&$roles): void {
                if (!in_array($role, $roles, true)) {
                    $roles[] = $role;
                }
            };

            if ($entry->isDirectory()) {
                $addRole('zip-directory');
                ++$directoryEntryCount;
            }
            if ($isMimetype) {
                $addRole('epub-mimetype');
                $addRole('ocf-core');
            }
            if ($isContainer) {
                $addRole('ocf-container');
                $addRole('ocf-core');
            }
            if (str_starts_with($packagePath, 'META-INF/')) {
                $addRole('ocf-meta-inf');
            }
            if ($isRootfile) {
                $addRole('container-rootfile');
            }
            if ($isOpfPackage) {
                $addRole('opf-package-document');
            }
            if ($isSidecar) {
                $addRole('ocf-sidecar');
                foreach ($sidecarKinds as $kind) {
                    $addRole('ocf-' . $kind . '-sidecar');
                }
            }
            if ($isEncryptionSidecar) {
                $addRole('ocf-encryption-sidecar');
            }
            if ($declaredInOpfManifest) {
                $addRole('opf-manifest-declared');
                ++$opfManifestDeclaredEntryCount;
            }
            if ($resourceKind !== null) {
                $addRole('resource-kind-' . $resourceKind);
                $resourceKindCounts[$resourceKind] = ($resourceKindCounts[$resourceKind] ?? 0) + 1;
                $resourceKindByteLengths[$resourceKind] = ($resourceKindByteLengths[$resourceKind] ?? 0) + $entry->uncompressedSize;
                $resourceKindCompressedByteLengths[$resourceKind] = ($resourceKindCompressedByteLengths[$resourceKind] ?? 0) + $entry->compressedSize;
            }
            if ($inSpine) {
                $addRole('spine-reading-order');
                ++$spineEntryCount;
                $spinePartNames[$partName] = true;
            }
            if ($encrypted) {
                $addRole('encrypted-resource');
                ++$encryptedEntryCount;
                $encryptedPartNames[$partName] = true;
            }
            if ($obfuscatedFont) {
                $addRole('obfuscated-font');
                ++$obfuscatedFontEntryCount;
                $obfuscatedFontPartNames[$partName] = true;
            }
            if ($undeclared) {
                $addRole('undeclared-package-entry');
                $undeclaredPartNames[$partName] = true;
            }

            $provenance = self::zipEntryProvenance($entry);
            if (($provenance['compressionSupported'] ?? false) !== true) {
                ++$unsupportedCompressionMethodCount;
                $unsupportedCompressionPartNames[$partName] = true;
                $unsupportedCompressionByteLength += $entry->uncompressedSize;
                $unsupportedCompressionCompressedByteLength += $entry->compressedSize;
            }
            $totalByteLength += $entry->uncompressedSize;
            $totalCompressedByteLength += $entry->compressedSize;

            $item = [
                'index' => $index,
                'localOrder' => $localOrderByName[$packagePath] ?? null,
                'packagePath' => $packagePath,
                'partName' => $partName,
                'isDirectory' => $entry->isDirectory(),
                'declaredPackageEntry' => $declaredPackageEntry,
                'undeclared' => $undeclared,
                'declaredInOpfManifest' => $declaredInOpfManifest,
                'manifestIds' => $manifestIds,
                'manifestItemCount' => count($manifestMatches),
                'inSpine' => $inSpine,
                'spineIndexes' => $spineIndexes,
                'rootfile' => $isRootfile,
                'rootfileIndexes' => array_map(
                    static fn (array $item): int => (int) ($item['index'] ?? 0),
                    $rootfileMatches
                ),
                'ocfSidecar' => $isSidecar,
                'ocfSidecarKinds' => $sidecarKinds,
                'encryptionSidecar' => $isEncryptionSidecar,
                'encrypted' => $encrypted,
                'obfuscatedFont' => $obfuscatedFont,
                'mediaType' => $mediaType,
                'mediaTypeBase' => $mediaTypeBase,
                'properties' => $properties,
                'resourceKind' => $resourceKind,
                'roles' => $roles,
            ] + $provenance;
            $item['byteExposurePolicy'] = 'epub-package-entry-metadata-only';
            if ($encrypted) {
                $item['canExposeBytes'] = false;
                $item['byteExposurePolicy'] = $obfuscatedFont
                    ? 'obfuscated-font-bytes-blocked'
                    : 'encrypted-resource-bytes-blocked';
            }

            foreach ($roles as $role) {
                $roleCounts[$role] = ($roleCounts[$role] ?? 0) + 1;
                $roleByteLengths[$role] = ($roleByteLengths[$role] ?? 0) + $entry->uncompressedSize;
                $roleCompressedByteLengths[$role] = ($roleCompressedByteLengths[$role] ?? 0) + $entry->compressedSize;
            }
            if (($item['canExposeBytes'] ?? false) === true) {
                ++$exposableEntryCount;
                $exposableByteLength += $entry->uncompressedSize;
                $exposableCompressedByteLength += $entry->compressedSize;
            } else {
                ++$blockedEntryCount;
                $blockedByteLength += $entry->uncompressedSize;
                $blockedCompressedByteLength += $entry->compressedSize;
            }

            $entries[] = $item;
            $byPackagePath[$packagePath] = $item;
        }

        ksort($roleCounts, SORT_STRING);
        ksort($roleByteLengths, SORT_STRING);
        ksort($roleCompressedByteLengths, SORT_STRING);
        ksort($resourceKindCounts, SORT_STRING);
        ksort($resourceKindByteLengths, SORT_STRING);
        ksort($resourceKindCompressedByteLengths, SORT_STRING);

        return [
            'entryCount' => count($entries),
            'fileEntryCount' => count($entries) - $directoryEntryCount,
            'directoryEntryCount' => $directoryEntryCount,
            'opfManifestDeclaredEntryCount' => $opfManifestDeclaredEntryCount,
            'opfManifestDeclaredPartCount' => count($manifestDeclaredPartNames),
            'missingOpfManifestDeclaredPartCount' => count($missingManifestDeclaredPartNames),
            'undeclaredEntryCount' => count($undeclaredPartNames),
            'spineEntryCount' => $spineEntryCount,
            'encryptedEntryCount' => $encryptedEntryCount,
            'obfuscatedFontEntryCount' => $obfuscatedFontEntryCount,
            'unsupportedCompressionMethodCount' => $unsupportedCompressionMethodCount,
            'totalByteLength' => $totalByteLength,
            'totalCompressedByteLength' => $totalCompressedByteLength,
            'exposableEntryCount' => $exposableEntryCount,
            'blockedEntryCount' => $blockedEntryCount,
            'exposableByteLength' => $exposableByteLength,
            'exposableCompressedByteLength' => $exposableCompressedByteLength,
            'blockedByteLength' => $blockedByteLength,
            'blockedCompressedByteLength' => $blockedCompressedByteLength,
            'unsupportedCompressionByteLength' => $unsupportedCompressionByteLength,
            'unsupportedCompressionCompressedByteLength' => $unsupportedCompressionCompressedByteLength,
            'byteExposurePolicy' => 'epub-package-inventory-metadata-only',
            'canExposeBytes' => false,
            'roles' => array_keys($roleCounts),
            'roleCounts' => $roleCounts,
            'roleByteLengths' => $roleByteLengths,
            'roleCompressedByteLengths' => $roleCompressedByteLengths,
            'resourceKindCounts' => $resourceKindCounts,
            'resourceKindByteLengths' => $resourceKindByteLengths,
            'resourceKindCompressedByteLengths' => $resourceKindCompressedByteLengths,
            'opfManifestDeclaredPartNames' => array_keys($manifestDeclaredPartNames),
            'missingOpfManifestDeclaredPartNames' => array_keys($missingManifestDeclaredPartNames),
            'undeclaredPartNames' => array_keys($undeclaredPartNames),
            'spinePartNames' => array_keys($spinePartNames),
            'encryptedPartNames' => array_keys($encryptedPartNames),
            'obfuscatedFontPartNames' => array_keys($obfuscatedFontPartNames),
            'unsupportedCompressionPartNames' => array_keys($unsupportedCompressionPartNames),
            'localPackagePaths' => $package->localNames(),
            'centralPackagePaths' => $package->names(),
            'byPackagePath' => $byPackagePath,
            'entries' => $entries,
        ];
    }

    /**
     * @param list<array<string, mixed>> $spine
     * @param array<string, mixed> $packageInventory
     *
     * @return array<string, mixed>
     */
    private static function readingOrderInventoryReport(array $spine, array $packageInventory): array
    {
        $inventoryByPackagePath = is_array($packageInventory['byPackagePath'] ?? null)
            ? $packageInventory['byPackagePath']
            : [];
        $items = [];
        $itemsByIdref = [];
        $diagnostics = [];
        $partNames = [];
        $packagePaths = [];
        $missingPartNames = [];
        $encryptedPartNames = [];
        $obfuscatedFontPartNames = [];
        $unsupportedCompressionPartNames = [];
        $byteExposurePolicyCounts = [];
        $compressionMethodCounts = [];
        $linearItemCount = 0;
        $nonLinearItemCount = 0;
        $existingItemCount = 0;
        $missingPackagePartCount = 0;
        $manifestItemMissingCount = 0;
        $externalItemCount = 0;
        $encryptedItemCount = 0;
        $obfuscatedFontItemCount = 0;
        $unsupportedCompressionItemCount = 0;
        $exposableItemCount = 0;
        $blockedItemCount = 0;
        $totalByteLength = 0;
        $totalCompressedByteLength = 0;
        $exposableByteLength = 0;
        $exposableCompressedByteLength = 0;
        $blockedByteLength = 0;
        $blockedCompressedByteLength = 0;
        $unsupportedCompressionByteLength = 0;
        $unsupportedCompressionCompressedByteLength = 0;

        foreach ($spine as $index => $spineItem) {
            $idref = is_string($spineItem['idref'] ?? null) ? $spineItem['idref'] : '';
            $partName = is_string($spineItem['partName'] ?? null) ? $spineItem['partName'] : '';
            $packagePath = self::packageInventoryEntryName($partName);
            $inventoryItem = null;
            if ($packagePath !== null && isset($inventoryByPackagePath[$packagePath]) && is_array($inventoryByPackagePath[$packagePath])) {
                $inventoryItem = $inventoryByPackagePath[$packagePath];
            }
            $linear = ($spineItem['linear'] ?? true) !== false;
            $manifestItemMissing = ($spineItem['manifestItemMissing'] ?? false) === true;
            $external = ($spineItem['external'] ?? false) === true;
            $exists = !$manifestItemMissing && !$external && ($spineItem['exists'] ?? false) === true && is_array($inventoryItem);
            $missingPackagePart = !$manifestItemMissing && !$external && !$exists;
            $encrypted = is_array($inventoryItem) && ($inventoryItem['encrypted'] ?? false) === true;
            $obfuscatedFont = is_array($inventoryItem) && ($inventoryItem['obfuscatedFont'] ?? false) === true;
            $compressionSupported = is_array($inventoryItem) ? ($inventoryItem['compressionSupported'] ?? null) : null;
            $unsupportedCompression = $compressionSupported === false;
            $canExposeBytes = $exists && !$encrypted && !$unsupportedCompression && ($inventoryItem['canExposeBytes'] ?? false) === true;
            $byteLength = is_array($inventoryItem) && is_int($inventoryItem['byteLength'] ?? null) ? $inventoryItem['byteLength'] : null;
            $compressedByteLength = is_array($inventoryItem) && is_int($inventoryItem['compressedByteLength'] ?? null) ? $inventoryItem['compressedByteLength'] : null;
            $compressionMethodName = is_array($inventoryItem) && is_string($inventoryItem['compressionMethodName'] ?? null)
                ? $inventoryItem['compressionMethodName']
                : null;

            if ($manifestItemMissing) {
                $byteExposurePolicy = 'missing-spine-manifest-item-metadata-only';
            } elseif ($external) {
                $byteExposurePolicy = 'external-spine-resource-metadata-only';
            } elseif ($missingPackagePart) {
                $byteExposurePolicy = 'missing-spine-package-part-metadata-only';
            } elseif ($obfuscatedFont) {
                $byteExposurePolicy = 'obfuscated-font-bytes-blocked';
            } elseif ($encrypted) {
                $byteExposurePolicy = 'encrypted-resource-bytes-blocked';
            } elseif ($unsupportedCompression) {
                $byteExposurePolicy = 'unsupported-compression-metadata-only';
            } elseif ($canExposeBytes) {
                $byteExposurePolicy = 'spine-content-bytes-exposable';
            } else {
                $byteExposurePolicy = 'spine-content-metadata-only';
            }

            $itemDiagnostics = [];
            if ($manifestItemMissing) {
                $itemDiagnostics[] = [
                    'type' => 'missing-spine-manifest-item',
                    'idref' => $idref,
                    'message' => 'EPUB spine itemref references a manifest item id that is not present',
                ];
            } elseif ($external) {
                $itemDiagnostics[] = [
                    'type' => 'external-spine-resource-reference',
                    'idref' => $idref,
                    'href' => is_string($spineItem['href'] ?? null) ? $spineItem['href'] : '',
                    'message' => 'EPUB spine itemref resolves to an external manifest resource and was not fetched',
                ];
            } elseif ($missingPackagePart) {
                $itemDiagnostics[] = [
                    'type' => 'missing-spine-package-part',
                    'idref' => $idref,
                    'partName' => $partName,
                    'message' => 'EPUB spine itemref points at a manifest package part that is not present in the ZIP',
                ];
            }
            if ($encrypted) {
                $itemDiagnostics[] = [
                    'type' => 'encrypted-spine-package-part',
                    'idref' => $idref,
                    'partName' => $partName,
                    'byteExposurePolicy' => $byteExposurePolicy,
                    'message' => 'EPUB spine package part is encrypted and remains metadata-only for compact ingestion',
                ];
            }
            if ($unsupportedCompression) {
                $itemDiagnostics[] = [
                    'type' => 'unsupported-spine-package-compression',
                    'idref' => $idref,
                    'partName' => $partName,
                    'compressionMethod' => is_array($inventoryItem) && is_int($inventoryItem['compressionMethod'] ?? null) ? $inventoryItem['compressionMethod'] : null,
                    'compressionMethodName' => $compressionMethodName,
                    'message' => 'EPUB spine package part uses a ZIP compression method that compact ingestion does not inflate',
                ];
            }

            $item = [
                'index' => $index,
                'id' => is_string($spineItem['id'] ?? null) ? $spineItem['id'] : null,
                'idref' => $idref,
                'href' => is_string($spineItem['href'] ?? null) ? $spineItem['href'] : '',
                'partName' => $partName === '' ? null : $partName,
                'packagePath' => $packagePath,
                'linear' => $linear,
                'manifestItemMissing' => $manifestItemMissing,
                'external' => $external,
                'exists' => $exists,
                'missingPackagePart' => $missingPackagePart,
                'mediaType' => is_string($spineItem['mediaType'] ?? null) ? $spineItem['mediaType'] : '',
                'mediaTypeBase' => self::mediaTypeBase((string) ($spineItem['mediaType'] ?? '')),
                'resourceKind' => is_array($inventoryItem) && is_string($inventoryItem['resourceKind'] ?? null) ? $inventoryItem['resourceKind'] : null,
                'encrypted' => $encrypted,
                'obfuscatedFont' => $obfuscatedFont,
                'compressionSupported' => $compressionSupported,
                'unsupportedCompression' => $unsupportedCompression,
                'compressionMethod' => is_array($inventoryItem) && is_int($inventoryItem['compressionMethod'] ?? null) ? $inventoryItem['compressionMethod'] : null,
                'compressionMethodName' => $compressionMethodName,
                'localOrder' => is_array($inventoryItem) && is_int($inventoryItem['localOrder'] ?? null) ? $inventoryItem['localOrder'] : null,
                'centralOrder' => is_array($inventoryItem) && is_int($inventoryItem['index'] ?? null) ? $inventoryItem['index'] : null,
                'byteLength' => $byteLength,
                'compressedByteLength' => $compressedByteLength,
                'crc32' => is_array($inventoryItem) && is_string($inventoryItem['crc32'] ?? null) ? $inventoryItem['crc32'] : null,
                'canExposeBytes' => $canExposeBytes,
                'byteExposurePolicy' => $byteExposurePolicy,
                'roles' => is_array($inventoryItem) && is_array($inventoryItem['roles'] ?? null) ? array_values($inventoryItem['roles']) : [],
                'diagnosticCount' => count($itemDiagnostics),
                'diagnostics' => $itemDiagnostics,
            ];

            $items[] = $item;
            if ($idref !== '') {
                $itemsByIdref[$idref][] = $item;
            }
            if ($partName !== '') {
                $partNames[] = $partName;
            }
            if ($packagePath !== null) {
                $packagePaths[] = $packagePath;
            }

            if ($linear) {
                ++$linearItemCount;
            } else {
                ++$nonLinearItemCount;
            }
            if ($exists) {
                ++$existingItemCount;
            }
            if ($missingPackagePart) {
                ++$missingPackagePartCount;
                if ($partName !== '') {
                    $missingPartNames[] = $partName;
                }
            }
            if ($manifestItemMissing) {
                ++$manifestItemMissingCount;
            }
            if ($external) {
                ++$externalItemCount;
            }
            if ($encrypted) {
                ++$encryptedItemCount;
                if ($partName !== '') {
                    $encryptedPartNames[] = $partName;
                }
            }
            if ($obfuscatedFont) {
                ++$obfuscatedFontItemCount;
                if ($partName !== '') {
                    $obfuscatedFontPartNames[] = $partName;
                }
            }
            if ($unsupportedCompression) {
                ++$unsupportedCompressionItemCount;
                if ($partName !== '') {
                    $unsupportedCompressionPartNames[] = $partName;
                }
            }
            if ($canExposeBytes) {
                ++$exposableItemCount;
            } else {
                ++$blockedItemCount;
            }

            $byteExposurePolicyCounts[$byteExposurePolicy] = ($byteExposurePolicyCounts[$byteExposurePolicy] ?? 0) + 1;
            if ($compressionMethodName !== null) {
                $compressionMethodCounts[$compressionMethodName] = ($compressionMethodCounts[$compressionMethodName] ?? 0) + 1;
            }

            $bytes = $byteLength ?? 0;
            $compressedBytes = $compressedByteLength ?? 0;
            $totalByteLength += $bytes;
            $totalCompressedByteLength += $compressedBytes;
            if ($canExposeBytes) {
                $exposableByteLength += $bytes;
                $exposableCompressedByteLength += $compressedBytes;
            } else {
                $blockedByteLength += $bytes;
                $blockedCompressedByteLength += $compressedBytes;
            }
            if ($unsupportedCompression) {
                $unsupportedCompressionByteLength += $bytes;
                $unsupportedCompressionCompressedByteLength += $compressedBytes;
            }

            foreach ($itemDiagnostics as $diagnostic) {
                $diagnostics[] = [
                    'index' => $index,
                    'idref' => $idref,
                ] + $diagnostic;
            }
        }

        ksort($byteExposurePolicyCounts, SORT_STRING);
        ksort($compressionMethodCounts, SORT_STRING);

        return [
            'present' => $items !== [],
            'itemCount' => count($items),
            'linearItemCount' => $linearItemCount,
            'nonLinearItemCount' => $nonLinearItemCount,
            'existingItemCount' => $existingItemCount,
            'missingItemCount' => $manifestItemMissingCount + $missingPackagePartCount,
            'missingPackagePartCount' => $missingPackagePartCount,
            'manifestItemMissingCount' => $manifestItemMissingCount,
            'externalItemCount' => $externalItemCount,
            'encryptedItemCount' => $encryptedItemCount,
            'obfuscatedFontItemCount' => $obfuscatedFontItemCount,
            'unsupportedCompressionItemCount' => $unsupportedCompressionItemCount,
            'exposableItemCount' => $exposableItemCount,
            'blockedItemCount' => $blockedItemCount,
            'totalByteLength' => $totalByteLength,
            'totalCompressedByteLength' => $totalCompressedByteLength,
            'exposableByteLength' => $exposableByteLength,
            'exposableCompressedByteLength' => $exposableCompressedByteLength,
            'blockedByteLength' => $blockedByteLength,
            'blockedCompressedByteLength' => $blockedCompressedByteLength,
            'unsupportedCompressionByteLength' => $unsupportedCompressionByteLength,
            'unsupportedCompressionCompressedByteLength' => $unsupportedCompressionCompressedByteLength,
            'byteExposurePolicyCounts' => $byteExposurePolicyCounts,
            'compressionMethodCounts' => $compressionMethodCounts,
            'partNames' => $partNames,
            'packagePaths' => $packagePaths,
            'missingPartNames' => $missingPartNames,
            'encryptedPartNames' => $encryptedPartNames,
            'obfuscatedFontPartNames' => $obfuscatedFontPartNames,
            'unsupportedCompressionPartNames' => $unsupportedCompressionPartNames,
            'diagnosticCount' => count($diagnostics),
            'diagnosticTypes' => self::compactDiagnosticTypes($diagnostics),
            'diagnostics' => $diagnostics,
            'itemsByIdref' => $itemsByIdref,
            'items' => $items,
        ];
    }

    private static function packageInventoryEntryName(mixed $partName): ?string
    {
        if (!is_string($partName)) {
            return null;
        }

        $name = ltrim(trim($partName), '/');

        return $name === '' ? null : $name;
    }

    private static function packageInventoryPartName(string $packagePath): string
    {
        return '/' . ltrim($packagePath, '/');
    }

    /**
     * @param list<string> $properties
     */
    private static function packageInventoryResourceKind(?string $mediaType, string $packagePath, array $properties): string
    {
        if (in_array('nav', $properties, true)) {
            return 'navigation';
        }
        if (in_array('cover-image', $properties, true)) {
            return 'cover-image';
        }

        $baseMediaType = $mediaType === null ? '' : self::mediaTypeBase($mediaType);
        if ($baseMediaType === self::OPF_MEDIA_TYPE) {
            return 'package-document';
        }
        if (isset(self::CORE_MEDIA_TYPE_KINDS[$baseMediaType])) {
            return self::CORE_MEDIA_TYPE_KINDS[$baseMediaType];
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
        if (self::isFontResource($baseMediaType, $packagePath)) {
            return 'font';
        }

        return 'asset';
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
     *     0:array<string, array{id:string, href:string, target:string, partName:?string, external:bool, mediaType:string, properties:list<string>, fallback:?string, fallbackStyle:?string, mediaOverlay:?string}>,
     *     1:list<array{id:string, href:string, target:string, partName:?string, external:bool, mediaType:string, properties:list<string>, fallback:?string, fallbackStyle:?string, mediaOverlay:?string}>
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
            $target = '';
            $external = false;
            $partName = null;
            $hrefSuffix = [
                'hasQuery' => false,
                'query' => null,
                'hasFragment' => false,
                'fragment' => null,
            ];
            $mediaTypeReport = self::mediaTypeReport($mediaType);
            $exists = false;
            $entry = null;
            $diagnostics = [];
            $missingRequiredAttributes = [];
            if (trim($id) === '') {
                $missingRequiredAttributes[] = 'id';
                $diagnostics[] = [
                    'type' => 'missing-manifest-item-id',
                    'message' => 'EPUB OPF manifest item is missing id',
                ];
            }
            if (trim($href) === '') {
                $missingRequiredAttributes[] = 'href';
                $diagnostics[] = [
                    'type' => 'missing-manifest-item-href',
                    'id' => $id,
                    'message' => 'EPUB OPF manifest item is missing href',
                ];
            }
            if (trim($mediaType) === '') {
                $missingRequiredAttributes[] = 'media-type';
                $diagnostics[] = [
                    'type' => 'missing-manifest-item-media-type',
                    'id' => $id,
                    'href' => $href,
                    'message' => 'EPUB OPF manifest item is missing media-type',
                ];
            }

            if (trim($href) !== '') {
                try {
                    $target = self::resolvePackageHref($opfPartName, $href);
                    $external = self::isAbsoluteUri($target);
                    $partName = $external ? null : OpcPackagePath::stripQueryAndFragment($target);
                    $hrefSuffix = self::packageHrefSuffixReport($target);
                    $exists = $partName !== null && $package->has($partName);
                    $entry = $exists ? $package->entry($partName) : null;
                } catch (\InvalidArgumentException $exception) {
                    $diagnostics[] = [
                        'type' => 'invalid-manifest-href-target',
                        'id' => $id,
                        'href' => $href,
                        'message' => $exception->getMessage(),
                    ];
                }
            }

            if ($external) {
                $diagnostics[] = [
                    'type' => 'external-manifest-href-target',
                    'id' => $id,
                    'href' => $href,
                    'target' => $target,
                    'message' => 'EPUB OPF manifest item points outside the package and was not fetched',
                ];
            }

            $attributes = self::elementAttributes($itemElement);
            $customAttributes = self::manifestItemCustomAttributes($attributes);
            $item = [
                'id' => $id,
                'href' => $href,
                'target' => $target,
                'partName' => $partName,
                'external' => $external,
                'mediaType' => $mediaType,
                'language' => self::metadataElementLanguage($itemElement),
                'direction' => self::metadataElementDirection($itemElement),
                'attributes' => $attributes,
                'customAttributes' => $customAttributes,
                'normalizedMediaType' => $mediaTypeReport['normalizedMediaType'],
                'mediaTypeBase' => $mediaTypeReport['mediaTypeBase'],
                'mediaTypeHasParameters' => $mediaTypeReport['mediaTypeHasParameters'],
                'mediaTypeParameterCount' => $mediaTypeReport['mediaTypeParameterCount'],
                'mediaTypeParameters' => $mediaTypeReport['mediaTypeParameters'],
                'mediaTypeParameterMap' => $mediaTypeReport['mediaTypeParameterMap'],
                'mediaTypeSyntaxValid' => $mediaTypeReport['mediaTypeSyntaxValid'],
                'mediaTypeDiagnostics' => $mediaTypeReport['mediaTypeDiagnostics'],
                'exists' => $exists,
                'properties' => self::splitTokens($itemElement->getAttribute('properties')),
                'fallback' => $itemElement->hasAttribute('fallback') ? $itemElement->getAttribute('fallback') : null,
                'fallbackStyle' => $itemElement->hasAttribute('fallback-style') ? $itemElement->getAttribute('fallback-style') : null,
                'mediaOverlay' => $itemElement->hasAttribute('media-overlay') ? $itemElement->getAttribute('media-overlay') : null,
                'diagnostics' => $diagnostics,
                'requiredAttributesPresent' => $missingRequiredAttributes === [],
                'missingRequiredAttributes' => $missingRequiredAttributes,
                'hrefHasQuery' => $hrefSuffix['hasQuery'],
                'hrefQuery' => $hrefSuffix['query'],
                'hrefHasFragment' => $hrefSuffix['hasFragment'],
                'hrefFragment' => $hrefSuffix['fragment'],
            ] + self::zipEntryProvenance($entry);

            if (trim($id) !== '') {
                $manifestIdIndexes[$id][] = count($items);
                $byId[$id] ??= $item;
            }
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
            $id = $itemrefElement->hasAttribute('id')
                ? self::emptyToNull($itemrefElement->getAttribute('id'))
                : null;
            $linearRaw = $itemrefElement->hasAttribute('linear')
                ? self::emptyToNull($itemrefElement->getAttribute('linear'))
                : null;
            $properties = self::splitTokens($itemrefElement->getAttribute('properties'));
            $itemProperties = self::spineItemPropertyReport($properties);
            $language = self::metadataElementLanguage($itemrefElement);
            $direction = self::metadataElementDirection($itemrefElement);
            $attributes = self::elementAttributes($itemrefElement);
            $customAttributes = self::spineItemrefCustomAttributes($attributes);
            if (trim($idref) === '') {
                $missingRequiredAttributes = ['idref'];
                $spine[] = [
                    'id' => $id,
                    'idref' => $idref,
                    'href' => '',
                    'partName' => '',
                    'external' => false,
                    'mediaType' => '',
                    'exists' => false,
                    'manifestItemMissing' => false,
                    'linear' => $linearRaw === null || strtolower($linearRaw) !== 'no',
                    'linearRaw' => $linearRaw,
                    'properties' => $properties,
                    'language' => $language,
                    'direction' => $direction,
                    'attributes' => $attributes,
                    'customAttributes' => $customAttributes,
                    'spineItemProperties' => $itemProperties,
                    'spineItemDiagnostics' => array_merge([
                        [
                            'type' => 'missing-spine-itemref-idref',
                            'attribute' => 'idref',
                            'message' => 'EPUB OPF spine itemref is missing idref',
                        ],
                    ], $itemProperties['diagnostics']),
                    'requiredAttributesPresent' => false,
                    'missingRequiredAttributes' => $missingRequiredAttributes,
                    'pageSpread' => $itemProperties['pageSpread']['placement'],
                    'pageSpreadProperties' => $itemProperties['pageSpread']['properties'],
                    'mediaOverlay' => null,
                    'refinements' => [],
                    'renditionViewportRefinements' => [],
                ];
                continue;
            }

            $item = $manifestById[$idref] ?? null;
            if (!is_array($item)) {
                $id = $itemrefElement->hasAttribute('id')
                    ? self::emptyToNull($itemrefElement->getAttribute('id'))
                    : null;
                $linearRaw = $itemrefElement->hasAttribute('linear')
                    ? self::emptyToNull($itemrefElement->getAttribute('linear'))
                    : null;
                $properties = self::splitTokens($itemrefElement->getAttribute('properties'));
                $itemProperties = self::spineItemPropertyReport($properties);
                $spine[] = [
                    'id' => $id,
                    'idref' => $idref,
                    'href' => '',
                    'partName' => '',
                    'external' => false,
                    'mediaType' => '',
                    'exists' => false,
                    'manifestItemMissing' => true,
                    'linear' => $linearRaw === null || strtolower($linearRaw) !== 'no',
                    'linearRaw' => $linearRaw,
                    'properties' => $properties,
                    'language' => $language,
                    'direction' => $direction,
                    'attributes' => $attributes,
                    'customAttributes' => $customAttributes,
                    'spineItemProperties' => $itemProperties,
                    'spineItemDiagnostics' => $itemProperties['diagnostics'],
                    'requiredAttributesPresent' => true,
                    'missingRequiredAttributes' => [],
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
            $linearRaw = $itemrefElement->hasAttribute('linear')
                ? self::emptyToNull($itemrefElement->getAttribute('linear'))
                : null;
            $refinements = $id !== null && isset($refinementsById[$id]) && is_array($refinementsById[$id])
                ? $refinementsById[$id]
                : [];
            $properties = self::splitTokens($itemrefElement->getAttribute('properties'));
            $itemProperties = self::spineItemPropertyReport($properties);

            $spine[] = [
                'id' => $id,
                'idref' => $idref,
                'href' => $item['href'],
                'partName' => $item['partName'],
                'external' => ($item['external'] ?? false) === true,
                'mediaType' => $item['mediaType'],
                'exists' => ($item['exists'] ?? false) === true,
                'manifestItemMissing' => false,
                'linear' => $linearRaw === null || strtolower($linearRaw) !== 'no',
                'linearRaw' => $linearRaw,
                'properties' => $properties,
                'language' => $language,
                'direction' => $direction,
                'attributes' => $attributes,
                'customAttributes' => $customAttributes,
                'spineItemProperties' => $itemProperties,
                'spineItemDiagnostics' => $itemProperties['diagnostics'],
                'requiredAttributesPresent' => true,
                'missingRequiredAttributes' => [],
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
            if (self::mediaTypeBase((string) ($item['mediaType'] ?? '')) === self::XHTML_MEDIA_TYPE && in_array('nav', $item['properties'], true)) {
                if (($item['exists'] ?? false) !== true || !$package->has($item['partName'])) {
                    continue;
                }

                try {
                    $report = self::parseNavDocument($package->read($item['partName']), $item['partName']);
                } catch (\RuntimeException | \InvalidArgumentException $exception) {
                    $diagnostic = [
                        'type' => 'invalid-nav-document',
                        'part' => $item['partName'],
                        'partName' => $item['partName'],
                        'error' => $exception->getMessage(),
                        'message' => 'EPUB navigation document could not be parsed for bounded package review',
                    ];

                    return [
                        'navigation' => [
                            'type' => 'nav',
                            'partName' => $item['partName'],
                            'entries' => [],
                            'valid' => false,
                            'diagnostics' => [$diagnostic],
                        ],
                        'sections' => [],
                    ];
                }

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

            $entries = self::parseNcxDocument(
                $package,
                $package->read($ncxItem['partName']),
                $ncxItem['partName'],
                self::manifestByPart($manifestById),
            );

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
            'manifestNormalizedMediaType' => is_array($manifestItem) ? (string) ($manifestItem['normalizedMediaType'] ?? '') : null,
            'manifestMediaTypeBase' => is_array($manifestItem) ? (string) ($manifestItem['mediaTypeBase'] ?? '') : null,
            'manifestMediaTypeHasParameters' => is_array($manifestItem) && ($manifestItem['mediaTypeHasParameters'] ?? false) === true,
            'manifestMediaTypeParameterCount' => is_array($manifestItem) ? (int) ($manifestItem['mediaTypeParameterCount'] ?? 0) : 0,
            'manifestMediaTypeParameters' => is_array($manifestItem) && is_array($manifestItem['mediaTypeParameters'] ?? null)
                ? array_values($manifestItem['mediaTypeParameters'])
                : [],
            'manifestMediaTypeParameterMap' => is_array($manifestItem) && is_array($manifestItem['mediaTypeParameterMap'] ?? null)
                ? $manifestItem['mediaTypeParameterMap']
                : [],
            'manifestMediaTypeSyntaxValid' => !is_array($manifestItem) || ($manifestItem['mediaTypeSyntaxValid'] ?? true) === true,
            'manifestMediaTypeDiagnostics' => is_array($manifestItem) && is_array($manifestItem['mediaTypeDiagnostics'] ?? null)
                ? array_values($manifestItem['mediaTypeDiagnostics'])
                : [],
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
        $manifestMediaTypeParameterItems = [];
        $manifestMediaTypeParameterNames = [];
        $manifestMediaTypeParameterCount = 0;
        $manifestMediaTypeDiagnostics = [];
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
                    'manifestMediaTypeBase' => is_string($reference['manifestMediaTypeBase'] ?? null) ? $reference['manifestMediaTypeBase'] : null,
                ];
            }

            $manifestMediaTypeParameters = is_array($reference['manifestMediaTypeParameters'] ?? null)
                ? array_values($reference['manifestMediaTypeParameters'])
                : [];
            if ($manifestMediaTypeParameters !== []) {
                $parameterNames = [];
                foreach ($manifestMediaTypeParameters as $parameter) {
                    if (!is_array($parameter)) {
                        continue;
                    }

                    $name = is_string($parameter['name'] ?? null) ? $parameter['name'] : '';
                    if ($name !== '') {
                        $parameterNames[] = $name;
                        $manifestMediaTypeParameterNames[$name] = true;
                    }
                }

                $manifestMediaTypeParameterCount += count($manifestMediaTypeParameters);
                $manifestMediaTypeParameterItems[] = [
                    'index' => $index,
                    'type' => $referenceType,
                    'target' => $target,
                    'partName' => is_string($reference['partName'] ?? null) ? $reference['partName'] : null,
                    'manifestId' => is_string($reference['manifestId'] ?? null) ? $reference['manifestId'] : null,
                    'manifestMediaType' => is_string($reference['manifestMediaType'] ?? null) ? $reference['manifestMediaType'] : null,
                    'manifestMediaTypeBase' => is_string($reference['manifestMediaTypeBase'] ?? null) ? $reference['manifestMediaTypeBase'] : null,
                    'parameterCount' => count($manifestMediaTypeParameters),
                    'parameterNames' => array_values(array_unique($parameterNames)),
                    'parameters' => $manifestMediaTypeParameters,
                    'parameterMap' => is_array($reference['manifestMediaTypeParameterMap'] ?? null)
                        ? $reference['manifestMediaTypeParameterMap']
                        : [],
                ];
            }

            foreach (is_array($reference['manifestMediaTypeDiagnostics'] ?? null) ? $reference['manifestMediaTypeDiagnostics'] : [] as $mediaTypeDiagnostic) {
                if (!is_array($mediaTypeDiagnostic)) {
                    continue;
                }

                $manifestMediaTypeDiagnostics[] = [
                    'index' => $index,
                    'type' => $referenceType,
                    'target' => $target,
                    'partName' => is_string($reference['partName'] ?? null) ? $reference['partName'] : null,
                    'manifestId' => is_string($reference['manifestId'] ?? null) ? $reference['manifestId'] : null,
                    'manifestMediaType' => is_string($reference['manifestMediaType'] ?? null) ? $reference['manifestMediaType'] : null,
                ] + $mediaTypeDiagnostic;
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
            'manifestMediaTypeParameterItemCount' => count($manifestMediaTypeParameterItems),
            'manifestMediaTypeParameterCount' => $manifestMediaTypeParameterCount,
            'manifestMediaTypeParameterNames' => array_keys($manifestMediaTypeParameterNames),
            'manifestMediaTypeDiagnosticCount' => count($manifestMediaTypeDiagnostics),
            'targets' => $targets,
            'localTargets' => $localTargets,
            'externalTargets' => $externalTargets,
            'missingTargets' => $missingTargets,
            'manifestLinkedTargets' => $manifestLinkedTargets,
            'manifestMediaTypeParameterItems' => $manifestMediaTypeParameterItems,
            'manifestMediaTypeDiagnostics' => $manifestMediaTypeDiagnostics,
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
            $partName = $item['partName'] ?? null;
            if (!is_string($partName) || $partName === '') {
                continue;
            }

            $byPart[$partName] = $item;
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
        array $manifestById,
        array $prefixBindings = []
    ): array {
        $manifestByPart = self::manifestByPart($manifestById);
        $collections = [];
        foreach (self::childElements($packageElement, 'collection', self::OPF_NAMESPACE) as $index => $collectionElement) {
            $collections[] = self::parseCollection($collectionElement, $index, $opfPartName, $package, $manifestByPart, $prefixBindings);
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
        array $manifestByPart,
        array $prefixBindings = []
    ): array {
        $links = [];
        foreach (self::childElements($collectionElement, 'link', self::OPF_NAMESPACE) as $linkIndex => $linkElement) {
            $links[] = self::parseCollectionLink($linkElement, $linkIndex, $opfPartName, $package, $manifestByPart, $prefixBindings);
        }

        $children = [];
        foreach (self::childElements($collectionElement, 'collection', self::OPF_NAMESPACE) as $childIndex => $childElement) {
            $children[] = self::parseCollection($childElement, $childIndex, $opfPartName, $package, $manifestByPart, $prefixBindings);
        }

        $metadataElement = self::firstChildElement($collectionElement, 'metadata', self::OPF_NAMESPACE);
        $metadata = $metadataElement instanceof \DOMElement
            ? self::parseMetadata($metadataElement, $collectionElement)
            : [];
        $role = self::emptyToNull($collectionElement->getAttribute('role'));
        $roleTokens = self::splitTokens($role ?? '');
        $roleVocabulary = self::collectionRoleTokenReport($roleTokens, $prefixBindings, $index);
        $report = self::collectionLinkReport($links, true);
        $diagnostics = array_merge($roleVocabulary['diagnostics'], $report['diagnostics']);

        return [
            'index' => $index,
            'id' => self::emptyToNull($collectionElement->getAttribute('id')),
            'role' => $role,
            'roleTokens' => $roleTokens,
            'primaryRole' => $roleTokens[0] ?? null,
            'roleVocabulary' => $roleVocabulary,
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
            'diagnosticCount' => count($diagnostics),
            'diagnostics' => $diagnostics,
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
            'relVocabulary' => self::collectionLinkTokenReport($rel, $prefixBindings, 'rel', $index),
            'propertyVocabulary' => self::collectionLinkTokenReport($properties, $prefixBindings, 'properties', $index),
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
    private static function collectionLinkReport(array $links, bool $includeVocabularyDiagnostics = false): array
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

            if ($includeVocabularyDiagnostics) {
                foreach (['relVocabulary', 'propertyVocabulary'] as $field) {
                    $report = is_array($link[$field] ?? null) ? $link[$field] : [];
                    foreach (is_array($report['diagnostics'] ?? null) ? $report['diagnostics'] : [] as $diagnostic) {
                        $diagnostics[] = ['index' => $linkIndex, 'id' => $link['id'] ?? null] + $diagnostic;
                    }
                }
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
        return self::linkVocabularyTokenReport(
            $tokens,
            $prefixBindings,
            $kind,
            $linkIndex,
            'metadata-link',
            'EPUB OPF metadata link',
        );
    }

    /**
     * @param list<string> $tokens
     * @param array<string, string> $prefixBindings
     *
     * @return array<string, mixed>
     */
    private static function collectionLinkTokenReport(array $tokens, array $prefixBindings, string $kind, int $linkIndex): array
    {
        return self::linkVocabularyTokenReport(
            $tokens,
            $prefixBindings,
            $kind,
            $linkIndex,
            'collection-link',
            'EPUB OPF collection link',
        );
    }

    /**
     * @param list<string> $tokens
     * @param array<string, string> $prefixBindings
     *
     * @return array<string, mixed>
     */
    private static function collectionRoleTokenReport(array $tokens, array $prefixBindings, int $collectionIndex): array
    {
        return self::linkVocabularyTokenReport(
            $tokens,
            $prefixBindings,
            'role',
            $collectionIndex,
            'collection',
            'EPUB OPF collection role',
        );
    }

    /**
     * @param list<string> $tokens
     * @param array<string, string> $prefixBindings
     *
     * @return array<string, mixed>
     */
    private static function linkVocabularyTokenReport(
        array $tokens,
        array $prefixBindings,
        string $kind,
        int $linkIndex,
        string $diagnosticSource,
        string $messageSubject
    ): array
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
                        'type' => 'unknown-' . $diagnosticSource . '-' . $kind . '-prefix',
                        'kind' => $kind,
                        'linkIndex' => $linkIndex,
                        'index' => (int) $index,
                        'value' => $value,
                        'prefix' => $prefix,
                        'message' => $messageSubject . ' vocabulary token uses a prefix that is not declared on the package element',
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
                    'type' => 'invalid-' . $diagnosticSource . '-' . $kind . '-url-fragment',
                    'kind' => $kind,
                    'linkIndex' => $linkIndex,
                    'index' => (int) $index,
                    'value' => $value,
                    'message' => $messageSubject . ' vocabulary URLs must include a fragment identifier',
                ];
            } elseif (preg_match('/^[A-Za-z_][A-Za-z0-9_.-]*$/', $value) !== 1) {
                $tokenKind = 'invalid';
                $valid = false;
                $diagnosticsForToken[] = [
                    'type' => 'invalid-' . $diagnosticSource . '-' . $kind . '-token',
                    'kind' => $kind,
                    'linkIndex' => $linkIndex,
                    'index' => (int) $index,
                    'value' => $value,
                    'message' => $messageSubject . ' vocabulary values must be NMTOKENs, prefixed names, or absolute URLs with fragments',
                ];
            }

            if (isset($seen[$value])) {
                ++$duplicateCount;
                $diagnosticsForToken[] = [
                    'type' => 'duplicate-' . $diagnosticSource . '-' . $kind . '-token',
                    'kind' => $kind,
                    'linkIndex' => $linkIndex,
                    'index' => (int) $index,
                    'previousIndex' => $seen[$value],
                    'value' => $value,
                    'message' => $messageSubject . ' vocabulary value is repeated',
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
     * @return array<string, mixed>
     */
    private static function collectionLinkVocabularySummary(array $collections): array
    {
        $links = [];
        self::appendCollectionLinks($collections, $links);

        return self::metadataLinkVocabularySummary($links);
    }

    /**
     * @param list<array<string, mixed>> $collections
     *
     * @return array<string, mixed>
     */
    private static function collectionRoleVocabularySummary(array $collections): array
    {
        $items = [];
        self::appendCollectionRoleVocabularyItems($collections, [], $items);

        $roles = [];
        $roleTokenCount = 0;
        $validTokenCount = 0;
        $invalidTokenCount = 0;
        $resolvedTokenCount = 0;
        $absoluteUrlTokenCount = 0;
        $duplicateTokenCount = 0;
        $diagnostics = [];

        foreach ($items as $item) {
            foreach (is_array($item['roleTokens'] ?? null) ? $item['roleTokens'] : [] as $token) {
                if (!is_string($token) || $token === '') {
                    continue;
                }

                $roles[$token] = ($roles[$token] ?? 0) + 1;
                ++$roleTokenCount;
            }

            $report = is_array($item['roleVocabulary'] ?? null) ? $item['roleVocabulary'] : [];
            $validTokenCount += (int) ($report['validCount'] ?? 0);
            $invalidTokenCount += (int) ($report['invalidCount'] ?? 0);
            $resolvedTokenCount += (int) ($report['resolvedCount'] ?? 0);
            $absoluteUrlTokenCount += (int) ($report['absoluteUrlCount'] ?? 0);
            $duplicateTokenCount += (int) ($report['duplicateCount'] ?? 0);
            foreach (is_array($report['diagnostics'] ?? null) ? $report['diagnostics'] : [] as $diagnostic) {
                if (!is_array($diagnostic)) {
                    continue;
                }

                $diagnostics[] = [
                    'collectionPath' => $item['collectionPath'],
                    'collectionId' => $item['collectionId'],
                    'role' => $item['role'],
                ] + $diagnostic;
            }
        }

        ksort($roles);

        return [
            'present' => $roleTokenCount > 0,
            'collectionCount' => count($items),
            'roleTokenCount' => $roleTokenCount,
            'validTokenCount' => $validTokenCount,
            'invalidTokenCount' => $invalidTokenCount,
            'resolvedTokenCount' => $resolvedTokenCount,
            'absoluteUrlTokenCount' => $absoluteUrlTokenCount,
            'duplicateTokenCount' => $duplicateTokenCount,
            'roles' => $roles,
            'items' => $items,
            'diagnosticCount' => count($diagnostics),
            'diagnostics' => $diagnostics,
        ];
    }

    /**
     * @param list<array<string, mixed>> $collections
     * @param list<array<string, mixed>> $links
     */
    private static function appendCollectionLinks(array $collections, array &$links): void
    {
        foreach ($collections as $collection) {
            if (!is_array($collection)) {
                continue;
            }

            foreach (is_array($collection['links'] ?? null) ? $collection['links'] : [] as $link) {
                if (is_array($link)) {
                    $links[] = $link;
                }
            }

            self::appendCollectionLinks(
                is_array($collection['children'] ?? null) ? $collection['children'] : [],
                $links,
            );
        }
    }

    /**
     * @param list<array<string, mixed>> $collections
     * @param list<int> $collectionPath
     * @param list<array<string, mixed>> $items
     */
    private static function appendCollectionRoleVocabularyItems(
        array $collections,
        array $collectionPath,
        array &$items
    ): void {
        foreach ($collections as $collectionIndex => $collection) {
            if (!is_array($collection)) {
                continue;
            }

            $currentPath = array_merge($collectionPath, [$collectionIndex]);
            $items[] = [
                'collectionPath' => $currentPath,
                'collectionId' => is_string($collection['id'] ?? null) ? $collection['id'] : null,
                'role' => is_string($collection['role'] ?? null) ? $collection['role'] : null,
                'roleTokens' => is_array($collection['roleTokens'] ?? null)
                    ? array_values($collection['roleTokens'])
                    : [],
                'primaryRole' => is_string($collection['primaryRole'] ?? null) ? $collection['primaryRole'] : null,
                'roleVocabulary' => is_array($collection['roleVocabulary'] ?? null)
                    ? $collection['roleVocabulary']
                    : self::collectionRoleTokenReport([], [], $collectionIndex),
            ];

            self::appendCollectionRoleVocabularyItems(
                is_array($collection['children'] ?? null) ? $collection['children'] : [],
                $currentPath,
                $items,
            );
        }
    }

    /**
     * @param list<array<string, mixed>> $collections
     *
     * @return array<string, mixed>
     */
    private static function collectionHierarchyReport(array $collections): array
    {
        $items = [];
        $diagnostics = [];
        $roleCounts = [];
        $primaryRoleCounts = [];
        $linkRelCounts = [];
        $depthCounts = [];
        $localLinkCount = 0;
        $externalLinkCount = 0;
        $missingLinkCount = 0;
        $maxDepth = 0;
        $leafCollectionCount = 0;

        self::appendCollectionHierarchyItems(
            $collections,
            [],
            $items,
            $diagnostics,
            $roleCounts,
            $primaryRoleCounts,
            $linkRelCounts,
            $depthCounts,
            $localLinkCount,
            $externalLinkCount,
            $missingLinkCount,
            $maxDepth,
            $leafCollectionCount,
        );

        ksort($roleCounts);
        ksort($primaryRoleCounts);
        ksort($linkRelCounts);
        ksort($depthCounts);

        $itemsByPath = [];
        foreach ($items as $item) {
            if (is_string($item['pathKey'] ?? null) && $item['pathKey'] !== '') {
                $itemsByPath[$item['pathKey']] = $item;
            }
        }

        return [
            'present' => $items !== [],
            'collectionCount' => count($items),
            'rootCollectionCount' => count(array_filter(
                $collections,
                static fn (mixed $collection): bool => is_array($collection),
            )),
            'leafCollectionCount' => $leafCollectionCount,
            'maxDepth' => $maxDepth,
            'pathKeys' => array_column($items, 'pathKey'),
            'roleCounts' => $roleCounts,
            'primaryRoleCounts' => $primaryRoleCounts,
            'linkRelCounts' => $linkRelCounts,
            'depthCounts' => $depthCounts,
            'localLinkCount' => $localLinkCount,
            'externalLinkCount' => $externalLinkCount,
            'missingLinkCount' => $missingLinkCount,
            'titles' => self::collectionTitles($collections),
            'linkTargets' => self::collectionLinkTargets($collections),
            'diagnosticCount' => count($diagnostics),
            'diagnostics' => $diagnostics,
            'items' => $items,
            'itemsByPath' => $itemsByPath,
        ];
    }

    /**
     * @param list<array<string, mixed>> $collections
     * @param list<int> $parentPath
     * @param list<array<string, mixed>> $items
     * @param list<array<string, mixed>> $diagnostics
     * @param array<string, int> $roleCounts
     * @param array<string, int> $primaryRoleCounts
     * @param array<string, int> $linkRelCounts
     * @param array<int, int> $depthCounts
     */
    private static function appendCollectionHierarchyItems(
        array $collections,
        array $parentPath,
        array &$items,
        array &$diagnostics,
        array &$roleCounts,
        array &$primaryRoleCounts,
        array &$linkRelCounts,
        array &$depthCounts,
        int &$localLinkCount,
        int &$externalLinkCount,
        int &$missingLinkCount,
        int &$maxDepth,
        int &$leafCollectionCount
    ): void {
        foreach ($collections as $collectionIndex => $collection) {
            if (!is_array($collection)) {
                continue;
            }

            $currentPath = array_merge($parentPath, [$collectionIndex]);
            $pathKey = implode('/', $currentPath);
            $parentPathKey = $parentPath === [] ? null : implode('/', $parentPath);
            $children = is_array($collection['children'] ?? null) ? $collection['children'] : [];
            $links = is_array($collection['links'] ?? null) ? $collection['links'] : [];
            $metadata = is_array($collection['metadata'] ?? null) ? $collection['metadata'] : [];
            $roleTokens = is_array($collection['roleTokens'] ?? null) ? array_values($collection['roleTokens']) : [];
            $primaryRole = is_string($collection['primaryRole'] ?? null) ? $collection['primaryRole'] : null;
            $depth = count($currentPath);
            $maxDepth = max($maxDepth, $depth);
            $depthCounts[$depth] = ($depthCounts[$depth] ?? 0) + 1;

            if ($children === []) {
                ++$leafCollectionCount;
            }

            foreach ($roleTokens as $roleToken) {
                if (!is_string($roleToken) || $roleToken === '') {
                    continue;
                }

                $roleCounts[$roleToken] = ($roleCounts[$roleToken] ?? 0) + 1;
            }

            if ($primaryRole !== null && $primaryRole !== '') {
                $primaryRoleCounts[$primaryRole] = ($primaryRoleCounts[$primaryRole] ?? 0) + 1;
            }

            foreach (is_array($collection['linkRelCounts'] ?? null) ? $collection['linkRelCounts'] : [] as $rel => $count) {
                if (!is_string($rel) || $rel === '') {
                    continue;
                }

                $linkRelCounts[$rel] = ($linkRelCounts[$rel] ?? 0) + (int) $count;
            }

            $localLinkCount += (int) ($collection['localLinkCount'] ?? 0);
            $externalLinkCount += (int) ($collection['externalLinkCount'] ?? 0);
            $missingLinkCount += (int) ($collection['missingLinkCount'] ?? 0);

            $itemDiagnostics = [];
            foreach (is_array($collection['diagnostics'] ?? null) ? $collection['diagnostics'] : [] as $diagnostic) {
                if (!is_array($diagnostic)) {
                    continue;
                }

                $diagnosticWithPath = [
                    'collectionPath' => $currentPath,
                    'collectionPathKey' => $pathKey,
                    'collectionId' => is_string($collection['id'] ?? null) ? $collection['id'] : null,
                ] + $diagnostic;
                $itemDiagnostics[] = $diagnosticWithPath;
                $diagnostics[] = $diagnosticWithPath;
            }

            $items[] = [
                'path' => $currentPath,
                'pathKey' => $pathKey,
                'parentPath' => $parentPath === [] ? null : $parentPath,
                'parentPathKey' => $parentPathKey,
                'index' => $collectionIndex,
                'depth' => $depth,
                'id' => is_string($collection['id'] ?? null) ? $collection['id'] : null,
                'role' => is_string($collection['role'] ?? null) ? $collection['role'] : null,
                'roleTokens' => $roleTokens,
                'primaryRole' => $primaryRole,
                'title' => is_string($metadata['title'] ?? null) ? $metadata['title'] : null,
                'language' => is_string($collection['language'] ?? null) ? $collection['language'] : null,
                'direction' => is_string($collection['direction'] ?? null) ? $collection['direction'] : null,
                'linkCount' => (int) ($collection['linkCount'] ?? count($links)),
                'localLinkCount' => (int) ($collection['localLinkCount'] ?? 0),
                'externalLinkCount' => (int) ($collection['externalLinkCount'] ?? 0),
                'missingLinkCount' => (int) ($collection['missingLinkCount'] ?? 0),
                'linkRelCounts' => is_array($collection['linkRelCounts'] ?? null) ? $collection['linkRelCounts'] : [],
                'linkTargets' => self::collectionOwnLinkTargets($links),
                'childCount' => count($children),
                'leaf' => $children === [],
                'diagnosticCount' => count($itemDiagnostics),
                'diagnostics' => $itemDiagnostics,
            ];

            self::appendCollectionHierarchyItems(
                $children,
                $currentPath,
                $items,
                $diagnostics,
                $roleCounts,
                $primaryRoleCounts,
                $linkRelCounts,
                $depthCounts,
                $localLinkCount,
                $externalLinkCount,
                $missingLinkCount,
                $maxDepth,
                $leafCollectionCount,
            );
        }
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
     * @param list<array<string, mixed>> $links
     *
     * @return list<string>
     */
    private static function collectionOwnLinkTargets(array $links): array
    {
        $targets = [];
        foreach ($links as $link) {
            if (!is_array($link)) {
                continue;
            }

            $target = $link['target'] ?? null;
            if (is_string($target) && $target !== '') {
                $targets[] = $target;
            }
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
        $textLocalTargets = [];
        $textExternalTargets = [];
        $textMissingTargets = [];
        $audioLocalTargets = [];
        $audioExternalTargets = [];
        $audioMissingTargets = [];
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
                    'textLocalTargetCount' => 0,
                    'textExternalTargetCount' => 0,
                    'textMissingTargetCount' => 0,
                    'audioLocalTargetCount' => 0,
                    'audioExternalTargetCount' => 0,
                    'audioMissingTargetCount' => 0,
                    'textLocalTargets' => [],
                    'textExternalTargets' => [],
                    'textMissingTargets' => [],
                    'audioLocalTargets' => [],
                    'audioExternalTargets' => [],
                    'audioMissingTargets' => [],
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
            array_push($textLocalTargets, ...$item['textLocalTargets']);
            array_push($textExternalTargets, ...$item['textExternalTargets']);
            array_push($textMissingTargets, ...$item['textMissingTargets']);
            array_push($audioLocalTargets, ...$item['audioLocalTargets']);
            array_push($audioExternalTargets, ...$item['audioExternalTargets']);
            array_push($audioMissingTargets, ...$item['audioMissingTargets']);
            array_push($diagnostics, ...self::mediaOverlayItemDiagnostics($item));
        }

        $textLocalTargets = array_values(array_unique($textLocalTargets));
        $textExternalTargets = array_values(array_unique($textExternalTargets));
        $textMissingTargets = array_values(array_unique($textMissingTargets));
        $audioLocalTargets = array_values(array_unique($audioLocalTargets));
        $audioExternalTargets = array_values(array_unique($audioExternalTargets));
        $audioMissingTargets = array_values(array_unique($audioMissingTargets));

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
            'textLocalTargetCount' => count($textLocalTargets),
            'textExternalTargetCount' => count($textExternalTargets),
            'textMissingTargetCount' => count($textMissingTargets),
            'audioLocalTargetCount' => count($audioLocalTargets),
            'audioExternalTargetCount' => count($audioExternalTargets),
            'audioMissingTargetCount' => count($audioMissingTargets),
            'textLocalTargets' => $textLocalTargets,
            'textExternalTargets' => $textExternalTargets,
            'textMissingTargets' => $textMissingTargets,
            'audioLocalTargets' => $audioLocalTargets,
            'audioExternalTargets' => $audioExternalTargets,
            'audioMissingTargets' => $audioMissingTargets,
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
        $textLocalTargets = [];
        $textExternalTargets = [];
        $textMissingTargets = [];
        $audioLocalTargets = [];
        $audioExternalTargets = [];
        $audioMissingTargets = [];
        foreach ($timelineItems as $item) {
            $textTarget = $item['textTarget'] ?? null;
            if (is_string($textTarget) && $textTarget !== '') {
                $textTargets[] = $textTarget;
                if (($item['textExternal'] ?? false) === true) {
                    $textExternalTargets[] = $textTarget;
                } elseif (($item['textExists'] ?? false) === true) {
                    $textLocalTargets[] = $textTarget;
                } else {
                    $textMissingTargets[] = $textTarget;
                }
            }
            $audioTarget = $item['audioTarget'] ?? null;
            if (is_string($audioTarget) && $audioTarget !== '') {
                $audioTargets[] = $audioTarget;
                if (($item['audioExternal'] ?? false) === true) {
                    $audioExternalTargets[] = $audioTarget;
                } elseif (($item['audioExists'] ?? false) === true) {
                    $audioLocalTargets[] = $audioTarget;
                } else {
                    $audioMissingTargets[] = $audioTarget;
                }
            }
        }
        $textLocalTargets = array_values(array_unique($textLocalTargets));
        $textExternalTargets = array_values(array_unique($textExternalTargets));
        $textMissingTargets = array_values(array_unique($textMissingTargets));
        $audioLocalTargets = array_values(array_unique($audioLocalTargets));
        $audioExternalTargets = array_values(array_unique($audioExternalTargets));
        $audioMissingTargets = array_values(array_unique($audioMissingTargets));
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
            'textLocalTargetCount' => count($textLocalTargets),
            'textExternalTargetCount' => count($textExternalTargets),
            'textMissingTargetCount' => count($textMissingTargets),
            'audioLocalTargetCount' => count($audioLocalTargets),
            'audioExternalTargetCount' => count($audioExternalTargets),
            'audioMissingTargetCount' => count($audioMissingTargets),
            'textLocalTargets' => $textLocalTargets,
            'textExternalTargets' => $textExternalTargets,
            'textMissingTargets' => $textMissingTargets,
            'audioLocalTargets' => $audioLocalTargets,
            'audioExternalTargets' => $audioExternalTargets,
            'audioMissingTargets' => $audioMissingTargets,
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
                'textExternal' => $textReference['external'],
                'textManifestId' => $textReference['manifestId'],
                'textManifestMediaType' => $textReference['manifestMediaType'],
                'textHrefHasQuery' => $textReference['hrefHasQuery'],
                'textHrefQuery' => $textReference['hrefQuery'],
                'textHrefHasFragment' => $textReference['hrefHasFragment'],
                'textHrefFragment' => $textReference['hrefFragment'],
                'textByteLength' => $textReference['byteLength'],
                'textCompressedByteLength' => $textReference['compressedByteLength'],
                'textCompressionMethod' => $textReference['compressionMethod'],
                'textCompressionMethodName' => $textReference['compressionMethodName'],
                'textCompressionSupported' => $textReference['compressionSupported'],
                'textCrc32' => $textReference['crc32'],
                'textCanExposeBytes' => $textReference['canExposeBytes'],
                'audioSrc' => $audioSrc,
                'audioTarget' => $audioReference['target'],
                'audioPartName' => $audioReference['partName'],
                'audioExists' => $audioReference['exists'],
                'audioExternal' => $audioReference['external'],
                'audioManifestId' => $audioReference['manifestId'],
                'audioManifestMediaType' => $audioReference['manifestMediaType'],
                'audioHrefHasQuery' => $audioReference['hrefHasQuery'],
                'audioHrefQuery' => $audioReference['hrefQuery'],
                'audioHrefHasFragment' => $audioReference['hrefHasFragment'],
                'audioHrefFragment' => $audioReference['hrefFragment'],
                'audioByteLength' => $audioReference['byteLength'],
                'audioCompressedByteLength' => $audioReference['compressedByteLength'],
                'audioCompressionMethod' => $audioReference['compressionMethod'],
                'audioCompressionMethodName' => $audioReference['compressionMethodName'],
                'audioCompressionSupported' => $audioReference['compressionSupported'],
                'audioCrc32' => $audioReference['crc32'],
                'audioCanExposeBytes' => $audioReference['canExposeBytes'],
                'clipBegin' => $clipBegin,
                'clipBeginSeconds' => $clip['clipBeginSeconds'],
                'clipEnd' => $clipEnd,
                'clipEndSeconds' => $clip['clipEndSeconds'],
                'clipDurationSeconds' => $clip['clipDurationSeconds'],
                'clipValid' => $clip['valid'],
                'textDiagnostics' => $textReference['diagnostics'],
                'audioDiagnostics' => $audioReference['diagnostics'],
                'clipDiagnostics' => $clip['diagnostics'],
                'diagnostics' => array_merge($textReference['diagnostics'], $audioReference['diagnostics'], $clip['diagnostics']),
            ];
        }

        return $items;
    }

    /**
     * @param array<string, array<string, mixed>> $manifestByPart
     *
     * @return array<string, mixed>
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
                'manifestMediaType' => null,
                'hrefHasQuery' => false,
                'hrefQuery' => null,
                'hrefHasFragment' => false,
                'hrefFragment' => null,
                'byteLength' => null,
                'compressedByteLength' => null,
                'compressionMethod' => null,
                'compressionMethodName' => null,
                'compressionSupported' => null,
                'crc32' => null,
                'canExposeBytes' => false,
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
                'manifestMediaType' => null,
                'hrefHasQuery' => false,
                'hrefQuery' => null,
                'hrefHasFragment' => false,
                'hrefFragment' => null,
                'byteLength' => null,
                'compressedByteLength' => null,
                'compressionMethod' => null,
                'compressionMethodName' => null,
                'compressionSupported' => null,
                'crc32' => null,
                'canExposeBytes' => false,
                'diagnostics' => [[
                    'type' => 'invalid-media-overlay-' . $kind . '-reference',
                    'href' => $href,
                    'message' => $exception->getMessage(),
                ]],
            ];
        }

        $hrefSuffix = self::packageHrefSuffixReport($target);
        if (self::isAbsoluteUri($target)) {
            return [
                'target' => $target,
                'partName' => null,
                'exists' => false,
                'external' => true,
                'manifestId' => null,
                'manifestMediaType' => null,
                'hrefHasQuery' => $hrefSuffix['hasQuery'],
                'hrefQuery' => $hrefSuffix['query'],
                'hrefHasFragment' => $hrefSuffix['hasFragment'],
                'hrefFragment' => $hrefSuffix['fragment'],
                'byteLength' => null,
                'compressedByteLength' => null,
                'compressionMethod' => null,
                'compressionMethodName' => null,
                'compressionSupported' => null,
                'crc32' => null,
                'canExposeBytes' => false,
                'diagnostics' => [[
                    'type' => 'external-media-overlay-' . $kind . '-reference',
                    'href' => $href,
                    'message' => 'EPUB media-overlay ' . $kind . ' reference points outside the package and was not fetched',
                ]],
            ];
        }

        $partName = OpcPackagePath::stripQueryAndFragment($target);
        $exists = $package->has($partName);
        $entry = $exists ? $package->entry($partName) : null;
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
        $provenance = self::zipEntryProvenance($entry);
        if (is_array($manifestItem) && ($manifestItem['canExposeBytes'] ?? false) !== true) {
            $provenance['canExposeBytes'] = false;
        }

        return [
            'target' => $target,
            'partName' => $partName,
            'exists' => $exists,
            'external' => false,
            'manifestId' => is_array($manifestItem) ? (string) ($manifestItem['id'] ?? '') : null,
            'manifestMediaType' => is_array($manifestItem) ? (string) ($manifestItem['mediaType'] ?? '') : null,
            'hrefHasQuery' => $hrefSuffix['hasQuery'],
            'hrefQuery' => $hrefSuffix['query'],
            'hrefHasFragment' => $hrefSuffix['hasFragment'],
            'hrefFragment' => $hrefSuffix['fragment'],
            'diagnostics' => $diagnostics,
        ] + $provenance;
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
                'missingPart' => 'missing-manifest-fallback-style-package-part',
                'unreadablePart' => 'unreadable-manifest-fallback-style-package-part',
                'key' => 'fallbackStyle',
                'cycleMessage' => 'EPUB OPF manifest fallback-style chain cycles before reaching a CSS resource',
                'missingMessage' => 'EPUB OPF manifest fallback-style references an item id that is not in the OPF manifest',
                'unsupportedMessage' => 'EPUB OPF manifest fallback-style chain terminates at a non-CSS resource',
                'missingPartMessage' => 'EPUB OPF manifest fallback-style chain terminates at a package part that is not present in the ZIP',
                'unreadablePartMessage' => 'EPUB OPF manifest fallback-style chain terminates at a package part whose bytes cannot be exposed',
            ]
            : [
                'cyclic' => 'cyclic-manifest-fallback-chain',
                'missing' => 'missing-manifest-fallback-item',
                'unsupported' => 'unsupported-manifest-fallback-terminal',
                'missingPart' => 'missing-manifest-fallback-package-part',
                'unreadablePart' => 'unreadable-manifest-fallback-package-part',
                'key' => 'fallback',
                'cycleMessage' => 'EPUB OPF manifest fallback chain cycles before reaching a core media type',
                'missingMessage' => 'EPUB OPF manifest fallback references an item id that is not in the OPF manifest',
                'unsupportedMessage' => 'EPUB OPF manifest fallback chain terminates at another non-core media type',
                'missingPartMessage' => 'EPUB OPF manifest fallback chain terminates at a package part that is not present in the ZIP',
                'unreadablePartMessage' => 'EPUB OPF manifest fallback chain terminates at a package part whose bytes cannot be exposed',
            ];
        $chainBroken = false;

        while ($next !== null) {
            if (isset($visited[$next])) {
                $diagnostics[] = [
                    'type' => $diagnosticNames['cyclic'],
                    'id' => (string) ($current['id'] ?? ''),
                    $diagnosticNames['key'] => $next,
                    'chainIds' => array_map(static fn (array $chainItem): string => (string) $chainItem['id'], $chain),
                    'message' => $diagnosticNames['cycleMessage'],
                ];
                $chainBroken = true;
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
                $chainBroken = true;
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
                $chainBroken = true;
            } elseif (!$isStyle && ($terminal['coreMediaType'] ?? false) !== true) {
                $diagnostics[] = [
                    'type' => $diagnosticNames['unsupported'],
                    'id' => (string) ($item['id'] ?? ''),
                    'fallback' => $fallbackId,
                    'terminalId' => (string) ($terminal['id'] ?? ''),
                    'terminalMediaType' => (string) ($terminal['mediaType'] ?? ''),
                    'message' => $diagnosticNames['unsupportedMessage'],
                ];
                $chainBroken = true;
            }

            if (($terminal['exists'] ?? false) !== true) {
                $diagnostics[] = [
                    'type' => $diagnosticNames['missingPart'],
                    'id' => (string) ($item['id'] ?? ''),
                    $diagnosticNames['key'] => $fallbackId,
                    'terminalId' => (string) ($terminal['id'] ?? ''),
                    'terminalPartName' => (string) ($terminal['partName'] ?? ''),
                    'terminalMediaType' => (string) ($terminal['mediaType'] ?? ''),
                    'message' => $diagnosticNames['missingPartMessage'],
                ];
            } elseif (($terminal['canExposeBytes'] ?? false) !== true) {
                $diagnostics[] = [
                    'type' => $diagnosticNames['unreadablePart'],
                    'id' => (string) ($item['id'] ?? ''),
                    $diagnosticNames['key'] => $fallbackId,
                    'terminalId' => (string) ($terminal['id'] ?? ''),
                    'terminalPartName' => (string) ($terminal['partName'] ?? ''),
                    'terminalMediaType' => (string) ($terminal['mediaType'] ?? ''),
                    'compressionMethod' => $terminal['compressionMethod'] ?? null,
                    'compressionMethodName' => $terminal['compressionMethodName'] ?? null,
                    'message' => $diagnosticNames['unreadablePartMessage'],
                ];
            }
        }
        $terminalUsable = is_array($terminal)
            && ($terminal['exists'] ?? false) === true
            && ($terminal['canExposeBytes'] ?? false) === true;
        $resolved = !$chainBroken && $chain !== [];

        return [
            'id' => $fallbackId,
            'resolved' => $resolved,
            'usable' => $resolved && $terminalUsable && $diagnostics === [],
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
            $cipherReferenceReport = self::encryptionCipherReferenceReport($cipherReference);
            $keyInfo = self::encryptionKeyInfoReport($encryptedData);
            $obfuscatedFont = self::isObfuscatedFont($algorithm, $mediaType, $partName);
            $isCoverImage = in_array('cover-image', $properties, true);
            $item = [
                'index' => $index,
                'encryptedDataId' => self::emptyToNull($encryptedData->getAttribute('Id')),
                'encryptedDataType' => self::emptyToNull($encryptedData->getAttribute('Type')),
                'encryptedDataMimeType' => self::emptyToNull($encryptedData->getAttribute('MimeType')),
                'encryptedDataEncoding' => self::emptyToNull($encryptedData->getAttribute('Encoding')),
                'encryptedDataAttributes' => self::elementAttributes($encryptedData),
                'uri' => $uri,
                'partName' => $partName,
                'algorithm' => $algorithm,
                'encryptionMethodAttributes' => $method instanceof \DOMElement ? self::elementAttributes($method) : [],
                'cipherReferenceAttributes' => $cipherReference instanceof \DOMElement ? self::elementAttributes($cipherReference) : [],
                'cipherReferenceTransformCount' => $cipherReferenceReport['transformCount'],
                'cipherReferenceTransforms' => $cipherReferenceReport['transforms'],
                'cipherReferenceTransformAlgorithms' => $cipherReferenceReport['transformAlgorithms'],
                'keyInfo' => $keyInfo,
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

    /**
     * @return array<string, mixed>
     */
    private static function encryptionCipherReferenceReport(?\DOMElement $cipherReference): array
    {
        if (!$cipherReference instanceof \DOMElement) {
            return [
                'transformCount' => 0,
                'transformAlgorithms' => [],
                'transforms' => [],
            ];
        }

        $transformsElement = null;
        foreach (self::childElements($cipherReference, 'Transforms') as $child) {
            $transformsElement = $child;
            break;
        }

        $transforms = [];
        $algorithms = [];
        if ($transformsElement instanceof \DOMElement) {
            foreach (self::childElements($transformsElement, 'Transform') as $index => $transform) {
                $algorithm = self::emptyToNull($transform->getAttribute('Algorithm'));
                $transforms[] = [
                    'index' => $index,
                    'algorithm' => $algorithm,
                    'attributes' => self::elementAttributes($transform),
                ];
                if ($algorithm !== null) {
                    $algorithms[] = $algorithm;
                }
            }
        }

        return [
            'transformCount' => count($transforms),
            'transformAlgorithms' => array_values(array_unique($algorithms)),
            'transforms' => $transforms,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function encryptionKeyInfoReport(\DOMElement $encryptedData): array
    {
        $keyInfo = self::firstChildElement($encryptedData, 'KeyInfo', self::XMLDSIG_NAMESPACE);
        if (!$keyInfo instanceof \DOMElement) {
            return [
                'present' => false,
                'attributes' => [],
                'childElementCount' => 0,
                'childElementNames' => [],
                'keyNameCount' => 0,
                'keyNames' => [],
                'retrievalMethodCount' => 0,
                'x509DataCount' => 0,
            ];
        }

        $childElementNames = [];
        $keyNames = [];
        $retrievalMethodCount = 0;
        $x509DataCount = 0;
        foreach (self::childElements($keyInfo) as $child) {
            $childElementNames[] = self::qualifiedElementName($child);
            if ($child->namespaceURI !== self::XMLDSIG_NAMESPACE) {
                continue;
            }
            if ($child->localName === 'KeyName') {
                $keyName = self::normalizeText($child->textContent);
                if ($keyName !== '') {
                    $keyNames[] = $keyName;
                }
                continue;
            }
            if ($child->localName === 'RetrievalMethod') {
                ++$retrievalMethodCount;
                continue;
            }
            if ($child->localName === 'X509Data') {
                ++$x509DataCount;
            }
        }

        return [
            'present' => true,
            'attributes' => self::elementAttributes($keyInfo),
            'childElementCount' => count($childElementNames),
            'childElementNames' => $childElementNames,
            'keyNameCount' => count($keyNames),
            'keyNames' => $keyNames,
            'retrievalMethodCount' => $retrievalMethodCount,
            'x509DataCount' => $x509DataCount,
        ];
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
        $cipherReferenceTransformCount = 0;
        $cipherReferenceTransformAlgorithmCounts = [];
        $keyInfoCount = 0;
        $keyNames = [];

        foreach ($items as $item) {
            $partName = is_string($item['partName'] ?? null) ? $item['partName'] : null;
            $role = is_string($item['role'] ?? null) ? $item['role'] : 'asset';
            $obfuscatedFont = ($item['obfuscatedFont'] ?? false) === true;
            $canExposeBytes = ($item['canExposeBytes'] ?? false) === true;
            $attachmentCandidateBlocked = ($item['attachmentCandidateBlocked'] ?? false) === true;
            $cipherReferenceTransforms = is_array($item['cipherReferenceTransforms'] ?? null)
                ? array_values(array_filter(
                    $item['cipherReferenceTransforms'],
                    static fn (mixed $transform): bool => is_array($transform),
                ))
                : [];
            $cipherReferenceTransformAlgorithms = is_array($item['cipherReferenceTransformAlgorithms'] ?? null)
                ? array_values(array_filter(
                    $item['cipherReferenceTransformAlgorithms'],
                    static fn (mixed $algorithm): bool => is_string($algorithm) && $algorithm !== '',
                ))
                : [];
            $keyInfo = is_array($item['keyInfo'] ?? null) ? $item['keyInfo'] : [
                'present' => false,
                'attributes' => [],
                'childElementCount' => 0,
                'childElementNames' => [],
                'keyNameCount' => 0,
                'keyNames' => [],
                'retrievalMethodCount' => 0,
                'x509DataCount' => 0,
            ];

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
            $cipherReferenceTransformCount += count($cipherReferenceTransforms);
            foreach ($cipherReferenceTransformAlgorithms as $algorithm) {
                $cipherReferenceTransformAlgorithmCounts[$algorithm] = ($cipherReferenceTransformAlgorithmCounts[$algorithm] ?? 0) + 1;
            }
            if (($keyInfo['present'] ?? false) === true) {
                ++$keyInfoCount;
            }
            foreach (is_array($keyInfo['keyNames'] ?? null) ? $keyInfo['keyNames'] : [] as $keyName) {
                if (is_string($keyName) && $keyName !== '') {
                    $keyNames[] = $keyName;
                }
            }

            $reportItems[] = [
                'index' => (int) ($item['index'] ?? 0),
                'encryptedDataId' => is_string($item['encryptedDataId'] ?? null) ? $item['encryptedDataId'] : null,
                'encryptedDataType' => is_string($item['encryptedDataType'] ?? null) ? $item['encryptedDataType'] : null,
                'encryptedDataMimeType' => is_string($item['encryptedDataMimeType'] ?? null) ? $item['encryptedDataMimeType'] : null,
                'encryptedDataEncoding' => is_string($item['encryptedDataEncoding'] ?? null) ? $item['encryptedDataEncoding'] : null,
                'encryptedDataAttributes' => is_array($item['encryptedDataAttributes'] ?? null) ? $item['encryptedDataAttributes'] : [],
                'uri' => is_string($item['uri'] ?? null) ? $item['uri'] : null,
                'partName' => $partName,
                'manifestId' => is_string($item['manifestId'] ?? null) ? $item['manifestId'] : null,
                'mediaType' => is_string($item['mediaType'] ?? null) ? $item['mediaType'] : null,
                'role' => $role,
                'algorithm' => is_string($item['algorithm'] ?? null) ? $item['algorithm'] : null,
                'encryptionMethodAttributes' => is_array($item['encryptionMethodAttributes'] ?? null) ? $item['encryptionMethodAttributes'] : [],
                'cipherReferenceAttributes' => is_array($item['cipherReferenceAttributes'] ?? null) ? $item['cipherReferenceAttributes'] : [],
                'cipherReferenceTransformCount' => count($cipherReferenceTransforms),
                'cipherReferenceTransforms' => $cipherReferenceTransforms,
                'cipherReferenceTransformAlgorithms' => $cipherReferenceTransformAlgorithms,
                'keyInfo' => $keyInfo,
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
        ksort($cipherReferenceTransformAlgorithmCounts);
        $obfuscatedFontParts = array_values(array_unique($obfuscatedFontParts));
        $nonObfuscatedEncryptedParts = array_values(array_unique($nonObfuscatedEncryptedParts));
        $keyNames = array_values(array_unique($keyNames));
        sort($obfuscatedFontParts, SORT_STRING);
        sort($nonObfuscatedEncryptedParts, SORT_STRING);
        sort($keyNames, SORT_STRING);

        return [
            'present' => $items !== [],
            'itemCount' => count($items),
            'blockedByteExposureCount' => $blockedByteExposureCount,
            'obfuscatedFontCount' => count($obfuscatedFontParts),
            'nonObfuscatedEncryptedCount' => count($nonObfuscatedEncryptedParts),
            'attachmentCandidateBlockedCount' => $attachmentCandidateBlockedCount,
            'cipherReferenceTransformCount' => $cipherReferenceTransformCount,
            'cipherReferenceTransformAlgorithms' => array_keys($cipherReferenceTransformAlgorithmCounts),
            'cipherReferenceTransformAlgorithmCounts' => $cipherReferenceTransformAlgorithmCounts,
            'keyInfoCount' => $keyInfoCount,
            'keyNames' => $keyNames,
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
        return self::mediaTypeReport($mediaType)['mediaTypeBase'];
    }

    /**
     * @return array{
     *     mediaType:string,
     *     normalizedMediaType:string,
     *     mediaTypeBase:string,
     *     mediaTypeHasParameters:bool,
     *     mediaTypeParameterCount:int,
     *     mediaTypeParameters:list<array{name:string, value:string, raw:string}>,
     *     mediaTypeParameterMap:array<string, string>,
     *     mediaTypeSyntaxValid:bool,
     *     mediaTypeDiagnostics:list<array<string, mixed>>
     * }
     */
    private static function mediaTypeReport(string $mediaType): array
    {
        $segments = self::mediaTypeSegments($mediaType);
        $base = strtolower(trim((string) array_shift($segments)));
        $parameters = [];
        $parameterMap = [];
        $diagnostics = [];

        if ($base === '' || preg_match('/^[A-Za-z0-9!#$%&\'*+.^_`{|}~-]+\/[A-Za-z0-9!#$%&\'*+.^_`{|}~-]+$/', $base) !== 1) {
            $diagnostics[] = [
                'type' => 'invalid-manifest-media-type',
                'mediaType' => $mediaType,
                'mediaTypeBase' => $base,
                'message' => 'EPUB OPF manifest media-type must be a MIME type in type/subtype form',
            ];
        }

        foreach ($segments as $index => $segment) {
            $raw = trim($segment);
            if ($raw === '') {
                continue;
            }

            $equals = strpos($raw, '=');
            if ($equals === false) {
                $diagnostics[] = [
                    'type' => 'invalid-manifest-media-type-parameter',
                    'mediaType' => $mediaType,
                    'parameter' => $raw,
                    'parameterIndex' => $index,
                    'message' => 'EPUB OPF manifest media-type parameters must use name=value syntax',
                ];
                continue;
            }

            $name = strtolower(trim(substr($raw, 0, $equals)));
            if ($name === '' || preg_match('/^[A-Za-z0-9!#$%&\'*+.^_`{|}~-]+$/', $name) !== 1) {
                $diagnostics[] = [
                    'type' => 'invalid-manifest-media-type-parameter-name',
                    'mediaType' => $mediaType,
                    'parameter' => $raw,
                    'parameterIndex' => $index,
                    'name' => $name,
                    'message' => 'EPUB OPF manifest media-type parameter names must be MIME tokens',
                ];
                continue;
            }

            $value = trim(substr($raw, $equals + 1));
            if (strlen($value) >= 2 && $value[0] === '"' && substr($value, -1) === '"') {
                $value = substr($value, 1, -1);
                $value = preg_replace('/\\\\([\x20-\x7E])/', '$1', $value) ?? $value;
            }

            if (isset($parameterMap[$name])) {
                $diagnostics[] = [
                    'type' => 'duplicate-manifest-media-type-parameter',
                    'mediaType' => $mediaType,
                    'parameter' => $name,
                    'parameterIndex' => $index,
                    'previousValue' => $parameterMap[$name],
                    'value' => $value,
                    'message' => 'EPUB OPF manifest media-type parameter repeats a name; later value is retained for package review',
                ];
            }

            $parameters[] = [
                'name' => $name,
                'value' => $value,
                'raw' => $raw,
            ];
            $parameterMap[$name] = $value;
        }

        $normalized = $base;
        foreach ($parameterMap as $name => $value) {
            $normalized .= '; ' . $name . '=' . strtolower($value);
        }

        return [
            'mediaType' => $mediaType,
            'normalizedMediaType' => $normalized,
            'mediaTypeBase' => $base,
            'mediaTypeHasParameters' => $parameters !== [],
            'mediaTypeParameterCount' => count($parameters),
            'mediaTypeParameters' => $parameters,
            'mediaTypeParameterMap' => $parameterMap,
            'mediaTypeSyntaxValid' => $diagnostics === [],
            'mediaTypeDiagnostics' => $diagnostics,
        ];
    }

    /**
     * @return list<string>
     */
    private static function mediaTypeSegments(string $mediaType): array
    {
        $segments = [];
        $current = '';
        $inQuote = false;
        $escaped = false;
        $length = strlen($mediaType);

        for ($index = 0; $index < $length; $index++) {
            $char = $mediaType[$index];
            if ($escaped) {
                $current .= $char;
                $escaped = false;
                continue;
            }

            if ($inQuote && $char === '\\') {
                $current .= $char;
                $escaped = true;
                continue;
            }

            if ($char === '"') {
                $inQuote = !$inQuote;
                $current .= $char;
                continue;
            }

            if ($char === ';' && !$inQuote) {
                $segments[] = $current;
                $current = '';
                continue;
            }

            $current .= $char;
        }

        $segments[] = $current;

        return $segments;
    }

    /**
     * @param array<string, mixed> $item
     *
     * @return array<string, mixed>
     */
    private static function manifestMediaTypeItemReport(array $item, int $index): array
    {
        $id = (string) ($item['id'] ?? '');
        $mediaType = (string) ($item['mediaType'] ?? '');
        $baseMediaType = is_string($item['mediaTypeBase'] ?? null)
            ? $item['mediaTypeBase']
            : self::mediaTypeBase($mediaType);
        $parameters = is_array($item['mediaTypeParameters'] ?? null)
            ? array_values($item['mediaTypeParameters'])
            : [];
        $parameterMap = is_array($item['mediaTypeParameterMap'] ?? null)
            ? $item['mediaTypeParameterMap']
            : [];
        $parameterItems = [];
        $duplicateParameters = [];
        $seen = [];

        foreach ($parameters as $ordinal => $parameter) {
            if (!is_array($parameter)) {
                continue;
            }

            $name = is_string($parameter['name'] ?? null) ? $parameter['name'] : '';
            $value = is_string($parameter['value'] ?? null) ? $parameter['value'] : '';
            $duplicate = array_key_exists($name, $seen);
            $previousValue = $duplicate ? $seen[$name] : null;
            $parameterIndex = is_int($parameter['index'] ?? null) ? $parameter['index'] : $ordinal;
            $reviewItem = [
                'index' => $parameterIndex,
                'raw' => is_string($parameter['raw'] ?? null) ? $parameter['raw'] : '',
                'name' => $name,
                'value' => $value,
                'duplicate' => $duplicate,
                'previousValue' => $previousValue,
            ];
            $parameterItems[] = $reviewItem;
            if ($duplicate) {
                $duplicateParameters[] = [
                    'index' => $parameterIndex,
                    'raw' => $reviewItem['raw'],
                    'name' => $name,
                    'previousValue' => (string) $previousValue,
                    'value' => $value,
                ];
            }
            $seen[$name] = $value;
        }

        $diagnostics = [];
        foreach (is_array($item['mediaTypeDiagnostics'] ?? null) ? $item['mediaTypeDiagnostics'] : [] as $diagnostic) {
            if (!is_array($diagnostic)) {
                continue;
            }

            $diagnostics[] = [
                'index' => $index,
                'id' => $id,
                'href' => (string) ($item['href'] ?? ''),
                'partName' => is_string($item['partName'] ?? null) ? $item['partName'] : '',
            ] + $diagnostic;
        }

        return [
            'index' => $index,
            'id' => $id,
            'href' => (string) ($item['href'] ?? ''),
            'partName' => is_string($item['partName'] ?? null) ? $item['partName'] : '',
            'mediaType' => $mediaType,
            'mediaTypeBase' => $baseMediaType,
            'baseMediaType' => $baseMediaType,
            'normalizedMediaType' => is_string($item['normalizedMediaType'] ?? null)
                ? $item['normalizedMediaType']
                : self::mediaTypeReport($mediaType)['normalizedMediaType'],
            'mediaTypeParameters' => $parameterMap,
            'parameters' => $parameters,
            'parameterMap' => $parameterMap,
            'parameterNames' => array_keys($parameterMap),
            'parameterItems' => $parameterItems,
            'parameterCount' => count($parameterItems),
            'duplicateParameters' => $duplicateParameters,
            'duplicateParameterCount' => count($duplicateParameters),
            'valid' => $diagnostics === [],
            'diagnosticCount' => count($diagnostics),
            'diagnostics' => $diagnostics,
        ];
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
        $exposableItems = [];
        $blockedByteExposureItems = [];
        $missingItems = [];
        $externalItems = [];
        $encryptedItems = [];
        $unsupportedCompressionItems = [];
        $byteExposurePolicyCounts = [];
        $exposableByteLength = 0;
        $exposableCompressedByteLength = 0;

        foreach ($manifest as $index => $item) {
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
            $exists = ($item['exists'] ?? false) === true;
            $external = ($item['external'] ?? false) === true;
            $encrypted = ($item['encrypted'] ?? false) === true;
            $canExposeBytes = ($item['canExposeBytes'] ?? false) === true;
            $compressionSupported = is_bool($item['compressionSupported'] ?? null) ? $item['compressionSupported'] : null;
            $byteLength = is_int($item['byteLength'] ?? null) ? $item['byteLength'] : null;
            $compressedByteLength = is_int($item['compressedByteLength'] ?? null) ? $item['compressedByteLength'] : null;
            $partName = is_string($item['partName'] ?? null) ? $item['partName'] : null;
            $target = is_string($item['target'] ?? null) ? $item['target'] : $partName;
            $byteExposurePolicy = self::resourcePropertyByteExposurePolicy(
                $item,
                $exists,
                $external,
                $encrypted,
                $canExposeBytes,
                $compressionSupported,
            );
            $reportItem = [
                'index' => (int) $index,
                'id' => (string) ($item['id'] ?? ''),
                'href' => (string) ($item['href'] ?? ''),
                'target' => $target,
                'partName' => $partName,
                'mediaType' => (string) ($item['mediaType'] ?? ''),
                'exists' => $exists,
                'external' => $external,
                'encrypted' => $encrypted,
                'canExposeBytes' => $canExposeBytes,
                'byteExposurePolicy' => $byteExposurePolicy,
                'byteLength' => $byteLength,
                'compressedByteLength' => $compressedByteLength,
                'compressionMethod' => is_int($item['compressionMethod'] ?? null) ? $item['compressionMethod'] : null,
                'compressionMethodName' => is_string($item['compressionMethodName'] ?? null) ? $item['compressionMethodName'] : null,
                'compressionSupported' => $compressionSupported,
                'crc32' => is_string($item['crc32'] ?? null) ? $item['crc32'] : null,
                'encryption' => is_array($item['encryption'] ?? null) ? $item['encryption'] : null,
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
            if ($canExposeBytes) {
                $exposableItems[] = $reportItem;
                $exposableByteLength += $byteLength ?? 0;
                $exposableCompressedByteLength += $compressedByteLength ?? 0;
            } else {
                $blockedByteExposureItems[] = $reportItem;
            }
            if (!$exists) {
                $missingItems[] = $reportItem;
            }
            if ($external) {
                $externalItems[] = $reportItem;
            }
            if ($encrypted) {
                $encryptedItems[] = $reportItem;
            }
            if ($compressionSupported === false) {
                $unsupportedCompressionItems[] = $reportItem;
            }
            $byteExposurePolicyCounts[$byteExposurePolicy] = ($byteExposurePolicyCounts[$byteExposurePolicy] ?? 0) + 1;
        }

        ksort($byteExposurePolicyCounts, SORT_STRING);

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
                'exposableItemCount' => count($exposableItems),
                'blockedByteExposureCount' => count($blockedByteExposureItems),
                'missingItemCount' => count($missingItems),
                'externalItemCount' => count($externalItems),
                'encryptedItemCount' => count($encryptedItems),
                'unsupportedCompressionItemCount' => count($unsupportedCompressionItems),
                'exposableByteLength' => $exposableByteLength,
                'exposableCompressedByteLength' => $exposableCompressedByteLength,
                'byteExposurePolicyCounts' => $byteExposurePolicyCounts,
            ],
            'items' => $items,
            'itemsById' => $itemsById,
            'itemsByProperty' => $itemsByProperty,
            'reviewItems' => $reviewItems,
            'exposableItems' => $exposableItems,
            'blockedByteExposureItems' => $blockedByteExposureItems,
            'missingItems' => $missingItems,
            'externalItems' => $externalItems,
            'encryptedItems' => $encryptedItems,
            'unsupportedCompressionItems' => $unsupportedCompressionItems,
            'byteExposurePolicyCounts' => $byteExposurePolicyCounts,
            'propertyVocabulary' => $propertyVocabulary,
        ];
    }

    /**
     * @param array<string, mixed> $item
     */
    private static function resourcePropertyByteExposurePolicy(
        array $item,
        bool $exists,
        bool $external,
        bool $encrypted,
        bool $canExposeBytes,
        ?bool $compressionSupported
    ): string {
        if ($external) {
            return 'external-resource-metadata-only';
        }

        if (!$exists) {
            return 'missing-resource-metadata-only';
        }

        if ($encrypted) {
            $encryption = is_array($item['encryption'] ?? null) ? $item['encryption'] : [];

            return is_string($encryption['byteExposurePolicy'] ?? null)
                ? $encryption['byteExposurePolicy']
                : 'encrypted-resource-bytes-blocked';
        }

        if ($canExposeBytes) {
            return 'manifest-resource-bytes-exposable';
        }

        if ($compressionSupported === false) {
            return 'unsupported-compression-metadata-only';
        }

        return 'manifest-resource-metadata-only';
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
            } elseif (($handler['external'] ?? false) === true) {
                $itemDiagnostics[] = [
                    'type' => 'external-binding-handler',
                    'mediaType' => $mediaType === '' ? null : $mediaType,
                    'handlerId' => $handlerId,
                    'handlerHref' => (string) ($handler['href'] ?? ''),
                    'handlerTarget' => (string) ($handler['target'] ?? ''),
                    'message' => 'EPUB OPF binding handler points outside the package and was not fetched',
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

            $handlerPartName = is_array($handler) && is_string($handler['partName'] ?? null) ? $handler['partName'] : null;
            $entry = $handlerPartName !== null && $package->has($handlerPartName)
                ? $package->entry($handlerPartName)
                : null;
            $handlerEncrypted = is_array($handler) && ($handler['encrypted'] ?? false) === true;
            $handlerProvenance = self::zipEntryProvenance($entry);
            if ($handlerEncrypted) {
                $handlerProvenance['canExposeBytes'] = false;
            }
            $handlerByteSha256 = ($handlerProvenance['canExposeBytes'] ?? false) === true && $handlerPartName !== null
                ? hash('sha256', $package->read($handlerPartName))
                : null;

            $items[] = [
                'index' => $index,
                'mediaType' => $mediaType === '' ? null : $mediaType,
                'handlerId' => $handlerId === '' ? null : $handlerId,
                'handlerHref' => is_array($handler) ? (string) $handler['href'] : null,
                'handlerTarget' => is_array($handler) ? (string) ($handler['target'] ?? '') : null,
                'handlerPartName' => $handlerPartName,
                'handlerExternal' => is_array($handler) && ($handler['external'] ?? false) === true,
                'handlerMediaType' => is_array($handler) ? (string) $handler['mediaType'] : null,
                'handlerProperties' => is_array($handler) ? $handler['properties'] : [],
                'handlerHrefHasQuery' => is_array($handler) && ($handler['hrefHasQuery'] ?? false) === true,
                'handlerHrefQuery' => is_array($handler) ? ($handler['hrefQuery'] ?? null) : null,
                'handlerHrefHasFragment' => is_array($handler) && ($handler['hrefHasFragment'] ?? false) === true,
                'handlerHrefFragment' => is_array($handler) ? ($handler['hrefFragment'] ?? null) : null,
                'handlerManifestDiagnostics' => is_array($handler) && is_array($handler['diagnostics'] ?? null) ? $handler['diagnostics'] : [],
                'handlerExists' => $entry instanceof ZipPackageEntry,
                'handlerEncrypted' => $handlerEncrypted,
                'handlerCanExposeBytes' => $handlerProvenance['canExposeBytes'],
                'handlerEncryption' => is_array($handler) && is_array($handler['encryption'] ?? null) ? $handler['encryption'] : null,
                'handlerByteLength' => $handlerProvenance['byteLength'],
                'handlerCompressedByteLength' => $handlerProvenance['compressedByteLength'],
                'handlerCompressionMethod' => $handlerProvenance['compressionMethod'],
                'handlerCompressionMethodName' => $handlerProvenance['compressionMethodName'],
                'handlerCompressionSupported' => $handlerProvenance['compressionSupported'],
                'handlerByteSha256' => $handlerByteSha256,
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
     * @param array<string, array<string, mixed>> $manifestByPart
     *
     * @return list<array<string, mixed>>
     */
    private static function parseNcxDocument(
        ZipPackage $package,
        string $xml,
        string $ncxPartName,
        array $manifestByPart
    ): array
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

        return self::parseNcxNavPoints($package, $navMap, $ncxPartName, $manifestByPart, 1);
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
     * @param array<string, array<string, mixed>> $manifestByPart
     *
     * @return list<array<string, mixed>>
     */
    private static function parseNcxNavPoints(
        ZipPackage $package,
        \DOMElement $parent,
        string $ncxPartName,
        array $manifestByPart,
        int $depth
    ): array
    {
        $entries = [];
        foreach (self::childElements($parent, 'navPoint') as $navPoint) {
            $labelContainer = self::firstChildElement($navPoint, 'navLabel');
            $labelElement = $labelContainer instanceof \DOMElement
                ? self::firstDescendantElement($labelContainer, 'text')
                : null;
            $labelAudio = self::parseNcxLabelAudio($package, $labelContainer, $ncxPartName, $manifestByPart);
            $contentElement = self::firstChildElement($navPoint, 'content');
            $href = $contentElement instanceof \DOMElement && $contentElement->hasAttribute('src')
                ? $contentElement->getAttribute('src')
                : null;
            $playOrder = $navPoint->hasAttribute('playOrder') && ctype_digit($navPoint->getAttribute('playOrder'))
                ? (int) $navPoint->getAttribute('playOrder')
                : null;

            $entries[] = [
                'id' => self::emptyToNull($navPoint->getAttribute('id')),
                'label' => $labelElement instanceof \DOMElement ? self::normalizeText($labelElement->textContent) : '',
                'href' => $href,
                'target' => $href === null || $href === '' ? null : self::resolveReadingHref($ncxPartName, $href),
                'depth' => $depth,
                'playOrder' => $playOrder,
                'labelAudio' => $labelAudio,
                'labelAudioCount' => count($labelAudio),
                'labelAudioDiagnostics' => self::ncxAudioDiagnostics($labelAudio),
            ];

            array_push(
                $entries,
                ...self::parseNcxNavPoints($package, $navPoint, $ncxPartName, $manifestByPart, $depth + 1),
            );
        }

        return $entries;
    }

    /**
     * @param array<string, array<string, mixed>> $manifestByPart
     *
     * @return list<array<string, mixed>>
     */
    private static function parseNcxLabelAudio(
        ZipPackage $package,
        ?\DOMElement $labelContainer,
        string $ncxPartName,
        array $manifestByPart
    ): array {
        if (!$labelContainer instanceof \DOMElement) {
            return [];
        }

        $items = [];
        foreach (self::childElements($labelContainer, 'audio') as $index => $audio) {
            $src = self::emptyToNull($audio->getAttribute('src'));
            $reference = self::ncxAudioReference($package, $ncxPartName, $src, $manifestByPart);
            $clipBegin = self::emptyToNull($audio->getAttribute('clipBegin'));
            $clipEnd = self::emptyToNull($audio->getAttribute('clipEnd'));
            $clip = self::ncxAudioClipTiming($clipBegin, $clipEnd);
            $diagnostics = array_merge($reference['diagnostics'], $clip['diagnostics']);
            $mediaType = is_string($reference['manifestMediaType'] ?? null) ? (string) $reference['manifestMediaType'] : null;

            if ($mediaType !== null && !str_starts_with(self::mediaTypeBase($mediaType), 'audio/')) {
                $diagnostics[] = [
                    'type' => 'unexpected-ncx-audio-media-type',
                    'src' => $src,
                    'partName' => $reference['partName'],
                    'mediaType' => $mediaType,
                    'message' => 'EPUB NCX label audio points at a non-audio manifest item',
                ];
            }

            $items[] = [
                'index' => $index,
                'id' => self::emptyToNull($audio->getAttribute('id')),
                'src' => $src,
                'clipBegin' => $clipBegin,
                'clipEnd' => $clipEnd,
                'clipBeginSeconds' => $clip['clipBeginSeconds'],
                'clipEndSeconds' => $clip['clipEndSeconds'],
                'clipDurationSeconds' => $clip['clipDurationSeconds'],
                'clipValid' => $clip['valid'],
                'clipDiagnostics' => $clip['diagnostics'],
                'diagnostics' => $diagnostics,
                'diagnosticCount' => count($diagnostics),
            ] + $reference;
        }

        return $items;
    }

    /**
     * @param array<string, array<string, mixed>> $manifestByPart
     *
     * @return array<string, mixed>
     */
    private static function ncxAudioReference(
        ZipPackage $package,
        string $ncxPartName,
        ?string $src,
        array $manifestByPart
    ): array {
        if ($src === null) {
            return [
                'target' => null,
                'partName' => null,
                'part' => null,
                'exists' => false,
                'external' => false,
                'manifestId' => null,
                'manifestMediaType' => null,
                'hrefHasQuery' => false,
                'hrefQuery' => null,
                'hrefHasFragment' => false,
                'hrefFragment' => null,
                'byteLength' => null,
                'compressedByteLength' => null,
                'compressionMethod' => null,
                'compressionMethodName' => null,
                'compressionSupported' => null,
                'crc32' => null,
                'byteSha256' => null,
                'canExposeBytes' => false,
                'diagnostics' => [[
                    'type' => 'missing-ncx-audio-src',
                    'message' => 'EPUB NCX label audio is missing src',
                ]],
            ];
        }

        try {
            $target = self::resolvePackageHref($ncxPartName, $src);
        } catch (\InvalidArgumentException $exception) {
            return [
                'target' => null,
                'partName' => null,
                'part' => null,
                'exists' => false,
                'external' => false,
                'manifestId' => null,
                'manifestMediaType' => null,
                'hrefHasQuery' => false,
                'hrefQuery' => null,
                'hrefHasFragment' => false,
                'hrefFragment' => null,
                'byteLength' => null,
                'compressedByteLength' => null,
                'compressionMethod' => null,
                'compressionMethodName' => null,
                'compressionSupported' => null,
                'crc32' => null,
                'byteSha256' => null,
                'canExposeBytes' => false,
                'diagnostics' => [[
                    'type' => 'invalid-ncx-audio-reference',
                    'src' => $src,
                    'message' => $exception->getMessage(),
                ]],
            ];
        }

        $hrefSuffix = self::packageHrefSuffixReport($target);
        if (self::isAbsoluteUri($target)) {
            return [
                'target' => $target,
                'partName' => null,
                'part' => null,
                'exists' => false,
                'external' => true,
                'manifestId' => null,
                'manifestMediaType' => null,
                'hrefHasQuery' => $hrefSuffix['hasQuery'],
                'hrefQuery' => $hrefSuffix['query'],
                'hrefHasFragment' => $hrefSuffix['hasFragment'],
                'hrefFragment' => $hrefSuffix['fragment'],
                'byteLength' => null,
                'compressedByteLength' => null,
                'compressionMethod' => null,
                'compressionMethodName' => null,
                'compressionSupported' => null,
                'crc32' => null,
                'byteSha256' => null,
                'canExposeBytes' => false,
                'diagnostics' => [[
                    'type' => 'external-ncx-audio-reference',
                    'src' => $src,
                    'target' => $target,
                    'message' => 'EPUB NCX label audio points outside the package and was not fetched',
                ]],
            ];
        }

        $partName = OpcPackagePath::stripQueryAndFragment($target);
        $exists = $package->has($partName);
        $entry = $exists ? $package->entry($partName) : null;
        $manifestItem = $manifestByPart[$partName] ?? null;
        $provenance = self::zipEntryProvenance($entry);
        $canExposeBytes = $exists && $provenance['canExposeBytes'];
        if (is_array($manifestItem) && ($manifestItem['canExposeBytes'] ?? false) !== true) {
            $canExposeBytes = false;
        }
        $provenance['canExposeBytes'] = $canExposeBytes;
        $diagnostics = [];
        if (!$exists) {
            $diagnostics[] = [
                'type' => 'missing-ncx-audio-reference',
                'src' => $src,
                'partName' => $partName,
                'message' => 'EPUB NCX label audio target is missing from the package',
            ];
        } elseif (!$canExposeBytes) {
            $diagnostics[] = [
                'type' => 'ncx-audio-reference-bytes-unavailable',
                'src' => $src,
                'partName' => $partName,
                'compressionMethod' => $provenance['compressionMethod'],
                'compressionMethodName' => $provenance['compressionMethodName'],
                'message' => 'EPUB NCX label audio bytes are not exposed by compact package review policy',
            ];
        }

        return [
            'target' => $target,
            'partName' => $partName,
            'part' => $partName,
            'exists' => $exists,
            'external' => false,
            'manifestId' => is_array($manifestItem) ? (string) ($manifestItem['id'] ?? '') : null,
            'manifestMediaType' => is_array($manifestItem) ? (string) ($manifestItem['mediaType'] ?? '') : null,
            'hrefHasQuery' => $hrefSuffix['hasQuery'],
            'hrefQuery' => $hrefSuffix['query'],
            'hrefHasFragment' => $hrefSuffix['hasFragment'],
            'hrefFragment' => $hrefSuffix['fragment'],
            'byteSha256' => $canExposeBytes ? hash('sha256', $package->read($partName)) : null,
            'diagnostics' => $diagnostics,
        ] + $provenance;
    }

    /**
     * @return array{clipBeginSeconds:?float, clipEndSeconds:?float, clipDurationSeconds:?float, valid:bool, diagnostics:list<array<string, mixed>>}
     */
    private static function ncxAudioClipTiming(?string $clipBegin, ?string $clipEnd): array
    {
        $beginSeconds = $clipBegin === null ? null : self::smilClockSeconds($clipBegin);
        $endSeconds = $clipEnd === null ? null : self::smilClockSeconds($clipEnd);
        $diagnostics = [];

        if ($clipBegin !== null && $beginSeconds === null) {
            $diagnostics[] = [
                'type' => 'invalid-ncx-audio-clip-begin',
                'clipBegin' => $clipBegin,
                'message' => 'EPUB NCX audio clipBegin must be a bounded SMIL clock value',
            ];
        }
        if ($clipEnd !== null && $endSeconds === null) {
            $diagnostics[] = [
                'type' => 'invalid-ncx-audio-clip-end',
                'clipEnd' => $clipEnd,
                'message' => 'EPUB NCX audio clipEnd must be a bounded SMIL clock value',
            ];
        }
        if ($beginSeconds !== null && $endSeconds !== null && $endSeconds < $beginSeconds) {
            $diagnostics[] = [
                'type' => 'ncx-audio-clip-end-before-begin',
                'clipBegin' => $clipBegin,
                'clipEnd' => $clipEnd,
                'clipBeginSeconds' => $beginSeconds,
                'clipEndSeconds' => $endSeconds,
                'message' => 'EPUB NCX audio clipEnd must not be earlier than clipBegin',
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

    /**
     * @param list<array<string, mixed>> $audioItems
     *
     * @return list<array<string, mixed>>
     */
    private static function ncxAudioDiagnostics(array $audioItems): array
    {
        $diagnostics = [];
        foreach ($audioItems as $item) {
            if (!is_array($item['diagnostics'] ?? null)) {
                continue;
            }
            array_push($diagnostics, ...$item['diagnostics']);
        }

        return $diagnostics;
    }

    /**
     * @param array<string, mixed>|null $navigation
     *
     * @return array<string, mixed>
     */
    private static function ncxAudioLabelReport(?array $navigation): array
    {
        $items = [];
        $targets = [];
        $localTargets = [];
        $externalTargets = [];
        $missingTargets = [];
        $diagnostics = [];
        $localCount = 0;
        $externalCount = 0;
        $missingCount = 0;
        $blockedCount = 0;

        if (!is_array($navigation) || ($navigation['type'] ?? null) !== 'ncx') {
            return [
                'present' => false,
                'count' => 0,
                'localCount' => 0,
                'externalCount' => 0,
                'missingCount' => 0,
                'blockedCount' => 0,
                'diagnosticCount' => 0,
                'targets' => [],
                'localTargets' => [],
                'externalTargets' => [],
                'missingTargets' => [],
                'items' => [],
                'diagnostics' => [],
            ];
        }

        foreach (is_array($navigation['entries'] ?? null) ? $navigation['entries'] : [] as $entryIndex => $entry) {
            if (!is_array($entry) || !is_array($entry['labelAudio'] ?? null)) {
                continue;
            }

            foreach ($entry['labelAudio'] as $audioIndex => $audio) {
                if (!is_array($audio)) {
                    continue;
                }

                $item = [
                    'entryIndex' => $entryIndex,
                    'audioIndex' => $audioIndex,
                    'entryId' => is_string($entry['id'] ?? null) ? $entry['id'] : null,
                    'entryLabel' => is_string($entry['label'] ?? null) ? $entry['label'] : '',
                    'entryTarget' => is_string($entry['target'] ?? null) ? $entry['target'] : null,
                ] + $audio;
                $items[] = $item;

                $target = is_string($audio['target'] ?? null) ? $audio['target'] : null;
                if ($target !== null) {
                    $targets[] = $target;
                }
                if (($audio['external'] ?? false) === true) {
                    ++$externalCount;
                    if ($target !== null) {
                        $externalTargets[] = $target;
                    }
                } elseif (($audio['exists'] ?? false) === true) {
                    ++$localCount;
                    if ($target !== null) {
                        $localTargets[] = $target;
                    }
                    if (($audio['canExposeBytes'] ?? false) !== true) {
                        ++$blockedCount;
                    }
                } else {
                    ++$missingCount;
                    if ($target !== null) {
                        $missingTargets[] = $target;
                    }
                }

                if (is_array($audio['diagnostics'] ?? null)) {
                    array_push($diagnostics, ...$audio['diagnostics']);
                }
            }
        }

        return [
            'present' => $items !== [],
            'count' => count($items),
            'localCount' => $localCount,
            'externalCount' => $externalCount,
            'missingCount' => $missingCount,
            'blockedCount' => $blockedCount,
            'diagnosticCount' => count($diagnostics),
            'targets' => array_values(array_unique($targets)),
            'localTargets' => array_values(array_unique($localTargets)),
            'externalTargets' => array_values(array_unique($externalTargets)),
            'missingTargets' => array_values(array_unique($missingTargets)),
            'items' => $items,
            'diagnostics' => $diagnostics,
        ];
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

            $name = is_string($attribute->prefix) && $attribute->prefix !== ''
                ? $attribute->prefix . ':' . $attribute->localName
                : $attribute->name;
            $attributes[$name] = $attribute->value;
        }

        ksort($attributes);

        return $attributes;
    }

    private static function qualifiedElementName(\DOMElement $element): string
    {
        if (is_string($element->prefix) && $element->prefix !== '') {
            return $element->prefix . ':' . $element->localName;
        }

        return $element->localName;
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
