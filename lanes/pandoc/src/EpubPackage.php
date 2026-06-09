<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class EpubPackage
{
    public const OCF_CONTAINER_NAMESPACE = 'urn:oasis:names:tc:opendocument:xmlns:container';
    public const OPF_NAMESPACE = 'http://www.idpf.org/2007/opf';
    public const DC_NAMESPACE = 'http://purl.org/dc/elements/1.1/';
    public const EPUB_OPS_NAMESPACE = 'http://www.idpf.org/2007/ops';
    public const XHTML_NAMESPACE = 'http://www.w3.org/1999/xhtml';
    public const NCX_NAMESPACE = 'http://www.daisy.org/z3986/2005/ncx/';
    public const EPUB_MIMETYPE = 'application/epub+zip';
    public const OPF_MEDIA_TYPE = 'application/oebps-package+xml';
    public const XHTML_MEDIA_TYPE = 'application/xhtml+xml';
    public const NCX_MEDIA_TYPE = 'application/x-dtbncx+xml';
    private const RESERVED_PACKAGE_PREFIXES = [
        'a11y' => 'http://www.idpf.org/epub/vocab/package/a11y/#',
        'dcterms' => 'http://purl.org/dc/terms/',
        'media' => 'http://www.idpf.org/epub/vocab/overlays/#',
        'rendition' => 'http://www.idpf.org/vocab/rendition/#',
        'schema' => 'http://schema.org/',
        'xsd' => 'http://www.w3.org/2001/XMLSchema#',
    ];

    /**
     * @param list<array{fullPath:string, partName:string, mediaType:string}> $rootfiles
     * @param array<string, mixed> $metadata
     * @param list<array<string, mixed>> $packageLinks
     * @param array<string, array{id:string, href:string, partName:string, mediaType:string, properties:list<string>, fallback:?string}> $manifestById
     * @param list<array{id:string, href:string, partName:string, mediaType:string, properties:list<string>, fallback:?string}> $manifestItems
     * @param list<array{idref:string, href:string, partName:string, mediaType:string, linear:bool, properties:list<string>}> $spine
     * @param list<array{type:?string, title:?string, href:?string, target:?string, partName:?string, external:bool, exists:bool}> $guideReferences
     * @param list<array<string, mixed>> $collections
     * @param array<string, mixed> $bindings
     * @param array{type:string, partName:string, entries:list<array{label:string, href:?string, target:?string, depth:int, playOrder:?int}>}|null $navigation
     * @param list<array{type:?string, types:list<string>, label:?string, partName:string, entries:list<array{label:string, href:?string, target:?string, depth:int, playOrder:?int}>}> $navigationSections
     */
    private function __construct(
        private readonly ZipPackage $package,
        private readonly array $rootfiles,
        private readonly string $opfPartName,
        private readonly array $metadata,
        private readonly array $packageLinks,
        private readonly array $manifestById,
        private readonly array $manifestItems,
        private readonly array $spine,
        private readonly array $guideReferences,
        private readonly array $collections,
        private readonly array $bindings,
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
        $navigation = self::loadNavigation($package, $opfPartName, $opf['manifestById'], $opf['spineTocId']);

        return new self(
            $package,
            $rootfiles,
            $opfPartName,
            $opf['metadata'],
            $opf['packageLinks'],
            $opf['manifestById'],
            $opf['manifestItems'],
            $opf['spine'],
            $opf['guideReferences'],
            $opf['collections'],
            $opf['bindings'],
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
     * @return list<array{id:string, href:string, partName:string, mediaType:string, properties:list<string>, fallback:?string}>
     */
    public function manifestItems(): array
    {
        return $this->manifestItems;
    }

    /**
     * @return array{id:string, href:string, partName:string, mediaType:string, properties:list<string>, fallback:?string}|null
     */
    public function manifestItem(string $id): ?array
    {
        return $this->manifestById[$id] ?? null;
    }

    /**
     * @return list<array{idref:string, href:string, partName:string, mediaType:string, linear:bool, properties:list<string>}>
     */
    public function spine(): array
    {
        return $this->spine;
    }

    /**
     * @return list<array{idref:string, href:string, partName:string, mediaType:string, linear:bool, properties:list<string>}>
     */
    public function readingOrder(): array
    {
        return $this->spine;
    }

    /**
     * @return list<array{type:?string, title:?string, href:?string, target:?string, partName:?string, external:bool, exists:bool}>
     */
    public function guideReferences(): array
    {
        return $this->guideReferences;
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
     * @return list<array{id:string, href:string, partName:string, mediaType:string, properties:list<string>, fallback:?string}>
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
        return self::remoteResourcePolicyReport($this->packageLinks, $this->collections);
    }

    /**
     * @return array<string, mixed>
     */
    public function summary(): array
    {
        $assetSummary = $this->assetSummary();
        $navigationEntries = $this->navigation['entries'] ?? [];
        $packageLinkReport = self::collectionLinkReport($this->packageLinks);
        $packageLinkVocabulary = is_array($this->metadata['linkVocabulary'] ?? null)
            ? $this->metadata['linkVocabulary']
            : self::metadataLinkVocabularySummary($this->packageLinks);
        $remoteResourcePolicy = $this->remoteResourcePolicy();

        return [
            'opfPart' => $this->opfPartName,
            'rootfiles' => $this->rootfiles,
            'metadata' => $this->metadata,
            'packageLinks' => $this->packageLinks,
            'packageLinksByRel' => $packageLinkReport['linksByRel'],
            'packageLinkRelCounts' => $packageLinkReport['relCounts'],
            'packageLinkDiagnostics' => $packageLinkReport['diagnostics'],
            'packageLinkVocabulary' => $packageLinkVocabulary,
            'manifest' => $this->manifestItems,
            'readingOrder' => $this->spine,
            'guide' => $this->guideReferences,
            'collections' => $this->collections,
            'bindings' => $this->bindings,
            'navigation' => $this->navigation,
            'navigationSections' => $this->navigationSections,
            'assets' => $assetSummary,
            'remoteResourcePolicy' => $remoteResourcePolicy,
            'wordpressImport' => [
                'title' => $this->metadata['title'],
                'creators' => $this->metadata['creators'],
                'language' => $this->metadata['language'],
                'metadataDetails' => [
                    'titleDetails' => $this->metadata['titleDetails'] ?? [],
                    'titlesByType' => $this->metadata['titlesByType'] ?? [],
                    'mainTitle' => $this->metadata['mainTitle'] ?? null,
                    'subtitle' => $this->metadata['subtitle'] ?? null,
                    'shortTitle' => $this->metadata['shortTitle'] ?? null,
                    'sortTitle' => $this->metadata['sortTitle'] ?? null,
                    'creatorDetails' => $this->metadata['creatorDetails'] ?? [],
                    'creatorsByRole' => $this->metadata['creatorsByRole'] ?? [],
                    'identifierDetails' => $this->metadata['identifierDetails'] ?? [],
                    'identifiersByType' => $this->metadata['identifiersByType'] ?? [],
                    'refinementsById' => $this->metadata['refinementsById'] ?? [],
                ],
                'readingOrderParts' => $assetSummary['readingOrderParts'],
                'navigationLabels' => array_values(array_map(
                    static fn (array $entry): string => $entry['label'],
                    $navigationEntries,
                )),
                'guideReferences' => $this->guideReferences,
                'collections' => $this->collections,
                'collectionTitles' => self::collectionTitles($this->collections),
                'collectionLinkTargets' => self::collectionLinkTargets($this->collections),
                'collectionDiagnostics' => self::collectionDiagnostics($this->collections),
                'packageLinks' => $this->packageLinks,
                'packageLinksByRel' => $packageLinkReport['linksByRel'],
                'packageLinkTargets' => self::packageLinkTargets($this->packageLinks),
                'packageLinkDiagnostics' => $packageLinkReport['diagnostics'],
                'packageLinkVocabulary' => $packageLinkVocabulary,
                'packageLinkVocabularyDiagnostics' => $packageLinkVocabulary['diagnostics'],
                'remoteResourcePolicy' => $remoteResourcePolicy,
                'remoteResourceExternalTargets' => $remoteResourcePolicy['externalTargets'],
                'remoteResourcePolicyDiagnostics' => $remoteResourcePolicy['diagnostics'],
                'mediaTypeBindings' => $this->bindings['items'],
                'mediaTypeBindingDiagnostics' => $this->bindings['diagnostics'],
                'landmarkTargets' => self::navigationEntriesForSectionType($this->navigationSections, 'landmarks'),
                'pageListTargets' => self::navigationEntriesForSectionType($this->navigationSections, 'page-list'),
                'coverImagePart' => $assetSummary['coverImagePart'],
                'stylesheetParts' => $assetSummary['stylesheetParts'],
                'imageParts' => $assetSummary['imageParts'],
            ],
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
     * @return array{
     *     metadata:array<string, mixed>,
     *     packageLinks:list<array<string, mixed>>,
     *     manifestById:array<string, array{id:string, href:string, partName:string, mediaType:string, properties:list<string>, fallback:?string}>,
     *     manifestItems:list<array{id:string, href:string, partName:string, mediaType:string, properties:list<string>, fallback:?string}>,
     *     spine:list<array{idref:string, href:string, partName:string, mediaType:string, linear:bool, properties:list<string>}>,
     *     guideReferences:list<array{type:?string, title:?string, href:?string, target:?string, partName:?string, external:bool, exists:bool}>,
     *     collections:list<array<string, mixed>>,
     *     bindings:array<string, mixed>,
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
        $packageLinks = self::parsePackageLinks(
            $metadataElement,
            $opfPartName,
            $package,
            self::manifestByPart($manifestById),
            is_array($metadata['prefixBindings'] ?? null) ? $metadata['prefixBindings'] : [],
        );
        $packageLinkReport = self::collectionLinkReport($packageLinks);
        $metadata['links'] = $packageLinks;
        $metadata['linksByRel'] = $packageLinkReport['linksByRel'];
        $metadata['linkRelCounts'] = $packageLinkReport['relCounts'];
        $metadata['linkDiagnostics'] = $packageLinkReport['diagnostics'];
        $metadata['linkVocabulary'] = self::metadataLinkVocabularySummary($packageLinks);
        $spine = self::parseSpine($spineElement, $manifestById);
        $guideReferences = self::parseGuide(self::firstChildElement($root, 'guide', self::OPF_NAMESPACE), $opfPartName, $package);
        $collections = self::parseCollections($root, $opfPartName, $package, $manifestById);
        $bindings = self::parseBindings(self::firstChildElement($root, 'bindings', self::OPF_NAMESPACE), $manifestById, $package);

        return [
            'metadata' => $metadata,
            'packageLinks' => $packageLinks,
            'manifestById' => $manifestById,
            'manifestItems' => $manifestItems,
            'spine' => $spine,
            'guideReferences' => $guideReferences,
            'collections' => $collections,
            'bindings' => $bindings,
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
        $titleDetails = self::metadataTitleDetails($dc['title'] ?? []);
        $mainTitle = self::firstMetadataTitleByType($titleDetails, 'main') ?? ($titleDetails[0] ?? null);
        $creatorDetails = self::metadataAgentDetails($dc['creator'] ?? [], 'creator');
        $contributorDetails = self::metadataAgentDetails($dc['contributor'] ?? [], 'contributor');
        $identifierDetails = self::metadataIdentifierDetails($dc['identifier'] ?? []);

        $uniqueIdentifierId = $packageElement->hasAttribute('unique-identifier')
            ? $packageElement->getAttribute('unique-identifier')
            : null;
        $identifier = null;
        if ($uniqueIdentifierId !== null && $uniqueIdentifierId !== '') {
            foreach ($identifiers as $entry) {
                if ($entry['id'] === $uniqueIdentifierId) {
                    $identifier = $entry['value'];
                    break;
                }
            }
        }
        $identifier ??= $identifiers[0]['value'] ?? '';

        return [
            'version' => $packageElement->hasAttribute('version') ? $packageElement->getAttribute('version') : '',
            'uniqueIdentifierId' => $uniqueIdentifierId,
            'identifier' => $identifier,
            'identifiers' => $identifiers,
            'identifierDetails' => $identifierDetails,
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
    private static function metadataIdentifierDetails(array $entries): array
    {
        $details = [];
        foreach ($entries as $index => $entry) {
            $refinements = is_array($entry['refinements'] ?? null) ? $entry['refinements'] : [];
            $identifierTypes = self::metadataRefinementEntries($refinements, 'identifier-type');
            $details[] = [
                'kind' => 'identifier',
                'index' => $index,
                'value' => (string) ($entry['text'] ?? ''),
                'id' => is_string($entry['id'] ?? null) ? $entry['id'] : null,
                'scheme' => is_string($entry['scheme'] ?? null) ? $entry['scheme'] : null,
                'identifierType' => $identifierTypes[0]['value'] ?? null,
                'identifierTypes' => $identifierTypes,
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
     *     0:array<string, array{id:string, href:string, partName:string, mediaType:string, properties:list<string>, fallback:?string}>,
     *     1:list<array{id:string, href:string, partName:string, mediaType:string, properties:list<string>, fallback:?string}>
     * }
     */
    private static function parseManifest(\DOMElement $manifestElement, string $opfPartName, ZipPackage $package): array
    {
        $byId = [];
        $items = [];

        foreach (self::childElements($manifestElement, 'item', self::OPF_NAMESPACE) as $itemElement) {
            $id = $itemElement->getAttribute('id');
            $href = $itemElement->getAttribute('href');
            $mediaType = $itemElement->getAttribute('media-type');
            if ($id === '' || $href === '' || $mediaType === '') {
                throw new \RuntimeException('EPUB manifest items must include id, href, and media-type');
            }

            if (isset($byId[$id])) {
                throw new \RuntimeException("Duplicate EPUB manifest item id: {$id}");
            }

            $target = self::resolvePackageHref($opfPartName, $href);
            if (self::isAbsoluteUri($target)) {
                throw new \RuntimeException("EPUB manifest item {$id} must reference an internal package part");
            }

            $partName = OpcPackagePath::stripQueryAndFragment($target);
            if (!$package->has($partName)) {
                throw new \RuntimeException("EPUB manifest item {$id} references missing package part: {$partName}");
            }

            $item = [
                'id' => $id,
                'href' => $href,
                'partName' => $partName,
                'mediaType' => $mediaType,
                'properties' => self::splitTokens($itemElement->getAttribute('properties')),
                'fallback' => $itemElement->hasAttribute('fallback') ? $itemElement->getAttribute('fallback') : null,
            ];

            $byId[$id] = $item;
            $items[] = $item;
        }

        if ($items === []) {
            throw new \RuntimeException('EPUB OPF manifest must contain at least one item');
        }

        return [$byId, $items];
    }

    /**
     * @param array<string, array{id:string, href:string, partName:string, mediaType:string, properties:list<string>, fallback:?string}> $manifestById
     *
     * @return list<array{idref:string, href:string, partName:string, mediaType:string, linear:bool, properties:list<string>}>
     */
    private static function parseSpine(\DOMElement $spineElement, array $manifestById): array
    {
        $spine = [];
        foreach (self::childElements($spineElement, 'itemref', self::OPF_NAMESPACE) as $itemrefElement) {
            $idref = $itemrefElement->getAttribute('idref');
            if ($idref === '') {
                throw new \RuntimeException('EPUB spine itemref must include idref');
            }

            $item = $manifestById[$idref] ?? null;
            if (!is_array($item)) {
                throw new \RuntimeException("EPUB spine references missing manifest item: {$idref}");
            }

            $spine[] = [
                'idref' => $idref,
                'href' => $item['href'],
                'partName' => $item['partName'],
                'mediaType' => $item['mediaType'],
                'linear' => strtolower($itemrefElement->getAttribute('linear')) !== 'no',
                'properties' => self::splitTokens($itemrefElement->getAttribute('properties')),
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
            if ($item['mediaType'] === self::XHTML_MEDIA_TYPE && in_array('nav', $item['properties'], true)) {
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
        if ($spineTocId !== null && isset($manifestById[$spineTocId])) {
            $ncxItem = $manifestById[$spineTocId];
        } else {
            foreach ($manifestById as $item) {
                if ($item['mediaType'] === self::NCX_MEDIA_TYPE) {
                    $ncxItem = $item;
                    break;
                }
            }
        }

        if (is_array($ncxItem)) {
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
            $list = self::firstChildElement($node, 'ol') ?? self::firstDescendantElement($node, 'ol');
            $entries = $list instanceof \DOMElement ? self::parseNavList($list, $navPartName, 1) : [];
            $section = [
                'type' => $types[0] ?? null,
                'types' => $types,
                'label' => self::navSectionLabel($node),
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
        $entries = [];
        foreach (self::childElements($list, 'li') as $li) {
            $labelElement = self::firstChildElement($li, 'a') ?? self::firstChildElement($li, 'span');
            if ($labelElement instanceof \DOMElement) {
                $href = $labelElement->localName === 'a' && $labelElement->hasAttribute('href')
                    ? $labelElement->getAttribute('href')
                    : null;
                $label = self::normalizeText($labelElement->textContent);

                if ($label !== '' || ($href !== null && $href !== '')) {
                    $entries[] = [
                        'label' => $label,
                        'href' => $href,
                        'target' => $href === null || $href === '' ? null : self::resolveReadingHref($navPartName, $href),
                        'depth' => $depth,
                        'playOrder' => null,
                    ];
                }
            }

            foreach (self::childElements($li, 'ol') as $nestedList) {
                array_push($entries, ...self::parseNavList($nestedList, $navPartName, $depth + 1));
            }
        }

        return $entries;
    }

    /**
     * @return list<array{type:?string, title:?string, href:?string, target:?string, partName:?string, external:bool, exists:bool}>
     */
    private static function parseGuide(?\DOMElement $guideElement, string $opfPartName, ZipPackage $package): array
    {
        if (!$guideElement instanceof \DOMElement) {
            return [];
        }

        $references = [];
        foreach (self::childElements($guideElement, 'reference', self::OPF_NAMESPACE) as $reference) {
            $href = trim($reference->getAttribute('href'));
            $target = null;
            $partName = null;
            $external = false;
            $exists = false;

            if ($href !== '') {
                $target = self::resolvePackageHref($opfPartName, $href);
                $external = self::isAbsoluteUri($target);
                if (!$external) {
                    $partName = OpcPackagePath::stripQueryAndFragment($target);
                    $exists = $package->has($partName);
                }
            }

            $type = trim($reference->getAttribute('type'));
            $title = trim($reference->getAttribute('title'));
            $references[] = [
                'type' => $type === '' ? null : $type,
                'title' => $title === '' ? null : $title,
                'href' => $href === '' ? null : $href,
                'target' => $target,
                'partName' => $partName,
                'external' => $external,
                'exists' => $exists,
            ];
        }

        return $references;
    }

    /**
     * @param array<string, array{id:string, href:string, partName:string, mediaType:string, properties:list<string>, fallback:?string}> $manifestById
     *
     * @return array<string, array{id:string, href:string, partName:string, mediaType:string, properties:list<string>, fallback:?string}>
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
     * @param array<string, array{id:string, href:string, partName:string, mediaType:string, properties:list<string>, fallback:?string}> $manifestByPart
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
     * @param array<string, array{id:string, href:string, partName:string, mediaType:string, properties:list<string>, fallback:?string}> $manifestByPart
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
            'byteLength' => $entry instanceof ZipPackageEntry ? $entry->uncompressedSize : null,
            'crc32' => $entry instanceof ZipPackageEntry ? $entry->crc32Hex() : null,
            'diagnostics' => $diagnostics,
        ];
    }

    /**
     * @param array<string, array{id:string, href:string, partName:string, mediaType:string, properties:list<string>, fallback:?string}> $manifestById
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
        $diagnostics = [];

        if ($href === null) {
            $diagnostics[] = [
                'type' => 'missing-collection-link-href',
                'message' => 'EPUB OPF collection link is missing href',
            ];
        } else {
            try {
                $target = self::resolvePackageHref($opfPartName, $href);
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
            'byteLength' => $entry instanceof ZipPackageEntry ? $entry->uncompressedSize : null,
            'crc32' => $entry instanceof ZipPackageEntry ? $entry->crc32Hex() : null,
            'diagnostics' => $diagnostics,
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
     *
     * @return array<string, mixed>
     */
    private static function remoteResourcePolicyReport(array $packageLinks, array $collections): array
    {
        $items = [];
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
        $packageLinkCount = 0;
        $collectionLinkCount = 0;

        foreach ($items as $item) {
            $policy = (string) $item['policy'];
            $policyCounts[$policy] = ($policyCounts[$policy] ?? 0) + 1;
            $itemsByPolicy[$policy][] = $item;

            if ($item['source'] === 'package-link') {
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
            }

            foreach ($itemDiagnostics as $diagnostic) {
                $diagnostics[] = ['index' => $index] + $diagnostic;
            }

            $handlerPartName = is_array($handler) ? (string) $handler['partName'] : null;
            $entry = $handlerPartName !== null && $package->has($handlerPartName)
                ? $package->entry($handlerPartName)
                : null;

            $items[] = [
                'index' => $index,
                'mediaType' => $mediaType === '' ? null : $mediaType,
                'handlerId' => $handlerId === '' ? null : $handlerId,
                'handlerHref' => is_array($handler) ? (string) $handler['href'] : null,
                'handlerPartName' => $handlerPartName,
                'handlerMediaType' => is_array($handler) ? (string) $handler['mediaType'] : null,
                'handlerProperties' => is_array($handler) ? $handler['properties'] : [],
                'handlerExists' => $entry instanceof ZipPackageEntry,
                'handlerByteLength' => $entry instanceof ZipPackageEntry ? $entry->uncompressedSize : null,
                'handlerCrc32' => $entry instanceof ZipPackageEntry ? $entry->crc32Hex() : null,
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
