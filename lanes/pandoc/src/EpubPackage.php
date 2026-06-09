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

    /**
     * @param list<array{fullPath:string, partName:string, mediaType:string}> $rootfiles
     * @param array<string, mixed> $metadata
     * @param array<string, array{id:string, href:string, partName:string, mediaType:string, properties:list<string>, fallback:?string}> $manifestById
     * @param list<array{id:string, href:string, partName:string, mediaType:string, properties:list<string>, fallback:?string}> $manifestItems
     * @param list<array{idref:string, href:string, partName:string, mediaType:string, linear:bool, properties:list<string>}> $spine
     * @param list<array{type:?string, title:?string, href:?string, target:?string, partName:?string, external:bool, exists:bool}> $guideReferences
     * @param array<string, mixed> $bindings
     * @param array{type:string, partName:string, entries:list<array{label:string, href:?string, target:?string, depth:int, playOrder:?int}>}|null $navigation
     * @param list<array{type:?string, types:list<string>, label:?string, partName:string, entries:list<array{label:string, href:?string, target:?string, depth:int, playOrder:?int}>}> $navigationSections
     */
    private function __construct(
        private readonly ZipPackage $package,
        private readonly array $rootfiles,
        private readonly string $opfPartName,
        private readonly array $metadata,
        private readonly array $manifestById,
        private readonly array $manifestItems,
        private readonly array $spine,
        private readonly array $guideReferences,
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
            $opf['manifestById'],
            $opf['manifestItems'],
            $opf['spine'],
            $opf['guideReferences'],
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
    public function summary(): array
    {
        $assetSummary = $this->assetSummary();
        $navigationEntries = $this->navigation['entries'] ?? [];

        return [
            'opfPart' => $this->opfPartName,
            'rootfiles' => $this->rootfiles,
            'metadata' => $this->metadata,
            'manifest' => $this->manifestItems,
            'readingOrder' => $this->spine,
            'guide' => $this->guideReferences,
            'bindings' => $this->bindings,
            'navigation' => $this->navigation,
            'navigationSections' => $this->navigationSections,
            'assets' => $assetSummary,
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
     *     manifestById:array<string, array{id:string, href:string, partName:string, mediaType:string, properties:list<string>, fallback:?string}>,
     *     manifestItems:list<array{id:string, href:string, partName:string, mediaType:string, properties:list<string>, fallback:?string}>,
     *     spine:list<array{idref:string, href:string, partName:string, mediaType:string, linear:bool, properties:list<string>}>,
     *     guideReferences:list<array{type:?string, title:?string, href:?string, target:?string, partName:?string, external:bool, exists:bool}>,
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
        $spine = self::parseSpine($spineElement, $manifestById);
        $guideReferences = self::parseGuide(self::firstChildElement($root, 'guide', self::OPF_NAMESPACE), $opfPartName, $package);
        $bindings = self::parseBindings(self::firstChildElement($root, 'bindings', self::OPF_NAMESPACE), $manifestById, $package);

        return [
            'metadata' => $metadata,
            'manifestById' => $manifestById,
            'manifestItems' => $manifestItems,
            'spine' => $spine,
            'guideReferences' => $guideReferences,
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
        ];
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
