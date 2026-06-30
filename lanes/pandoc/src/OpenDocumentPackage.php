<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class OpenDocumentPackage
{
    public const TEXT_MIMETYPE = 'application/vnd.oasis.opendocument.text';
    public const MANIFEST_NAMESPACE = 'urn:oasis:names:tc:opendocument:xmlns:manifest:1.0';
    public const OFFICE_NAMESPACE = 'urn:oasis:names:tc:opendocument:xmlns:office:1.0';
    public const TEXT_NAMESPACE = 'urn:oasis:names:tc:opendocument:xmlns:text:1.0';
    public const STYLE_NAMESPACE = 'urn:oasis:names:tc:opendocument:xmlns:style:1.0';
    public const DRAW_NAMESPACE = 'urn:oasis:names:tc:opendocument:xmlns:drawing:1.0';
    public const XLINK_NAMESPACE = 'http://www.w3.org/1999/xlink';
    public const DC_NAMESPACE = 'http://purl.org/dc/elements/1.1/';
    public const META_NAMESPACE = 'urn:oasis:names:tc:opendocument:xmlns:meta:1.0';
    public const CONFIG_NAMESPACE = 'urn:oasis:names:tc:opendocument:xmlns:config:1.0';
    public const RDF_NAMESPACE = 'http://www.w3.org/1999/02/22-rdf-syntax-ns#';
    public const XML_NAMESPACE = 'http://www.w3.org/XML/1998/namespace';
    private const XMLNS_NAMESPACE = 'http://www.w3.org/2000/xmlns/';
    private const MANIFEST_FILE_ENTRY_STRUCTURAL_ATTRIBUTES = [
        'full-path' => true,
        'media-type' => true,
        'preferred-view-mode' => true,
        'size' => true,
        'version' => true,
    ];
    private const MANIFEST_FILE_ENTRY_STRUCTURAL_CHILD_ELEMENTS = [
        'encryption-data' => true,
    ];
    private const MANIFEST_ROOT_STRUCTURAL_ATTRIBUTES = [
        'version' => true,
    ];
    private const OFFICE_DOCUMENT_ROOT_STRUCTURAL_ATTRIBUTES = [
        'version' => true,
    ];
    private const PREFERRED_VIEW_MODE_VALUES = [
        'edit' => true,
        'presentation-slide-show' => true,
        'read-only' => true,
    ];
    private const MANIFEST_DECLARED_SIZE_LARGEST_ITEM_LIMIT = 5;

    /** @var array<string, array<string, mixed>> */
    private array $manifestEntriesByPath;

    /**
     * @param list<array<string, mixed>> $manifestEntries
     * @param array<string, array<string, mixed>> $manifestEntriesByPath
     * @param array<string, array{name:string, family:string, parent:string|null, displayName:string|null}> $stylesByName
     * @param array<string, mixed> $metadata
     * @param array<string, mixed> $settings
     * @param array<string, mixed> $rdfMetadata
     */
    private function __construct(
        private readonly ZipPackage $package,
        private readonly ?string $manifestVersion,
        private readonly array $manifestRootAttributes,
        private readonly array $manifestEntries,
        array $manifestEntriesByPath,
        private readonly array $stylesByName,
        private readonly array $metadata,
        private readonly array $settings,
        private readonly array $rdfMetadata,
    ) {
        $this->manifestEntriesByPath = $manifestEntriesByPath;
    }

    public static function fromPackage(ZipPackage $package): self
    {
        self::assertTextPackageMimetype($package);

        if (!$package->has('META-INF/manifest.xml')) {
            throw new \RuntimeException('ODT package is missing META-INF/manifest.xml');
        }

        $manifest = self::parseManifest($package->read('META-INF/manifest.xml'));
        $manifestEntries = self::withPackageEntryMetadata($manifest['entries'], $package);
        $manifestEntriesByPath = [];
        $manifestEntriesByPackagePath = [];
        foreach ($manifestEntries as $entry) {
            if (isset($manifestEntriesByPath[$entry['path']])) {
                throw new \InvalidArgumentException('Duplicate ODF manifest full-path: ' . $entry['path']);
            }

            if (is_string($entry['packagePath'] ?? null) && $entry['packagePath'] !== '') {
                if (isset($manifestEntriesByPackagePath[$entry['packagePath']])) {
                    throw new \InvalidArgumentException('Duplicate ODF manifest package part: ' . $entry['packagePath']);
                }

                $manifestEntriesByPackagePath[$entry['packagePath']] = true;
            }

            $manifestEntriesByPath[$entry['path']] = $entry;
            if (is_string($entry['pathReference'] ?? null) && $entry['pathReference'] !== '' && !isset($manifestEntriesByPath[$entry['pathReference']])) {
                $manifestEntriesByPath[$entry['pathReference']] = $entry;
            }
            if (is_string($entry['packagePath'] ?? null) && !isset($manifestEntriesByPath[$entry['packagePath']])) {
                $manifestEntriesByPath[$entry['packagePath']] = $entry;
            }
        }

        $root = $manifestEntriesByPath['/'] ?? null;
        if ($root === null || $root['mediaType'] !== self::TEXT_MIMETYPE) {
            throw new \RuntimeException('ODT manifest root must identify an OpenDocument text package');
        }

        foreach (['content.xml'] as $requiredPart) {
            if (!isset($manifestEntriesByPath[$requiredPart])) {
                throw new \RuntimeException("ODT manifest is missing required part {$requiredPart}");
            }
            if (!$package->has($requiredPart)) {
                throw new \RuntimeException("ODT package is missing manifest-declared part {$requiredPart}");
            }
        }

        foreach (['styles.xml', 'meta.xml', 'settings.xml'] as $optionalPart) {
            if (isset($manifestEntriesByPath[$optionalPart]) && !$package->has($optionalPart)) {
                throw new \RuntimeException("ODT package is missing manifest-declared part {$optionalPart}");
            }
        }

        $styles = isset($manifestEntriesByPath['styles.xml']) ? self::parseStyles($package->read('styles.xml')) : [];
        $metadata = isset($manifestEntriesByPath['meta.xml']) ? self::parseMetadata($package->read('meta.xml')) : [];
        $settings = isset($manifestEntriesByPath['settings.xml']) ? self::parseSettings($package->read('settings.xml')) : self::emptySettings();
        $rdfMetadata = self::readRdfMetadata($package, $manifestEntries);

        return new self(
            $package,
            $manifest['version'],
            $manifest['rootAttributes'],
            $manifestEntries,
            $manifestEntriesByPath,
            $styles,
            $metadata,
            $settings,
            $rdfMetadata
        );
    }

    public function package(): ZipPackage
    {
        return $this->package;
    }

    public function manifestVersion(): ?string
    {
        return $this->manifestVersion;
    }

    /**
     * @return array<string, mixed>
     */
    public function manifestRootAttributes(): array
    {
        return $this->manifestRootAttributes;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function manifestEntries(): array
    {
        return $this->manifestEntries;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function manifestEntry(string $path): ?array
    {
        return $this->manifestEntriesByPath[self::normalizeManifestPath($path)] ?? null;
    }

    public function mediaTypeForPath(string $path): ?string
    {
        return $this->manifestEntry($path)['mediaType'] ?? null;
    }

    /**
     * @return array<string, array{name:string, family:string, parent:string|null, displayName:string|null}>
     */
    public function stylesByName(): array
    {
        return $this->stylesByName;
    }

    /**
     * @return array<string, mixed>
     */
    public function metadata(): array
    {
        return $this->metadata;
    }

    /**
     * @return array<string, mixed>
     */
    public function settings(): array
    {
        return $this->settings;
    }

    /**
     * @return array<string, mixed>
     */
    public function rdfMetadata(): array
    {
        return $this->rdfMetadata;
    }

    public function readContentDocument(): AstNode
    {
        $dom = self::loadXml($this->package->read('content.xml'), 'ODT content.xml');
        $root = $dom->documentElement;
        if (!$root instanceof \DOMElement || !in_array($root->localName, ['document-content', 'document'], true) || $root->namespaceURI !== self::OFFICE_NAMESPACE) {
            throw new \InvalidArgumentException('ODT content.xml must use an office:document-content or office:document root');
        }

        $body = self::firstElementByPath($root, [
            [self::OFFICE_NAMESPACE, 'body'],
            [self::OFFICE_NAMESPACE, 'text'],
        ]);
        if (!$body instanceof \DOMElement) {
            throw new \InvalidArgumentException('ODT content.xml is missing office:body/office:text');
        }

        $blocks = [];
        foreach ($body->childNodes as $child) {
            if (!$child instanceof \DOMElement) {
                continue;
            }

            if ($child->namespaceURI === self::TEXT_NAMESPACE && $child->localName === 'h') {
                $blocks[] = new AstNode('heading', [
                    'level' => self::intAttribute($child, self::TEXT_NAMESPACE, 'outline-level', 1),
                    'text' => self::plainText($child),
                    'styleName' => self::namespacedAttribute($child, self::TEXT_NAMESPACE, 'style-name'),
                ], self::inlineNodes($child));
                continue;
            }

            if ($child->namespaceURI === self::TEXT_NAMESPACE && $child->localName === 'p') {
                $inlines = self::inlineNodes($child);
                if ($inlines !== []) {
                    $blocks[] = new AstNode('paragraph', [
                        'text' => self::plainText($child),
                        'styleName' => self::namespacedAttribute($child, self::TEXT_NAMESPACE, 'style-name'),
                    ], $inlines);
                }
            }
        }

        return new AstNode('document', [
            'format' => 'odt',
            'metadata' => $this->metadata,
            'styles' => $this->stylesByName,
            'settings' => $this->settings,
            'rdfMetadata' => $this->rdfMetadata,
        ], $blocks);
    }

    /**
     * @return array{
     *     mimetype:string,
     *     mimetypeEntry:array<string, mixed>,
     *     manifestVersion:string|null,
     *     contentXml:bool,
     *     stylesXml:bool,
     *     metaXml:bool,
     *     settingsXml:bool,
     *     mediaParts:list<array<string, mixed>>,
     *     missingMediaPartCount:int,
     *     missingMediaParts:list<array{path:string, mediaType:string}>,
     *     exposableMediaPartCount:int,
     *     corePackageHandoff:array<string, mixed>,
     *     documentPartVersions:array<string, mixed>,
     *     encryptedCount:int,
     *     encryptedParts:list<string>,
     *     manifestReview:array<string, mixed>,
     *     packageInventory:array<string, mixed>,
     *     packageIdentity:array<string, mixed>,
     *     undeclaredPackageEntryCount:int,
     *     undeclaredPackageEntries:list<array<string, mixed>>,
     *     packageThumbnails:array<string, mixed>,
     *     packageSignatures:array<string, mixed>,
     *     packageObjects:array<string, mixed>,
     *     packageObjectReplacements:array<string, mixed>,
     *     packageScripts:array<string, mixed>,
     *     packageConfigurations:array<string, mixed>,
     *     packageFonts:array<string, mixed>,
     *     packageLayoutCaches:array<string, mixed>,
     *     packageMetaInfSidecars:array<string, mixed>,
     *     packageLinkedResources:array<string, mixed>,
     *     packageDatabases:array<string, mixed>,
     *     packageVersions:array<string, mixed>,
     *     packageGalleries:array<string, mixed>,
     *     packageForms:array<string, mixed>,
     *     packageReports:array<string, mixed>,
     *     packageAttachments:array<string, mixed>,
     *     packageTemplates:array<string, mixed>,
     *     packageDictionaries:array<string, mixed>,
     *     packageEvents:array<string, mixed>,
     *     packageStyles:array<string, mixed>,
     *     comments:array<string, mixed>,
     *     rdfMetadata:array<string, mixed>,
     *     metadata:array<string, mixed>,
     *     settings:array<string, mixed>,
     *     styleNames:list<string>,
     *     contentBlocks:int
     * }
     */
    public function summarize(): array
    {
        $packageInventory = $this->packageInventory();
        $corePackageHandoff = $this->corePackageHandoffPreflight();
        $mediaParts = [];
        $missingMediaParts = [];
        $exposableMediaPartCount = 0;
        $encryptedParts = [];
        $undeclaredPackageEntries = $this->undeclaredPackageEntries();
        $packageThumbnails = self::packageThumbnailMetadata($this->package, $this->manifestEntries, $undeclaredPackageEntries);
        $packageSignatures = self::packageSignatureMetadata($this->package, $this->manifestEntries, $undeclaredPackageEntries);
        $packageObjects = self::embeddedObjectPackageMetadata($this->package, $this->manifestEntries);
        $packageObjectReplacements = self::packageObjectReplacementMetadata($this->package, $this->manifestEntries, $undeclaredPackageEntries);
        $packageScripts = self::packageScriptMetadata($this->package, $this->manifestEntries, $undeclaredPackageEntries);
        $packageConfigurations = self::packageConfigurationMetadata($this->package, $this->manifestEntries, $undeclaredPackageEntries);
        $packageFonts = self::packageFontMetadata($this->package, $this->manifestEntries, $undeclaredPackageEntries);
        $packageLayoutCaches = self::packageLayoutCacheMetadata($this->package, $this->manifestEntries, $undeclaredPackageEntries);
        $packageMetaInfSidecars = self::packageMetaInfSidecarMetadata($this->package, $this->manifestEntries, $undeclaredPackageEntries);
        $packageLinkedResources = self::packageLinkedResourceMetadata($this->package, $this->manifestEntries, $undeclaredPackageEntries);
        $packageDatabases = self::packageDatabaseMetadata($this->package, $this->manifestEntries, $undeclaredPackageEntries);
        $packageVersions = self::packageVersionMetadata($this->package, $this->manifestEntries, $undeclaredPackageEntries);
        $packageGalleries = self::packageGalleryMetadata($this->package, $this->manifestEntries, $undeclaredPackageEntries);
        $packageForms = self::packageFormMetadata($this->package, $this->manifestEntries, $undeclaredPackageEntries);
        $packageReports = self::packageReportMetadata($this->package, $this->manifestEntries, $undeclaredPackageEntries);
        $packageAttachments = self::packageAttachmentMetadata($this->package, $this->manifestEntries, $undeclaredPackageEntries);
        $packageTemplates = self::packageTemplateMetadata($this->package, $this->manifestEntries, $undeclaredPackageEntries);
        $packageDictionaries = self::packageDictionaryMetadata($this->package, $this->manifestEntries, $undeclaredPackageEntries);
        $packageEvents = self::packageEventMetadata($this->package, $this->manifestEntries, $undeclaredPackageEntries);
        $packageStyles = $this->packageStyleProvenance($packageInventory);
        foreach ($this->manifestEntries as $entry) {
            if (self::isMediaResourceManifestEntry($entry)) {
                $mediaParts[] = [
                    'path' => $entry['path'],
                    'packagePath' => $entry['packagePath'],
                    'pathReference' => $entry['pathReference'],
                    'pathSuffix' => $entry['pathSuffix'],
                    'pathQuery' => $entry['pathQuery'],
                    'pathFragment' => $entry['pathFragment'],
                    'uriEncodedPackageReference' => ($entry['uriEncodedPackageReference'] ?? false) === true,
                    'manifestMediaFamily' => $entry['manifestMediaFamily'],
                    'mediaType' => $entry['mediaType'],
                    'mediaTypeBase' => $entry['mediaTypeBase'],
                    'mediaTypeHasParameters' => $entry['mediaTypeHasParameters'],
                    'mediaTypeParameterCount' => $entry['mediaTypeParameterCount'],
                    'mediaTypeParameters' => $entry['mediaTypeParameters'],
                    'mediaTypeParameterMap' => $entry['mediaTypeParameterMap'],
                    'version' => $entry['version'],
                    'preferredViewMode' => $entry['preferredViewMode'],
                    'exists' => $entry['exists'],
                    'byteLength' => $entry['byteLength'],
                    'storedByteLength' => $entry['storedByteLength'],
                    'compressedByteLength' => $entry['compressedByteLength'],
                    'compressionMethod' => $entry['compressionMethod'],
                    'compressionMethodName' => $entry['compressionMethodName'],
                    'crc32' => $entry['crc32'],
                    'storedCrc32' => $entry['storedCrc32'],
                    'byteSha256' => $entry['byteSha256'],
                    'zipModifiedAt' => $entry['zipModifiedAt'],
                    'zipTimestampSource' => $entry['zipTimestampSource'],
                    'zipModifiedDosTime' => $entry['zipModifiedDosTime'],
                    'zipModifiedDosDate' => $entry['zipModifiedDosDate'],
                    'zipHasDosTimestamp' => $entry['zipHasDosTimestamp'],
                    'zipIsDosTimestampValid' => $entry['zipIsDosTimestampValid'],
                    'zipDosModifiedAt' => $entry['zipDosModifiedAt'],
                    'zipExtendedModifiedAt' => $entry['zipExtendedModifiedAt'],
                    'zipNtfsModifiedAt' => $entry['zipNtfsModifiedAt'],
                    'zipLocalModifiedAt' => $entry['zipLocalModifiedAt'],
                    'zipLocalTimestampSource' => $entry['zipLocalTimestampSource'],
                    'zipTimestampIssues' => $entry['zipTimestampIssues'],
                    'declaredSize' => $entry['size'],
                    'declaredSizeRaw' => $entry['declaredSizeRaw'] ?? null,
                    'declaredSizeValid' => ($entry['declaredSizeValid'] ?? false) === true,
                    'declaredSizeInvalid' => ($entry['declaredSizeInvalid'] ?? false) === true,
                    'declaredSizeMismatch' => $entry['declaredSizeMismatch'],
                    'missingMediaType' => ($entry['missingMediaType'] ?? false) === true,
                    'encrypted' => $entry['encrypted'],
                    'encryption' => $entry['encryption'],
                    'encryptionRecordCount' => is_array($entry['encryption']) ? ($entry['encryption']['recordCount'] ?? 0) : 0,
                    'encryptionIssueCodes' => is_array($entry['encryption']) ? ($entry['encryption']['issueCodes'] ?? []) : [],
                    'canExposeBytes' => $entry['canExposeBytes'],
                    'byteExposurePolicy' => $entry['byteExposurePolicy'],
                    'diagnostics' => $entry['diagnostics'],
                ];
                if (!$entry['exists']) {
                    $missingMediaParts[] = [
                        'path' => $entry['path'],
                        'mediaType' => $entry['mediaType'],
                    ];
                }
                if ($entry['canExposeBytes']) {
                    ++$exposableMediaPartCount;
                }
            }
            if ($entry['encrypted']) {
                $encryptedParts[] = $entry['path'];
            }
        }

        return [
            'mimetype' => self::TEXT_MIMETYPE,
            'mimetypeEntry' => self::mimetypeEntryReview($this->package),
            'manifestVersion' => $this->manifestVersion,
            'manifestRootAttributes' => $this->manifestRootAttributes,
            'contentXml' => isset($this->manifestEntriesByPath['content.xml']),
            'stylesXml' => isset($this->manifestEntriesByPath['styles.xml']),
            'metaXml' => isset($this->manifestEntriesByPath['meta.xml']),
            'settingsXml' => isset($this->manifestEntriesByPath['settings.xml']),
            'mediaParts' => $mediaParts,
            'missingMediaPartCount' => count($missingMediaParts),
            'missingMediaParts' => $missingMediaParts,
            'exposableMediaPartCount' => $exposableMediaPartCount,
            'corePackageHandoff' => $corePackageHandoff,
            'documentPartVersions' => $this->documentPartVersions(),
            'encryptedCount' => count($encryptedParts),
            'encryptedParts' => $encryptedParts,
            'undeclaredPackageEntryCount' => count($undeclaredPackageEntries),
            'undeclaredPackageEntries' => $undeclaredPackageEntries,
            'packageThumbnails' => $packageThumbnails,
            'packageSignatures' => $packageSignatures,
            'packageObjects' => $packageObjects,
            'packageObjectReplacements' => $packageObjectReplacements,
            'packageScripts' => $packageScripts,
            'packageConfigurations' => $packageConfigurations,
            'packageFonts' => $packageFonts,
            'packageLayoutCaches' => $packageLayoutCaches,
            'packageMetaInfSidecars' => $packageMetaInfSidecars,
            'packageLinkedResources' => $packageLinkedResources,
            'packageDatabases' => $packageDatabases,
            'packageVersions' => $packageVersions,
            'packageGalleries' => $packageGalleries,
            'packageForms' => $packageForms,
            'packageReports' => $packageReports,
            'packageAttachments' => $packageAttachments,
            'packageTemplates' => $packageTemplates,
            'packageDictionaries' => $packageDictionaries,
            'packageEvents' => $packageEvents,
            'packageStyles' => $packageStyles,
            'rdfMetadata' => $this->rdfMetadata,
            'manifestEncryption' => self::manifestEncryptionSummary($this->manifestEntries),
            'manifestReview' => self::manifestReview($this->manifestEntries, $undeclaredPackageEntries, $this->manifestRootAttributes),
            'packageInventory' => $packageInventory,
            'packageIdentity' => $this->packageIdentity($packageInventory),
            'metadata' => $this->metadata,
            'settings' => $this->settings,
            'styleNames' => array_keys($this->stylesByName),
            'contentBlocks' => count($this->readContentDocument()->children),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function corePackageHandoffPreflight(): array
    {
        $summary = $this->package->entryHandoffPreflight(self::corePackageHandoffRequests());

        foreach (['entries', 'handoffEntries', 'failedEntries', 'missingEntries'] as $listKey) {
            if (!is_array($summary[$listKey] ?? null)) {
                continue;
            }

            $summary[$listKey] = array_values(array_map(
                fn (array $entry): array => $this->corePackageHandoffEntryWithManifestState($entry),
                $summary[$listKey]
            ));
        }
        foreach ([
            'selectedSourceByteSpanEntries',
            'selectedLocalHeaderFixedFieldEntries',
            'selectedLocalHeaderFixedFieldIssueEntries',
            'selectedCentralDirectoryFixedFieldEntries',
            'selectedCentralDirectoryFixedFieldIssueEntries',
            'selectedDataDescriptorProvenanceEntries',
            'selectedDataDescriptorIssueEntries',
        ] as $listKey) {
            if (!is_array($summary[$listKey] ?? null)) {
                continue;
            }

            $summary[$listKey] = array_values(array_map(
                fn (array $entry): array => $this->corePackageHandoffEntryWithManifestState(['exists' => true] + $entry),
                $summary[$listKey]
            ));
        }

        $manifestDeclarationStateCounts = [];
        $manifestDeclaredSelectedEntryCount = 0;
        $undeclaredSelectedEntryCount = 0;
        $specialPackageSelectedEntryCount = 0;
        foreach ($summary['entries'] ?? [] as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $state = is_string($entry['manifestDeclarationState'] ?? null) ? $entry['manifestDeclarationState'] : 'unknown';
            $manifestDeclarationStateCounts[$state] = ($manifestDeclarationStateCounts[$state] ?? 0) + 1;
            if (($entry['exists'] ?? false) !== true) {
                continue;
            }
            if ($state === 'declared') {
                ++$manifestDeclaredSelectedEntryCount;
            } elseif ($state === 'undeclared') {
                ++$undeclaredSelectedEntryCount;
            } elseif ($state === 'package-mimetype-entry' || $state === 'package-manifest-entry') {
                ++$specialPackageSelectedEntryCount;
            }
        }
        ksort($manifestDeclarationStateCounts, SORT_STRING);

        return [
            'scope' => 'odf-core-package-handoff',
            'byteExposurePolicy' => 'odf-core-package-handoff-metadata-only',
            'reviewPolicy' => 'core-package-selected-entry-preflight',
            'manifestDeclarationStateCounts' => $manifestDeclarationStateCounts,
            'manifestDeclaredSelectedEntryCount' => $manifestDeclaredSelectedEntryCount,
            'undeclaredSelectedEntryCount' => $undeclaredSelectedEntryCount,
            'specialPackageSelectedEntryCount' => $specialPackageSelectedEntryCount,
        ] + $summary;
    }

    /**
     * @return list<array{name:string, required:bool, kind:string, role:string}>
     */
    private static function corePackageHandoffRequests(): array
    {
        return [
            ['name' => 'mimetype', 'required' => true, 'kind' => 'file', 'role' => 'odf-mimetype'],
            ['name' => 'META-INF/manifest.xml', 'required' => true, 'kind' => 'file', 'role' => 'odf-manifest'],
            ['name' => 'content.xml', 'required' => true, 'kind' => 'file', 'role' => 'odf-content'],
            ['name' => 'styles.xml', 'required' => false, 'kind' => 'file', 'role' => 'odf-styles'],
            ['name' => 'meta.xml', 'required' => false, 'kind' => 'file', 'role' => 'odf-meta'],
            ['name' => 'settings.xml', 'required' => false, 'kind' => 'file', 'role' => 'odf-settings'],
        ];
    }

    /**
     * @param array<string, mixed> $entry
     * @return array<string, mixed>
     */
    private function corePackageHandoffEntryWithManifestState(array $entry): array
    {
        $name = is_string($entry['name'] ?? null) ? $entry['name'] : '';
        $manifestEntry = $this->corePackageManifestEntryForName($name);
        $state = $this->corePackageManifestDeclarationState($name, $entry, $manifestEntry);

        return $entry + [
            'odfCorePackagePart' => true,
            'manifestDeclarationState' => $state,
            'manifestDeclared' => is_array($manifestEntry) && $state === 'declared',
            'manifestIndex' => is_array($manifestEntry) ? ($manifestEntry['manifestIndex'] ?? null) : null,
            'manifestFullPath' => is_array($manifestEntry) ? ($manifestEntry['path'] ?? null) : null,
            'manifestPackagePath' => is_array($manifestEntry) ? ($manifestEntry['packagePath'] ?? null) : null,
            'manifestPathReference' => is_array($manifestEntry) ? ($manifestEntry['pathReference'] ?? null) : null,
            'manifestPathSuffix' => is_array($manifestEntry) ? ($manifestEntry['pathSuffix'] ?? null) : null,
            'manifestPathQuery' => is_array($manifestEntry) ? ($manifestEntry['pathQuery'] ?? null) : null,
            'manifestPathFragment' => is_array($manifestEntry) ? ($manifestEntry['pathFragment'] ?? null) : null,
            'manifestUriEncodedPackageReference' => is_array($manifestEntry) && ($manifestEntry['uriEncodedPackageReference'] ?? false) === true,
            'manifestMediaType' => is_array($manifestEntry) ? ($manifestEntry['mediaType'] ?? null) : null,
            'manifestMediaTypeBase' => is_array($manifestEntry) ? ($manifestEntry['mediaTypeBase'] ?? null) : null,
            'manifestMediaTypeHasParameters' => is_array($manifestEntry) && ($manifestEntry['mediaTypeHasParameters'] ?? false) === true,
            'manifestMediaTypeParameterCount' => is_array($manifestEntry) ? ($manifestEntry['mediaTypeParameterCount'] ?? 0) : 0,
            'manifestMediaTypeParameters' => is_array($manifestEntry) ? ($manifestEntry['mediaTypeParameters'] ?? []) : [],
            'manifestMediaTypeParameterMap' => is_array($manifestEntry) ? ($manifestEntry['mediaTypeParameterMap'] ?? []) : [],
            'manifestVersion' => is_array($manifestEntry) ? ($manifestEntry['version'] ?? null) : null,
            'manifestPreferredViewMode' => is_array($manifestEntry) ? ($manifestEntry['preferredViewMode'] ?? null) : null,
            'manifestDeclaredSize' => is_array($manifestEntry) ? ($manifestEntry['declaredSize'] ?? $manifestEntry['size'] ?? null) : null,
            'manifestDeclaredSizeRaw' => is_array($manifestEntry) ? ($manifestEntry['declaredSizeRaw'] ?? null) : null,
            'manifestDeclaredSizeValid' => is_array($manifestEntry) && ($manifestEntry['declaredSizeValid'] ?? false) === true,
            'manifestDeclaredSizeInvalid' => is_array($manifestEntry) && ($manifestEntry['declaredSizeInvalid'] ?? false) === true,
            'manifestDeclaredSizeMismatch' => is_array($manifestEntry) && ($manifestEntry['declaredSizeMismatch'] ?? false) === true,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function corePackageManifestEntryForName(string $name): ?array
    {
        if ($name === 'mimetype') {
            return $this->manifestEntry('/');
        }
        if ($name === 'META-INF/manifest.xml') {
            return null;
        }

        return $this->manifestEntry($name);
    }

    /**
     * @param array<string, mixed> $entry
     * @param array<string, mixed>|null $manifestEntry
     */
    private function corePackageManifestDeclarationState(string $name, array $entry, ?array $manifestEntry): string
    {
        if ($name === 'mimetype') {
            return 'package-mimetype-entry';
        }
        if ($name === 'META-INF/manifest.xml') {
            return 'package-manifest-entry';
        }
        if (is_array($manifestEntry)) {
            return 'declared';
        }

        return ($entry['exists'] ?? false) === true ? 'undeclared' : 'not-declared';
    }

    /**
     * @return array<string, mixed>
     */
    private function documentPartVersions(): array
    {
        $expectedRoots = [
            'content.xml' => 'document-content',
            'styles.xml' => 'document-styles',
            'meta.xml' => 'document-meta',
            'settings.xml' => 'document-settings',
        ];
        $items = [];
        $versionCounts = [];
        $missingVersionParts = [];
        $versionMismatches = [];
        $rootCustomAttributeCount = 0;
        $rootCustomAttributeNames = [];
        $rootCustomAttributeItems = [];
        $manifestPartReferenceSuffixItems = [];
        $manifestMediaTypeMismatches = [];
        $byteExposurePolicyCounts = [];

        foreach ($expectedRoots as $part => $expectedRoot) {
            $manifestEntry = $this->manifestEntriesByPath[$part] ?? null;
            if (!$this->package->has($part) && !is_array($manifestEntry)) {
                continue;
            }

            $entry = $this->package->has($part) ? $this->package->entry($part) : null;
            $rootName = null;
            $officeVersion = null;
            $validRoot = false;
            $diagnostics = [];
            $rootAttributeProvenance = self::emptyAttributeProvenance();

            if (!$entry instanceof ZipPackageEntry) {
                $diagnostics[] = 'odf-xml-part-missing-package-part';
            } else {
                $dom = self::loadXml($this->package->read($part), 'ODT ' . $part);
                $root = $dom->documentElement;
                if ($root instanceof \DOMElement) {
                    $rootName = $root->localName;
                    $validRoot = $root->namespaceURI === self::OFFICE_NAMESPACE && $root->localName === $expectedRoot;
                    $officeVersion = self::optionalString(self::namespacedAttribute($root, self::OFFICE_NAMESPACE, 'version'));
                    $rootAttributeProvenance = self::documentPartRootAttributeProvenance($root);
                }

                if (!$validRoot) {
                    $diagnostics[] = 'odf-xml-part-unexpected-root';
                }
                if ($officeVersion === null) {
                    $diagnostics[] = 'odf-xml-part-missing-office-version';
                    $missingVersionParts[] = $part;
                } else {
                    $versionCounts[$officeVersion] = ($versionCounts[$officeVersion] ?? 0) + 1;
                    if ($this->manifestVersion !== null && $officeVersion !== $this->manifestVersion) {
                        $diagnostics[] = 'odf-xml-part-version-mismatch';
                        $versionMismatches[] = [
                            'part' => $part,
                            'officeVersion' => $officeVersion,
                            'manifestVersion' => $this->manifestVersion,
                        ];
                    }
                }
            }

            if (!is_array($manifestEntry)) {
                $diagnostics[] = 'odf-xml-part-undeclared-package-part';
            }
            $manifestMediaTypeBase = is_array($manifestEntry) && is_string($manifestEntry['mediaTypeBase'] ?? null)
                ? $manifestEntry['mediaTypeBase']
                : null;
            $manifestMediaTypeValid = is_array($manifestEntry)
                && $manifestMediaTypeBase !== null
                && self::isXmlMediaTypeBase($manifestMediaTypeBase);
            $manifestMediaTypeMismatch = is_array($manifestEntry) && !$manifestMediaTypeValid;
            if ($manifestMediaTypeMismatch) {
                $diagnostics[] = 'odf-xml-part-manifest-media-type-mismatch';
                $manifestMediaTypeMismatches[] = [
                    'part' => $part,
                    'expectedManifestMediaTypeFamily' => 'xml',
                    'manifestFullPath' => $manifestEntry['path'] ?? null,
                    'manifestPackagePath' => $manifestEntry['packagePath'] ?? null,
                    'manifestPathReference' => $manifestEntry['pathReference'] ?? null,
                    'manifestMediaType' => $manifestEntry['mediaType'] ?? null,
                    'manifestMediaTypeBase' => $manifestMediaTypeBase,
                ];
            }
            if (is_array($manifestEntry) && is_string($manifestEntry['pathSuffix'] ?? null)) {
                $manifestPartReferenceSuffixItems[] = [
                    'part' => $part,
                    'manifestFullPath' => $manifestEntry['path'] ?? null,
                    'manifestPackagePath' => $manifestEntry['packagePath'] ?? null,
                    'manifestPathReference' => $manifestEntry['pathReference'] ?? null,
                    'manifestPathSuffix' => $manifestEntry['pathSuffix'] ?? null,
                    'manifestPathQuery' => $manifestEntry['pathQuery'] ?? null,
                    'manifestPathFragment' => $manifestEntry['pathFragment'] ?? null,
                    'manifestUriEncodedPackageReference' => ($manifestEntry['uriEncodedPackageReference'] ?? false) === true,
                ];
            }
            if (is_array($manifestEntry) && is_string($manifestEntry['byteExposurePolicy'] ?? null) && $manifestEntry['byteExposurePolicy'] !== '') {
                $policy = $manifestEntry['byteExposurePolicy'];
                $byteExposurePolicyCounts[$policy] = ($byteExposurePolicyCounts[$policy] ?? 0) + 1;
            }

            $partCustomAttributeCount = (int) ($rootAttributeProvenance['customAttributeCount'] ?? 0);
            if ($partCustomAttributeCount > 0) {
                $rootCustomAttributeCount += $partCustomAttributeCount;
                foreach ($rootAttributeProvenance['customAttributeNames'] ?? [] as $attributeName) {
                    if (is_string($attributeName) && $attributeName !== '' && !in_array($attributeName, $rootCustomAttributeNames, true)) {
                        $rootCustomAttributeNames[] = $attributeName;
                    }
                }
                $rootCustomAttributeItems[] = [
                    'part' => $part,
                    'expectedRoot' => $expectedRoot,
                    'rootName' => $rootName,
                    'rootCustomAttributeCount' => $partCustomAttributeCount,
                    'rootCustomAttributeNames' => $rootAttributeProvenance['customAttributeNames'] ?? [],
                    'rootCustomAttributes' => $rootAttributeProvenance['customAttributes'] ?? [],
                    'rootCustomAttributeMap' => $rootAttributeProvenance['customAttributeMap'] ?? [],
                    'rootNamespaceDeclarationCount' => $rootAttributeProvenance['namespaceDeclarationCount'] ?? 0,
                    'rootNamespaceDeclarationNames' => $rootAttributeProvenance['namespaceDeclarationNames'] ?? [],
                    'rootNamespaceDeclarations' => $rootAttributeProvenance['namespaceDeclarations'] ?? [],
                    'rootNamespaceDeclarationMap' => $rootAttributeProvenance['namespaceDeclarationMap'] ?? [],
                ];
            }

            $items[] = [
                'part' => $part,
                'expectedRoot' => $expectedRoot,
                'rootName' => $rootName,
                'validRoot' => $validRoot,
                'officeVersion' => $officeVersion,
                'rootAttributeCount' => $rootAttributeProvenance['attributeCount'] ?? 0,
                'rootAttributeNames' => $rootAttributeProvenance['attributeNames'] ?? [],
                'rootAttributes' => $rootAttributeProvenance['attributes'] ?? [],
                'rootCustomAttributeCount' => $rootAttributeProvenance['customAttributeCount'] ?? 0,
                'rootCustomAttributeNames' => $rootAttributeProvenance['customAttributeNames'] ?? [],
                'rootCustomAttributes' => $rootAttributeProvenance['customAttributes'] ?? [],
                'rootCustomAttributeMap' => $rootAttributeProvenance['customAttributeMap'] ?? [],
                'rootNamespaceDeclarationCount' => $rootAttributeProvenance['namespaceDeclarationCount'] ?? 0,
                'rootNamespaceDeclarationNames' => $rootAttributeProvenance['namespaceDeclarationNames'] ?? [],
                'rootNamespaceDeclarations' => $rootAttributeProvenance['namespaceDeclarations'] ?? [],
                'rootNamespaceDeclarationMap' => $rootAttributeProvenance['namespaceDeclarationMap'] ?? [],
                'manifestVersion' => $this->manifestVersion,
                'declaredInManifest' => is_array($manifestEntry),
                'manifestIndex' => is_array($manifestEntry) ? ($manifestEntry['manifestIndex'] ?? null) : null,
                'manifestFullPath' => is_array($manifestEntry) ? ($manifestEntry['path'] ?? null) : null,
                'manifestPackagePath' => is_array($manifestEntry) ? ($manifestEntry['packagePath'] ?? null) : null,
                'manifestPathReference' => is_array($manifestEntry) ? ($manifestEntry['pathReference'] ?? null) : null,
                'manifestPathSuffix' => is_array($manifestEntry) ? ($manifestEntry['pathSuffix'] ?? null) : null,
                'manifestPathQuery' => is_array($manifestEntry) ? ($manifestEntry['pathQuery'] ?? null) : null,
                'manifestPathFragment' => is_array($manifestEntry) ? ($manifestEntry['pathFragment'] ?? null) : null,
                'manifestUriEncodedPackageReference' => is_array($manifestEntry) && ($manifestEntry['uriEncodedPackageReference'] ?? false) === true,
                'manifestMediaType' => is_array($manifestEntry) ? ($manifestEntry['mediaType'] ?? null) : null,
                'manifestMediaTypeBase' => is_array($manifestEntry) ? ($manifestEntry['mediaTypeBase'] ?? null) : null,
                'manifestMediaTypeValid' => $manifestMediaTypeValid,
                'manifestMediaTypeMismatch' => $manifestMediaTypeMismatch,
                'expectedManifestMediaTypeFamily' => 'xml',
                'manifestMediaTypeHasParameters' => is_array($manifestEntry) && ($manifestEntry['mediaTypeHasParameters'] ?? false) === true,
                'manifestMediaTypeParameterCount' => is_array($manifestEntry) ? ($manifestEntry['mediaTypeParameterCount'] ?? 0) : 0,
                'manifestMediaTypeParameters' => is_array($manifestEntry) ? ($manifestEntry['mediaTypeParameters'] ?? []) : [],
                'manifestMediaTypeParameterMap' => is_array($manifestEntry) ? ($manifestEntry['mediaTypeParameterMap'] ?? []) : [],
                'manifestDeclaredSize' => is_array($manifestEntry) ? ($manifestEntry['declaredSize'] ?? $manifestEntry['size'] ?? null) : null,
                'manifestDeclaredSizeRaw' => is_array($manifestEntry) ? ($manifestEntry['declaredSizeRaw'] ?? null) : null,
                'manifestDeclaredSizeValid' => is_array($manifestEntry) && ($manifestEntry['declaredSizeValid'] ?? false) === true,
                'manifestDeclaredSizeInvalid' => is_array($manifestEntry) && ($manifestEntry['declaredSizeInvalid'] ?? false) === true,
                'manifestDeclaredSizeMismatch' => is_array($manifestEntry) && ($manifestEntry['declaredSizeMismatch'] ?? false) === true,
                'manifestDiagnostics' => is_array($manifestEntry) ? ($manifestEntry['diagnostics'] ?? []) : [],
                'exists' => $entry instanceof ZipPackageEntry,
                'canExposeBytes' => is_array($manifestEntry) && ($manifestEntry['canExposeBytes'] ?? false) === true,
                'byteExposurePolicy' => is_array($manifestEntry) ? ($manifestEntry['byteExposurePolicy'] ?? null) : null,
                'byteLength' => $entry instanceof ZipPackageEntry ? $entry->uncompressedSize : null,
                'compressedByteLength' => $entry instanceof ZipPackageEntry ? $entry->compressedSize : null,
                'compressionMethod' => $entry instanceof ZipPackageEntry ? $entry->compressionMethod : null,
                'compressionMethodName' => $entry instanceof ZipPackageEntry ? self::compressionMethodName($entry->compressionMethod) : null,
                'crc32' => $entry instanceof ZipPackageEntry ? $entry->crc32Hex() : null,
                'diagnostics' => array_values(array_unique($diagnostics)),
            ];
        }

        ksort($versionCounts, SORT_STRING);
        sort($rootCustomAttributeNames, SORT_STRING);
        ksort($byteExposurePolicyCounts, SORT_STRING);

        return [
            'count' => count($items),
            'versionedCount' => array_sum($versionCounts),
            'missingVersionCount' => count($missingVersionParts),
            'missingVersionParts' => $missingVersionParts,
            'versionMismatchCount' => count($versionMismatches),
            'versionMismatches' => $versionMismatches,
            'manifestVersion' => $this->manifestVersion,
            'versionCounts' => $versionCounts,
            'rootCustomAttributePartCount' => count($rootCustomAttributeItems),
            'rootCustomAttributeCount' => $rootCustomAttributeCount,
            'rootCustomAttributeNames' => $rootCustomAttributeNames,
            'rootCustomAttributeItems' => $rootCustomAttributeItems,
            'manifestPartReferenceSuffixCount' => count($manifestPartReferenceSuffixItems),
            'manifestPartReferenceQueryCount' => count(array_filter(
                $manifestPartReferenceSuffixItems,
                static fn (array $item): bool => is_string($item['manifestPathQuery'] ?? null)
            )),
            'manifestPartReferenceFragmentCount' => count(array_filter(
                $manifestPartReferenceSuffixItems,
                static fn (array $item): bool => is_string($item['manifestPathFragment'] ?? null)
            )),
            'manifestUriEncodedPackageReferenceCount' => count(array_filter(
                $manifestPartReferenceSuffixItems,
                static fn (array $item): bool => ($item['manifestUriEncodedPackageReference'] ?? false) === true
            )),
            'manifestPartReferenceSuffixItems' => $manifestPartReferenceSuffixItems,
            'manifestMediaTypeMismatchCount' => count($manifestMediaTypeMismatches),
            'manifestMediaTypeMismatches' => $manifestMediaTypeMismatches,
            'byteExposurePolicyCounts' => $byteExposurePolicyCounts,
            'items' => $items,
        ];
    }

    /**
     * @param array<string, mixed> $packageInventory
     * @return array<string, mixed>
     */
    private function packageStyleProvenance(array $packageInventory): array
    {
        $part = is_array($packageInventory['parts']['styles.xml'] ?? null)
            ? $packageInventory['parts']['styles.xml']
            : null;
        $styleNames = array_keys($this->stylesByName);
        sort($styleNames, SORT_STRING);
        $items = [];

        if (is_array($part) || $styleNames !== []) {
            $items[] = self::withoutEmptyValues([
                'path' => 'styles.xml',
                'declaredInManifest' => is_array($part) && ($part['declaredInManifest'] ?? false) === true,
                'exists' => is_array($part),
                'manifestIndex' => is_array($part) ? ($part['manifestIndex'] ?? null) : null,
                'manifestPath' => is_array($part) ? ($part['manifestPath'] ?? null) : null,
                'manifestMediaType' => is_array($part) ? ($part['manifestMediaType'] ?? null) : null,
                'styleCount' => count($styleNames),
                'styleNames' => $styleNames,
                'storedByteLength' => is_array($part) ? ($part['byteLength'] ?? null) : null,
                'compressedByteLength' => is_array($part) ? ($part['compressedByteLength'] ?? null) : null,
                'compressionMethod' => is_array($part) ? ($part['compressionMethod'] ?? null) : null,
                'compressionMethodName' => is_array($part) ? ($part['compressionMethodName'] ?? null) : null,
                'crc32' => is_array($part) ? ($part['crc32'] ?? null) : null,
                'packageByteExposurePolicy' => is_array($part) ? ($part['byteExposurePolicy'] ?? null) : null,
                'byteExposurePolicy' => 'odf-style-package-provenance-metadata-only',
                'canExposeBytes' => false,
            ]);
        }

        return [
            'count' => count($items),
            'styleCount' => count($styleNames),
            'styleNames' => $styleNames,
            'sourceParts' => $items === [] ? [] : ['styles.xml'],
            'byteExposurePolicy' => 'odf-style-package-provenance-metadata-only',
            'canExposeBytes' => false,
            'items' => $items,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function packageInventory(): array
    {
        $declaredPackagePaths = [
            'mimetype' => true,
            'META-INF/manifest.xml' => true,
        ];
        $manifestDeclaredPartCount = 0;
        foreach ($this->manifestEntries as $entry) {
            $path = $entry['path'] ?? null;
            if (!is_string($path) || $path === '' || $path === '/') {
                continue;
            }

            $packagePath = $entry['packagePath'] ?? null;
            $pathReference = $entry['pathReference'] ?? null;
            if (is_string($packagePath) && $packagePath !== '') {
                $declaredPackagePaths[$packagePath] = true;
            } elseif (is_string($pathReference) && $pathReference !== '') {
                $declaredPackagePaths[$pathReference] = true;
            } else {
                $declaredPackagePaths[$path] = true;
            }
            ++$manifestDeclaredPartCount;
        }

        $localHeaderOrder = $this->package->localHeaderOrderPreflight();
        $packageManifest = $this->package->packageManifestPreflight();
        $compressionMethods = $this->package->compressionMethodPreflight();
        $comments = $this->package->commentPreflight();
        $modificationTimes = $this->package->modificationTimePreflight();
        $modificationTimeByName = self::zipModificationTimeEntriesByName($modificationTimes);
        $platformMetadata = $this->package->platformMetadataPreflight();
        $platformMetadataByName = self::zipPreflightEntriesByName($platformMetadata);
        $permissions = $this->package->permissionPreflight();
        $permissionsByName = self::zipPreflightEntriesByName($permissions);
        $creatorHostSystems = $this->package->creatorHostSystemPreflight();
        $creatorHostSystemsByName = self::zipPreflightEntriesByName($creatorHostSystems);
        $dosAttributes = $this->package->dosAttributePreflight();
        $dosAttributesByName = self::zipPreflightEntriesByName($dosAttributes);
        $internalAttributes = $this->package->internalAttributePreflight();
        $internalAttributesByName = self::zipPreflightEntriesByName($internalAttributes);
        $objectPackageRootParts = self::embeddedObjectPackageRootParts($this->manifestEntries);
        $localOrderByName = [];
        foreach ($localHeaderOrder['entries'] as $entry) {
            $name = $entry['name'] ?? null;
            if (is_string($name) && $name !== '') {
                $localOrderByName[$name] = $entry;
            }
        }
        $sourceRecordEntriesByName = self::zipPreflightEntriesByName($packageManifest);
        $commentEntriesByName = [];
        foreach ($comments['entries'] ?? [] as $entry) {
            $name = $entry['name'] ?? null;
            if (is_string($name) && $name !== '') {
                $commentEntriesByName[$name] = $entry;
            }
        }

        $parts = [];
        $undeclaredEntries = [];
        $packageDirectoryCount = 0;
        $roleCounts = [];
        $roleByteLengths = [];
        $roleCompressedByteLengths = [];
        $undeclaredRoleCounts = [];
        $unsupportedCompressionPartNames = [];
        $corePackagePartCount = 0;
        $mediaResourcePartCount = 0;
        $packageThumbnailPartCount = 0;
        $packageSignaturePartCount = 0;
        $embeddedObjectPackageRootCount = 0;
        $embeddedObjectPackagePartCount = 0;
        $objectReplacementPartCount = 0;
        $scriptPackagePartCount = 0;
        $configurationPackagePartCount = 0;
        $fontPackagePartCount = 0;
        $rdfMetadataPartCount = 0;
        $layoutCachePartCount = 0;
        $metaInfSidecarPartCount = 0;
        $linkedResourcePackagePartCount = 0;
        $databasePackagePartCount = 0;
        $versionPackagePartCount = 0;
        $galleryPackagePartCount = 0;
        $formPackagePartCount = 0;
        $reportPackagePartCount = 0;
        $attachmentPackagePartCount = 0;
        $templatePackagePartCount = 0;
        $dictionaryPackagePartCount = 0;
        $eventPackagePartCount = 0;
        $unsupportedCompressionMethodCount = 0;
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
        $byteExposurePolicyCounts = [];
        $byteExposurePolicyByteLengths = [];
        $byteExposurePolicyCompressedByteLengths = [];
        $byteExposurePolicyItems = [];
        $manifestMediaFamilyCounts = [];
        $manifestMediaFamilyByteLengths = [];
        $manifestMediaFamilyCompressedByteLengths = [];
        $rawNameProvenanceEntryCount = 0;
        $legacyEncodedNameEntryCount = 0;
        $unicodePathExtraEntryCount = 0;
        $decodedNameDiffersFromRawNameEntryCount = 0;
        $rawNameProvenanceEntries = [];
        foreach ($this->package->entries() as $centralDirectoryIndex => $entry) {
            $manifestEntry = $this->manifestEntriesByPath[$entry->name] ?? null;
            $isUndeclared = !$entry->isDirectory() && !isset($declaredPackagePaths[$entry->name]);
            $localOrder = $localOrderByName[$entry->name] ?? null;
            $commentEntry = $commentEntriesByName[$entry->name] ?? null;
            $embeddedObjectPackage = self::embeddedObjectPackageMembership($entry->name, $objectPackageRootParts);
            $rawNameProvenance = self::zipEntryRawNameProvenance($entry);
            $timestampProvenance = self::zipTimestampProvenance($modificationTimeByName[$entry->name] ?? null);
            $platformAttributeProvenance = self::zipPlatformAttributeProvenance(
                $entry,
                $platformMetadataByName[$entry->name] ?? null,
                $permissionsByName[$entry->name] ?? null,
                $creatorHostSystemsByName[$entry->name] ?? null,
                $dosAttributesByName[$entry->name] ?? null,
                $internalAttributesByName[$entry->name] ?? null
            );
            if ($entry->isDirectory()) {
                ++$packageDirectoryCount;
            }

            $roles = self::packageEntryRoles($entry, $manifestEntry, $isUndeclared, $embeddedObjectPackage);
            $byteExposurePolicy = null;
            $sourceRecordProvenance = self::zipEntrySourceRecordProvenance($sourceRecordEntriesByName[$entry->name] ?? null);
            if (is_array($manifestEntry)) {
                $byteExposurePolicy = $manifestEntry['byteExposurePolicy'] ?? null;
            } elseif ($isUndeclared) {
                $byteExposurePolicy = self::undeclaredPackageEntryByteExposurePolicy($entry->name);
            } elseif (is_array($embeddedObjectPackage)) {
                $byteExposurePolicy = 'embedded-object-package-bytes-blocked';
            }
            $item = [
                'path' => $entry->name,
                'roles' => $roles,
                'centralDirectoryIndex' => $centralDirectoryIndex,
                'localHeaderOrder' => is_array($localOrder) ? $localOrder['localHeaderOrder'] : null,
                'localHeaderOffset' => $entry->localHeaderOffset,
                'matchesCentralDirectoryOrder' => is_array($localOrder)
                    ? $localOrder['matchesCentralDirectoryOrder']
                    : null,
                'compressionMethod' => $entry->compressionMethod,
                'compressionMethodName' => self::compressionMethodName($entry->compressionMethod),
                'byteLength' => $entry->uncompressedSize,
                'compressedByteLength' => $entry->compressedSize,
                'crc32' => $entry->crc32Hex(),
                'byteSha256' => is_array($manifestEntry) ? ($manifestEntry['byteSha256'] ?? null) : null,
                'zipEntryComment' => $entry->comment,
                'zipEntryCommentLength' => strlen($entry->rawComment),
                'zipEntryCommentEncoding' => $entry->commentEncoding,
                'zipEntryHasComment' => $entry->comment !== '',
                'zipEntryCommentIssues' => is_array($commentEntry) ? ($commentEntry['issues'] ?? []) : [],
                'isDirectory' => $entry->isDirectory(),
                'declaredInManifest' => is_array($manifestEntry),
                'manifestIndex' => is_array($manifestEntry) ? $manifestEntry['manifestIndex'] : null,
                'manifestPath' => is_array($manifestEntry) ? $manifestEntry['path'] : null,
                'manifestPackagePath' => is_array($manifestEntry) ? $manifestEntry['packagePath'] : null,
                'manifestPathReference' => is_array($manifestEntry) ? $manifestEntry['pathReference'] : null,
                'manifestPathSuffix' => is_array($manifestEntry) ? $manifestEntry['pathSuffix'] : null,
                'manifestPathQuery' => is_array($manifestEntry) ? $manifestEntry['pathQuery'] : null,
                'manifestPathFragment' => is_array($manifestEntry) ? $manifestEntry['pathFragment'] : null,
                'manifestUriEncodedPackageReference' => is_array($manifestEntry) && ($manifestEntry['uriEncodedPackageReference'] ?? false) === true,
                'manifestMediaFamily' => is_array($manifestEntry) ? ($manifestEntry['manifestMediaFamily'] ?? null) : null,
                'manifestMediaType' => is_array($manifestEntry) ? $manifestEntry['mediaType'] : null,
                'manifestMediaTypeBase' => is_array($manifestEntry) ? $manifestEntry['mediaTypeBase'] : null,
                'manifestMediaTypeHasParameters' => is_array($manifestEntry) ? $manifestEntry['mediaTypeHasParameters'] : false,
                'manifestMediaTypeParameterCount' => is_array($manifestEntry) ? $manifestEntry['mediaTypeParameterCount'] : 0,
                'manifestMediaTypeParameters' => is_array($manifestEntry) ? $manifestEntry['mediaTypeParameters'] : [],
                'manifestMediaTypeParameterMap' => is_array($manifestEntry) ? $manifestEntry['mediaTypeParameterMap'] : [],
                'manifestVersion' => is_array($manifestEntry) ? $manifestEntry['version'] : null,
                'manifestPreferredViewMode' => is_array($manifestEntry) ? $manifestEntry['preferredViewMode'] : null,
                'manifestAttributeCount' => is_array($manifestEntry) ? ($manifestEntry['manifestAttributeCount'] ?? 0) : 0,
                'manifestAttributeNames' => is_array($manifestEntry) ? ($manifestEntry['manifestAttributeNames'] ?? []) : [],
                'manifestAttributes' => is_array($manifestEntry) ? ($manifestEntry['manifestAttributes'] ?? []) : [],
                'customManifestAttributeCount' => is_array($manifestEntry) ? ($manifestEntry['customManifestAttributeCount'] ?? 0) : 0,
                'customManifestAttributeNames' => is_array($manifestEntry) ? ($manifestEntry['customManifestAttributeNames'] ?? []) : [],
                'customManifestAttributes' => is_array($manifestEntry) ? ($manifestEntry['customManifestAttributes'] ?? []) : [],
                'customManifestAttributeMap' => is_array($manifestEntry) ? ($manifestEntry['customManifestAttributeMap'] ?? []) : [],
                'manifestNamespaceDeclarationCount' => is_array($manifestEntry) ? ($manifestEntry['manifestNamespaceDeclarationCount'] ?? 0) : 0,
                'manifestNamespaceDeclarationNames' => is_array($manifestEntry) ? ($manifestEntry['manifestNamespaceDeclarationNames'] ?? []) : [],
                'manifestNamespaceDeclarations' => is_array($manifestEntry) ? ($manifestEntry['manifestNamespaceDeclarations'] ?? []) : [],
                'manifestNamespaceDeclarationMap' => is_array($manifestEntry) ? ($manifestEntry['manifestNamespaceDeclarationMap'] ?? []) : [],
                'manifestChildElementCount' => is_array($manifestEntry) ? ($manifestEntry['manifestChildElementCount'] ?? 0) : 0,
                'manifestChildElementNames' => is_array($manifestEntry) ? ($manifestEntry['manifestChildElementNames'] ?? []) : [],
                'manifestChildElements' => is_array($manifestEntry) ? ($manifestEntry['manifestChildElements'] ?? []) : [],
                'customManifestChildElementCount' => is_array($manifestEntry) ? ($manifestEntry['customManifestChildElementCount'] ?? 0) : 0,
                'customManifestChildElementNames' => is_array($manifestEntry) ? ($manifestEntry['customManifestChildElementNames'] ?? []) : [],
                'customManifestChildElements' => is_array($manifestEntry) ? ($manifestEntry['customManifestChildElements'] ?? []) : [],
                'manifestDeclaredSize' => is_array($manifestEntry) ? $manifestEntry['declaredSize'] : null,
                'manifestDeclaredSizeRaw' => is_array($manifestEntry) ? ($manifestEntry['declaredSizeRaw'] ?? null) : null,
                'manifestDeclaredSizeValid' => is_array($manifestEntry) && ($manifestEntry['declaredSizeValid'] ?? false) === true,
                'manifestDeclaredSizeInvalid' => is_array($manifestEntry) && ($manifestEntry['declaredSizeInvalid'] ?? false) === true,
                'manifestDeclaredSizeMismatch' => is_array($manifestEntry) && ($manifestEntry['declaredSizeMismatch'] ?? false) === true,
                'manifestMissingMediaType' => is_array($manifestEntry) && ($manifestEntry['missingMediaType'] ?? false) === true,
                'manifestDiagnostics' => is_array($manifestEntry) ? ($manifestEntry['diagnostics'] ?? []) : [],
                'manifestEncryption' => is_array($manifestEntry) ? $manifestEntry['encryption'] : null,
                'manifestEncryptionRecordCount' => is_array($manifestEntry) && is_array($manifestEntry['encryption'] ?? null)
                    ? ($manifestEntry['encryption']['recordCount'] ?? 0)
                    : 0,
                'manifestEncryptionIssueCodes' => is_array($manifestEntry) && is_array($manifestEntry['encryption'] ?? null)
                    ? ($manifestEntry['encryption']['issueCodes'] ?? [])
                    : [],
                'embeddedObjectPackagePart' => is_array($embeddedObjectPackage),
                'embeddedObjectRootPart' => is_array($embeddedObjectPackage) ? $embeddedObjectPackage['rootPart'] : null,
                'embeddedObjectPath' => is_array($embeddedObjectPackage) ? $embeddedObjectPackage['objectPath'] : null,
                'embeddedObjectType' => is_array($embeddedObjectPackage) ? $embeddedObjectPackage['objectType'] : null,
                'embeddedObjectRoot' => is_array($embeddedObjectPackage) && $embeddedObjectPackage['isRoot'] === true,
                'embeddedObjectContainedPart' => is_array($embeddedObjectPackage) && $embeddedObjectPackage['isRoot'] !== true,
                'embeddedObjectMediaType' => is_array($embeddedObjectPackage) ? $embeddedObjectPackage['mediaType'] : null,
                'objectReplacementPackagePart' => self::isObjectReplacementPackagePartName($entry->name),
                'thumbnailPackagePart' => self::isThumbnailPackagePartName($entry->name),
                'scriptPackagePart' => self::isScriptPackagePartName($entry->name),
                'signaturePackagePart' => self::isSignaturePackagePartName($entry->name),
                'configurationPackagePart' => self::isConfigurationPackagePartName($entry->name),
                'fontPackagePart' => self::isFontPackagePart($entry->name, is_array($manifestEntry) ? (string) ($manifestEntry['mediaType'] ?? '') : null),
                'rdfMetadataPart' => self::isRdfMetadataPart($entry->name, is_array($manifestEntry) ? (string) ($manifestEntry['mediaType'] ?? '') : null),
                'layoutCachePackagePart' => self::isLayoutCachePackagePartName($entry->name),
                'metaInfSidecarPackagePart' => self::isMetaInfSidecarPackagePartName($entry->name),
                'linkedResourcePackagePart' => self::isLinkedResourcePackagePartName($entry->name),
                'databasePackagePart' => self::isDatabasePackagePartName($entry->name),
                'versionPackagePart' => self::isVersionPackagePartName($entry->name),
                'galleryPackagePart' => self::isGalleryPackagePartName($entry->name),
                'formPackagePart' => self::isFormPackagePartName($entry->name),
                'reportPackagePart' => self::isReportPackagePartName($entry->name),
                'attachmentPackagePart' => self::isAttachmentPackagePartName($entry->name),
                'templatePackagePart' => self::isTemplatePackagePartName($entry->name),
                'dictionaryPackagePart' => self::isDictionaryPackagePartName($entry->name),
                'eventPackagePart' => self::isEventPackagePartName($entry->name),
                'encrypted' => is_array($manifestEntry) && ($manifestEntry['encrypted'] ?? false) === true,
                'canExposeBytes' => is_array($manifestEntry) && ($manifestEntry['canExposeBytes'] ?? false) === true,
                'byteExposurePolicy' => $byteExposurePolicy,
                'undeclared' => $isUndeclared,
            ] + $rawNameProvenance + $sourceRecordProvenance + $timestampProvenance + $platformAttributeProvenance;

            foreach ($roles as $role) {
                $roleCounts[$role] = ($roleCounts[$role] ?? 0) + 1;
                $roleByteLengths[$role] = ($roleByteLengths[$role] ?? 0) + $entry->uncompressedSize;
                $roleCompressedByteLengths[$role] = ($roleCompressedByteLengths[$role] ?? 0) + $entry->compressedSize;
                if ($isUndeclared) {
                    $undeclaredRoleCounts[$role] = ($undeclaredRoleCounts[$role] ?? 0) + 1;
                }
            }
            $totalByteLength += $entry->uncompressedSize;
            $totalCompressedByteLength += $entry->compressedSize;
            if (($entry->compressionMethod !== 0 && $entry->compressionMethod !== 8)) {
                ++$unsupportedCompressionMethodCount;
                $unsupportedCompressionPartNames[$entry->name] = true;
                $unsupportedCompressionByteLength += $entry->uncompressedSize;
                $unsupportedCompressionCompressedByteLength += $entry->compressedSize;
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
            if (is_string($byteExposurePolicy) && $byteExposurePolicy !== '') {
                $byteExposurePolicyCounts[$byteExposurePolicy] = ($byteExposurePolicyCounts[$byteExposurePolicy] ?? 0) + 1;
                $byteExposurePolicyByteLengths[$byteExposurePolicy] = ($byteExposurePolicyByteLengths[$byteExposurePolicy] ?? 0) + $entry->uncompressedSize;
                $byteExposurePolicyCompressedByteLengths[$byteExposurePolicy] = ($byteExposurePolicyCompressedByteLengths[$byteExposurePolicy] ?? 0) + $entry->compressedSize;
                $byteExposurePolicyItems[] = self::withoutEmptyValues([
                    'path' => $entry->name,
                    'centralDirectoryIndex' => $centralDirectoryIndex,
                    'roles' => $roles,
                    'byteExposurePolicy' => $byteExposurePolicy,
                    'declaredInManifest' => is_array($manifestEntry),
                    'undeclared' => $isUndeclared,
                    'canExposeBytes' => is_array($manifestEntry) && ($manifestEntry['canExposeBytes'] ?? false) === true,
                ]);
            }
            if (is_array($manifestEntry) && is_string($manifestEntry['manifestMediaFamily'] ?? null)) {
                $family = $manifestEntry['manifestMediaFamily'];
                $manifestMediaFamilyCounts[$family] = ($manifestMediaFamilyCounts[$family] ?? 0) + 1;
                $manifestMediaFamilyByteLengths[$family] = ($manifestMediaFamilyByteLengths[$family] ?? 0) + $entry->uncompressedSize;
                $manifestMediaFamilyCompressedByteLengths[$family] = ($manifestMediaFamilyCompressedByteLengths[$family] ?? 0) + $entry->compressedSize;
            }
            if (array_intersect($roles, ['odf-mimetype', 'odf-manifest', 'odf-content', 'odf-styles', 'odf-meta', 'odf-settings']) !== []) {
                ++$corePackagePartCount;
            }
            if (in_array('media-resource', $roles, true)) {
                ++$mediaResourcePartCount;
            }
            if (in_array('package-thumbnail', $roles, true)) {
                ++$packageThumbnailPartCount;
            }
            if (in_array('package-signature', $roles, true)) {
                ++$packageSignaturePartCount;
            }
            if (in_array('embedded-object-root', $roles, true)) {
                ++$embeddedObjectPackageRootCount;
            }
            if (in_array('embedded-object-part', $roles, true)) {
                ++$embeddedObjectPackagePartCount;
            }
            if (in_array('object-replacement', $roles, true)) {
                ++$objectReplacementPartCount;
            }
            if (in_array('script-package', $roles, true)) {
                ++$scriptPackagePartCount;
            }
            if (in_array('configuration-package', $roles, true)) {
                ++$configurationPackagePartCount;
            }
            if (in_array('font-package', $roles, true)) {
                ++$fontPackagePartCount;
            }
            if (in_array('rdf-metadata', $roles, true)) {
                ++$rdfMetadataPartCount;
            }
            if (in_array('layout-cache', $roles, true)) {
                ++$layoutCachePartCount;
            }
            if (in_array('meta-inf-sidecar', $roles, true)) {
                ++$metaInfSidecarPartCount;
            }
            if (in_array('linked-resource-package', $roles, true)) {
                ++$linkedResourcePackagePartCount;
            }
            if (in_array('database-package', $roles, true)) {
                ++$databasePackagePartCount;
            }
            if (in_array('version-package', $roles, true)) {
                ++$versionPackagePartCount;
            }
            if (in_array('gallery-package', $roles, true)) {
                ++$galleryPackagePartCount;
            }
            if (in_array('form-package', $roles, true)) {
                ++$formPackagePartCount;
            }
            if (in_array('report-package', $roles, true)) {
                ++$reportPackagePartCount;
            }
            if (in_array('attachment-package', $roles, true)) {
                ++$attachmentPackagePartCount;
            }
            if (in_array('template-package', $roles, true)) {
                ++$templatePackagePartCount;
            }
            if (in_array('dictionary-package', $roles, true)) {
                ++$dictionaryPackagePartCount;
            }
            if (in_array('event-package', $roles, true)) {
                ++$eventPackagePartCount;
            }
            if ($rawNameProvenance['hasRawNameProvenance']) {
                ++$rawNameProvenanceEntryCount;
                $rawNameProvenanceEntries[] = [
                    'path' => $entry->name,
                    'centralDirectoryIndex' => $centralDirectoryIndex,
                ] + $rawNameProvenance;
            }
            if ($rawNameProvenance['usesLegacyNameEncoding']) {
                ++$legacyEncodedNameEntryCount;
            }
            if ($rawNameProvenance['usesUnicodePathExtraField']) {
                ++$unicodePathExtraEntryCount;
            }
            if (!$rawNameProvenance['rawNameMatchesDecodedName']) {
                ++$decodedNameDiffersFromRawNameEntryCount;
            }

            $parts[$entry->name] = $item;
            if ($isUndeclared) {
                $undeclaredEntries[] = $item;
            }
        }
        ksort($roleCounts, SORT_STRING);
        ksort($roleByteLengths, SORT_STRING);
        ksort($roleCompressedByteLengths, SORT_STRING);
        ksort($undeclaredRoleCounts, SORT_STRING);
        ksort($byteExposurePolicyCounts, SORT_STRING);
        ksort($byteExposurePolicyByteLengths, SORT_STRING);
        ksort($byteExposurePolicyCompressedByteLengths, SORT_STRING);
        ksort($manifestMediaFamilyCounts, SORT_STRING);
        ksort($manifestMediaFamilyByteLengths, SORT_STRING);
        ksort($manifestMediaFamilyCompressedByteLengths, SORT_STRING);

        return [
            'entryCount' => count($parts),
            'manifestDeclaredPartCount' => $manifestDeclaredPartCount,
            'undeclaredEntryCount' => count($undeclaredEntries),
            'undeclaredEntries' => $undeclaredEntries,
            'packageDirectoryCount' => $packageDirectoryCount,
            'roleCounts' => $roleCounts,
            'undeclaredRoleCounts' => $undeclaredRoleCounts,
            'corePackagePartCount' => $corePackagePartCount,
            'mediaResourcePartCount' => $mediaResourcePartCount,
            'packageThumbnailPartCount' => $packageThumbnailPartCount,
            'packageSignaturePartCount' => $packageSignaturePartCount,
            'embeddedObjectPackageRootCount' => $embeddedObjectPackageRootCount,
            'embeddedObjectPackagePartCount' => $embeddedObjectPackagePartCount,
            'objectReplacementPartCount' => $objectReplacementPartCount,
            'scriptPackagePartCount' => $scriptPackagePartCount,
            'configurationPackagePartCount' => $configurationPackagePartCount,
            'fontPackagePartCount' => $fontPackagePartCount,
            'rdfMetadataPartCount' => $rdfMetadataPartCount,
            'layoutCachePartCount' => $layoutCachePartCount,
            'metaInfSidecarPartCount' => $metaInfSidecarPartCount,
            'linkedResourcePackagePartCount' => $linkedResourcePackagePartCount,
            'databasePackagePartCount' => $databasePackagePartCount,
            'versionPackagePartCount' => $versionPackagePartCount,
            'galleryPackagePartCount' => $galleryPackagePartCount,
            'formPackagePartCount' => $formPackagePartCount,
            'reportPackagePartCount' => $reportPackagePartCount,
            'attachmentPackagePartCount' => $attachmentPackagePartCount,
            'templatePackagePartCount' => $templatePackagePartCount,
            'dictionaryPackagePartCount' => $dictionaryPackagePartCount,
            'eventPackagePartCount' => $eventPackagePartCount,
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
            'byteExposurePolicyCounts' => $byteExposurePolicyCounts,
            'byteExposurePolicyItemCount' => count($byteExposurePolicyItems),
            'byteExposurePolicyItems' => $byteExposurePolicyItems,
            'byteExposurePolicyByteLengths' => $byteExposurePolicyByteLengths,
            'byteExposurePolicyCompressedByteLengths' => $byteExposurePolicyCompressedByteLengths,
            'manifestMediaFamilyCounts' => $manifestMediaFamilyCounts,
            'manifestMediaFamilyByteLengths' => $manifestMediaFamilyByteLengths,
            'manifestMediaFamilyCompressedByteLengths' => $manifestMediaFamilyCompressedByteLengths,
            'rawNameProvenanceEntryCount' => $rawNameProvenanceEntryCount,
            'legacyEncodedNameEntryCount' => $legacyEncodedNameEntryCount,
            'unicodePathExtraEntryCount' => $unicodePathExtraEntryCount,
            'decodedNameDiffersFromRawNameEntryCount' => $decodedNameDiffersFromRawNameEntryCount,
            'rawNameProvenanceEntries' => $rawNameProvenanceEntries,
            'zipSourceRecords' => self::zipSourceRecordSummary($packageManifest, $parts),
            'byteExposurePolicy' => 'odf-package-inventory-metadata-only',
            'canExposeBytes' => false,
            'roles' => array_keys($roleCounts),
            'centralDirectoryOrderMatchesLocalHeaderOrder' => !$localHeaderOrder['hasCentralDirectoryOrderMismatch'],
            'roleByteLengths' => $roleByteLengths,
            'roleCompressedByteLengths' => $roleCompressedByteLengths,
            'unsupportedCompressionPartNames' => array_keys($unsupportedCompressionPartNames),
            'localHeaderOrder' => $localHeaderOrder,
            'compressionMethods' => $compressionMethods,
            'comments' => $comments,
            'hasPackageComment' => ($comments['hasPackageComment'] ?? false) === true,
            'hasEntryComments' => ($comments['hasEntryComments'] ?? false) === true,
            'entryCommentCount' => is_int($comments['entryCommentCount'] ?? null) ? $comments['entryCommentCount'] : 0,
            'commentedEntryNames' => is_array($comments['commentedEntryNames'] ?? null) ? $comments['commentedEntryNames'] : [],
            'modificationTimes' => $modificationTimes,
            'zipTimestampEntryCount' => $modificationTimes['timestampEntryCount'],
            'zipDosTimestampEntryCount' => $modificationTimes['dosTimestampEntryCount'],
            'zipExtendedTimestampEntryCount' => $modificationTimes['extendedTimestampEntryCount'],
            'zipNtfsTimestampEntryCount' => $modificationTimes['ntfsTimestampEntryCount'],
            'zipInvalidDosTimestampEntryCount' => $modificationTimes['invalidDosTimestampEntryCount'],
            'zipInvalidDosTimestampEntries' => $modificationTimes['invalidDosTimestampEntries'],
            'platformMetadata' => $platformMetadata,
            'platformMetadataEntryCount' => $platformMetadata['platformMetadataEntryCount'],
            'creatorHostSystems' => $creatorHostSystems,
            'knownCreatorHostSystemEntryCount' => $creatorHostSystems['knownHostSystemEntryCount'],
            'unknownCreatorHostSystemEntryCount' => $creatorHostSystems['unknownHostSystemEntryCount'],
            'creatorVersionBelowNeededEntryCount' => $creatorHostSystems['creatorVersionBelowNeededEntryCount'],
            'permissions' => $permissions,
            'unixModeEntryCount' => $permissions['unixModeEntryCount'],
            'executableFileCount' => $permissions['executableFileCount'],
            'writablePermissionEntryCount' => $permissions['writablePermissionEntryCount'],
            'dosAttributes' => $dosAttributes,
            'dosAttributeEntryCount' => $dosAttributes['dosAttributeEntryCount'],
            'hiddenSystemOrVolumeLabelEntryCount' => $dosAttributes['hiddenSystemOrVolumeLabelEntryCount'],
            'internalAttributes' => $internalAttributes,
            'internalAttributeEntryCount' => $internalAttributes['internalAttributeEntryCount'],
            'parts' => $parts,
        ];
    }

    /**
     * @param array<string, mixed> $packageInventory
     * @return array<string, mixed>
     */
    private function packageIdentity(array $packageInventory): array
    {
        $manifestRootAttributes = $this->manifestRootAttributes;
        $manifestEntries = [];
        foreach ($this->manifestEntries as $entry) {
            $manifestEntries[] = self::withoutEmptyValues([
                'manifestIndex' => $entry['manifestIndex'] ?? null,
                'path' => $entry['path'] ?? null,
                'packagePath' => $entry['packagePath'] ?? null,
                'pathReference' => $entry['pathReference'] ?? null,
                'pathSuffix' => $entry['pathSuffix'] ?? null,
                'mediaType' => $entry['mediaType'] ?? null,
                'mediaTypeBase' => $entry['mediaTypeBase'] ?? null,
                'manifestMediaFamily' => $entry['manifestMediaFamily'] ?? null,
                'version' => $entry['version'] ?? null,
                'exists' => ($entry['exists'] ?? false) === true,
                'isDirectory' => ($entry['isDirectory'] ?? false) === true,
                'encrypted' => ($entry['encrypted'] ?? false) === true,
                'thumbnailPackagePart' => ($entry['thumbnailPackagePart'] ?? false) === true,
                'scriptPackagePart' => ($entry['scriptPackagePart'] ?? false) === true,
                'signaturePackagePart' => ($entry['signaturePackagePart'] ?? false) === true,
                'configurationPackagePart' => ($entry['configurationPackagePart'] ?? false) === true,
                'fontPackagePart' => ($entry['fontPackagePart'] ?? false) === true,
                'rdfMetadataPart' => ($entry['rdfMetadataPart'] ?? false) === true,
                'objectReplacementPackagePart' => ($entry['objectReplacementPackagePart'] ?? false) === true,
                'layoutCachePackagePart' => ($entry['layoutCachePackagePart'] ?? false) === true,
                'metaInfSidecarPackagePart' => ($entry['metaInfSidecarPackagePart'] ?? false) === true,
                'linkedResourcePackagePart' => ($entry['linkedResourcePackagePart'] ?? false) === true,
                'databasePackagePart' => ($entry['databasePackagePart'] ?? false) === true,
                'versionPackagePart' => ($entry['versionPackagePart'] ?? false) === true,
                'galleryPackagePart' => ($entry['galleryPackagePart'] ?? false) === true,
                'formPackagePart' => ($entry['formPackagePart'] ?? false) === true,
                'reportPackagePart' => ($entry['reportPackagePart'] ?? false) === true,
                'attachmentPackagePart' => ($entry['attachmentPackagePart'] ?? false) === true,
                'templatePackagePart' => ($entry['templatePackagePart'] ?? false) === true,
                'dictionaryPackagePart' => ($entry['dictionaryPackagePart'] ?? false) === true,
                'eventPackagePart' => ($entry['eventPackagePart'] ?? false) === true,
                'canExposeBytes' => ($entry['canExposeBytes'] ?? false) === true,
                'byteExposurePolicy' => $entry['byteExposurePolicy'] ?? null,
                'storedByteLength' => $entry['storedByteLength'] ?? null,
                'compressedByteLength' => $entry['compressedByteLength'] ?? null,
                'compressionMethod' => $entry['compressionMethod'] ?? null,
                'compressionMethodName' => $entry['compressionMethodName'] ?? null,
                'storedCrc32' => $entry['storedCrc32'] ?? null,
                'byteSha256' => $entry['byteSha256'] ?? null,
                'declaredSize' => $entry['declaredSize'] ?? null,
                'declaredSizeRaw' => $entry['declaredSizeRaw'] ?? null,
                'declaredSizeValid' => ($entry['declaredSizeValid'] ?? false) === true,
                'declaredSizeInvalid' => ($entry['declaredSizeInvalid'] ?? false) === true,
                'declaredSizeMismatch' => ($entry['declaredSizeMismatch'] ?? false) === true,
                'customManifestChildElementCount' => $entry['customManifestChildElementCount'] ?? 0,
                'customManifestChildElementNames' => $entry['customManifestChildElementNames'] ?? [],
                'customManifestChildElements' => $entry['customManifestChildElements'] ?? [],
                'diagnostics' => $entry['diagnostics'] ?? [],
            ]);
        }

        $packageEntries = [];
        foreach ($packageInventory['parts'] ?? [] as $part) {
            if (!is_array($part)) {
                continue;
            }

            $packageEntries[] = self::withoutEmptyValues([
                'path' => $part['path'] ?? null,
                'roles' => $part['roles'] ?? [],
                'centralDirectoryIndex' => $part['centralDirectoryIndex'] ?? null,
                'localHeaderOrder' => $part['localHeaderOrder'] ?? null,
                'compressionMethod' => $part['compressionMethod'] ?? null,
                'compressionMethodName' => $part['compressionMethodName'] ?? null,
                'byteLength' => $part['byteLength'] ?? null,
                'compressedByteLength' => $part['compressedByteLength'] ?? null,
                'crc32' => $part['crc32'] ?? null,
                'byteSha256' => $part['byteSha256'] ?? null,
                'madeByHostSystem' => $part['madeByHostSystem'] ?? null,
                'madeByHostSystemName' => $part['madeByHostSystemName'] ?? null,
                'madeByVersion' => $part['madeByVersion'] ?? null,
                'versionMadeBy' => $part['versionMadeBy'] ?? null,
                'versionNeededToExtract' => $part['versionNeededToExtract'] ?? null,
                'creatorVersionMeetsNeeded' => $part['creatorVersionMeetsNeeded'] ?? null,
                'creatorVersionComparison' => $part['creatorVersionComparison'] ?? null,
                'creatorVersionDelta' => $part['creatorVersionDelta'] ?? null,
                'creatorHostIssues' => $part['creatorHostIssues'] ?? [],
                'externalAttributes' => $part['externalAttributes'] ?? null,
                'externalAttributesHex' => $part['externalAttributesHex'] ?? null,
                'hasExternalAttributes' => ($part['hasExternalAttributes'] ?? false) === true,
                'dosAttributes' => $part['dosAttributes'] ?? null,
                'dosAttributeNames' => $part['dosAttributeNames'] ?? [],
                'hasDosAttributes' => ($part['hasDosAttributes'] ?? false) === true,
                'hasDosReadOnlyAttribute' => ($part['hasDosReadOnlyAttribute'] ?? false) === true,
                'hasDosHiddenAttribute' => ($part['hasDosHiddenAttribute'] ?? false) === true,
                'hasDosSystemAttribute' => ($part['hasDosSystemAttribute'] ?? false) === true,
                'hasDosVolumeLabelAttribute' => ($part['hasDosVolumeLabelAttribute'] ?? false) === true,
                'hasDosDirectoryAttribute' => ($part['hasDosDirectoryAttribute'] ?? false) === true,
                'hasDosArchiveAttribute' => ($part['hasDosArchiveAttribute'] ?? false) === true,
                'internalFileAttributes' => $part['internalFileAttributes'] ?? null,
                'internalFileAttributesHex' => $part['internalFileAttributesHex'] ?? null,
                'internalAttributeNames' => $part['internalAttributeNames'] ?? [],
                'hasInternalFileAttributes' => ($part['hasInternalFileAttributes'] ?? false) === true,
                'hasTextInternalAttribute' => ($part['hasTextInternalAttribute'] ?? false) === true,
                'hasUnknownInternalAttributeBits' => ($part['hasUnknownInternalAttributeBits'] ?? false) === true,
                'unknownInternalAttributeBits' => $part['unknownInternalAttributeBits'] ?? null,
                'unixMode' => $part['unixMode'] ?? null,
                'unixModeOctal' => $part['unixModeOctal'] ?? null,
                'unixPermissions' => $part['unixPermissions'] ?? null,
                'unixPermissionsOctal' => $part['unixPermissionsOctal'] ?? null,
                'hasUnixMode' => ($part['hasUnixMode'] ?? false) === true,
                'unixFileType' => $part['unixFileType'] ?? null,
                'unixFileTypeName' => $part['unixFileTypeName'] ?? null,
                'isUnixExecutableFile' => ($part['isUnixExecutableFile'] ?? false) === true,
                'isGroupWritable' => ($part['isGroupWritable'] ?? false) === true,
                'isWorldWritable' => ($part['isWorldWritable'] ?? false) === true,
                'hasWritablePermissions' => ($part['hasWritablePermissions'] ?? false) === true,
                'platformMetadataPlatform' => $part['platformMetadataPlatform'] ?? null,
                'platformMetadataIssues' => $part['platformMetadataIssues'] ?? [],
                'platformAttributeIssues' => $part['platformAttributeIssues'] ?? [],
                'hasPlatformAttributeProvenance' => ($part['hasPlatformAttributeProvenance'] ?? false) === true,
                'declaredInManifest' => ($part['declaredInManifest'] ?? false) === true,
                'manifestIndex' => $part['manifestIndex'] ?? null,
                'manifestPath' => $part['manifestPath'] ?? null,
                'manifestPackagePath' => $part['manifestPackagePath'] ?? null,
                'manifestMediaTypeBase' => $part['manifestMediaTypeBase'] ?? null,
                'manifestMediaFamily' => $part['manifestMediaFamily'] ?? null,
                'manifestDeclaredSize' => $part['manifestDeclaredSize'] ?? null,
                'manifestDeclaredSizeRaw' => $part['manifestDeclaredSizeRaw'] ?? null,
                'manifestDeclaredSizeValid' => ($part['manifestDeclaredSizeValid'] ?? false) === true,
                'manifestDeclaredSizeInvalid' => ($part['manifestDeclaredSizeInvalid'] ?? false) === true,
                'manifestDeclaredSizeMismatch' => ($part['manifestDeclaredSizeMismatch'] ?? false) === true,
                'customManifestChildElementCount' => $part['customManifestChildElementCount'] ?? 0,
                'customManifestChildElementNames' => $part['customManifestChildElementNames'] ?? [],
                'manifestDiagnostics' => $part['manifestDiagnostics'] ?? [],
                'thumbnailPackagePart' => ($part['thumbnailPackagePart'] ?? false) === true,
                'scriptPackagePart' => ($part['scriptPackagePart'] ?? false) === true,
                'signaturePackagePart' => ($part['signaturePackagePart'] ?? false) === true,
                'configurationPackagePart' => ($part['configurationPackagePart'] ?? false) === true,
                'fontPackagePart' => ($part['fontPackagePart'] ?? false) === true,
                'rdfMetadataPart' => ($part['rdfMetadataPart'] ?? false) === true,
                'objectReplacementPackagePart' => ($part['objectReplacementPackagePart'] ?? false) === true,
                'layoutCachePackagePart' => ($part['layoutCachePackagePart'] ?? false) === true,
                'metaInfSidecarPackagePart' => ($part['metaInfSidecarPackagePart'] ?? false) === true,
                'linkedResourcePackagePart' => ($part['linkedResourcePackagePart'] ?? false) === true,
                'databasePackagePart' => ($part['databasePackagePart'] ?? false) === true,
                'versionPackagePart' => ($part['versionPackagePart'] ?? false) === true,
                'galleryPackagePart' => ($part['galleryPackagePart'] ?? false) === true,
                'formPackagePart' => ($part['formPackagePart'] ?? false) === true,
                'reportPackagePart' => ($part['reportPackagePart'] ?? false) === true,
                'attachmentPackagePart' => ($part['attachmentPackagePart'] ?? false) === true,
                'templatePackagePart' => ($part['templatePackagePart'] ?? false) === true,
                'dictionaryPackagePart' => ($part['dictionaryPackagePart'] ?? false) === true,
                'eventPackagePart' => ($part['eventPackagePart'] ?? false) === true,
                'canExposeBytes' => ($part['canExposeBytes'] ?? false) === true,
                'byteExposurePolicy' => $part['byteExposurePolicy'] ?? null,
                'undeclared' => ($part['undeclared'] ?? false) === true,
            ]);
        }

        $payload = [
            'identityVersion' => 1,
            'packageType' => 'opendocument-text',
            'mimetype' => self::TEXT_MIMETYPE,
            'manifestVersion' => $this->manifestVersion,
            'manifestEntryCount' => count($manifestEntries),
            'packageEntryCount' => count($packageEntries),
            'manifestPaths' => array_column($manifestEntries, 'path'),
            'packagePaths' => array_column($packageEntries, 'path'),
            'manifestRootAttributeCount' => $manifestRootAttributes['attributeCount'] ?? 0,
            'manifestRootAttributeNames' => $manifestRootAttributes['attributeNames'] ?? [],
            'manifestRootAttributes' => $manifestRootAttributes['attributes'] ?? [],
            'manifestRootCustomAttributeCount' => $manifestRootAttributes['customAttributeCount'] ?? 0,
            'manifestRootCustomAttributeNames' => $manifestRootAttributes['customAttributeNames'] ?? [],
            'manifestRootCustomAttributes' => $manifestRootAttributes['customAttributes'] ?? [],
            'manifestRootCustomAttributeMap' => $manifestRootAttributes['customAttributeMap'] ?? [],
            'manifestRootNamespaceDeclarationCount' => $manifestRootAttributes['namespaceDeclarationCount'] ?? 0,
            'manifestRootNamespaceDeclarationNames' => $manifestRootAttributes['namespaceDeclarationNames'] ?? [],
            'manifestRootNamespaceDeclarations' => $manifestRootAttributes['namespaceDeclarations'] ?? [],
            'manifestRootNamespaceDeclarationMap' => $manifestRootAttributes['namespaceDeclarationMap'] ?? [],
            'manifestEntries' => $manifestEntries,
            'packageEntries' => $packageEntries,
            'manifestMediaFamilyCounts' => $packageInventory['manifestMediaFamilyCounts'] ?? [],
            'byteExposurePolicyCounts' => $packageInventory['byteExposurePolicyCounts'] ?? [],
            'roleCounts' => $packageInventory['roleCounts'] ?? [],
            'undeclaredEntryCount' => $packageInventory['undeclaredEntryCount'] ?? 0,
            'unsupportedCompressionMethodCount' => $packageInventory['unsupportedCompressionMethodCount'] ?? 0,
            'encryptedCount' => count($this->encryptedManifestEntries()),
            'platformMetadataEntryCount' => $packageInventory['platformMetadataEntryCount'] ?? 0,
            'knownCreatorHostSystemEntryCount' => $packageInventory['knownCreatorHostSystemEntryCount'] ?? 0,
            'unknownCreatorHostSystemEntryCount' => $packageInventory['unknownCreatorHostSystemEntryCount'] ?? 0,
            'creatorVersionBelowNeededEntryCount' => $packageInventory['creatorVersionBelowNeededEntryCount'] ?? 0,
            'packageThumbnailPartCount' => $packageInventory['packageThumbnailPartCount'] ?? 0,
            'packageSignaturePartCount' => $packageInventory['packageSignaturePartCount'] ?? 0,
            'embeddedObjectPackageRootCount' => $packageInventory['embeddedObjectPackageRootCount'] ?? 0,
            'embeddedObjectPackagePartCount' => $packageInventory['embeddedObjectPackagePartCount'] ?? 0,
            'objectReplacementPartCount' => $packageInventory['objectReplacementPartCount'] ?? 0,
            'scriptPackagePartCount' => $packageInventory['scriptPackagePartCount'] ?? 0,
            'configurationPackagePartCount' => $packageInventory['configurationPackagePartCount'] ?? 0,
            'fontPackagePartCount' => $packageInventory['fontPackagePartCount'] ?? 0,
            'rdfMetadataPartCount' => $packageInventory['rdfMetadataPartCount'] ?? 0,
            'layoutCachePartCount' => $packageInventory['layoutCachePartCount'] ?? 0,
            'metaInfSidecarPartCount' => $packageInventory['metaInfSidecarPartCount'] ?? 0,
            'linkedResourcePackagePartCount' => $packageInventory['linkedResourcePackagePartCount'] ?? 0,
            'databasePackagePartCount' => $packageInventory['databasePackagePartCount'] ?? 0,
            'versionPackagePartCount' => $packageInventory['versionPackagePartCount'] ?? 0,
            'galleryPackagePartCount' => $packageInventory['galleryPackagePartCount'] ?? 0,
            'formPackagePartCount' => $packageInventory['formPackagePartCount'] ?? 0,
            'reportPackagePartCount' => $packageInventory['reportPackagePartCount'] ?? 0,
            'attachmentPackagePartCount' => $packageInventory['attachmentPackagePartCount'] ?? 0,
            'templatePackagePartCount' => $packageInventory['templatePackagePartCount'] ?? 0,
            'dictionaryPackagePartCount' => $packageInventory['dictionaryPackagePartCount'] ?? 0,
            'eventPackagePartCount' => $packageInventory['eventPackagePartCount'] ?? 0,
            'unixModeEntryCount' => $packageInventory['unixModeEntryCount'] ?? 0,
            'executableFileCount' => $packageInventory['executableFileCount'] ?? 0,
            'writablePermissionEntryCount' => $packageInventory['writablePermissionEntryCount'] ?? 0,
            'dosAttributeEntryCount' => $packageInventory['dosAttributeEntryCount'] ?? 0,
            'hiddenSystemOrVolumeLabelEntryCount' => $packageInventory['hiddenSystemOrVolumeLabelEntryCount'] ?? 0,
            'internalAttributeEntryCount' => $packageInventory['internalAttributeEntryCount'] ?? 0,
            'totalByteLength' => $packageInventory['totalByteLength'] ?? 0,
            'totalCompressedByteLength' => $packageInventory['totalCompressedByteLength'] ?? 0,
            'exposableByteLength' => $packageInventory['exposableByteLength'] ?? 0,
            'blockedByteLength' => $packageInventory['blockedByteLength'] ?? 0,
        ];
        $identityJson = json_encode(
            self::canonicalIdentityValue($payload),
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
        );

        $payload['identitySha256'] = hash('sha256', $identityJson);
        $payload['identityPayloadByteLength'] = strlen($identityJson);
        $payload['byteExposurePolicy'] = 'odf-package-identity-metadata-only';
        $payload['canExposeBytes'] = false;

        return $payload;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function encryptedManifestEntries(): array
    {
        return array_values(array_filter(
            $this->manifestEntries,
            static fn (array $entry): bool => ($entry['encrypted'] ?? false) === true
        ));
    }

    private static function canonicalIdentityValue(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }

        if (array_is_list($value)) {
            return array_map(static fn (mixed $item): mixed => self::canonicalIdentityValue($item), $value);
        }

        $canonical = [];
        foreach ($value as $key => $item) {
            $canonical[(string) $key] = self::canonicalIdentityValue($item);
        }
        ksort($canonical, SORT_STRING);

        return $canonical;
    }

    /**
     * @return array{rawNameHex:string,nameEncoding:string,rawNameMatchesDecodedName:bool,usesLegacyNameEncoding:bool,usesUnicodePathExtraField:bool,hasRawNameProvenance:bool}
     */
    private static function zipEntryRawNameProvenance(ZipPackageEntry $entry): array
    {
        $rawNameMatchesDecodedName = $entry->rawName === $entry->name;
        $usesLegacyNameEncoding = $entry->nameEncoding === 'cp437';
        $usesUnicodePathExtraField = $entry->nameEncoding === 'info-zip-unicode-path';

        return [
            'rawNameHex' => bin2hex($entry->rawName),
            'nameEncoding' => $entry->nameEncoding,
            'rawNameMatchesDecodedName' => $rawNameMatchesDecodedName,
            'usesLegacyNameEncoding' => $usesLegacyNameEncoding,
            'usesUnicodePathExtraField' => $usesUnicodePathExtraField,
            'hasRawNameProvenance' => !$rawNameMatchesDecodedName
                || $usesLegacyNameEncoding
                || $usesUnicodePathExtraField,
        ];
    }

    /**
     * @param array<string, mixed> $modificationTimes
     * @return array<string, array<string, mixed>>
     */
    private static function zipModificationTimeEntriesByName(array $modificationTimes): array
    {
        $entriesByName = [];
        foreach (is_array($modificationTimes['entries'] ?? null) ? $modificationTimes['entries'] : [] as $entry) {
            $name = $entry['name'] ?? null;
            if (is_string($name) && $name !== '') {
                $entriesByName[$name] = $entry;
            }
        }

        return $entriesByName;
    }

    /**
     * @param array<string, mixed> $summary
     * @return array<string, array<string, mixed>>
     */
    private static function zipPreflightEntriesByName(array $summary): array
    {
        $entriesByName = [];
        foreach (is_array($summary['entries'] ?? null) ? $summary['entries'] : [] as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $name = $entry['name'] ?? null;
            if (is_string($name) && $name !== '') {
                $entriesByName[$name] = $entry;
            }
        }

        return $entriesByName;
    }

    /**
     * @param array<string, mixed>|null $entry
     * @return array<string, mixed>
     */
    private static function zipEntrySourceRecordProvenance(?array $entry): array
    {
        if ($entry === null) {
            return [
                'hasZipSourceRecordProvenance' => false,
                'zipLocalHeaderLength' => null,
                'zipLocalHeaderSha256' => null,
                'zipCentralDirectoryRecordOffset' => null,
                'zipCentralDirectoryRecordEnd' => null,
                'zipCentralDirectoryRecordBytes' => null,
                'zipCentralDirectoryRecordSha256' => null,
                'zipSourceRecordReviewBytes' => null,
            ];
        }

        $centralDirectoryRecordOffset = is_int($entry['centralDirectoryRecordOffset'] ?? null)
            ? $entry['centralDirectoryRecordOffset']
            : null;
        $centralDirectoryRecordEnd = is_int($entry['centralDirectoryRecordEnd'] ?? null)
            ? $entry['centralDirectoryRecordEnd']
            : null;
        $centralDirectoryRecordBytes = $centralDirectoryRecordOffset !== null
            && $centralDirectoryRecordEnd !== null
            && $centralDirectoryRecordEnd >= $centralDirectoryRecordOffset
                ? $centralDirectoryRecordEnd - $centralDirectoryRecordOffset
                : null;
        $localHeaderLength = is_int($entry['localHeaderLength'] ?? null) ? $entry['localHeaderLength'] : null;

        return [
            'hasZipSourceRecordProvenance' => true,
            'zipLocalHeaderLength' => $localHeaderLength,
            'zipLocalHeaderSha256' => is_string($entry['localHeaderSha256'] ?? null) ? $entry['localHeaderSha256'] : null,
            'zipCentralDirectoryRecordOffset' => $centralDirectoryRecordOffset,
            'zipCentralDirectoryRecordEnd' => $centralDirectoryRecordEnd,
            'zipCentralDirectoryRecordBytes' => $centralDirectoryRecordBytes,
            'zipCentralDirectoryRecordSha256' => is_string($entry['centralDirectoryRecordSha256'] ?? null)
                ? $entry['centralDirectoryRecordSha256']
                : null,
            'zipSourceRecordReviewBytes' => ($localHeaderLength ?? 0) + ($centralDirectoryRecordBytes ?? 0),
        ];
    }

    /**
     * @param array<string, mixed> $packageManifest
     * @param array<string, array<string, mixed>> $parts
     * @return array<string, mixed>
     */
    private static function zipSourceRecordSummary(array $packageManifest, array $parts): array
    {
        $items = [];
        $localHeaderBytes = 0;
        $centralDirectoryRecordBytes = 0;
        $reviewBytes = 0;

        foreach (is_array($packageManifest['entries'] ?? null) ? $packageManifest['entries'] : [] as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $name = $entry['name'] ?? null;
            if (!is_string($name) || $name === '') {
                continue;
            }

            $provenance = self::zipEntrySourceRecordProvenance($entry);
            $part = $parts[$name] ?? [];
            if (is_int($provenance['zipLocalHeaderLength'])) {
                $localHeaderBytes += $provenance['zipLocalHeaderLength'];
            }
            if (is_int($provenance['zipCentralDirectoryRecordBytes'])) {
                $centralDirectoryRecordBytes += $provenance['zipCentralDirectoryRecordBytes'];
            }
            if (is_int($provenance['zipSourceRecordReviewBytes'])) {
                $reviewBytes += $provenance['zipSourceRecordReviewBytes'];
            }

            $items[] = self::withoutEmptyValues([
                'path' => $name,
                'centralDirectoryIndex' => $entry['centralDirectoryIndex'] ?? null,
                'localHeaderOrder' => $entry['localHeaderOrder'] ?? null,
                'roles' => is_array($part['roles'] ?? null) ? $part['roles'] : [],
                'canExposeBytes' => ($part['canExposeBytes'] ?? false) === true,
                'byteExposurePolicy' => $part['byteExposurePolicy'] ?? null,
            ] + $provenance);
        }

        return [
            'entryCount' => count($items),
            'localHeaderBytes' => $localHeaderBytes,
            'centralDirectoryRecordBytes' => $centralDirectoryRecordBytes,
            'sourceRecordReviewBytes' => $reviewBytes,
            'canExposeBytes' => false,
            'byteExposurePolicy' => 'zip-source-record-provenance-metadata-only',
            'items' => $items,
        ];
    }

    /**
     * @param array<string, mixed>|null $platformMetadata
     * @param array<string, mixed>|null $permissions
     * @param array<string, mixed>|null $creatorHost
     * @param array<string, mixed>|null $dosAttributes
     * @param array<string, mixed>|null $internalAttributes
     * @return array<string, mixed>
     */
    private static function zipPlatformAttributeProvenance(
        ZipPackageEntry $entry,
        ?array $platformMetadata,
        ?array $permissions,
        ?array $creatorHost,
        ?array $dosAttributes,
        ?array $internalAttributes
    ): array {
        $unixMode = $entry->unixMode();
        $unixPermissions = $entry->unixPermissionBits();
        $platformAttributeIssues = [];
        foreach ([$platformMetadata, $permissions, $creatorHost, $internalAttributes] as $summary) {
            foreach (is_array($summary['issues'] ?? null) ? $summary['issues'] : [] as $issue) {
                if (is_string($issue) && $issue !== '' && !in_array($issue, $platformAttributeIssues, true)) {
                    $platformAttributeIssues[] = $issue;
                }
            }
        }
        if (is_array($dosAttributes)) {
            foreach ([
                'hasHiddenAttribute' => 'dos-hidden-attribute',
                'hasSystemAttribute' => 'dos-system-attribute',
                'hasVolumeLabelAttribute' => 'dos-volume-label-attribute',
            ] as $flag => $issue) {
                if (($dosAttributes[$flag] ?? false) === true && !in_array($issue, $platformAttributeIssues, true)) {
                    $platformAttributeIssues[] = $issue;
                }
            }
        }

        return [
            'madeByHostSystem' => $entry->madeByHostSystem(),
            'madeByHostSystemName' => $creatorHost['madeByHostSystemName'] ?? null,
            'madeByVersion' => $entry->madeByVersion(),
            'versionMadeBy' => $entry->versionMadeBy,
            'versionNeededToExtract' => $entry->neededToExtractVersion(),
            'creatorVersionMeetsNeeded' => $creatorHost['creatorVersionMeetsNeeded'] ?? null,
            'creatorVersionComparison' => $creatorHost['creatorVersionComparison'] ?? null,
            'creatorVersionDelta' => $creatorHost['creatorVersionDelta'] ?? null,
            'creatorHostIssues' => is_array($creatorHost['issues'] ?? null) ? $creatorHost['issues'] : [],
            'externalAttributes' => $entry->externalFileAttributes,
            'externalAttributesHex' => sprintf('%08x', $entry->externalFileAttributes),
            'hasExternalAttributes' => $entry->externalFileAttributes !== 0,
            'dosAttributes' => $dosAttributes['dosAttributes'] ?? ($entry->externalFileAttributes & 0xff),
            'dosAttributeNames' => is_array($dosAttributes['dosAttributeNames'] ?? null) ? $dosAttributes['dosAttributeNames'] : $entry->dosAttributeNames(),
            'hasDosAttributes' => (($dosAttributes['dosAttributes'] ?? ($entry->externalFileAttributes & 0xff)) !== 0),
            'hasDosReadOnlyAttribute' => $dosAttributes['hasReadOnlyAttribute'] ?? $entry->hasDosReadOnlyAttribute(),
            'hasDosHiddenAttribute' => $dosAttributes['hasHiddenAttribute'] ?? $entry->hasDosHiddenAttribute(),
            'hasDosSystemAttribute' => $dosAttributes['hasSystemAttribute'] ?? $entry->hasDosSystemAttribute(),
            'hasDosVolumeLabelAttribute' => $dosAttributes['hasVolumeLabelAttribute'] ?? $entry->hasDosVolumeLabelAttribute(),
            'hasDosDirectoryAttribute' => $dosAttributes['hasDirectoryAttribute'] ?? $entry->hasDosDirectoryAttribute(),
            'hasDosArchiveAttribute' => $dosAttributes['hasArchiveAttribute'] ?? $entry->hasDosArchiveAttribute(),
            'internalFileAttributes' => $entry->internalFileAttributes,
            'internalFileAttributesHex' => sprintf('%04x', $entry->internalFileAttributes),
            'internalAttributeNames' => is_array($internalAttributes['internalAttributeNames'] ?? null) ? $internalAttributes['internalAttributeNames'] : $entry->internalAttributeNames(),
            'hasInternalFileAttributes' => $internalAttributes['hasInternalFileAttributes'] ?? ($entry->internalFileAttributes !== 0),
            'hasTextInternalAttribute' => $internalAttributes['hasTextInternalAttribute'] ?? $entry->hasTextInternalAttribute(),
            'hasUnknownInternalAttributeBits' => $internalAttributes['hasUnknownInternalAttributeBits'] ?? ($entry->unknownInternalAttributeBits() !== 0),
            'unknownInternalAttributeBits' => $internalAttributes['unknownInternalAttributeBits'] ?? $entry->unknownInternalAttributeBits(),
            'unixMode' => $unixMode,
            'unixModeOctal' => $unixMode === null ? null : sprintf('%06o', $unixMode),
            'unixPermissions' => $unixPermissions,
            'unixPermissionsOctal' => $unixPermissions === null ? null : sprintf('%04o', $unixPermissions),
            'hasUnixMode' => $unixMode !== null,
            'unixFileType' => $entry->unixFileType(),
            'unixFileTypeName' => $entry->unixFileTypeName(),
            'isUnixExecutableFile' => $permissions['isExecutableFile'] ?? $entry->isUnixExecutableFile(),
            'isGroupWritable' => $permissions['isGroupWritable'] ?? false,
            'isWorldWritable' => $permissions['isWorldWritable'] ?? false,
            'hasWritablePermissions' => $permissions['hasWritablePermissions'] ?? false,
            'platformMetadataPlatform' => $platformMetadata['platform'] ?? null,
            'platformMetadataIssues' => is_array($platformMetadata['issues'] ?? null) ? $platformMetadata['issues'] : [],
            'platformAttributeIssues' => $platformAttributeIssues,
            'hasPlatformAttributeProvenance' => $platformAttributeIssues !== []
                || $entry->externalFileAttributes !== 0
                || $entry->internalFileAttributes !== 0
                || $entry->madeByHostSystem() !== 3
                || $entry->madeByVersion() !== $entry->neededToExtractVersion(),
        ];
    }

    /**
     * @param array<string, mixed>|null $timestamp
     * @return array<string, mixed>
     */
    private static function zipTimestampProvenance(?array $timestamp): array
    {
        return [
            'zipModifiedAt' => $timestamp['modifiedAt'] ?? null,
            'zipTimestampSource' => $timestamp['timestampSource'] ?? null,
            'zipModifiedDosTime' => $timestamp['modifiedDosTime'] ?? null,
            'zipModifiedDosDate' => $timestamp['modifiedDosDate'] ?? null,
            'zipHasDosTimestamp' => ($timestamp['hasDosTimestamp'] ?? false) === true,
            'zipIsDosTimestampValid' => ($timestamp['isDosTimestampValid'] ?? true) === true,
            'zipDosModifiedAt' => $timestamp['dosModifiedAt'] ?? null,
            'zipExtendedModifiedAt' => $timestamp['extendedModifiedAt'] ?? null,
            'zipNtfsModifiedAt' => $timestamp['ntfsModifiedAt'] ?? null,
            'zipCentralModifiedAt' => $timestamp['centralModifiedAt'] ?? null,
            'zipCentralTimestampSource' => $timestamp['centralTimestampSource'] ?? null,
            'zipLocalExtendedModifiedAt' => $timestamp['localExtendedModifiedAt'] ?? null,
            'zipLocalNtfsModifiedAt' => $timestamp['localNtfsModifiedAt'] ?? null,
            'zipLocalModifiedAt' => $timestamp['localModifiedAt'] ?? null,
            'zipLocalTimestampSource' => $timestamp['localTimestampSource'] ?? null,
            'zipTimestampIssues' => is_array($timestamp['issues'] ?? null) ? $timestamp['issues'] : [],
        ];
    }

    /**
     * @param array<string, mixed>|null $manifestEntry
     * @param array<string, mixed>|null $embeddedObjectPackage
     * @return list<string>
     */
    private static function packageEntryRoles(
        ZipPackageEntry $entry,
        ?array $manifestEntry,
        bool $undeclared,
        ?array $embeddedObjectPackage
    ): array
    {
        $roles = [];
        if ($entry->name === 'mimetype') {
            $roles[] = 'odf-mimetype';
        }
        if ($entry->name === 'META-INF/manifest.xml') {
            $roles[] = 'odf-manifest';
        }
        if ($entry->name === 'content.xml') {
            $roles[] = 'odf-content';
        }
        if ($entry->name === 'styles.xml') {
            $roles[] = 'odf-styles';
        }
        if ($entry->name === 'meta.xml') {
            $roles[] = 'odf-meta';
        }
        if ($entry->name === 'settings.xml') {
            $roles[] = 'odf-settings';
        }
        if (self::isThumbnailPackagePartName($entry->name)) {
            $roles[] = 'package-thumbnail';
        }
        if (self::isSignaturePackagePartName($entry->name)) {
            $roles[] = 'package-signature';
        }
        if (self::isScriptPackagePartName($entry->name)) {
            $roles[] = 'script-package';
        }
        if (self::isConfigurationPackagePartName($entry->name)) {
            $roles[] = 'configuration-package';
        }
        if (self::isFontPackagePart($entry->name, is_array($manifestEntry) ? (string) ($manifestEntry['mediaType'] ?? '') : null)) {
            $roles[] = 'font-package';
        }
        if (self::isRdfMetadataPart($entry->name, is_array($manifestEntry) ? (string) ($manifestEntry['mediaType'] ?? '') : null)) {
            $roles[] = 'rdf-metadata';
        }
        if ($entry->isDirectory()) {
            $roles[] = 'zip-directory';
        }
        if (is_array($embeddedObjectPackage)) {
            $roles[] = $embeddedObjectPackage['isRoot'] === true ? 'embedded-object-root' : 'embedded-object-part';
        }
        if (self::isObjectReplacementPackagePartName($entry->name)) {
            $roles[] = 'object-replacement';
        }
        if (self::isLayoutCachePackagePartName($entry->name)) {
            $roles[] = 'layout-cache';
        }
        if (self::isMetaInfSidecarPackagePartName($entry->name)) {
            $roles[] = 'meta-inf-sidecar';
        }
        if (self::isLinkedResourcePackagePartName($entry->name)) {
            $roles[] = 'linked-resource-package';
        }
        if (self::isDatabasePackagePartName($entry->name)) {
            $roles[] = 'database-package';
        }
        if (self::isVersionPackagePartName($entry->name)) {
            $roles[] = 'version-package';
        }
        if (self::isGalleryPackagePartName($entry->name)) {
            $roles[] = 'gallery-package';
        }
        if (self::isFormPackagePartName($entry->name)) {
            $roles[] = 'form-package';
        }
        if (self::isReportPackagePartName($entry->name)) {
            $roles[] = 'report-package';
        }
        if (self::isAttachmentPackagePartName($entry->name)) {
            $roles[] = 'attachment-package';
        }
        if (self::isTemplatePackagePartName($entry->name)) {
            $roles[] = 'template-package';
        }
        if (self::isDictionaryPackagePartName($entry->name)) {
            $roles[] = 'dictionary-package';
        }
        if (self::isEventPackagePartName($entry->name)) {
            $roles[] = 'event-package';
        }
        if (is_array($manifestEntry)) {
            $roles[] = 'manifest-declared';
            if (!$entry->isDirectory() && self::isMediaResourceManifestEntry($manifestEntry)) {
                $roles[] = 'media-resource';
            }
        }
        if ($undeclared) {
            $roles[] = 'undeclared-package-entry';
        }

        return $roles === [] ? ['package-part'] : array_values(array_unique($roles));
    }

    private static function isScriptPackagePartName(string $path): bool
    {
        $normalized = strtolower(ltrim($path, '/'));

        return str_starts_with($normalized, 'basic/')
            || str_starts_with($normalized, 'dialogs/')
            || str_starts_with($normalized, 'scripts/');
    }

    private static function isConfigurationPackagePartName(string $path): bool
    {
        $normalized = strtolower(ltrim($path, '/'));

        return str_starts_with($normalized, 'configurations2/')
            || str_starts_with($normalized, 'configurations/');
    }

    private static function configurationPackageRoot(string $path): ?string
    {
        $root = strtolower((string) (explode('/', trim($path, '/'), 2)[0] ?? ''));

        return match ($root) {
            'configurations2' => 'Configurations2',
            'configurations' => 'Configurations',
            default => null,
        };
    }

    private static function isDatabasePackagePartName(string $path): bool
    {
        return str_starts_with(strtolower(ltrim($path, '/')), 'database/');
    }

    private static function isVersionPackagePartName(string $path): bool
    {
        return str_starts_with(strtolower(ltrim($path, '/')), 'versions/');
    }

    private static function isGalleryPackagePartName(string $path): bool
    {
        return str_starts_with(strtolower(ltrim($path, '/')), 'gallery/');
    }

    private static function isFormPackagePartName(string $path): bool
    {
        return str_starts_with(strtolower(ltrim($path, '/')), 'forms/');
    }

    private static function isEventPackagePartName(string $path): bool
    {
        return str_starts_with(strtolower(ltrim($path, '/')), 'events/');
    }

    private static function isReportPackagePartName(string $path): bool
    {
        return str_starts_with(strtolower(ltrim($path, '/')), 'reports/');
    }

    private static function isAttachmentPackagePartName(string $path): bool
    {
        return str_starts_with(strtolower(ltrim($path, '/')), 'attachments/');
    }

    private static function isLinkedResourcePackagePartName(string $path): bool
    {
        $normalized = strtolower(ltrim($path, '/'));

        return str_starts_with($normalized, 'links/');
    }

    private static function linkedResourceMediaTypeFromPart(string $path): ?string
    {
        if (str_ends_with($path, '/')) {
            return '';
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return match ($extension) {
            'xml', 'xhtml', 'html' => 'text/xml',
            'rdf' => 'application/rdf+xml',
            'txt', 'csv' => 'text/plain',
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'svg' => 'image/svg+xml',
            'pdf' => 'application/pdf',
            'bin', 'dat', 'cache' => 'application/octet-stream',
            default => null,
        };
    }

    private static function isLinkedResourceMediaType(string $mediaType): bool
    {
        $base = self::mediaTypeReport($mediaType)['mediaTypeBase'];

        return self::isXmlMediaTypeBase($base)
            || self::mediaResourceFamilyFromMediaTypeBase($base) !== null
            || in_array($base, ['text/plain', 'text/csv', 'application/pdf', 'application/rdf+xml', 'application/octet-stream', 'application/binary'], true)
            || str_starts_with($base, 'application/vnd.');
    }

    private static function linkedResourcePackagePartKind(string $path, string $mediaType): string
    {
        if (str_ends_with($path, '/')) {
            return 'linked-resource-directory';
        }

        $basename = strtolower(basename($path));
        if (in_array($basename, ['manifest.xml', 'links.xml', 'settings.xml'], true)) {
            return 'linked-resource-manifest';
        }

        $base = self::mediaTypeReport($mediaType)['mediaTypeBase'];
        if (self::mediaResourceFamilyFromMediaTypeBase($base) !== null) {
            return 'linked-resource-media-cache';
        }
        if (self::isXmlMediaTypeBase($base) || $base === 'application/rdf+xml') {
            return 'linked-resource-xml-cache';
        }
        if (in_array($base, ['application/octet-stream', 'application/binary'], true)) {
            return 'linked-resource-binary-cache';
        }

        return 'linked-resource-cache';
    }

    private static function isFontPackagePart(string $path, ?string $mediaType = null): bool
    {
        if (self::isFontPackagePartName($path)) {
            return true;
        }

        return $mediaType !== null && self::isFontMediaType($mediaType);
    }

    private static function isRdfMetadataPart(string $path, ?string $mediaType = null): bool
    {
        if (self::isRdfPartName($path)) {
            return true;
        }

        return $mediaType !== null && self::mediaTypeReport($mediaType)['mediaTypeBase'] === 'application/rdf+xml';
    }

    private static function isRdfPartName(string $path): bool
    {
        return strtolower(basename($path)) === 'manifest.rdf';
    }

    private static function isFontPackagePartName(string $path): bool
    {
        $normalized = strtolower(ltrim($path, '/'));
        if (str_ends_with($normalized, '/')) {
            return false;
        }

        return str_starts_with($normalized, 'fonts/');
    }

    private static function isObjectReplacementPackagePartName(string $path): bool
    {
        $normalized = strtolower(ltrim($path, '/'));

        return str_starts_with($normalized, 'objectreplacements/');
    }

    private static function isLayoutCachePackagePartName(string $path): bool
    {
        return strtolower(ltrim($path, '/')) === 'layout-cache';
    }

    private static function isMetaInfSidecarPackagePartName(string $path): bool
    {
        $normalized = strtolower(ltrim($path, '/'));

        return str_starts_with($normalized, 'meta-inf/')
            && $normalized !== 'meta-inf/manifest.xml'
            && !self::isSignaturePackagePartName($path)
            && !str_ends_with($normalized, '/');
    }

    private static function layoutCacheMediaTypeFromPart(string $path): string
    {
        return self::isLayoutCachePackagePartName($path) ? 'application/binary' : '';
    }

    private static function isLayoutCacheMediaType(string $mediaType): bool
    {
        return in_array(self::mediaTypeReport($mediaType)['mediaTypeBase'], ['application/binary', 'application/octet-stream'], true);
    }

    private static function objectReplacementMediaTypeFromPart(string $path): ?string
    {
        if (str_ends_with($path, '/')) {
            return '';
        }

        return self::thumbnailMediaTypeFromPart($path);
    }

    private static function isObjectReplacementMediaType(string $mediaType): bool
    {
        return str_starts_with(self::mediaTypeReport($mediaType)['mediaTypeBase'], 'image/');
    }

    private static function objectReplacementPackagePartKind(string $path, string $mediaType, bool $isDirectory): string
    {
        if ($isDirectory) {
            return 'object-replacement-directory';
        }

        return self::isObjectReplacementMediaType($mediaType)
            ? 'object-replacement-preview'
            : 'object-replacement-resource';
    }

    private static function isFontMediaType(string $mediaType): bool
    {
        $base = self::mediaTypeReport($mediaType)['mediaTypeBase'];

        return str_starts_with($base, 'font/')
            || in_array($base, [
                'application/font-woff',
                'application/font-woff2',
                'application/vnd.ms-fontobject',
                'application/vnd.ms-opentype',
                'application/x-font-opentype',
                'application/x-font-otf',
                'application/x-font-ttf',
                'application/x-font-type1',
                'application/x-font-woff',
                'application/x-font-woff2',
                'application/x-opentype',
                'application/x-truetype-font',
            ], true);
    }

    /**
     * @param list<array<string, mixed>> $entries
     * @return array<string, array<string, mixed>>
     */
    private static function embeddedObjectPackageRootParts(array $entries): array
    {
        $roots = [];
        foreach ($entries as $entry) {
            $packagePath = $entry['packagePath'] ?? null;
            if (!is_string($packagePath) || $packagePath === '') {
                continue;
            }
            if (!self::isEmbeddedObjectPackageMediaType((string) ($entry['mediaTypeBase'] ?? $entry['mediaType'] ?? ''))) {
                continue;
            }

            $root = rtrim($packagePath, '/') . '/';
            if ($root === '/') {
                continue;
            }

            $roots[$root] = $entry;
        }

        return $roots;
    }

    private static function isEmbeddedObjectPackageMediaType(string $mediaType): bool
    {
        $base = self::mediaTypeReport($mediaType)['mediaTypeBase'];

        return in_array($base, [
            'application/vnd.oasis.opendocument.chart',
            'application/vnd.oasis.opendocument.formula',
            'application/vnd.oasis.opendocument.graphics',
            'application/vnd.oasis.opendocument.presentation',
            'application/vnd.oasis.opendocument.spreadsheet',
            'application/vnd.oasis.opendocument.text',
        ], true);
    }

    /**
     * @param array<string, array<string, mixed>> $objectPackageRootParts
     * @return array<string, mixed>|null
     */
    private static function embeddedObjectPackageMembership(string $part, array $objectPackageRootParts): ?array
    {
        foreach ($objectPackageRootParts as $rootPart => $rootEntry) {
            if ($part !== $rootPart && !str_starts_with($part, $rootPart)) {
                continue;
            }

            return [
                'rootPart' => $rootPart,
                'objectPath' => rtrim($rootPart, '/'),
                'objectType' => self::objectTypeForMediaType((string) ($rootEntry['mediaTypeBase'] ?? $rootEntry['mediaType'] ?? '')),
                'mediaType' => (string) ($rootEntry['mediaType'] ?? ''),
                'mediaTypeBase' => (string) ($rootEntry['mediaTypeBase'] ?? ''),
                'isRoot' => $part === $rootPart,
            ];
        }

        return null;
    }

    private static function objectTypeForMediaType(string $mediaType): string
    {
        $base = self::mediaTypeReport($mediaType)['mediaTypeBase'];

        return match ($base) {
            'application/vnd.oasis.opendocument.chart' => 'chart',
            'application/vnd.oasis.opendocument.formula' => 'formula',
            'application/vnd.oasis.opendocument.graphics' => 'graphics',
            'application/vnd.oasis.opendocument.presentation' => 'presentation',
            'application/vnd.oasis.opendocument.spreadsheet' => 'spreadsheet',
            'application/vnd.oasis.opendocument.text' => 'text',
            default => 'object',
        };
    }

    /**
     * @param array<string, mixed> $entry
     */
    private static function isMediaResourceManifestEntry(array $entry): bool
    {
        $packagePath = $entry['packagePath'] ?? $entry['path'] ?? '';
        if (!is_string($packagePath) || $packagePath === '' || str_ends_with($packagePath, '/')) {
            return false;
        }
        if (($entry['embeddedObjectPackagePart'] ?? false) === true) {
            return false;
        }
        if (is_string($packagePath) && self::isThumbnailPackagePartName($packagePath)) {
            return false;
        }
        if (is_string($packagePath) && self::isSignaturePackagePartName($packagePath)) {
            return false;
        }
        if (is_string($packagePath) && self::isScriptPackagePartName($packagePath)) {
            return false;
        }
        if (is_string($packagePath) && self::isConfigurationPackagePartName($packagePath)) {
            return false;
        }
        if (is_string($packagePath) && self::isFontPackagePart($packagePath, is_string($entry['mediaType'] ?? null) ? $entry['mediaType'] : null)) {
            return false;
        }
        if (
            ($entry['rdfMetadataPart'] ?? false) === true
            || (is_string($packagePath) && self::isRdfMetadataPart($packagePath, is_string($entry['mediaType'] ?? null) ? $entry['mediaType'] : null))
        ) {
            return false;
        }
        if (is_string($packagePath) && self::isObjectReplacementPackagePartName($packagePath)) {
            return false;
        }
        if (is_string($packagePath) && self::isLayoutCachePackagePartName($packagePath)) {
            return false;
        }
        if (is_string($packagePath) && self::isMetaInfSidecarPackagePartName($packagePath)) {
            return false;
        }
        if (is_string($packagePath) && self::isLinkedResourcePackagePartName($packagePath)) {
            return false;
        }
        if (is_string($packagePath) && self::isDatabasePackagePartName($packagePath)) {
            return false;
        }
        if (is_string($packagePath) && self::isVersionPackagePartName($packagePath)) {
            return false;
        }
        if (is_string($packagePath) && self::isGalleryPackagePartName($packagePath)) {
            return false;
        }
        if (is_string($packagePath) && self::isFormPackagePartName($packagePath)) {
            return false;
        }
        if (is_string($packagePath) && self::isReportPackagePartName($packagePath)) {
            return false;
        }
        if (is_string($packagePath) && self::isAttachmentPackagePartName($packagePath)) {
            return false;
        }
        if (is_string($packagePath) && self::isTemplatePackagePartName($packagePath)) {
            return false;
        }
        if (is_string($packagePath) && self::isDictionaryPackagePartName($packagePath)) {
            return false;
        }
        if (is_string($packagePath) && self::isEventPackagePartName($packagePath)) {
            return false;
        }

        $mediaTypeBase = (string) ($entry['mediaTypeBase'] ?? $entry['mediaType'] ?? '');
        if (
            str_starts_with($mediaTypeBase, 'image/')
            || str_starts_with($mediaTypeBase, 'audio/')
            || str_starts_with($mediaTypeBase, 'video/')
        ) {
            return true;
        }
        if ($mediaTypeBase === 'application/octet-stream' && self::mediaResourceFamilyFromPackagePart($packagePath) !== null) {
            return true;
        }

        return str_starts_with((string) ($entry['path'] ?? ''), 'Pictures/');
    }

    /**
     * @param list<array<string, mixed>> $entries
     * @return list<array<string, mixed>>
     */
    private static function withPackageEntryMetadata(array $entries, ZipPackage $package): array
    {
        $hydrated = [];
        $modificationTimeByName = self::zipModificationTimeEntriesByName($package->modificationTimePreflight());
        $objectPackageRootParts = self::embeddedObjectPackageRootParts($entries);
        foreach ($entries as $entry) {
            $isRoot = $entry['path'] === '/';
            $packagePath = $entry['packagePath'];
            $isDirectory = is_string($packagePath) && str_ends_with($packagePath, '/');
            $embeddedObjectPackage = is_string($packagePath)
                ? self::embeddedObjectPackageMembership($packagePath, $objectPackageRootParts)
                : null;
            $embeddedObjectPackagePart = is_array($embeddedObjectPackage);
            $scriptPackagePart = is_string($packagePath) && self::isScriptPackagePartName($packagePath);
            $thumbnailPackagePart = is_string($packagePath) && self::isThumbnailPackagePartName($packagePath);
            $signaturePackagePart = is_string($packagePath) && self::isSignaturePackagePartName($packagePath);
            $configurationPackagePart = is_string($packagePath) && self::isConfigurationPackagePartName($packagePath);
            $fontPackagePart = is_string($packagePath) && self::isFontPackagePart($packagePath, (string) ($entry['mediaType'] ?? ''));
            $rdfMetadataPart = is_string($packagePath) && self::isRdfMetadataPart($packagePath, (string) ($entry['mediaType'] ?? ''));
            $objectReplacementPackagePart = is_string($packagePath) && self::isObjectReplacementPackagePartName($packagePath);
            $layoutCachePackagePart = is_string($packagePath) && self::isLayoutCachePackagePartName($packagePath);
            $metaInfSidecarPackagePart = is_string($packagePath) && self::isMetaInfSidecarPackagePartName($packagePath);
            $linkedResourcePackagePart = is_string($packagePath) && self::isLinkedResourcePackagePartName($packagePath);
            $databasePackagePart = is_string($packagePath) && self::isDatabasePackagePartName($packagePath);
            $versionPackagePart = is_string($packagePath) && self::isVersionPackagePartName($packagePath);
            $galleryPackagePart = is_string($packagePath) && self::isGalleryPackagePartName($packagePath);
            $formPackagePart = is_string($packagePath) && self::isFormPackagePartName($packagePath);
            $reportPackagePart = is_string($packagePath) && self::isReportPackagePartName($packagePath);
            $attachmentPackagePart = is_string($packagePath) && self::isAttachmentPackagePartName($packagePath);
            $templatePackagePart = is_string($packagePath) && self::isTemplatePackagePartName($packagePath);
            $dictionaryPackagePart = is_string($packagePath) && self::isDictionaryPackagePartName($packagePath);
            $eventPackagePart = is_string($packagePath) && self::isEventPackagePartName($packagePath);
            $zipEntry = (!$isRoot && is_string($packagePath) && $package->has($packagePath))
                ? $package->entry($packagePath)
                : null;
            $timestampProvenance = $zipEntry instanceof ZipPackageEntry
                ? self::zipTimestampProvenance($modificationTimeByName[$zipEntry->name] ?? null)
                : self::zipTimestampProvenance(null);
            $exists = $isRoot || $isDirectory || $zipEntry instanceof ZipPackageEntry;
            $encrypted = is_array($entry['encryption']);
            $missingMediaType = ($entry['missingMediaType'] ?? false) === true;
            $storedByteLength = $zipEntry instanceof ZipPackageEntry ? $zipEntry->uncompressedSize : null;
            $compressionMethod = $zipEntry instanceof ZipPackageEntry ? $zipEntry->compressionMethod : null;
            $hasSupportedCompression = $compressionMethod === null || $compressionMethod === 0 || $compressionMethod === 8;
            $canExposeBytes = !$isRoot
                && $exists
                && !$isDirectory
                && !$encrypted
                && !$embeddedObjectPackagePart
                && !$scriptPackagePart
                && !$thumbnailPackagePart
                && !$signaturePackagePart
                && !$configurationPackagePart
                && !$fontPackagePart
                && !$rdfMetadataPart
                && !$objectReplacementPackagePart
                && !$layoutCachePackagePart
                && !$metaInfSidecarPackagePart
                && !$linkedResourcePackagePart
                && !$databasePackagePart
                && !$versionPackagePart
                && !$galleryPackagePart
                && !$formPackagePart
                && !$reportPackagePart
                && !$attachmentPackagePart
                && !$templatePackagePart
                && !$dictionaryPackagePart
                && !$eventPackagePart
                && !$missingMediaType
                && $hasSupportedCompression;
            $declaredSize = is_int($entry['size'] ?? null) ? $entry['size'] : null;
            $declaredSizeMismatch = $declaredSize !== null
                && $storedByteLength !== null
                && !$isDirectory
                && $declaredSize !== $storedByteLength;
            $diagnostics = is_array($entry['diagnostics'] ?? null) ? array_values($entry['diagnostics']) : [];

            if (($entry['declaredSizeInvalid'] ?? false) === true) {
                $diagnostics[] = 'odf-manifest-invalid-declared-size';
            }
            if (!$exists) {
                $diagnostics[] = 'odf-manifest-missing-package-part';
            }
            if ($isDirectory) {
                $diagnostics[] = 'odf-manifest-directory-entry';
            }
            if ($encrypted) {
                $diagnostics[] = 'odf-manifest-encrypted-package-part';
            }
            if ($declaredSizeMismatch) {
                $diagnostics[] = 'odf-manifest-declared-size-mismatch';
            }
            if (!$hasSupportedCompression) {
                $diagnostics[] = 'odf-manifest-unsupported-compression-method';
            }
            $manifestMediaFamily = self::manifestMediaFamily(
                $entry,
                $isRoot,
                $isDirectory,
                $embeddedObjectPackage,
                $scriptPackagePart,
                $configurationPackagePart,
                $fontPackagePart,
                $rdfMetadataPart,
                $objectReplacementPackagePart,
                $layoutCachePackagePart,
                $metaInfSidecarPackagePart,
                $linkedResourcePackagePart,
                $databasePackagePart,
                $versionPackagePart,
                $galleryPackagePart,
                $formPackagePart,
                $reportPackagePart,
                $attachmentPackagePart,
                $templatePackagePart,
                $dictionaryPackagePart,
                $eventPackagePart
            );

            $hydrated[] = array_merge($entry, [
                'exists' => $exists,
                'isDirectory' => $isDirectory,
                'manifestMediaFamily' => $manifestMediaFamily,
                'byteLength' => $canExposeBytes && $zipEntry instanceof ZipPackageEntry ? $zipEntry->uncompressedSize : null,
                'storedByteLength' => $storedByteLength,
                'compressedByteLength' => $zipEntry instanceof ZipPackageEntry ? $zipEntry->compressedSize : null,
                'compressionMethod' => $compressionMethod,
                'compressionMethodName' => $compressionMethod !== null ? self::compressionMethodName($compressionMethod) : null,
                'crc32' => $canExposeBytes && $zipEntry instanceof ZipPackageEntry ? $zipEntry->crc32Hex() : null,
                'storedCrc32' => $zipEntry instanceof ZipPackageEntry ? $zipEntry->crc32Hex() : null,
                'byteSha256' => self::packageEntryByteSha256($package, $packagePath, $canExposeBytes),
                'declaredSize' => $declaredSize,
                'declaredSizeRaw' => $entry['declaredSizeRaw'] ?? null,
                'declaredSizeValid' => ($entry['declaredSizeValid'] ?? false) === true,
                'declaredSizeInvalid' => ($entry['declaredSizeInvalid'] ?? false) === true,
                'declaredSizeMismatch' => $declaredSizeMismatch,
                'embeddedObjectPackagePart' => $embeddedObjectPackagePart,
                'embeddedObjectRootPart' => is_array($embeddedObjectPackage) ? $embeddedObjectPackage['rootPart'] : null,
                'embeddedObjectPath' => is_array($embeddedObjectPackage) ? $embeddedObjectPackage['objectPath'] : null,
                'embeddedObjectType' => is_array($embeddedObjectPackage) ? $embeddedObjectPackage['objectType'] : null,
                'embeddedObjectRoot' => is_array($embeddedObjectPackage) && $embeddedObjectPackage['isRoot'] === true,
                'embeddedObjectContainedPart' => is_array($embeddedObjectPackage) && $embeddedObjectPackage['isRoot'] !== true,
                'embeddedObjectMediaType' => is_array($embeddedObjectPackage) ? $embeddedObjectPackage['mediaType'] : null,
                'scriptPackagePart' => $scriptPackagePart,
                'thumbnailPackagePart' => $thumbnailPackagePart,
                'signaturePackagePart' => $signaturePackagePart,
                'configurationPackagePart' => $configurationPackagePart,
                'fontPackagePart' => $fontPackagePart,
                'rdfMetadataPart' => $rdfMetadataPart,
                'objectReplacementPackagePart' => $objectReplacementPackagePart,
                'layoutCachePackagePart' => $layoutCachePackagePart,
                'metaInfSidecarPackagePart' => $metaInfSidecarPackagePart,
                'linkedResourcePackagePart' => $linkedResourcePackagePart,
                'databasePackagePart' => $databasePackagePart,
                'versionPackagePart' => $versionPackagePart,
                'galleryPackagePart' => $galleryPackagePart,
                'formPackagePart' => $formPackagePart,
                'reportPackagePart' => $reportPackagePart,
                'attachmentPackagePart' => $attachmentPackagePart,
                'templatePackagePart' => $templatePackagePart,
                'dictionaryPackagePart' => $dictionaryPackagePart,
                'eventPackagePart' => $eventPackagePart,
                'canExposeBytes' => $canExposeBytes,
                'byteExposurePolicy' => self::byteExposurePolicy(
                    $isRoot,
                    $exists,
                    $isDirectory,
                    $encrypted,
                    $embeddedObjectPackagePart,
                    $scriptPackagePart,
                    $thumbnailPackagePart,
                    $signaturePackagePart,
                    $configurationPackagePart,
                    $fontPackagePart,
                    $rdfMetadataPart,
                    $objectReplacementPackagePart,
                    $layoutCachePackagePart,
                    $metaInfSidecarPackagePart,
                    $linkedResourcePackagePart,
                    $databasePackagePart,
                    $versionPackagePart,
                    $galleryPackagePart,
                    $formPackagePart,
                    $reportPackagePart,
                    $attachmentPackagePart,
                    $templatePackagePart,
                    $dictionaryPackagePart,
                    $eventPackagePart,
                    $missingMediaType,
                    $hasSupportedCompression
                ),
                'diagnostics' => $diagnostics,
            ], $timestampProvenance);
        }

        return $hydrated;
    }

    private static function byteExposurePolicy(
        bool $isRoot,
        bool $exists,
        bool $isDirectory,
        bool $encrypted,
        bool $embeddedObjectPackagePart,
        bool $scriptPackagePart,
        bool $thumbnailPackagePart,
        bool $signaturePackagePart,
        bool $configurationPackagePart,
        bool $fontPackagePart,
        bool $rdfMetadataPart,
        bool $objectReplacementPackagePart,
        bool $layoutCachePackagePart,
        bool $metaInfSidecarPackagePart,
        bool $linkedResourcePackagePart,
        bool $databasePackagePart,
        bool $versionPackagePart,
        bool $galleryPackagePart,
        bool $formPackagePart,
        bool $reportPackagePart,
        bool $attachmentPackagePart,
        bool $templatePackagePart,
        bool $dictionaryPackagePart,
        bool $eventPackagePart,
        bool $missingMediaType,
        bool $hasSupportedCompression
    ): string {
        if ($isRoot) {
            return 'package-root-no-bytes';
        }
        if ($isDirectory) {
            return 'directory-entry-no-bytes';
        }
        if ($encrypted) {
            return 'encrypted-resource-bytes-blocked';
        }
        if ($embeddedObjectPackagePart) {
            return 'embedded-object-package-bytes-blocked';
        }
        if ($scriptPackagePart) {
            return 'script-package-bytes-blocked';
        }
        if ($thumbnailPackagePart) {
            return 'package-thumbnail-bytes-blocked';
        }
        if ($signaturePackagePart) {
            return 'signature-package-bytes-blocked';
        }
        if ($configurationPackagePart) {
            return 'configuration-package-bytes-blocked';
        }
        if ($fontPackagePart) {
            return 'font-package-bytes-blocked';
        }
        if ($rdfMetadataPart) {
            return 'rdf-metadata-bytes-blocked';
        }
        if ($objectReplacementPackagePart) {
            return 'object-replacement-package-bytes-blocked';
        }
        if ($layoutCachePackagePart) {
            return 'layout-cache-package-bytes-blocked';
        }
        if ($metaInfSidecarPackagePart) {
            return 'meta-inf-sidecar-package-bytes-blocked';
        }
        if ($linkedResourcePackagePart) {
            return 'linked-resource-package-bytes-blocked';
        }
        if ($databasePackagePart) {
            return 'database-package-bytes-blocked';
        }
        if ($versionPackagePart) {
            return 'version-package-bytes-blocked';
        }
        if ($galleryPackagePart) {
            return 'gallery-package-bytes-blocked';
        }
        if ($formPackagePart) {
            return 'form-package-bytes-blocked';
        }
        if ($reportPackagePart) {
            return 'report-package-bytes-blocked';
        }
        if ($attachmentPackagePart) {
            return 'attachment-package-bytes-blocked';
        }
        if ($templatePackagePart) {
            return 'template-package-bytes-blocked';
        }
        if ($dictionaryPackagePart) {
            return 'dictionary-package-bytes-blocked';
        }
        if ($eventPackagePart) {
            return 'event-package-bytes-blocked';
        }
        if ($missingMediaType) {
            return 'missing-media-type-bytes-blocked';
        }
        if (!$hasSupportedCompression) {
            return 'unsupported-compression-bytes-blocked';
        }

        return $exists ? 'package-bytes-exposable' : 'missing-package-part';
    }

    /**
     * @param array<string, mixed> $entry
     * @param array<string, mixed>|null $embeddedObjectPackage
     */
    private static function manifestMediaFamily(
        array $entry,
        bool $isRoot,
        bool $isDirectory,
        ?array $embeddedObjectPackage,
        bool $scriptPackagePart,
        bool $configurationPackagePart,
        bool $fontPackagePart,
        bool $rdfMetadataPart,
        bool $objectReplacementPackagePart,
        bool $layoutCachePackagePart,
        bool $metaInfSidecarPackagePart,
        bool $linkedResourcePackagePart,
        bool $databasePackagePart,
        bool $versionPackagePart,
        bool $galleryPackagePart,
        bool $formPackagePart,
        bool $reportPackagePart,
        bool $attachmentPackagePart,
        bool $templatePackagePart,
        bool $dictionaryPackagePart,
        bool $eventPackagePart
    ): string {
        if ($isRoot) {
            return 'opendocument-text-package';
        }

        $packagePath = is_string($entry['packagePath'] ?? null) ? $entry['packagePath'] : '';
        $mediaTypeBase = strtolower(trim((string) ($entry['mediaTypeBase'] ?? '')));

        if ($isDirectory && is_array($embeddedObjectPackage) && ($embeddedObjectPackage['isRoot'] ?? false) === true) {
            return 'opendocument-object-package';
        }
        if ($isDirectory) {
            return 'directory';
        }
        if ($scriptPackagePart) {
            return 'script';
        }
        if ($configurationPackagePart) {
            return 'configuration';
        }
        if (self::isThumbnailPackagePartName($packagePath)) {
            return 'thumbnail';
        }
        if (self::isSignaturePackagePartName($packagePath)) {
            return 'signature';
        }
        if ($objectReplacementPackagePart) {
            return 'object-replacement';
        }
        if ($layoutCachePackagePart) {
            return 'layout-cache';
        }
        if ($metaInfSidecarPackagePart) {
            return 'meta-inf-sidecar';
        }
        if ($linkedResourcePackagePart) {
            return 'linked-resource';
        }
        if ($databasePackagePart) {
            return 'database';
        }
        if ($versionPackagePart) {
            return 'version-history';
        }
        if ($galleryPackagePart) {
            return 'gallery';
        }
        if ($formPackagePart) {
            return 'form';
        }
        if ($reportPackagePart) {
            return 'report';
        }
        if ($attachmentPackagePart) {
            return 'attachment';
        }
        if ($templatePackagePart) {
            return 'template';
        }
        if ($dictionaryPackagePart) {
            return 'dictionary';
        }
        if ($eventPackagePart) {
            return 'event';
        }
        if ($fontPackagePart || ($mediaTypeBase !== '' && self::isFontMediaType($mediaTypeBase))) {
            return 'font';
        }
        if ($rdfMetadataPart || $mediaTypeBase === 'application/rdf+xml') {
            return 'rdf';
        }
        if (is_array($embeddedObjectPackage) && ($embeddedObjectPackage['isRoot'] ?? false) === true) {
            return 'opendocument-object-package';
        }
        if ($mediaTypeBase !== '' && self::isEmbeddedObjectPackageMediaType($mediaTypeBase)) {
            return 'opendocument-object-package';
        }

        $mediaResourceFamily = self::mediaResourceFamilyFromMediaTypeBase($mediaTypeBase);
        if ($mediaResourceFamily !== null) {
            return $mediaResourceFamily;
        }
        $packageMediaResourceFamily = self::mediaResourceFamilyFromPackagePart($packagePath);
        if ($mediaTypeBase === 'application/octet-stream' && $packageMediaResourceFamily !== null) {
            return $packageMediaResourceFamily;
        }
        if (self::isXmlMediaTypeBase($mediaTypeBase)) {
            return 'xml';
        }
        if (($entry['missingMediaType'] ?? false) === true || $mediaTypeBase === '') {
            return 'missing-media-type';
        }
        if ($mediaTypeBase === 'application/octet-stream' || str_starts_with($mediaTypeBase, 'application/vnd.')) {
            return 'binary';
        }

        return 'other';
    }

    private static function packageEntryByteSha256(ZipPackage $package, ?string $packagePath, bool $canExposeBytes): ?string
    {
        if (!$canExposeBytes || $packagePath === null || $packagePath === '' || str_ends_with($packagePath, '/')) {
            return null;
        }

        return hash('sha256', $package->read($packagePath));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function undeclaredPackageEntries(): array
    {
        $specialPackageParts = [
            'mimetype' => true,
            'META-INF/manifest.xml' => true,
        ];
        $entries = [];

        foreach ($this->package->entries() as $entry) {
            $path = $entry->name;
            if (isset($this->manifestEntriesByPath[$path]) || isset($specialPackageParts[$path])) {
                continue;
            }

            $entries[] = [
                'path' => $path,
                'isDirectory' => str_ends_with($path, '/'),
                'storedByteLength' => $entry->uncompressedSize,
                'compressedByteLength' => $entry->compressedSize,
                'compressionMethod' => $entry->compressionMethod,
                'compressionMethodName' => self::compressionMethodName($entry->compressionMethod),
                'crc32' => $entry->crc32Hex(),
                'byteSha256' => null,
                'scriptPackagePart' => self::isScriptPackagePartName($path),
                'configurationPackagePart' => self::isConfigurationPackagePartName($path),
                'thumbnailPackagePart' => self::isThumbnailPackagePartName($path),
                'signaturePackagePart' => self::isSignaturePackagePartName($path),
                'fontPackagePart' => self::isFontPackagePartName($path),
                'rdfMetadataPart' => self::isRdfPartName($path),
                'objectReplacementPackagePart' => self::isObjectReplacementPackagePartName($path),
                'layoutCachePackagePart' => self::isLayoutCachePackagePartName($path),
                'metaInfSidecarPackagePart' => self::isMetaInfSidecarPackagePartName($path),
                'linkedResourcePackagePart' => self::isLinkedResourcePackagePartName($path),
                'databasePackagePart' => self::isDatabasePackagePartName($path),
                'versionPackagePart' => self::isVersionPackagePartName($path),
                'galleryPackagePart' => self::isGalleryPackagePartName($path),
                'formPackagePart' => self::isFormPackagePartName($path),
                'reportPackagePart' => self::isReportPackagePartName($path),
                'attachmentPackagePart' => self::isAttachmentPackagePartName($path),
                'templatePackagePart' => self::isTemplatePackagePartName($path),
                'dictionaryPackagePart' => self::isDictionaryPackagePartName($path),
                'eventPackagePart' => self::isEventPackagePartName($path),
                'canExposeBytes' => false,
                'byteExposurePolicy' => self::undeclaredPackageEntryByteExposurePolicy($path),
                'diagnostics' => ['odf-manifest-undeclared-package-entry'],
            ];
        }

        return $entries;
    }

    private static function undeclaredPackageEntryByteExposurePolicy(string $path): string
    {
        if (str_ends_with($path, '/')) {
            return 'directory-entry-no-bytes';
        }
        if (self::isScriptPackagePartName($path)) {
            return 'script-package-bytes-blocked';
        }
        if (self::isConfigurationPackagePartName($path)) {
            return 'configuration-package-bytes-blocked';
        }
        if (self::isThumbnailPackagePartName($path)) {
            return 'package-thumbnail-bytes-blocked';
        }
        if (self::isSignaturePackagePartName($path)) {
            return 'signature-package-bytes-blocked';
        }
        if (self::isFontPackagePartName($path)) {
            return 'font-package-bytes-blocked';
        }
        if (self::isRdfPartName($path)) {
            return 'rdf-metadata-bytes-blocked';
        }
        if (self::isObjectReplacementPackagePartName($path)) {
            return 'object-replacement-package-bytes-blocked';
        }
        if (self::isLayoutCachePackagePartName($path)) {
            return 'layout-cache-package-bytes-blocked';
        }
        if (self::isMetaInfSidecarPackagePartName($path)) {
            return 'meta-inf-sidecar-package-bytes-blocked';
        }
        if (self::isLinkedResourcePackagePartName($path)) {
            return 'linked-resource-package-bytes-blocked';
        }
        if (self::isDatabasePackagePartName($path)) {
            return 'database-package-bytes-blocked';
        }
        if (self::isVersionPackagePartName($path)) {
            return 'version-package-bytes-blocked';
        }
        if (self::isGalleryPackagePartName($path)) {
            return 'gallery-package-bytes-blocked';
        }
        if (self::isFormPackagePartName($path)) {
            return 'form-package-bytes-blocked';
        }
        if (self::isReportPackagePartName($path)) {
            return 'report-package-bytes-blocked';
        }
        if (self::isAttachmentPackagePartName($path)) {
            return 'attachment-package-bytes-blocked';
        }
        if (self::isTemplatePackagePartName($path)) {
            return 'template-package-bytes-blocked';
        }
        if (self::isDictionaryPackagePartName($path)) {
            return 'dictionary-package-bytes-blocked';
        }
        if (self::isEventPackagePartName($path)) {
            return 'event-package-bytes-blocked';
        }

        return 'undeclared-package-entry-no-bytes';
    }

    /**
     * @param list<array<string, mixed>> $manifestEntries
     * @return array<string, mixed>
     */
    private static function readRdfMetadata(ZipPackage $package, array $manifestEntries): array
    {
        $candidatesByPart = [];
        foreach ($manifestEntries as $entry) {
            if (!self::isRdfManifestEntry($entry)) {
                continue;
            }

            $packagePath = $entry['packagePath'] ?? null;
            if (is_string($packagePath) && $packagePath !== '' && !str_ends_with($packagePath, '/')) {
                $entry['declared'] = true;
                $candidatesByPart[$packagePath] = $entry;
            }
        }

        $undeclaredCandidates = [];
        foreach ($package->entries() as $entry) {
            if ($entry->isDirectory() || !self::isRdfPartName($entry->name) || isset($candidatesByPart[$entry->name])) {
                continue;
            }

            $undeclaredCandidates[$entry->name] = [
                'path' => $entry->name,
                'packagePath' => $entry->name,
                'pathReference' => $entry->name,
                'mediaType' => null,
                'exists' => true,
                'encrypted' => false,
                'declared' => false,
            ];
        }
        ksort($undeclaredCandidates, SORT_STRING);
        $candidatesByPart += $undeclaredCandidates;

        $parts = [];
        $subjectsBySubject = [];
        $parsedPartCount = 0;
        $parseErrorCount = 0;
        $tripleCount = 0;
        $literalCount = 0;
        $resourceCount = 0;

        foreach ($candidatesByPart as $part => $entry) {
            $zipEntry = $package->has($part) ? $package->entry($part) : null;
            $encrypted = ($entry['encrypted'] ?? false) === true;
            $declared = ($entry['declared'] ?? false) === true;
            $partMetadata = [
                'fullPath' => $entry['path'] ?? $part,
                'path' => $entry['path'] ?? $part,
                'packagePath' => $part,
                'part' => $part,
                'pathReference' => $entry['pathReference'] ?? null,
                'pathSuffix' => $entry['pathSuffix'] ?? null,
                'pathQuery' => $entry['pathQuery'] ?? null,
                'pathFragment' => $entry['pathFragment'] ?? null,
                'mediaType' => $entry['mediaType'] ?? null,
                'mediaTypeBase' => $entry['mediaTypeBase'] ?? null,
                'mediaTypeHasParameters' => ($entry['mediaTypeHasParameters'] ?? false) === true,
                'mediaTypeParameterCount' => $entry['mediaTypeParameterCount'] ?? 0,
                'mediaTypeParameters' => $entry['mediaTypeParameters'] ?? [],
                'mediaTypeParameterMap' => $entry['mediaTypeParameterMap'] ?? [],
                'exists' => $zipEntry instanceof ZipPackageEntry,
                'declared' => $declared,
                'undeclared' => !$declared,
                'encrypted' => $encrypted,
                'canExposeAsDocumentMedia' => false,
                'reviewPolicy' => 'package-rdf-metadata-only',
                'byteLength' => !$encrypted && $zipEntry instanceof ZipPackageEntry ? $zipEntry->uncompressedSize : null,
                'compressedByteLength' => $zipEntry instanceof ZipPackageEntry ? $zipEntry->compressedSize : null,
                'compressionMethod' => $zipEntry instanceof ZipPackageEntry ? $zipEntry->compressionMethod : null,
                'compressionMethodName' => $zipEntry instanceof ZipPackageEntry ? self::compressionMethodName($zipEntry->compressionMethod) : null,
                'crc32' => !$encrypted && $zipEntry instanceof ZipPackageEntry ? $zipEntry->crc32Hex() : null,
                'storedByteLength' => $zipEntry instanceof ZipPackageEntry ? $zipEntry->uncompressedSize : null,
                'storedCrc32' => $zipEntry instanceof ZipPackageEntry ? $zipEntry->crc32Hex() : null,
                'tripleCount' => 0,
                'literalCount' => 0,
                'resourceCount' => 0,
                'subjectCount' => 0,
                'subjects' => [],
                'triples' => [],
            ];

            if (!$zipEntry instanceof ZipPackageEntry) {
                $partMetadata['parseable'] = false;
                $partMetadata['diagnostic'] = 'missing-rdf-part';
                $parts[] = $partMetadata;
                continue;
            }

            if ($encrypted) {
                $partMetadata['parseable'] = false;
                $partMetadata['diagnostic'] = 'encrypted-rdf-part';
                $parts[] = $partMetadata;
                continue;
            }

            try {
                $parsed = self::parseRdfMetadataPart($package->read($part, 1048576), $part);
            } catch (\InvalidArgumentException $exception) {
                $partMetadata['parseable'] = false;
                $partMetadata['diagnostic'] = 'invalid-rdf-xml';
                $partMetadata['error'] = $exception->getMessage();
                ++$parseErrorCount;
                $parts[] = $partMetadata;
                continue;
            }

            $partMetadata = array_merge($partMetadata, $parsed, ['parseable' => true]);
            if (!$declared) {
                $partMetadata['diagnostic'] = 'odf-rdf-package-undeclared-part';
            }
            ++$parsedPartCount;
            $tripleCount += (int) ($parsed['tripleCount'] ?? 0);
            $literalCount += (int) ($parsed['literalCount'] ?? 0);
            $resourceCount += (int) ($parsed['resourceCount'] ?? 0);

            foreach ($parsed['subjectsBySubject'] ?? [] as $subject => $subjectMetadata) {
                if (!is_string($subject) || !is_array($subjectMetadata)) {
                    continue;
                }

                if (!isset($subjectsBySubject[$subject])) {
                    $subjectsBySubject[$subject] = [
                        'subject' => $subject,
                        'partCount' => 0,
                        'tripleCount' => 0,
                        'literalCount' => 0,
                        'resourceCount' => 0,
                        'parts' => [],
                        'predicates' => [],
                    ];
                }

                ++$subjectsBySubject[$subject]['partCount'];
                $subjectsBySubject[$subject]['tripleCount'] += (int) ($subjectMetadata['tripleCount'] ?? 0);
                $subjectsBySubject[$subject]['literalCount'] += (int) ($subjectMetadata['literalCount'] ?? 0);
                $subjectsBySubject[$subject]['resourceCount'] += (int) ($subjectMetadata['resourceCount'] ?? 0);
                $subjectsBySubject[$subject]['parts'][] = $part;
                foreach ($subjectMetadata['predicates'] ?? [] as $predicate) {
                    if (is_string($predicate) && $predicate !== '' && !in_array($predicate, $subjectsBySubject[$subject]['predicates'], true)) {
                        $subjectsBySubject[$subject]['predicates'][] = $predicate;
                    }
                }
            }

            $parts[] = $partMetadata;
        }

        foreach ($subjectsBySubject as &$subjectMetadata) {
            $subjectMetadata['parts'] = array_values(array_unique($subjectMetadata['parts']));
            sort($subjectMetadata['predicates'], SORT_STRING);
        }
        unset($subjectMetadata);

        return [
            'count' => count($parts),
            'partCount' => count($parts),
            'parsedPartCount' => $parsedPartCount,
            'parseErrorCount' => $parseErrorCount,
            'tripleCount' => $tripleCount,
            'literalCount' => $literalCount,
            'resourceCount' => $resourceCount,
            'subjectCount' => count($subjectsBySubject),
            'parts' => $parts,
            'subjectsBySubject' => $subjectsBySubject,
        ];
    }

    /**
     * @param array<string, mixed> $entry
     */
    private static function isRdfManifestEntry(array $entry): bool
    {
        $mediaTypeBase = strtolower((string) ($entry['mediaTypeBase'] ?? self::mediaTypeReport((string) ($entry['mediaType'] ?? ''))['mediaTypeBase']));
        $packagePath = (string) ($entry['packagePath'] ?? '');

        return $mediaTypeBase === 'application/rdf+xml' || self::isRdfPartName($packagePath);
    }

    /**
     * @return array<string, mixed>
     */
    private static function parseRdfMetadataPart(string $xml, string $part): array
    {
        $dom = self::loadXml($xml, 'ODT RDF metadata ' . $part);
        $root = $dom->documentElement;
        if (!$root instanceof \DOMElement || !self::isElement($root, self::RDF_NAMESPACE, 'RDF')) {
            throw new \InvalidArgumentException('ODT RDF metadata ' . $part . ' must use rdf:RDF as its root element');
        }

        $triples = [];
        $subjectsBySubject = [];
        foreach (self::childElements($root) as $descriptionIndex => $description) {
            $subject = self::rdfSubject($description, $part, $descriptionIndex);
            $descriptionTriples = self::rdfDescriptionTriples($description, $subject);
            if (!self::isElement($description, self::RDF_NAMESPACE, 'Description')) {
                array_unshift($descriptionTriples, [
                    'subject' => $subject,
                    'predicate' => 'rdf:type',
                    'predicateNamespace' => self::RDF_NAMESPACE,
                    'predicateName' => 'type',
                    'objectType' => 'resource',
                    'object' => self::rdfExpandedName($description),
                ]);
            }

            foreach ($descriptionTriples as $triple) {
                if ($triple === []) {
                    continue;
                }

                $triples[] = $triple;
                if (!isset($subjectsBySubject[$subject])) {
                    $subjectsBySubject[$subject] = [
                        'subject' => $subject,
                        'tripleCount' => 0,
                        'literalCount' => 0,
                        'resourceCount' => 0,
                        'predicates' => [],
                    ];
                }

                ++$subjectsBySubject[$subject]['tripleCount'];
                if (($triple['objectType'] ?? '') === 'resource') {
                    ++$subjectsBySubject[$subject]['resourceCount'];
                } else {
                    ++$subjectsBySubject[$subject]['literalCount'];
                }
                $predicate = (string) ($triple['predicate'] ?? '');
                if ($predicate !== '' && !in_array($predicate, $subjectsBySubject[$subject]['predicates'], true)) {
                    $subjectsBySubject[$subject]['predicates'][] = $predicate;
                }
            }
        }

        foreach ($subjectsBySubject as &$subjectMetadata) {
            sort($subjectMetadata['predicates'], SORT_STRING);
        }
        unset($subjectMetadata);

        $literalCount = 0;
        $resourceCount = 0;
        foreach ($triples as $triple) {
            if (($triple['objectType'] ?? '') === 'resource') {
                ++$resourceCount;
            } else {
                ++$literalCount;
            }
        }

        return [
            'tripleCount' => count($triples),
            'literalCount' => $literalCount,
            'resourceCount' => $resourceCount,
            'subjectCount' => count($subjectsBySubject),
            'subjects' => array_values($subjectsBySubject),
            'subjectsBySubject' => $subjectsBySubject,
            'triples' => $triples,
        ];
    }

    private static function rdfSubject(\DOMElement $description, string $part, int $index): string
    {
        foreach (['about', 'ID', 'nodeID'] as $name) {
            $value = self::attributeValue($description, self::RDF_NAMESPACE, $name);
            if ($value === '') {
                continue;
            }

            if ($name === 'ID') {
                return '#' . $value;
            }
            if ($name === 'nodeID') {
                return '_:' . $value;
            }

            return $value;
        }

        return '_:' . $part . '#' . ($index + 1);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function rdfDescriptionTriples(\DOMElement $description, string $subject): array
    {
        $triples = [];
        foreach ($description->attributes ?? [] as $attribute) {
            if (!$attribute instanceof \DOMAttr) {
                continue;
            }
            if ($attribute->namespaceURI === self::RDF_NAMESPACE || $attribute->namespaceURI === 'http://www.w3.org/2000/xmlns/') {
                continue;
            }

            $value = trim($attribute->value);
            if ($value === '') {
                continue;
            }

            $triples[] = [
                'subject' => $subject,
                'predicate' => self::rdfNodeName($attribute),
                'predicateNamespace' => $attribute->namespaceURI,
                'predicateName' => $attribute->localName,
                'objectType' => 'literal',
                'object' => $value,
            ];
        }

        foreach (self::childElements($description) as $property) {
            $triple = self::rdfPropertyTriple($property, $subject);
            if ($triple !== []) {
                $triples[] = $triple;
            }
        }

        return $triples;
    }

    /**
     * @return array<string, mixed>
     */
    private static function rdfPropertyTriple(\DOMElement $property, string $subject): array
    {
        $resource = self::attributeValue($property, self::RDF_NAMESPACE, 'resource');
        $nodeId = self::attributeValue($property, self::RDF_NAMESPACE, 'nodeID');
        $language = self::attributeValue($property, self::XML_NAMESPACE, 'lang');
        $datatype = self::attributeValue($property, self::RDF_NAMESPACE, 'datatype');
        $parseType = self::attributeValue($property, self::RDF_NAMESPACE, 'parseType');

        $metadata = [
            'subject' => $subject,
            'predicate' => self::rdfNodeName($property),
            'predicateNamespace' => $property->namespaceURI,
            'predicateName' => $property->localName,
        ];

        if ($resource !== '') {
            $metadata['objectType'] = 'resource';
            $metadata['object'] = $resource;
        } elseif ($nodeId !== '') {
            $metadata['objectType'] = 'resource';
            $metadata['object'] = '_:' . $nodeId;
        } else {
            $metadata['objectType'] = 'literal';
            $metadata['object'] = self::normalizedText($property);
        }

        if ($language !== '') {
            $metadata['language'] = $language;
        }
        if ($datatype !== '') {
            $metadata['datatype'] = $datatype;
        }
        if ($parseType !== '') {
            $metadata['parseType'] = $parseType;
        }

        return self::withoutEmptyValues($metadata);
    }

    private static function rdfNodeName(\DOMNode $node): string
    {
        $prefix = $node->prefix;
        if (is_string($prefix) && $prefix !== '') {
            return $prefix . ':' . $node->localName;
        }

        return $node->localName ?? $node->nodeName;
    }

    private static function rdfExpandedName(\DOMElement $element): string
    {
        $namespace = $element->namespaceURI;
        if (is_string($namespace) && $namespace !== '') {
            return $namespace . $element->localName;
        }

        return $element->localName;
    }

    /**
     * @param list<array<string, mixed>> $manifestEntries
     * @param list<array<string, mixed>> $undeclaredPackageEntries
     * @return array{count:int, readableCount:int, declaredCount:int, undeclaredCount:int, missingCount:int, encryptedCount:int, invalidMediaTypeCount:int, issueCount:int, issueCodes:list<string>, items:list<array<string, mixed>>}
     */
    private static function packageFontMetadata(ZipPackage $package, array $manifestEntries, array $undeclaredPackageEntries): array
    {
        $candidatesByPath = [];
        foreach ($manifestEntries as $entry) {
            $packagePath = $entry['packagePath'] ?? null;
            if (!is_string($packagePath) || $packagePath === '' || !self::isFontPackagePart($packagePath, (string) ($entry['mediaType'] ?? ''))) {
                continue;
            }

            $entry['declared'] = true;
            $candidatesByPath[$packagePath] = $entry;
        }

        foreach ($undeclaredPackageEntries as $entry) {
            $packagePath = $entry['path'] ?? null;
            if (!is_string($packagePath) || $packagePath === '' || !self::isFontPackagePartName($packagePath)) {
                continue;
            }

            $mediaType = self::fontMediaTypeFromPart($packagePath);
            $mediaTypeReport = self::mediaTypeReport($mediaType ?? '');
            $candidatesByPath[$packagePath] = [
                'path' => $packagePath,
                'packagePath' => $packagePath,
                'pathReference' => $packagePath,
                'pathSuffix' => null,
                'pathQuery' => null,
                'pathFragment' => null,
                'mediaType' => $mediaType ?? '',
                'mediaTypeBase' => $mediaTypeReport['mediaTypeBase'],
                'mediaTypeHasParameters' => false,
                'mediaTypeParameterCount' => 0,
                'mediaTypeParameters' => [],
                'mediaTypeParameterMap' => [],
                'fontFormatSourceHint' => 'package-extension',
                'exists' => true,
                'encrypted' => false,
                'declared' => false,
                'declaredSize' => null,
                'declaredSizeMismatch' => false,
                'byteExposurePolicy' => $entry['byteExposurePolicy'] ?? 'font-package-bytes-blocked',
            ];
        }

        ksort($candidatesByPath, SORT_STRING);

        $items = [];
        $issueCodes = [];
        $fontFormatCounts = [];
        $fontFormatSourceCounts = [];
        $fontFormatFamilyCounts = [];
        $fontFileExtensionCounts = [];
        foreach ($candidatesByPath as $packagePath => $entry) {
            $zipEntry = $package->has($packagePath) ? $package->entry($packagePath) : null;
            $encrypted = ($entry['encrypted'] ?? false) === true;
            $declared = ($entry['declared'] ?? false) === true;
            $rawMediaType = trim((string) ($entry['mediaType'] ?? ''));
            $mediaType = $rawMediaType;
            if ($mediaType === '') {
                $mediaType = self::fontMediaTypeFromPart($packagePath) ?? '';
            }

            $mediaTypeReport = self::mediaTypeReport($mediaType);
            $mediaTypeValid = $mediaType === '' || self::isFontMediaType($mediaType);
            $fontFormat = self::fontFormatProvenance(
                $packagePath,
                $mediaTypeReport['mediaTypeBase'],
                $rawMediaType !== '' && ($entry['fontFormatSourceHint'] ?? '') !== 'package-extension'
            );
            $issues = [];
            if (!$zipEntry instanceof ZipPackageEntry) {
                $issues[] = 'odf-font-missing-package-part';
            }
            if (!$declared) {
                $issues[] = 'odf-font-undeclared-package-part';
            }
            if ($encrypted) {
                $issues[] = 'odf-font-encrypted-package-part';
            }
            if (!$mediaTypeValid) {
                $issues[] = 'odf-font-invalid-media-type';
            }
            foreach ($issues as $issue) {
                $issueCodes[$issue] = true;
            }
            $byteExposurePolicy = is_string($entry['byteExposurePolicy'] ?? null) && $entry['byteExposurePolicy'] !== ''
                ? $entry['byteExposurePolicy']
                : ($encrypted ? 'encrypted-resource-bytes-blocked' : 'font-package-bytes-blocked');
            $fontFormatCounts[$fontFormat['fontFormat']] = ($fontFormatCounts[$fontFormat['fontFormat']] ?? 0) + 1;
            $fontFormatSourceCounts[$fontFormat['fontFormatSource']] = ($fontFormatSourceCounts[$fontFormat['fontFormatSource']] ?? 0) + 1;
            $fontFormatFamilyCounts[$fontFormat['fontFormatFamily']] = ($fontFormatFamilyCounts[$fontFormat['fontFormatFamily']] ?? 0) + 1;
            $extensionKey = $fontFormat['fontFileExtension'] ?? 'none';
            $fontFileExtensionCounts[$extensionKey] = ($fontFileExtensionCounts[$extensionKey] ?? 0) + 1;

            $items[] = [
                'fullPath' => $entry['path'] ?? $packagePath,
                'path' => $entry['path'] ?? $packagePath,
                'packagePath' => $packagePath,
                'part' => $packagePath,
                'pathReference' => $entry['pathReference'] ?? null,
                'pathSuffix' => $entry['pathSuffix'] ?? null,
                'pathQuery' => $entry['pathQuery'] ?? null,
                'pathFragment' => $entry['pathFragment'] ?? null,
                'mediaType' => $mediaType === '' ? null : $mediaType,
                'mediaTypeBase' => $mediaTypeReport['mediaTypeBase'],
                'mediaTypeHasParameters' => $mediaTypeReport['mediaTypeHasParameters'],
                'mediaTypeParameterCount' => $mediaTypeReport['mediaTypeParameterCount'],
                'mediaTypeParameters' => $mediaTypeReport['mediaTypeParameters'],
                'mediaTypeParameterMap' => $mediaTypeReport['mediaTypeParameterMap'],
                'fontFileExtension' => $fontFormat['fontFileExtension'],
                'fontFormat' => $fontFormat['fontFormat'],
                'fontFormatSource' => $fontFormat['fontFormatSource'],
                'fontFormatFamily' => $fontFormat['fontFormatFamily'],
                'recognizedFontFormat' => $fontFormat['recognizedFontFormat'],
                'expectedMediaTypeRole' => 'font',
                'exists' => $zipEntry instanceof ZipPackageEntry,
                'declared' => $declared,
                'undeclared' => !$declared,
                'encrypted' => $encrypted,
                'valid' => $zipEntry instanceof ZipPackageEntry && !$encrypted && $mediaTypeValid,
                'byteLength' => !$encrypted && $zipEntry instanceof ZipPackageEntry ? $zipEntry->uncompressedSize : null,
                'compressedByteLength' => $zipEntry instanceof ZipPackageEntry ? $zipEntry->compressedSize : null,
                'compressionMethod' => $zipEntry instanceof ZipPackageEntry ? $zipEntry->compressionMethod : null,
                'compressionMethodName' => $zipEntry instanceof ZipPackageEntry ? self::compressionMethodName($zipEntry->compressionMethod) : null,
                'crc32' => !$encrypted && $zipEntry instanceof ZipPackageEntry ? $zipEntry->crc32Hex() : null,
                'storedByteLength' => $zipEntry instanceof ZipPackageEntry ? $zipEntry->uncompressedSize : null,
                'storedCrc32' => $zipEntry instanceof ZipPackageEntry ? $zipEntry->crc32Hex() : null,
                'declaredSize' => $entry['declaredSize'] ?? $entry['size'] ?? null,
                'declaredSizeMismatch' => ($entry['declaredSizeMismatch'] ?? false) === true,
                'canExposeBytes' => false,
                'canExposeAsDocumentMedia' => false,
                'byteExposurePolicy' => $byteExposurePolicy,
                'reviewPolicy' => 'package-font-metadata-only',
                'issues' => $issues,
            ];
        }

        ksort($issueCodes, SORT_STRING);
        ksort($fontFormatCounts, SORT_STRING);
        ksort($fontFormatSourceCounts, SORT_STRING);
        ksort($fontFormatFamilyCounts, SORT_STRING);
        ksort($fontFileExtensionCounts, SORT_STRING);

        return [
            'count' => count($items),
            'readableCount' => count(array_filter(
                $items,
                static fn (array $item): bool => $item['exists'] === true && $item['byteLength'] !== null,
            )),
            'declaredCount' => count(array_filter($items, static fn (array $item): bool => $item['declared'] === true)),
            'undeclaredCount' => count(array_filter($items, static fn (array $item): bool => $item['undeclared'] === true)),
            'missingCount' => count(array_filter($items, static fn (array $item): bool => $item['exists'] !== true)),
            'encryptedCount' => count(array_filter($items, static fn (array $item): bool => $item['encrypted'] === true)),
            'invalidMediaTypeCount' => count(array_filter(
                $items,
                static fn (array $item): bool => $item['mediaType'] !== null
                    && !self::isFontMediaType((string) $item['mediaType']),
            )),
            'issueCount' => count(array_filter($items, static fn (array $item): bool => ($item['issues'] ?? []) !== [])),
            'issueCodes' => array_keys($issueCodes),
            'fontFormatCounts' => $fontFormatCounts,
            'fontFormatSourceCounts' => $fontFormatSourceCounts,
            'fontFormatFamilyCounts' => $fontFormatFamilyCounts,
            'fontFileExtensionCounts' => $fontFileExtensionCounts,
            'recognizedFontFormatCount' => count(array_filter($items, static fn (array $item): bool => $item['recognizedFontFormat'] === true)),
            'unknownFontFormatCount' => count(array_filter($items, static fn (array $item): bool => $item['recognizedFontFormat'] !== true)),
            'byteExposurePolicy' => 'font-package-bytes-blocked',
            'reviewPolicy' => 'package-font-metadata-only',
            'items' => $items,
        ];
    }

    private static function fontMediaTypeFromPart(string $path): ?string
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return match ($extension) {
            'eot' => 'application/vnd.ms-fontobject',
            'otf' => 'font/otf',
            'pfb', 'pfa' => 'application/x-font-type1',
            'ttc' => 'font/collection',
            'ttf' => 'font/ttf',
            'woff' => 'font/woff',
            'woff2' => 'font/woff2',
            default => null,
        };
    }

    /**
     * @return array{fontFileExtension:string|null, fontFormat:string, fontFormatSource:string, fontFormatFamily:string, recognizedFontFormat:bool}
     */
    private static function fontFormatProvenance(string $path, string $mediaTypeBase, bool $hasManifestMediaType): array
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $extension = $extension === '' ? null : $extension;
        $format = $hasManifestMediaType ? self::fontFormatFromMediaTypeBase($mediaTypeBase) : null;
        $source = $format === null ? null : 'media-type';

        if ($format === null) {
            $format = $extension === null ? null : self::fontFormatFromExtension($extension);
            $source = $format === null ? null : 'package-extension';
        }

        $format ??= 'unknown';

        return [
            'fontFileExtension' => $extension,
            'fontFormat' => $format,
            'fontFormatSource' => $source ?? 'unknown',
            'fontFormatFamily' => self::fontFormatFamily($format),
            'recognizedFontFormat' => $format !== 'unknown',
        ];
    }

    private static function fontFormatFromMediaTypeBase(string $mediaTypeBase): ?string
    {
        return match (strtolower($mediaTypeBase)) {
            'application/font-woff',
            'application/x-font-woff',
            'font/woff' => 'woff',
            'application/font-woff2',
            'application/x-font-woff2',
            'font/woff2' => 'woff2',
            'application/vnd.ms-fontobject' => 'embedded-opentype',
            'application/vnd.ms-opentype',
            'application/x-font-opentype',
            'application/x-font-otf',
            'application/x-opentype',
            'font/otf' => 'opentype',
            'application/x-font-ttf',
            'application/x-truetype-font',
            'font/ttf' => 'truetype',
            'font/collection' => 'truetype-collection',
            'application/x-font-type1' => 'type1',
            default => null,
        };
    }

    private static function fontFormatFromExtension(string $extension): ?string
    {
        return match (strtolower($extension)) {
            'eot' => 'embedded-opentype',
            'otf' => 'opentype',
            'pfa', 'pfb' => 'type1',
            'ttc' => 'truetype-collection',
            'ttf' => 'truetype',
            'woff' => 'woff',
            'woff2' => 'woff2',
            default => null,
        };
    }

    private static function fontFormatFamily(string $format): string
    {
        return match ($format) {
            'embedded-opentype' => 'legacy-webfont',
            'opentype', 'truetype', 'truetype-collection' => 'sfnt',
            'type1' => 'type1',
            'woff', 'woff2' => 'webfont',
            default => 'unknown',
        };
    }

    /**
     * @param list<array<string, mixed>> $manifestEntries
     * @param list<array<string, mixed>> $undeclaredPackageEntries
     * @return array{count:int, readableCount:int, declaredCount:int, undeclaredCount:int, missingCount:int, encryptedCount:int, invalidMediaTypeCount:int, issueCount:int, issueCodes:list<string>, items:list<array<string, mixed>>}
     */
    private static function packageThumbnailMetadata(ZipPackage $package, array $manifestEntries, array $undeclaredPackageEntries): array
    {
        $candidatesByPath = [];
        foreach ($manifestEntries as $entry) {
            $packagePath = $entry['packagePath'] ?? null;
            if (!is_string($packagePath) || $packagePath === '' || !self::isThumbnailPackagePartName($packagePath)) {
                continue;
            }

            $entry['declared'] = true;
            $candidatesByPath[$packagePath] = $entry;
        }

        foreach ($undeclaredPackageEntries as $entry) {
            $packagePath = $entry['path'] ?? null;
            if (!is_string($packagePath) || $packagePath === '' || !self::isThumbnailPackagePartName($packagePath)) {
                continue;
            }

            $mediaType = self::thumbnailMediaTypeFromPart($packagePath);
            $mediaTypeReport = self::mediaTypeReport($mediaType ?? '');
            $candidatesByPath[$packagePath] = [
                'path' => $packagePath,
                'packagePath' => $packagePath,
                'pathReference' => $packagePath,
                'pathSuffix' => null,
                'pathQuery' => null,
                'pathFragment' => null,
                'mediaType' => $mediaType ?? '',
                'mediaTypeBase' => $mediaTypeReport['mediaTypeBase'],
                'mediaTypeHasParameters' => false,
                'mediaTypeParameterCount' => 0,
                'mediaTypeParameters' => [],
                'mediaTypeParameterMap' => [],
                'exists' => true,
                'encrypted' => false,
                'declared' => false,
                'declaredSize' => null,
                'declaredSizeMismatch' => false,
            ];
        }

        ksort($candidatesByPath, SORT_STRING);

        $items = [];
        $issueCodes = [];
        foreach ($candidatesByPath as $packagePath => $entry) {
            $zipEntry = $package->has($packagePath) ? $package->entry($packagePath) : null;
            $encrypted = ($entry['encrypted'] ?? false) === true;
            $declared = ($entry['declared'] ?? false) === true;
            $mediaType = (string) ($entry['mediaType'] ?? '');
            if ($mediaType === '') {
                $mediaType = self::thumbnailMediaTypeFromPart($packagePath) ?? '';
            }

            $mediaTypeReport = self::mediaTypeReport($mediaType);
            $mediaTypeValid = str_starts_with($mediaTypeReport['mediaTypeBase'], 'image/');
            $issues = [];
            if (!$zipEntry instanceof ZipPackageEntry) {
                $issues[] = 'odf-thumbnail-missing-package-part';
            }
            if (!$declared) {
                $issues[] = 'odf-thumbnail-undeclared-package-part';
            }
            if ($encrypted) {
                $issues[] = 'odf-thumbnail-encrypted-package-part';
            }
            if (!$mediaTypeValid) {
                $issues[] = 'odf-thumbnail-invalid-media-type';
            }
            foreach ($issues as $issue) {
                $issueCodes[$issue] = true;
            }
            $byteExposurePolicy = $encrypted ? 'encrypted-resource-bytes-blocked' : 'package-thumbnail-bytes-blocked';

            $items[] = [
                'fullPath' => $entry['path'] ?? $packagePath,
                'path' => $entry['path'] ?? $packagePath,
                'packagePath' => $packagePath,
                'part' => $packagePath,
                'pathReference' => $entry['pathReference'] ?? null,
                'pathSuffix' => $entry['pathSuffix'] ?? null,
                'pathQuery' => $entry['pathQuery'] ?? null,
                'pathFragment' => $entry['pathFragment'] ?? null,
                'mediaType' => $mediaType === '' ? null : $mediaType,
                'mediaTypeBase' => $mediaTypeReport['mediaTypeBase'],
                'mediaTypeHasParameters' => $mediaTypeReport['mediaTypeHasParameters'],
                'mediaTypeParameterCount' => $mediaTypeReport['mediaTypeParameterCount'],
                'mediaTypeParameters' => $mediaTypeReport['mediaTypeParameters'],
                'mediaTypeParameterMap' => $mediaTypeReport['mediaTypeParameterMap'],
                'expectedMediaTypePrefix' => 'image/',
                'exists' => $zipEntry instanceof ZipPackageEntry,
                'declared' => $declared,
                'undeclared' => !$declared,
                'encrypted' => $encrypted,
                'valid' => $zipEntry instanceof ZipPackageEntry && !$encrypted && $mediaTypeValid,
                'byteLength' => !$encrypted && $zipEntry instanceof ZipPackageEntry ? $zipEntry->uncompressedSize : null,
                'compressedByteLength' => $zipEntry instanceof ZipPackageEntry ? $zipEntry->compressedSize : null,
                'compressionMethod' => $zipEntry instanceof ZipPackageEntry ? $zipEntry->compressionMethod : null,
                'compressionMethodName' => $zipEntry instanceof ZipPackageEntry ? self::compressionMethodName($zipEntry->compressionMethod) : null,
                'crc32' => !$encrypted && $zipEntry instanceof ZipPackageEntry ? $zipEntry->crc32Hex() : null,
                'storedByteLength' => $zipEntry instanceof ZipPackageEntry ? $zipEntry->uncompressedSize : null,
                'storedCrc32' => $zipEntry instanceof ZipPackageEntry ? $zipEntry->crc32Hex() : null,
                'declaredSize' => $entry['declaredSize'] ?? $entry['size'] ?? null,
                'declaredSizeMismatch' => ($entry['declaredSizeMismatch'] ?? false) === true,
                'canExposeBytes' => false,
                'canExposeAsDocumentMedia' => false,
                'byteExposurePolicy' => $byteExposurePolicy,
                'reviewPolicy' => 'package-thumbnail-metadata-only',
                'issues' => $issues,
            ];
        }

        ksort($issueCodes, SORT_STRING);

        return [
            'count' => count($items),
            'readableCount' => count(array_filter(
                $items,
                static fn (array $item): bool => $item['exists'] === true && $item['byteLength'] !== null,
            )),
            'declaredCount' => count(array_filter($items, static fn (array $item): bool => $item['declared'] === true)),
            'undeclaredCount' => count(array_filter($items, static fn (array $item): bool => $item['undeclared'] === true)),
            'missingCount' => count(array_filter($items, static fn (array $item): bool => $item['exists'] !== true)),
            'encryptedCount' => count(array_filter($items, static fn (array $item): bool => $item['encrypted'] === true)),
            'invalidMediaTypeCount' => count(array_filter(
                $items,
                static fn (array $item): bool => $item['mediaType'] !== null
                    && !str_starts_with((string) $item['mediaTypeBase'], 'image/'),
            )),
            'issueCount' => count(array_filter($items, static fn (array $item): bool => $item['issues'] !== [])),
            'issueCodes' => array_keys($issueCodes),
            'items' => $items,
        ];
    }

    private static function isThumbnailPackagePartName(string $path): bool
    {
        $normalized = strtolower(ltrim($path, '/'));
        if (!str_starts_with($normalized, 'thumbnails/') || str_ends_with($normalized, '/')) {
            return false;
        }

        return self::thumbnailMediaTypeFromPart($normalized) !== null;
    }

    private static function thumbnailMediaTypeFromPart(string $path): ?string
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return match ($extension) {
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'svg' => 'image/svg+xml',
            'bmp' => 'image/bmp',
            'tif', 'tiff' => 'image/tiff',
            default => null,
        };
    }

    /**
     * @param list<array<string, mixed>> $manifestEntries
     * @param list<array<string, mixed>> $undeclaredPackageEntries
     * @return array{count:int, readableCount:int, declaredCount:int, undeclaredCount:int, missingCount:int, encryptedCount:int, invalidMediaTypeCount:int, issueCount:int, issueCodes:list<string>, items:list<array<string, mixed>>}
     */
    private static function packageSignatureMetadata(ZipPackage $package, array $manifestEntries, array $undeclaredPackageEntries): array
    {
        $candidatesByPath = [];
        foreach ($manifestEntries as $entry) {
            $packagePath = $entry['packagePath'] ?? null;
            if (!is_string($packagePath) || $packagePath === '' || !self::isSignaturePackagePartName($packagePath)) {
                continue;
            }

            $entry['declared'] = true;
            $candidatesByPath[$packagePath] = $entry;
        }

        foreach ($undeclaredPackageEntries as $entry) {
            $packagePath = $entry['path'] ?? null;
            if (!is_string($packagePath) || $packagePath === '' || !self::isSignaturePackagePartName($packagePath)) {
                continue;
            }

            $candidatesByPath[$packagePath] = [
                'path' => $packagePath,
                'packagePath' => $packagePath,
                'pathReference' => $packagePath,
                'pathSuffix' => null,
                'pathQuery' => null,
                'pathFragment' => null,
                'mediaType' => null,
                'mediaTypeBase' => '',
                'mediaTypeHasParameters' => false,
                'mediaTypeParameterCount' => 0,
                'mediaTypeParameters' => [],
                'mediaTypeParameterMap' => [],
                'exists' => true,
                'encrypted' => false,
                'declared' => false,
                'declaredSize' => null,
                'declaredSizeMismatch' => false,
            ];
        }

        ksort($candidatesByPath, SORT_STRING);

        $items = [];
        $issueCodes = [];
        foreach ($candidatesByPath as $packagePath => $entry) {
            $zipEntry = $package->has($packagePath) ? $package->entry($packagePath) : null;
            $encrypted = ($entry['encrypted'] ?? false) === true;
            $declared = ($entry['declared'] ?? false) === true;
            $mediaType = is_string($entry['mediaType'] ?? null) ? (string) $entry['mediaType'] : null;
            $mediaTypeReport = self::mediaTypeReport($mediaType ?? '');
            $kind = self::signaturePackagePartKind($packagePath);
            $expectedMediaTypes = self::signatureExpectedMediaTypes($kind);
            $mediaTypeValid = $mediaType === null
                || self::isSignaturePackageMediaType($kind, $mediaTypeReport['mediaTypeBase']);
            $issues = [];
            if (!$zipEntry instanceof ZipPackageEntry) {
                $issues[] = 'odf-signature-missing-package-part';
            }
            if (!$declared) {
                $issues[] = 'odf-signature-undeclared-package-part';
            }
            if ($encrypted) {
                $issues[] = 'odf-signature-encrypted-package-part';
            }
            if (!$mediaTypeValid) {
                $issues[] = 'odf-signature-invalid-media-type';
            }
            foreach ($issues as $issue) {
                $issueCodes[$issue] = true;
            }

            $items[] = [
                'fullPath' => $entry['path'] ?? $packagePath,
                'path' => $entry['path'] ?? $packagePath,
                'packagePath' => $packagePath,
                'part' => $packagePath,
                'pathReference' => $entry['pathReference'] ?? null,
                'pathSuffix' => $entry['pathSuffix'] ?? null,
                'pathQuery' => $entry['pathQuery'] ?? null,
                'pathFragment' => $entry['pathFragment'] ?? null,
                'mediaType' => $mediaType,
                'mediaTypeBase' => $mediaTypeReport['mediaTypeBase'],
                'mediaTypeHasParameters' => $mediaTypeReport['mediaTypeHasParameters'],
                'mediaTypeParameterCount' => $mediaTypeReport['mediaTypeParameterCount'],
                'mediaTypeParameters' => $mediaTypeReport['mediaTypeParameters'],
                'mediaTypeParameterMap' => $mediaTypeReport['mediaTypeParameterMap'],
                'kind' => $kind,
                'expectedMediaTypes' => $expectedMediaTypes,
                'exists' => $zipEntry instanceof ZipPackageEntry,
                'declared' => $declared,
                'undeclared' => !$declared,
                'encrypted' => $encrypted,
                'valid' => $zipEntry instanceof ZipPackageEntry && !$encrypted && $mediaTypeValid,
                'byteLength' => !$encrypted && $zipEntry instanceof ZipPackageEntry ? $zipEntry->uncompressedSize : null,
                'compressedByteLength' => $zipEntry instanceof ZipPackageEntry ? $zipEntry->compressedSize : null,
                'compressionMethod' => $zipEntry instanceof ZipPackageEntry ? $zipEntry->compressionMethod : null,
                'compressionMethodName' => $zipEntry instanceof ZipPackageEntry ? self::compressionMethodName($zipEntry->compressionMethod) : null,
                'crc32' => !$encrypted && $zipEntry instanceof ZipPackageEntry ? $zipEntry->crc32Hex() : null,
                'storedByteLength' => $zipEntry instanceof ZipPackageEntry ? $zipEntry->uncompressedSize : null,
                'storedCrc32' => $zipEntry instanceof ZipPackageEntry ? $zipEntry->crc32Hex() : null,
                'declaredSize' => $entry['declaredSize'] ?? $entry['size'] ?? null,
                'declaredSizeMismatch' => ($entry['declaredSizeMismatch'] ?? false) === true,
                'canExposeAsDocumentMedia' => false,
                'byteExposurePolicy' => $entry['byteExposurePolicy'] ?? ($declared ? 'signature-package-bytes-blocked' : 'undeclared-package-entry-no-bytes'),
                'reviewPolicy' => 'package-signature-metadata-only',
                'issues' => $issues,
            ];
        }

        ksort($issueCodes, SORT_STRING);

        return [
            'count' => count($items),
            'readableCount' => count(array_filter(
                $items,
                static fn (array $item): bool => $item['exists'] === true && $item['byteLength'] !== null,
            )),
            'declaredCount' => count(array_filter($items, static fn (array $item): bool => $item['declared'] === true)),
            'undeclaredCount' => count(array_filter($items, static fn (array $item): bool => $item['undeclared'] === true)),
            'missingCount' => count(array_filter($items, static fn (array $item): bool => $item['exists'] !== true)),
            'encryptedCount' => count(array_filter($items, static fn (array $item): bool => $item['encrypted'] === true)),
            'invalidMediaTypeCount' => count(array_filter(
                $items,
                static fn (array $item): bool => $item['mediaType'] !== null
                    && !self::isSignaturePackageMediaType(
                        (string) ($item['kind'] ?? 'xml-signature'),
                        (string) ($item['mediaTypeBase'] ?? '')
                    ),
            )),
            'issueCount' => count(array_filter($items, static fn (array $item): bool => $item['issues'] !== [])),
            'issueCodes' => array_keys($issueCodes),
            'items' => $items,
        ];
    }

    /**
     * @param list<array<string, mixed>> $manifestEntries
     * @param list<array<string, mixed>> $undeclaredPackageEntries
     * @return array{count:int, readableCount:int, declaredCount:int, undeclaredCount:int, missingCount:int, encryptedCount:int, invalidMediaTypeCount:int, issueCount:int, issueCodes:list<string>, items:list<array<string, mixed>>}
     */
    private static function packageObjectReplacementMetadata(ZipPackage $package, array $manifestEntries, array $undeclaredPackageEntries): array
    {
        $candidatesByPath = [];
        foreach ($manifestEntries as $entry) {
            $packagePath = $entry['packagePath'] ?? null;
            if (!is_string($packagePath) || $packagePath === '' || !self::isObjectReplacementPackagePartName($packagePath)) {
                continue;
            }

            $entry['declared'] = true;
            $candidatesByPath[$packagePath] = $entry;
        }

        foreach ($undeclaredPackageEntries as $entry) {
            $packagePath = $entry['path'] ?? null;
            if (!is_string($packagePath) || $packagePath === '' || !self::isObjectReplacementPackagePartName($packagePath)) {
                continue;
            }

            $mediaType = self::objectReplacementMediaTypeFromPart($packagePath);
            $mediaTypeReport = self::mediaTypeReport($mediaType ?? '');
            $candidatesByPath[$packagePath] = [
                'path' => $packagePath,
                'packagePath' => $packagePath,
                'pathReference' => $packagePath,
                'pathSuffix' => null,
                'pathQuery' => null,
                'pathFragment' => null,
                'mediaType' => $mediaType ?? '',
                'mediaTypeBase' => $mediaTypeReport['mediaTypeBase'],
                'mediaTypeHasParameters' => false,
                'mediaTypeParameterCount' => 0,
                'mediaTypeParameters' => [],
                'mediaTypeParameterMap' => [],
                'exists' => true,
                'encrypted' => false,
                'declared' => false,
                'declaredSize' => null,
                'declaredSizeMismatch' => false,
                'byteExposurePolicy' => 'object-replacement-package-bytes-blocked',
            ];
        }

        ksort($candidatesByPath, SORT_STRING);

        $items = [];
        $issueCodes = [];
        foreach ($candidatesByPath as $packagePath => $entry) {
            $zipEntry = $package->has($packagePath) ? $package->entry($packagePath) : null;
            $isDirectory = str_ends_with($packagePath, '/');
            $exists = $isDirectory || $zipEntry instanceof ZipPackageEntry;
            $encrypted = ($entry['encrypted'] ?? false) === true;
            $declared = ($entry['declared'] ?? false) === true;
            $mediaType = (string) ($entry['mediaType'] ?? '');
            if ($mediaType === '') {
                $mediaType = self::objectReplacementMediaTypeFromPart($packagePath) ?? '';
            }

            $mediaTypeReport = self::mediaTypeReport($mediaType);
            $mediaTypeValid = $isDirectory
                ? $mediaTypeReport['mediaTypeBase'] === ''
                : ($mediaType !== '' && self::isObjectReplacementMediaType($mediaType));
            $kind = self::objectReplacementPackagePartKind($packagePath, $mediaType, $isDirectory);
            $issues = [];
            if (!$exists) {
                $issues[] = 'odf-object-replacement-missing-package-part';
            }
            if (!$declared) {
                $issues[] = 'odf-object-replacement-undeclared-package-part';
            }
            if ($encrypted) {
                $issues[] = 'odf-object-replacement-encrypted-package-part';
            }
            if (!$mediaTypeValid) {
                $issues[] = 'odf-object-replacement-invalid-media-type';
            }
            foreach ($issues as $issue) {
                $issueCodes[$issue] = true;
            }

            $items[] = [
                'fullPath' => $entry['path'] ?? $packagePath,
                'path' => $entry['path'] ?? $packagePath,
                'packagePath' => $packagePath,
                'part' => $packagePath,
                'pathReference' => $entry['pathReference'] ?? null,
                'pathSuffix' => $entry['pathSuffix'] ?? null,
                'pathQuery' => $entry['pathQuery'] ?? null,
                'pathFragment' => $entry['pathFragment'] ?? null,
                'mediaType' => $mediaType === '' ? null : $mediaType,
                'mediaTypeBase' => $mediaTypeReport['mediaTypeBase'],
                'mediaTypeHasParameters' => $mediaTypeReport['mediaTypeHasParameters'],
                'mediaTypeParameterCount' => $mediaTypeReport['mediaTypeParameterCount'],
                'mediaTypeParameters' => $mediaTypeReport['mediaTypeParameters'],
                'mediaTypeParameterMap' => $mediaTypeReport['mediaTypeParameterMap'],
                'expectedMediaTypePrefix' => 'image/',
                'kind' => $kind,
                'isDirectory' => $isDirectory,
                'exists' => $exists,
                'declared' => $declared,
                'undeclared' => !$declared,
                'encrypted' => $encrypted,
                'valid' => $exists && !$encrypted && $mediaTypeValid,
                'byteLength' => !$isDirectory && !$encrypted && $zipEntry instanceof ZipPackageEntry ? $zipEntry->uncompressedSize : null,
                'compressedByteLength' => $zipEntry instanceof ZipPackageEntry ? $zipEntry->compressedSize : null,
                'compressionMethod' => $zipEntry instanceof ZipPackageEntry ? $zipEntry->compressionMethod : null,
                'compressionMethodName' => $zipEntry instanceof ZipPackageEntry ? self::compressionMethodName($zipEntry->compressionMethod) : null,
                'crc32' => !$isDirectory && !$encrypted && $zipEntry instanceof ZipPackageEntry ? $zipEntry->crc32Hex() : null,
                'storedByteLength' => $zipEntry instanceof ZipPackageEntry ? $zipEntry->uncompressedSize : null,
                'storedCrc32' => $zipEntry instanceof ZipPackageEntry ? $zipEntry->crc32Hex() : null,
                'declaredSize' => $entry['declaredSize'] ?? $entry['size'] ?? null,
                'declaredSizeMismatch' => ($entry['declaredSizeMismatch'] ?? false) === true,
                'canExposeBytes' => false,
                'canExposeAsDocumentMedia' => false,
                'byteExposurePolicy' => $entry['byteExposurePolicy'] ?? ($isDirectory ? 'directory-entry-no-bytes' : 'object-replacement-package-bytes-blocked'),
                'reviewPolicy' => 'object-replacement-metadata-only',
                'issues' => $issues,
            ];
        }

        ksort($issueCodes, SORT_STRING);
        $kindCounts = [];
        foreach ($items as $item) {
            if (is_string($item['kind'] ?? null)) {
                $kindCounts[$item['kind']] = ($kindCounts[$item['kind']] ?? 0) + 1;
            }
        }
        ksort($kindCounts, SORT_STRING);

        return [
            'count' => count($items),
            'readableCount' => count(array_filter(
                $items,
                static fn (array $item): bool => $item['exists'] === true && $item['byteLength'] !== null,
            )),
            'declaredCount' => count(array_filter($items, static fn (array $item): bool => $item['declared'] === true)),
            'undeclaredCount' => count(array_filter($items, static fn (array $item): bool => $item['undeclared'] === true)),
            'missingCount' => count(array_filter($items, static fn (array $item): bool => $item['exists'] !== true)),
            'directoryCount' => count(array_filter($items, static fn (array $item): bool => $item['isDirectory'] === true)),
            'encryptedCount' => count(array_filter($items, static fn (array $item): bool => $item['encrypted'] === true)),
            'invalidMediaTypeCount' => count(array_filter(
                $items,
                static fn (array $item): bool => $item['mediaType'] !== null
                    && !self::isObjectReplacementMediaType((string) $item['mediaType']),
            )),
            'issueCount' => count(array_filter($items, static fn (array $item): bool => $item['issues'] !== [])),
            'issueCodes' => array_keys($issueCodes),
            'kindCounts' => $kindCounts,
            'byteExposurePolicy' => 'object-replacement-package-bytes-blocked',
            'reviewPolicy' => 'object-replacement-metadata-only',
            'items' => $items,
        ];
    }

    /**
     * @param list<array<string, mixed>> $manifestEntries
     * @param list<array<string, mixed>> $undeclaredPackageEntries
     * @return array<string, mixed>
     */
    private static function packageLayoutCacheMetadata(ZipPackage $package, array $manifestEntries, array $undeclaredPackageEntries): array
    {
        $candidatesByPath = [];
        foreach ($manifestEntries as $entry) {
            $packagePath = $entry['packagePath'] ?? null;
            if (!is_string($packagePath) || $packagePath === '' || !self::isLayoutCachePackagePartName($packagePath)) {
                continue;
            }

            $entry['declared'] = true;
            $candidatesByPath[$packagePath] = $entry;
        }

        foreach ($undeclaredPackageEntries as $entry) {
            $packagePath = $entry['path'] ?? null;
            if (!is_string($packagePath) || $packagePath === '' || !self::isLayoutCachePackagePartName($packagePath)) {
                continue;
            }

            $mediaType = self::layoutCacheMediaTypeFromPart($packagePath);
            $mediaTypeReport = self::mediaTypeReport($mediaType);
            $candidatesByPath[$packagePath] = [
                'path' => $packagePath,
                'packagePath' => $packagePath,
                'pathReference' => $packagePath,
                'pathSuffix' => null,
                'pathQuery' => null,
                'pathFragment' => null,
                'mediaType' => $mediaType,
                'mediaTypeBase' => $mediaTypeReport['mediaTypeBase'],
                'mediaTypeHasParameters' => false,
                'mediaTypeParameterCount' => 0,
                'mediaTypeParameters' => [],
                'mediaTypeParameterMap' => [],
                'exists' => true,
                'encrypted' => false,
                'declared' => false,
                'declaredSize' => null,
                'declaredSizeMismatch' => false,
                'byteExposurePolicy' => 'layout-cache-package-bytes-blocked',
            ];
        }

        ksort($candidatesByPath, SORT_STRING);

        $items = [];
        $issueCodes = [];
        foreach ($candidatesByPath as $packagePath => $entry) {
            $zipEntry = $package->has($packagePath) ? $package->entry($packagePath) : null;
            $encrypted = ($entry['encrypted'] ?? false) === true;
            $declared = ($entry['declared'] ?? false) === true;
            $mediaType = (string) ($entry['mediaType'] ?? '');
            if ($mediaType === '') {
                $mediaType = self::layoutCacheMediaTypeFromPart($packagePath);
            }
            $mediaTypeReport = self::mediaTypeReport($mediaType);
            $mediaTypeValid = self::isLayoutCacheMediaType($mediaType);
            $issues = [];
            if (!$zipEntry instanceof ZipPackageEntry) {
                $issues[] = 'odf-layout-cache-missing-package-part';
            }
            if (!$declared) {
                $issues[] = 'odf-layout-cache-undeclared-package-part';
            }
            if ($encrypted) {
                $issues[] = 'odf-layout-cache-encrypted-package-part';
            }
            if (!$mediaTypeValid) {
                $issues[] = 'odf-layout-cache-invalid-media-type';
            }
            foreach ($issues as $issue) {
                $issueCodes[$issue] = true;
            }

            $items[] = [
                'fullPath' => $entry['path'] ?? $packagePath,
                'path' => $entry['path'] ?? $packagePath,
                'packagePath' => $packagePath,
                'part' => $packagePath,
                'pathReference' => $entry['pathReference'] ?? null,
                'pathSuffix' => $entry['pathSuffix'] ?? null,
                'pathQuery' => $entry['pathQuery'] ?? null,
                'pathFragment' => $entry['pathFragment'] ?? null,
                'mediaType' => $mediaType === '' ? null : $mediaType,
                'mediaTypeBase' => $mediaTypeReport['mediaTypeBase'],
                'mediaTypeHasParameters' => $mediaTypeReport['mediaTypeHasParameters'],
                'mediaTypeParameterCount' => $mediaTypeReport['mediaTypeParameterCount'],
                'mediaTypeParameters' => $mediaTypeReport['mediaTypeParameters'],
                'mediaTypeParameterMap' => $mediaTypeReport['mediaTypeParameterMap'],
                'expectedMediaTypes' => ['application/binary', 'application/octet-stream'],
                'exists' => $zipEntry instanceof ZipPackageEntry,
                'declared' => $declared,
                'undeclared' => !$declared,
                'encrypted' => $encrypted,
                'valid' => $zipEntry instanceof ZipPackageEntry && !$encrypted && $mediaTypeValid,
                'byteLength' => !$encrypted && $zipEntry instanceof ZipPackageEntry ? $zipEntry->uncompressedSize : null,
                'compressedByteLength' => $zipEntry instanceof ZipPackageEntry ? $zipEntry->compressedSize : null,
                'compressionMethod' => $zipEntry instanceof ZipPackageEntry ? $zipEntry->compressionMethod : null,
                'compressionMethodName' => $zipEntry instanceof ZipPackageEntry ? self::compressionMethodName($zipEntry->compressionMethod) : null,
                'crc32' => !$encrypted && $zipEntry instanceof ZipPackageEntry ? $zipEntry->crc32Hex() : null,
                'storedByteLength' => $zipEntry instanceof ZipPackageEntry ? $zipEntry->uncompressedSize : null,
                'storedCrc32' => $zipEntry instanceof ZipPackageEntry ? $zipEntry->crc32Hex() : null,
                'declaredSize' => $entry['declaredSize'] ?? $entry['size'] ?? null,
                'declaredSizeMismatch' => ($entry['declaredSizeMismatch'] ?? false) === true,
                'canExposeAsDocumentMedia' => false,
                'byteExposurePolicy' => $entry['byteExposurePolicy'] ?? 'layout-cache-package-bytes-blocked',
                'reviewPolicy' => 'layout-cache-metadata-only',
                'issues' => $issues,
            ];
        }

        ksort($issueCodes, SORT_STRING);

        return [
            'count' => count($items),
            'readableCount' => count(array_filter(
                $items,
                static fn (array $item): bool => $item['exists'] === true && ($item['byteLength'] ?? null) !== null,
            )),
            'declaredCount' => count(array_filter($items, static fn (array $item): bool => $item['declared'] === true)),
            'undeclaredCount' => count(array_filter($items, static fn (array $item): bool => $item['undeclared'] === true)),
            'missingCount' => count(array_filter($items, static fn (array $item): bool => $item['exists'] !== true)),
            'encryptedCount' => count(array_filter($items, static fn (array $item): bool => $item['encrypted'] === true)),
            'invalidMediaTypeCount' => count(array_filter(
                $items,
                static fn (array $item): bool => $item['mediaType'] !== null
                    && !self::isLayoutCacheMediaType((string) $item['mediaType']),
            )),
            'issueCount' => count(array_filter($items, static fn (array $item): bool => $item['issues'] !== [])),
            'issueCodes' => array_keys($issueCodes),
            'byteExposurePolicy' => 'layout-cache-package-bytes-blocked',
            'reviewPolicy' => 'layout-cache-metadata-only',
            'items' => $items,
        ];
    }

    /**
     * @param list<array<string, mixed>> $manifestEntries
     * @param list<array<string, mixed>> $undeclaredPackageEntries
     * @return array<string, mixed>
     */
    private static function packageMetaInfSidecarMetadata(ZipPackage $package, array $manifestEntries, array $undeclaredPackageEntries): array
    {
        $candidatesByPath = [];
        foreach ($manifestEntries as $entry) {
            $packagePath = $entry['packagePath'] ?? null;
            if (!is_string($packagePath) || $packagePath === '' || !self::isMetaInfSidecarPackagePartName($packagePath)) {
                continue;
            }

            $entry['declared'] = true;
            $candidatesByPath[$packagePath] = $entry;
        }

        foreach ($undeclaredPackageEntries as $entry) {
            $packagePath = $entry['path'] ?? null;
            if (!is_string($packagePath) || $packagePath === '' || !self::isMetaInfSidecarPackagePartName($packagePath)) {
                continue;
            }

            $candidatesByPath[$packagePath] = [
                'path' => $packagePath,
                'packagePath' => $packagePath,
                'pathReference' => $packagePath,
                'pathSuffix' => null,
                'pathQuery' => null,
                'pathFragment' => null,
                'mediaType' => null,
                'mediaTypeBase' => '',
                'mediaTypeHasParameters' => false,
                'mediaTypeParameterCount' => 0,
                'mediaTypeParameters' => [],
                'mediaTypeParameterMap' => [],
                'exists' => true,
                'encrypted' => false,
                'declared' => false,
                'declaredSize' => null,
                'declaredSizeMismatch' => false,
                'byteExposurePolicy' => 'meta-inf-sidecar-package-bytes-blocked',
            ];
        }

        ksort($candidatesByPath, SORT_STRING);

        $items = [];
        $issueCodes = [];
        foreach ($candidatesByPath as $packagePath => $entry) {
            $zipEntry = $package->has($packagePath) ? $package->entry($packagePath) : null;
            $encrypted = ($entry['encrypted'] ?? false) === true;
            $declared = ($entry['declared'] ?? false) === true;
            $mediaType = is_string($entry['mediaType'] ?? null) && $entry['mediaType'] !== ''
                ? (string) $entry['mediaType']
                : null;
            $mediaTypeReport = self::mediaTypeReport($mediaType ?? '');
            $issues = [];
            if (!$zipEntry instanceof ZipPackageEntry) {
                $issues[] = 'odf-meta-inf-sidecar-missing-package-part';
            }
            if (!$declared) {
                $issues[] = 'odf-meta-inf-sidecar-undeclared-package-part';
            }
            if ($encrypted) {
                $issues[] = 'odf-meta-inf-sidecar-encrypted-package-part';
            }
            foreach ($issues as $issue) {
                $issueCodes[$issue] = true;
            }

            $items[] = [
                'fullPath' => $entry['path'] ?? $packagePath,
                'path' => $entry['path'] ?? $packagePath,
                'packagePath' => $packagePath,
                'part' => $packagePath,
                'pathReference' => $entry['pathReference'] ?? null,
                'pathSuffix' => $entry['pathSuffix'] ?? null,
                'pathQuery' => $entry['pathQuery'] ?? null,
                'pathFragment' => $entry['pathFragment'] ?? null,
                'mediaType' => $mediaType,
                'mediaTypeBase' => $mediaTypeReport['mediaTypeBase'],
                'mediaTypeHasParameters' => $mediaTypeReport['mediaTypeHasParameters'],
                'mediaTypeParameterCount' => $mediaTypeReport['mediaTypeParameterCount'],
                'mediaTypeParameters' => $mediaTypeReport['mediaTypeParameters'],
                'mediaTypeParameterMap' => $mediaTypeReport['mediaTypeParameterMap'],
                'exists' => $zipEntry instanceof ZipPackageEntry,
                'declared' => $declared,
                'undeclared' => !$declared,
                'encrypted' => $encrypted,
                'valid' => $zipEntry instanceof ZipPackageEntry && !$encrypted,
                'byteLength' => !$encrypted && $zipEntry instanceof ZipPackageEntry ? $zipEntry->uncompressedSize : null,
                'compressedByteLength' => $zipEntry instanceof ZipPackageEntry ? $zipEntry->compressedSize : null,
                'compressionMethod' => $zipEntry instanceof ZipPackageEntry ? $zipEntry->compressionMethod : null,
                'compressionMethodName' => $zipEntry instanceof ZipPackageEntry ? self::compressionMethodName($zipEntry->compressionMethod) : null,
                'crc32' => !$encrypted && $zipEntry instanceof ZipPackageEntry ? $zipEntry->crc32Hex() : null,
                'storedByteLength' => $zipEntry instanceof ZipPackageEntry ? $zipEntry->uncompressedSize : null,
                'storedCrc32' => $zipEntry instanceof ZipPackageEntry ? $zipEntry->crc32Hex() : null,
                'declaredSize' => $entry['declaredSize'] ?? $entry['size'] ?? null,
                'declaredSizeMismatch' => ($entry['declaredSizeMismatch'] ?? false) === true,
                'canExposeAsDocumentMedia' => false,
                'byteExposurePolicy' => $entry['byteExposurePolicy'] ?? 'meta-inf-sidecar-package-bytes-blocked',
                'reviewPolicy' => 'meta-inf-sidecar-metadata-only',
                'issues' => $issues,
            ];
        }

        ksort($issueCodes, SORT_STRING);

        return [
            'count' => count($items),
            'readableCount' => count(array_filter(
                $items,
                static fn (array $item): bool => $item['exists'] === true && ($item['byteLength'] ?? null) !== null,
            )),
            'declaredCount' => count(array_filter($items, static fn (array $item): bool => $item['declared'] === true)),
            'undeclaredCount' => count(array_filter($items, static fn (array $item): bool => $item['undeclared'] === true)),
            'missingCount' => count(array_filter($items, static fn (array $item): bool => $item['exists'] !== true)),
            'encryptedCount' => count(array_filter($items, static fn (array $item): bool => $item['encrypted'] === true)),
            'invalidMediaTypeCount' => 0,
            'issueCount' => count(array_filter($items, static fn (array $item): bool => $item['issues'] !== [])),
            'issueCodes' => array_keys($issueCodes),
            'byteExposurePolicy' => 'meta-inf-sidecar-package-bytes-blocked',
            'reviewPolicy' => 'meta-inf-sidecar-metadata-only',
            'items' => $items,
        ];
    }

    /**
     * @param list<array<string, mixed>> $manifestEntries
     * @param list<array<string, mixed>> $undeclaredPackageEntries
     * @return array<string, mixed>
     */
    private static function packageLinkedResourceMetadata(ZipPackage $package, array $manifestEntries, array $undeclaredPackageEntries): array
    {
        $candidatesByPath = [];
        foreach ($manifestEntries as $entry) {
            $packagePath = $entry['packagePath'] ?? null;
            if (!is_string($packagePath) || $packagePath === '' || !self::isLinkedResourcePackagePartName($packagePath)) {
                continue;
            }

            $entry['declared'] = true;
            $candidatesByPath[$packagePath] = $entry;
        }

        foreach ($undeclaredPackageEntries as $entry) {
            $packagePath = $entry['path'] ?? null;
            if (!is_string($packagePath) || $packagePath === '' || str_ends_with($packagePath, '/') || !self::isLinkedResourcePackagePartName($packagePath)) {
                continue;
            }

            $candidatesByPath[$packagePath] = [
                'path' => $packagePath,
                'packagePath' => $packagePath,
                'pathReference' => $packagePath,
                'pathSuffix' => null,
                'pathQuery' => null,
                'pathFragment' => null,
                'mediaType' => null,
                'mediaTypeBase' => '',
                'mediaTypeHasParameters' => false,
                'mediaTypeParameterCount' => 0,
                'mediaTypeParameters' => [],
                'mediaTypeParameterMap' => [],
                'exists' => true,
                'encrypted' => false,
                'declared' => false,
                'declaredSize' => null,
                'declaredSizeMismatch' => false,
                'byteExposurePolicy' => 'linked-resource-package-bytes-blocked',
            ];
        }

        ksort($candidatesByPath, SORT_STRING);

        $items = [];
        $issueCodes = [];
        $kindCounts = [];
        foreach ($candidatesByPath as $packagePath => $entry) {
            $zipEntry = $package->has($packagePath) ? $package->entry($packagePath) : null;
            $isDirectory = str_ends_with($packagePath, '/');
            $exists = $isDirectory || $zipEntry instanceof ZipPackageEntry;
            $encrypted = ($entry['encrypted'] ?? false) === true;
            $declared = ($entry['declared'] ?? false) === true;
            $mediaType = is_string($entry['mediaType'] ?? null) && $entry['mediaType'] !== ''
                ? (string) $entry['mediaType']
                : self::linkedResourceMediaTypeFromPart($packagePath);
            $mediaTypeReport = self::mediaTypeReport($mediaType ?? '');
            $missingMediaType = ($mediaType ?? '') === '' && !$isDirectory;
            $mediaTypeValid = $isDirectory
                ? $mediaTypeReport['mediaTypeBase'] === ''
                : (!$missingMediaType && self::isLinkedResourceMediaType((string) $mediaType));
            $kind = self::linkedResourcePackagePartKind($packagePath, (string) $mediaType);
            $issues = [];
            if (!$exists) {
                $issues[] = 'odf-linked-resource-package-missing-part';
            }
            if (!$declared) {
                $issues[] = 'odf-linked-resource-package-undeclared-part';
            }
            if ($encrypted) {
                $issues[] = 'odf-linked-resource-package-encrypted-part';
            }
            if ($missingMediaType) {
                $issues[] = 'odf-linked-resource-package-missing-media-type';
            } elseif (!$mediaTypeValid) {
                $issues[] = 'odf-linked-resource-package-invalid-media-type';
            }
            foreach ($issues as $issue) {
                $issueCodes[$issue] = true;
            }
            $kindCounts[$kind] = ($kindCounts[$kind] ?? 0) + 1;

            $items[] = [
                'fullPath' => $entry['path'] ?? $packagePath,
                'path' => $entry['path'] ?? $packagePath,
                'packagePath' => $packagePath,
                'part' => $packagePath,
                'pathReference' => $entry['pathReference'] ?? null,
                'pathSuffix' => $entry['pathSuffix'] ?? null,
                'pathQuery' => $entry['pathQuery'] ?? null,
                'pathFragment' => $entry['pathFragment'] ?? null,
                'mediaType' => ($mediaType ?? '') === '' ? null : $mediaType,
                'mediaTypeBase' => $mediaTypeReport['mediaTypeBase'],
                'mediaTypeHasParameters' => $mediaTypeReport['mediaTypeHasParameters'],
                'mediaTypeParameterCount' => $mediaTypeReport['mediaTypeParameterCount'],
                'mediaTypeParameters' => $mediaTypeReport['mediaTypeParameters'],
                'mediaTypeParameterMap' => $mediaTypeReport['mediaTypeParameterMap'],
                'kind' => $kind,
                'packageRoot' => 'Links',
                'isDirectory' => $isDirectory,
                'exists' => $exists,
                'declared' => $declared,
                'undeclared' => !$declared,
                'encrypted' => $encrypted,
                'valid' => $exists && !$encrypted && $mediaTypeValid,
                'byteLength' => !$isDirectory && !$encrypted && $zipEntry instanceof ZipPackageEntry ? $zipEntry->uncompressedSize : null,
                'compressedByteLength' => $zipEntry instanceof ZipPackageEntry ? $zipEntry->compressedSize : null,
                'compressionMethod' => $zipEntry instanceof ZipPackageEntry ? $zipEntry->compressionMethod : null,
                'compressionMethodName' => $zipEntry instanceof ZipPackageEntry ? self::compressionMethodName($zipEntry->compressionMethod) : null,
                'crc32' => !$isDirectory && !$encrypted && $zipEntry instanceof ZipPackageEntry ? $zipEntry->crc32Hex() : null,
                'storedByteLength' => $zipEntry instanceof ZipPackageEntry ? $zipEntry->uncompressedSize : null,
                'storedCrc32' => $zipEntry instanceof ZipPackageEntry ? $zipEntry->crc32Hex() : null,
                'declaredSize' => $entry['declaredSize'] ?? $entry['size'] ?? null,
                'declaredSizeMismatch' => ($entry['declaredSizeMismatch'] ?? false) === true,
                'canExposeBytes' => false,
                'canExposeAsDocumentMedia' => false,
                'byteExposurePolicy' => $entry['byteExposurePolicy'] ?? ($isDirectory ? 'directory-entry-no-bytes' : 'linked-resource-package-bytes-blocked'),
                'reviewPolicy' => 'linked-resource-package-metadata-only',
                'encryption' => $entry['encryption'] ?? null,
                'issues' => $issues,
            ];
        }

        ksort($issueCodes, SORT_STRING);
        ksort($kindCounts, SORT_STRING);

        return [
            'count' => count($items),
            'readableCount' => count(array_filter(
                $items,
                static fn (array $item): bool => $item['exists'] === true && ($item['byteLength'] ?? null) !== null,
            )),
            'declaredCount' => count(array_filter($items, static fn (array $item): bool => $item['declared'] === true)),
            'undeclaredCount' => count(array_filter($items, static fn (array $item): bool => $item['undeclared'] === true)),
            'missingCount' => count(array_filter($items, static fn (array $item): bool => $item['exists'] !== true)),
            'directoryCount' => count(array_filter($items, static fn (array $item): bool => $item['isDirectory'] === true)),
            'encryptedCount' => count(array_filter($items, static fn (array $item): bool => $item['encrypted'] === true)),
            'missingMediaTypeCount' => count(array_filter($items, static fn (array $item): bool => in_array('odf-linked-resource-package-missing-media-type', $item['issues'], true))),
            'invalidMediaTypeCount' => count(array_filter($items, static fn (array $item): bool => in_array('odf-linked-resource-package-invalid-media-type', $item['issues'], true))),
            'issueCount' => count(array_filter($items, static fn (array $item): bool => $item['issues'] !== [])),
            'issueCodes' => array_keys($issueCodes),
            'kindCounts' => $kindCounts,
            'byteExposurePolicy' => 'linked-resource-package-bytes-blocked',
            'reviewPolicy' => 'linked-resource-package-metadata-only',
            'items' => $items,
        ];
    }

    /**
     * @param list<array<string, mixed>> $manifestEntries
     * @param list<array<string, mixed>> $undeclaredPackageEntries
     * @return array<string, mixed>
     */
    private static function packageGalleryMetadata(ZipPackage $package, array $manifestEntries, array $undeclaredPackageEntries): array
    {
        $candidatesByPath = [];
        foreach ($manifestEntries as $entry) {
            $packagePath = $entry['packagePath'] ?? null;
            if (!is_string($packagePath) || $packagePath === '' || !self::isGalleryPackagePartName($packagePath)) {
                continue;
            }

            $entry['declared'] = true;
            $candidatesByPath[$packagePath] = $entry;
        }

        foreach ($undeclaredPackageEntries as $entry) {
            $packagePath = $entry['path'] ?? null;
            if (!is_string($packagePath) || $packagePath === '' || !self::isGalleryPackagePartName($packagePath)) {
                continue;
            }

            $mediaType = self::galleryMediaTypeFromPart($packagePath) ?? '';
            $mediaTypeReport = self::mediaTypeReport($mediaType);
            $candidatesByPath[$packagePath] = [
                'path' => $packagePath,
                'packagePath' => $packagePath,
                'pathReference' => $packagePath,
                'pathSuffix' => null,
                'pathQuery' => null,
                'pathFragment' => null,
                'mediaType' => $mediaType,
                'mediaTypeBase' => $mediaTypeReport['mediaTypeBase'],
                'mediaTypeHasParameters' => false,
                'mediaTypeParameterCount' => 0,
                'mediaTypeParameters' => [],
                'mediaTypeParameterMap' => [],
                'exists' => true,
                'isDirectory' => str_ends_with($packagePath, '/'),
                'encrypted' => false,
                'declared' => false,
                'declaredSize' => null,
                'declaredSizeMismatch' => false,
                'byteExposurePolicy' => str_ends_with($packagePath, '/') ? 'directory-entry-no-bytes' : 'gallery-package-bytes-blocked',
            ];
        }

        ksort($candidatesByPath, SORT_STRING);

        $items = [];
        $issueCodes = [];
        $kindCounts = [];
        $groupCounts = [];
        foreach ($candidatesByPath as $packagePath => $entry) {
            $zipEntry = $package->has($packagePath) ? $package->entry($packagePath) : null;
            $isDirectory = str_ends_with($packagePath, '/');
            $exists = $isDirectory || $zipEntry instanceof ZipPackageEntry;
            $encrypted = ($entry['encrypted'] ?? false) === true;
            $declared = ($entry['declared'] ?? false) === true;
            $mediaType = (string) ($entry['mediaType'] ?? '');
            if ($mediaType === '') {
                $mediaType = self::galleryMediaTypeFromPart($packagePath) ?? '';
            }
            $mediaTypeReport = self::mediaTypeReport($mediaType);
            $missingMediaType = $mediaType === '' && !$isDirectory;
            $mediaTypeValid = $isDirectory ? $mediaTypeReport['mediaTypeBase'] === '' : (!$missingMediaType && self::isGalleryMediaType($mediaType));
            $kind = self::galleryPackagePartKind($packagePath, $mediaType, $isDirectory);
            $group = self::galleryPackagePartGroup($packagePath);
            $issues = [];
            if (!$exists) {
                $issues[] = 'odf-gallery-package-missing-part';
            }
            if (!$declared) {
                $issues[] = 'odf-gallery-package-undeclared-part';
            }
            if ($encrypted) {
                $issues[] = 'odf-gallery-package-encrypted-part';
            }
            if ($missingMediaType) {
                $issues[] = 'odf-gallery-package-missing-media-type';
            } elseif (!$mediaTypeValid) {
                $issues[] = 'odf-gallery-package-invalid-media-type';
            }
            foreach ($issues as $issue) {
                $issueCodes[$issue] = true;
            }
            $kindCounts[$kind] = ($kindCounts[$kind] ?? 0) + 1;
            if ($group !== null) {
                $groupCounts[$group] = ($groupCounts[$group] ?? 0) + 1;
            }

            $items[] = [
                'fullPath' => $entry['path'] ?? $packagePath,
                'path' => $entry['path'] ?? $packagePath,
                'packagePath' => $packagePath,
                'part' => $packagePath,
                'pathReference' => $entry['pathReference'] ?? null,
                'pathSuffix' => $entry['pathSuffix'] ?? null,
                'pathQuery' => $entry['pathQuery'] ?? null,
                'pathFragment' => $entry['pathFragment'] ?? null,
                'mediaType' => $mediaType === '' ? null : $mediaType,
                'mediaTypeBase' => $mediaTypeReport['mediaTypeBase'],
                'mediaTypeHasParameters' => $mediaTypeReport['mediaTypeHasParameters'],
                'mediaTypeParameterCount' => $mediaTypeReport['mediaTypeParameterCount'],
                'mediaTypeParameters' => $mediaTypeReport['mediaTypeParameters'],
                'mediaTypeParameterMap' => $mediaTypeReport['mediaTypeParameterMap'],
                'kind' => $kind,
                'group' => $group,
                'packageRoot' => 'Gallery',
                'isDirectory' => $isDirectory,
                'exists' => $exists,
                'declared' => $declared,
                'undeclared' => !$declared,
                'encrypted' => $encrypted,
                'valid' => $exists && !$encrypted && $mediaTypeValid,
                'byteLength' => !$isDirectory && !$encrypted && $zipEntry instanceof ZipPackageEntry ? $zipEntry->uncompressedSize : null,
                'compressedByteLength' => $zipEntry instanceof ZipPackageEntry ? $zipEntry->compressedSize : null,
                'compressionMethod' => $zipEntry instanceof ZipPackageEntry ? $zipEntry->compressionMethod : null,
                'compressionMethodName' => $zipEntry instanceof ZipPackageEntry ? self::compressionMethodName($zipEntry->compressionMethod) : null,
                'crc32' => !$isDirectory && !$encrypted && $zipEntry instanceof ZipPackageEntry ? $zipEntry->crc32Hex() : null,
                'storedByteLength' => $zipEntry instanceof ZipPackageEntry ? $zipEntry->uncompressedSize : null,
                'storedCrc32' => $zipEntry instanceof ZipPackageEntry ? $zipEntry->crc32Hex() : null,
                'declaredSize' => $entry['declaredSize'] ?? $entry['size'] ?? null,
                'declaredSizeMismatch' => ($entry['declaredSizeMismatch'] ?? false) === true,
                'canExposeBytes' => false,
                'canExposeAsDocumentMedia' => false,
                'byteExposurePolicy' => $entry['byteExposurePolicy'] ?? ($isDirectory ? 'directory-entry-no-bytes' : 'gallery-package-bytes-blocked'),
                'reviewPolicy' => 'gallery-package-metadata-only',
                'encryption' => $entry['encryption'] ?? null,
                'issues' => $issues,
            ];
        }

        ksort($issueCodes, SORT_STRING);
        ksort($kindCounts, SORT_STRING);
        ksort($groupCounts, SORT_STRING);

        return [
            'count' => count($items),
            'readableCount' => count(array_filter(
                $items,
                static fn (array $item): bool => $item['exists'] === true && ($item['byteLength'] ?? null) !== null,
            )),
            'declaredCount' => count(array_filter($items, static fn (array $item): bool => $item['declared'] === true)),
            'undeclaredCount' => count(array_filter($items, static fn (array $item): bool => $item['undeclared'] === true)),
            'missingCount' => count(array_filter($items, static fn (array $item): bool => $item['exists'] !== true)),
            'directoryCount' => count(array_filter($items, static fn (array $item): bool => $item['isDirectory'] === true)),
            'encryptedCount' => count(array_filter($items, static fn (array $item): bool => $item['encrypted'] === true)),
            'missingMediaTypeCount' => count(array_filter($items, static fn (array $item): bool => in_array('odf-gallery-package-missing-media-type', $item['issues'], true))),
            'invalidMediaTypeCount' => count(array_filter($items, static fn (array $item): bool => in_array('odf-gallery-package-invalid-media-type', $item['issues'], true))),
            'issueCount' => count(array_filter($items, static fn (array $item): bool => $item['issues'] !== [])),
            'issueCodes' => array_keys($issueCodes),
            'kindCounts' => $kindCounts,
            'groupCounts' => $groupCounts,
            'byteExposurePolicy' => 'gallery-package-bytes-blocked',
            'reviewPolicy' => 'gallery-package-metadata-only',
            'items' => $items,
        ];
    }

    private static function galleryMediaTypeFromPart(string $path): ?string
    {
        if (str_ends_with($path, '/')) {
            return '';
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return match ($extension) {
            'xml', 'thm' => 'text/xml',
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'svg' => 'image/svg+xml',
            'bmp' => 'image/bmp',
            'tif', 'tiff' => 'image/tiff',
            'svm' => 'application/vnd.sun.xml.gallery',
            'sdg', 'sdv', 'str', 'dat', 'bin' => 'application/octet-stream',
            'wav' => 'audio/wav',
            'mp3' => 'audio/mpeg',
            'ogg' => 'audio/ogg',
            default => null,
        };
    }

    private static function isGalleryMediaType(string $mediaType): bool
    {
        $base = self::mediaTypeReport($mediaType)['mediaTypeBase'];

        return self::isXmlMediaTypeBase($base)
            || self::mediaResourceFamilyFromMediaTypeBase($base) !== null
            || in_array($base, ['application/octet-stream', 'application/binary', 'application/vnd.sun.xml.gallery'], true)
            || str_starts_with($base, 'application/vnd.');
    }

    private static function galleryPackagePartKind(string $path, string $mediaType, bool $isDirectory): string
    {
        if ($isDirectory) {
            return 'gallery-directory';
        }

        $basename = strtolower(basename($path));
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if ($extension === 'thm' || (str_starts_with($basename, 'sg') && in_array($extension, ['sdg', 'sdv', 'str'], true))) {
            return 'gallery-index';
        }

        $base = self::mediaTypeReport($mediaType)['mediaTypeBase'];
        if (self::mediaResourceFamilyFromMediaTypeBase($base) !== null) {
            return 'gallery-media-resource';
        }
        if (self::isXmlMediaTypeBase($base)) {
            return 'gallery-xml';
        }
        if (in_array($base, ['application/octet-stream', 'application/binary', 'application/vnd.sun.xml.gallery'], true)) {
            return 'gallery-binary-resource';
        }

        return 'gallery-resource';
    }

    private static function galleryPackagePartGroup(string $path): ?string
    {
        $segments = explode('/', trim($path, '/'));
        $group = strtolower($segments[1] ?? '');

        return $group === '' ? null : $group;
    }

    /**
     * @param list<array<string, mixed>> $manifestEntries
     * @param list<array<string, mixed>> $undeclaredPackageEntries
     * @return array<string, mixed>
     */
    private static function packageFormMetadata(ZipPackage $package, array $manifestEntries, array $undeclaredPackageEntries): array
    {
        $candidatesByPath = [];
        foreach ($manifestEntries as $entry) {
            $packagePath = $entry['packagePath'] ?? null;
            if (!is_string($packagePath) || $packagePath === '' || !self::isFormPackagePartName($packagePath)) {
                continue;
            }

            $entry['declared'] = true;
            $candidatesByPath[$packagePath] = $entry;
        }

        foreach ($undeclaredPackageEntries as $entry) {
            $packagePath = $entry['path'] ?? null;
            if (!is_string($packagePath) || $packagePath === '' || !self::isFormPackagePartName($packagePath)) {
                continue;
            }

            $mediaType = self::formMediaTypeFromPart($packagePath) ?? '';
            $mediaTypeReport = self::mediaTypeReport($mediaType);
            $candidatesByPath[$packagePath] = [
                'path' => $packagePath,
                'packagePath' => $packagePath,
                'pathReference' => $packagePath,
                'pathSuffix' => null,
                'pathQuery' => null,
                'pathFragment' => null,
                'mediaType' => $mediaType,
                'mediaTypeBase' => $mediaTypeReport['mediaTypeBase'],
                'mediaTypeHasParameters' => false,
                'mediaTypeParameterCount' => 0,
                'mediaTypeParameters' => [],
                'mediaTypeParameterMap' => [],
                'exists' => true,
                'isDirectory' => str_ends_with($packagePath, '/'),
                'encrypted' => false,
                'declared' => false,
                'declaredSize' => null,
                'declaredSizeMismatch' => false,
                'byteExposurePolicy' => str_ends_with($packagePath, '/') ? 'directory-entry-no-bytes' : 'form-package-bytes-blocked',
            ];
        }

        ksort($candidatesByPath, SORT_STRING);

        $items = [];
        $issueCodes = [];
        $kindCounts = [];
        $groupCounts = [];
        foreach ($candidatesByPath as $packagePath => $entry) {
            $zipEntry = $package->has($packagePath) ? $package->entry($packagePath) : null;
            $isDirectory = str_ends_with($packagePath, '/');
            $exists = $isDirectory || $zipEntry instanceof ZipPackageEntry;
            $encrypted = ($entry['encrypted'] ?? false) === true;
            $declared = ($entry['declared'] ?? false) === true;
            $mediaType = (string) ($entry['mediaType'] ?? '');
            if ($mediaType === '') {
                $mediaType = self::formMediaTypeFromPart($packagePath) ?? '';
            }
            $mediaTypeReport = self::mediaTypeReport($mediaType);
            $missingMediaType = $mediaType === '' && !$isDirectory;
            $mediaTypeValid = $isDirectory
                ? $mediaTypeReport['mediaTypeBase'] === ''
                : (!$missingMediaType && self::isFormMediaType($mediaType));
            $kind = self::formPackagePartKind($packagePath, $mediaType, $isDirectory);
            $group = self::formPackagePartGroup($packagePath);
            $issues = [];
            if (!$exists) {
                $issues[] = 'odf-form-package-missing-part';
            }
            if (!$declared) {
                $issues[] = 'odf-form-package-undeclared-part';
            }
            if ($encrypted) {
                $issues[] = 'odf-form-package-encrypted-part';
            }
            if ($missingMediaType) {
                $issues[] = 'odf-form-package-missing-media-type';
            } elseif (!$mediaTypeValid) {
                $issues[] = 'odf-form-package-invalid-media-type';
            }
            foreach ($issues as $issue) {
                $issueCodes[$issue] = true;
            }
            $kindCounts[$kind] = ($kindCounts[$kind] ?? 0) + 1;
            if ($group !== null) {
                $groupCounts[$group] = ($groupCounts[$group] ?? 0) + 1;
            }

            $items[] = [
                'fullPath' => $entry['path'] ?? $packagePath,
                'path' => $entry['path'] ?? $packagePath,
                'packagePath' => $packagePath,
                'part' => $packagePath,
                'pathReference' => $entry['pathReference'] ?? null,
                'pathSuffix' => $entry['pathSuffix'] ?? null,
                'pathQuery' => $entry['pathQuery'] ?? null,
                'pathFragment' => $entry['pathFragment'] ?? null,
                'mediaType' => $mediaType === '' ? null : $mediaType,
                'mediaTypeBase' => $mediaTypeReport['mediaTypeBase'],
                'mediaTypeHasParameters' => $mediaTypeReport['mediaTypeHasParameters'],
                'mediaTypeParameterCount' => $mediaTypeReport['mediaTypeParameterCount'],
                'mediaTypeParameters' => $mediaTypeReport['mediaTypeParameters'],
                'mediaTypeParameterMap' => $mediaTypeReport['mediaTypeParameterMap'],
                'kind' => $kind,
                'group' => $group,
                'packageRoot' => 'Forms',
                'isDirectory' => $isDirectory,
                'exists' => $exists,
                'declared' => $declared,
                'undeclared' => !$declared,
                'encrypted' => $encrypted,
                'valid' => $exists && !$encrypted && $mediaTypeValid,
                'byteLength' => !$isDirectory && !$encrypted && $zipEntry instanceof ZipPackageEntry ? $zipEntry->uncompressedSize : null,
                'compressedByteLength' => $zipEntry instanceof ZipPackageEntry ? $zipEntry->compressedSize : null,
                'compressionMethod' => $zipEntry instanceof ZipPackageEntry ? $zipEntry->compressionMethod : null,
                'compressionMethodName' => $zipEntry instanceof ZipPackageEntry ? self::compressionMethodName($zipEntry->compressionMethod) : null,
                'crc32' => !$isDirectory && !$encrypted && $zipEntry instanceof ZipPackageEntry ? $zipEntry->crc32Hex() : null,
                'storedByteLength' => $zipEntry instanceof ZipPackageEntry ? $zipEntry->uncompressedSize : null,
                'storedCrc32' => $zipEntry instanceof ZipPackageEntry ? $zipEntry->crc32Hex() : null,
                'declaredSize' => $entry['declaredSize'] ?? $entry['size'] ?? null,
                'declaredSizeMismatch' => ($entry['declaredSizeMismatch'] ?? false) === true,
                'canExposeBytes' => false,
                'canExposeAsDocumentMedia' => false,
                'byteExposurePolicy' => $entry['byteExposurePolicy'] ?? ($isDirectory ? 'directory-entry-no-bytes' : 'form-package-bytes-blocked'),
                'reviewPolicy' => 'form-package-metadata-only',
                'encryption' => $entry['encryption'] ?? null,
                'issues' => $issues,
            ];
        }

        ksort($issueCodes, SORT_STRING);
        ksort($kindCounts, SORT_STRING);
        ksort($groupCounts, SORT_STRING);

        return [
            'count' => count($items),
            'readableCount' => count(array_filter(
                $items,
                static fn (array $item): bool => $item['exists'] === true && ($item['byteLength'] ?? null) !== null,
            )),
            'declaredCount' => count(array_filter($items, static fn (array $item): bool => $item['declared'] === true)),
            'undeclaredCount' => count(array_filter($items, static fn (array $item): bool => $item['undeclared'] === true)),
            'missingCount' => count(array_filter($items, static fn (array $item): bool => $item['exists'] !== true)),
            'directoryCount' => count(array_filter($items, static fn (array $item): bool => $item['isDirectory'] === true)),
            'encryptedCount' => count(array_filter($items, static fn (array $item): bool => $item['encrypted'] === true)),
            'missingMediaTypeCount' => count(array_filter($items, static fn (array $item): bool => in_array('odf-form-package-missing-media-type', $item['issues'], true))),
            'invalidMediaTypeCount' => count(array_filter($items, static fn (array $item): bool => in_array('odf-form-package-invalid-media-type', $item['issues'], true))),
            'issueCount' => count(array_filter($items, static fn (array $item): bool => $item['issues'] !== [])),
            'issueCodes' => array_keys($issueCodes),
            'kindCounts' => $kindCounts,
            'groupCounts' => $groupCounts,
            'byteExposurePolicy' => 'form-package-bytes-blocked',
            'reviewPolicy' => 'form-package-metadata-only',
            'items' => $items,
        ];
    }

    private static function formMediaTypeFromPart(string $path): ?string
    {
        if (str_ends_with($path, '/')) {
            return '';
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return match ($extension) {
            'xml', 'xforms', 'xhtml', 'html' => 'text/xml',
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'svg' => 'image/svg+xml',
            'txt', 'properties' => 'text/plain',
            'bin', 'dat' => 'application/octet-stream',
            default => null,
        };
    }

    private static function isFormMediaType(string $mediaType): bool
    {
        $base = self::mediaTypeReport($mediaType)['mediaTypeBase'];

        return self::isXmlMediaTypeBase($base)
            || self::mediaResourceFamilyFromMediaTypeBase($base) !== null
            || in_array($base, ['text/plain', 'application/octet-stream', 'application/binary'], true)
            || str_starts_with($base, 'application/vnd.')
            || str_starts_with($base, 'application/x-');
    }

    private static function formPackagePartKind(string $path, string $mediaType, bool $isDirectory): string
    {
        if ($isDirectory) {
            return 'form-directory';
        }

        $basename = strtolower(basename($path));
        $base = self::mediaTypeReport($mediaType)['mediaTypeBase'];
        if (in_array($basename, ['form.xml', 'forms.xml', 'content.xml', 'settings.xml'], true)
            || self::isXmlMediaTypeBase($base)
        ) {
            return 'form-definition';
        }
        if (self::mediaResourceFamilyFromMediaTypeBase($base) !== null) {
            return 'form-media-resource';
        }
        if (in_array($base, ['application/octet-stream', 'application/binary'], true)) {
            return 'form-binary-resource';
        }

        return 'form-resource';
    }

    private static function formPackagePartGroup(string $path): ?string
    {
        $segments = explode('/', trim($path, '/'));
        $group = strtolower($segments[1] ?? '');

        return $group === '' ? null : $group;
    }

    /**
     * @param list<array<string, mixed>> $manifestEntries
     * @param list<array<string, mixed>> $undeclaredPackageEntries
     * @return array<string, mixed>
     */
    private static function packageReportMetadata(ZipPackage $package, array $manifestEntries, array $undeclaredPackageEntries): array
    {
        $candidatesByPath = [];
        foreach ($manifestEntries as $entry) {
            $packagePath = $entry['packagePath'] ?? null;
            if (!is_string($packagePath) || $packagePath === '' || !self::isReportPackagePartName($packagePath)) {
                continue;
            }

            $entry['declared'] = true;
            $candidatesByPath[$packagePath] = $entry;
        }

        foreach ($undeclaredPackageEntries as $entry) {
            $packagePath = $entry['path'] ?? null;
            if (!is_string($packagePath) || $packagePath === '' || !self::isReportPackagePartName($packagePath)) {
                continue;
            }

            $mediaType = self::reportMediaTypeFromPart($packagePath) ?? '';
            $mediaTypeReport = self::mediaTypeReport($mediaType);
            $candidatesByPath[$packagePath] = [
                'path' => $packagePath,
                'packagePath' => $packagePath,
                'pathReference' => $packagePath,
                'pathSuffix' => null,
                'pathQuery' => null,
                'pathFragment' => null,
                'mediaType' => $mediaType,
                'mediaTypeBase' => $mediaTypeReport['mediaTypeBase'],
                'mediaTypeHasParameters' => false,
                'mediaTypeParameterCount' => 0,
                'mediaTypeParameters' => [],
                'mediaTypeParameterMap' => [],
                'exists' => true,
                'isDirectory' => str_ends_with($packagePath, '/'),
                'encrypted' => false,
                'declared' => false,
                'declaredSize' => null,
                'declaredSizeMismatch' => false,
                'byteExposurePolicy' => str_ends_with($packagePath, '/') ? 'directory-entry-no-bytes' : 'report-package-bytes-blocked',
            ];
        }

        ksort($candidatesByPath, SORT_STRING);

        $items = [];
        $issueCodes = [];
        $kindCounts = [];
        $groupCounts = [];
        foreach ($candidatesByPath as $packagePath => $entry) {
            $zipEntry = $package->has($packagePath) ? $package->entry($packagePath) : null;
            $isDirectory = str_ends_with($packagePath, '/');
            $exists = $isDirectory || $zipEntry instanceof ZipPackageEntry;
            $encrypted = ($entry['encrypted'] ?? false) === true;
            $declared = ($entry['declared'] ?? false) === true;
            $mediaType = (string) ($entry['mediaType'] ?? '');
            if ($mediaType === '') {
                $mediaType = self::reportMediaTypeFromPart($packagePath) ?? '';
            }
            $mediaTypeReport = self::mediaTypeReport($mediaType);
            $missingMediaType = $mediaType === '' && !$isDirectory;
            $mediaTypeValid = $isDirectory
                ? $mediaTypeReport['mediaTypeBase'] === ''
                : (!$missingMediaType && self::isReportMediaType($mediaType));
            $kind = self::reportPackagePartKind($packagePath, $mediaType, $isDirectory);
            $group = self::reportPackagePartGroup($packagePath);
            $issues = [];
            if (!$exists) {
                $issues[] = 'odf-report-package-missing-part';
            }
            if (!$declared) {
                $issues[] = 'odf-report-package-undeclared-part';
            }
            if ($encrypted) {
                $issues[] = 'odf-report-package-encrypted-part';
            }
            if ($missingMediaType) {
                $issues[] = 'odf-report-package-missing-media-type';
            } elseif (!$mediaTypeValid) {
                $issues[] = 'odf-report-package-invalid-media-type';
            }
            if (($entry['declaredSizeInvalid'] ?? false) === true) {
                $issues[] = 'odf-report-package-invalid-declared-size';
            }
            foreach ($issues as $issue) {
                $issueCodes[$issue] = true;
            }
            $kindCounts[$kind] = ($kindCounts[$kind] ?? 0) + 1;
            if ($group !== null) {
                $groupCounts[$group] = ($groupCounts[$group] ?? 0) + 1;
            }

            $items[] = [
                'fullPath' => $entry['path'] ?? $packagePath,
                'path' => $entry['path'] ?? $packagePath,
                'packagePath' => $packagePath,
                'part' => $packagePath,
                'pathReference' => $entry['pathReference'] ?? null,
                'pathSuffix' => $entry['pathSuffix'] ?? null,
                'pathQuery' => $entry['pathQuery'] ?? null,
                'pathFragment' => $entry['pathFragment'] ?? null,
                'mediaType' => $mediaType === '' ? null : $mediaType,
                'mediaTypeBase' => $mediaTypeReport['mediaTypeBase'],
                'mediaTypeHasParameters' => $mediaTypeReport['mediaTypeHasParameters'],
                'mediaTypeParameterCount' => $mediaTypeReport['mediaTypeParameterCount'],
                'mediaTypeParameters' => $mediaTypeReport['mediaTypeParameters'],
                'mediaTypeParameterMap' => $mediaTypeReport['mediaTypeParameterMap'],
                'kind' => $kind,
                'group' => $group,
                'packageRoot' => 'Reports',
                'isDirectory' => $isDirectory,
                'exists' => $exists,
                'declared' => $declared,
                'undeclared' => !$declared,
                'encrypted' => $encrypted,
                'valid' => $exists && !$encrypted && $mediaTypeValid,
                'byteLength' => !$isDirectory && !$encrypted && $zipEntry instanceof ZipPackageEntry ? $zipEntry->uncompressedSize : null,
                'compressedByteLength' => $zipEntry instanceof ZipPackageEntry ? $zipEntry->compressedSize : null,
                'compressionMethod' => $zipEntry instanceof ZipPackageEntry ? $zipEntry->compressionMethod : null,
                'compressionMethodName' => $zipEntry instanceof ZipPackageEntry ? self::compressionMethodName($zipEntry->compressionMethod) : null,
                'crc32' => !$isDirectory && !$encrypted && $zipEntry instanceof ZipPackageEntry ? $zipEntry->crc32Hex() : null,
                'storedByteLength' => $zipEntry instanceof ZipPackageEntry ? $zipEntry->uncompressedSize : null,
                'storedCrc32' => $zipEntry instanceof ZipPackageEntry ? $zipEntry->crc32Hex() : null,
                'declaredSize' => $entry['declaredSize'] ?? $entry['size'] ?? null,
                'declaredSizeRaw' => $entry['declaredSizeRaw'] ?? null,
                'declaredSizeValid' => ($entry['declaredSizeValid'] ?? false) === true,
                'declaredSizeInvalid' => ($entry['declaredSizeInvalid'] ?? false) === true,
                'declaredSizeMismatch' => ($entry['declaredSizeMismatch'] ?? false) === true,
                'canExposeBytes' => false,
                'canExposeAsDocumentMedia' => false,
                'byteExposurePolicy' => $entry['byteExposurePolicy'] ?? ($isDirectory ? 'directory-entry-no-bytes' : 'report-package-bytes-blocked'),
                'reviewPolicy' => 'report-package-metadata-only',
                'encryption' => $entry['encryption'] ?? null,
                'issues' => $issues,
            ];
        }

        ksort($issueCodes, SORT_STRING);
        ksort($kindCounts, SORT_STRING);
        ksort($groupCounts, SORT_STRING);

        return [
            'count' => count($items),
            'readableCount' => count(array_filter(
                $items,
                static fn (array $item): bool => $item['exists'] === true && ($item['byteLength'] ?? null) !== null,
            )),
            'declaredCount' => count(array_filter($items, static fn (array $item): bool => $item['declared'] === true)),
            'undeclaredCount' => count(array_filter($items, static fn (array $item): bool => $item['undeclared'] === true)),
            'missingCount' => count(array_filter($items, static fn (array $item): bool => $item['exists'] !== true)),
            'directoryCount' => count(array_filter($items, static fn (array $item): bool => $item['isDirectory'] === true)),
            'encryptedCount' => count(array_filter($items, static fn (array $item): bool => $item['encrypted'] === true)),
            'missingMediaTypeCount' => count(array_filter($items, static fn (array $item): bool => in_array('odf-report-package-missing-media-type', $item['issues'], true))),
            'invalidMediaTypeCount' => count(array_filter($items, static fn (array $item): bool => in_array('odf-report-package-invalid-media-type', $item['issues'], true))),
            'invalidDeclaredSizeCount' => count(array_filter($items, static fn (array $item): bool => $item['declaredSizeInvalid'] === true)),
            'issueCount' => count(array_filter($items, static fn (array $item): bool => $item['issues'] !== [])),
            'issueCodes' => array_keys($issueCodes),
            'kindCounts' => $kindCounts,
            'groupCounts' => $groupCounts,
            'byteExposurePolicy' => 'report-package-bytes-blocked',
            'reviewPolicy' => 'report-package-metadata-only',
            'items' => $items,
        ];
    }

    private static function reportMediaTypeFromPart(string $path): ?string
    {
        if (str_ends_with($path, '/')) {
            return '';
        }

        return match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'xml', 'xhtml', 'html', 'xsl', 'fo' => 'text/xml',
            'odt' => self::TEXT_MIMETYPE,
            'fodt' => 'application/vnd.oasis.opendocument.text-flat-xml',
            'ods' => 'application/vnd.oasis.opendocument.spreadsheet',
            'fods' => 'application/vnd.oasis.opendocument.spreadsheet-flat-xml',
            'odp' => 'application/vnd.oasis.opendocument.presentation',
            'fodp' => 'application/vnd.oasis.opendocument.presentation-flat-xml',
            'odg' => 'application/vnd.oasis.opendocument.graphics',
            'fodg' => 'application/vnd.oasis.opendocument.graphics-flat-xml',
            'odf' => 'application/vnd.oasis.opendocument.formula',
            'odc' => 'application/vnd.oasis.opendocument.chart',
            'odb' => 'application/vnd.oasis.opendocument.database',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'pdf' => 'application/pdf',
            'csv' => 'text/csv',
            'txt', 'properties' => 'text/plain',
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'svg' => 'image/svg+xml',
            'zip' => 'application/zip',
            'bin', 'dat' => 'application/octet-stream',
            default => null,
        };
    }

    private static function isReportMediaType(string $mediaType): bool
    {
        $base = self::mediaTypeReport($mediaType)['mediaTypeBase'];

        return self::isXmlMediaTypeBase($base)
            || self::mediaResourceFamilyFromMediaTypeBase($base) !== null
            || in_array($base, ['application/pdf', 'text/plain', 'text/csv', 'application/zip', 'application/octet-stream', 'application/binary'], true)
            || str_starts_with($base, 'application/vnd.oasis.opendocument.')
            || str_starts_with($base, 'application/vnd.')
            || str_starts_with($base, 'application/x-');
    }

    private static function reportPackagePartKind(string $path, string $mediaType, bool $isDirectory): string
    {
        if ($isDirectory) {
            return 'report-directory';
        }

        $basename = strtolower(basename($path));
        $base = self::mediaTypeReport($mediaType)['mediaTypeBase'];
        if (in_array($basename, ['manifest.xml', 'metadata.xml', 'settings.xml', 'content.xml'], true) || self::isXmlMediaTypeBase($base)) {
            return 'report-definition';
        }
        if (str_starts_with($base, 'application/vnd.oasis.opendocument.')
            || str_starts_with($base, 'application/vnd.openxmlformats-officedocument.')
        ) {
            return 'report-document';
        }
        if (self::mediaResourceFamilyFromMediaTypeBase($base) !== null) {
            return 'report-preview-media';
        }
        if (in_array($base, ['application/pdf', 'text/plain', 'text/csv'], true)) {
            return 'report-output-resource';
        }
        if (in_array($base, ['application/zip', 'application/octet-stream', 'application/binary'], true)) {
            return 'report-binary-resource';
        }

        return 'report-resource';
    }

    private static function reportPackagePartGroup(string $path): ?string
    {
        $segments = explode('/', trim($path, '/'));
        $group = strtolower($segments[1] ?? '');

        return $group === '' ? null : $group;
    }

    /**
     * @param list<array<string, mixed>> $manifestEntries
     * @param list<array<string, mixed>> $undeclaredPackageEntries
     * @return array<string, mixed>
     */
    private static function packageAttachmentMetadata(ZipPackage $package, array $manifestEntries, array $undeclaredPackageEntries): array
    {
        $candidatesByPath = [];
        foreach ($manifestEntries as $entry) {
            $packagePath = $entry['packagePath'] ?? null;
            if (!is_string($packagePath) || $packagePath === '' || !self::isAttachmentPackagePartName($packagePath)) {
                continue;
            }

            $entry['declared'] = true;
            $candidatesByPath[$packagePath] = $entry;
        }

        foreach ($undeclaredPackageEntries as $entry) {
            $packagePath = $entry['path'] ?? null;
            if (!is_string($packagePath) || $packagePath === '' || !self::isAttachmentPackagePartName($packagePath)) {
                continue;
            }

            $mediaType = self::attachmentMediaTypeFromPart($packagePath) ?? '';
            $mediaTypeReport = self::mediaTypeReport($mediaType);
            $candidatesByPath[$packagePath] = [
                'path' => $packagePath,
                'packagePath' => $packagePath,
                'pathReference' => $packagePath,
                'pathSuffix' => null,
                'pathQuery' => null,
                'pathFragment' => null,
                'mediaType' => $mediaType,
                'mediaTypeBase' => $mediaTypeReport['mediaTypeBase'],
                'mediaTypeHasParameters' => false,
                'mediaTypeParameterCount' => 0,
                'mediaTypeParameters' => [],
                'mediaTypeParameterMap' => [],
                'exists' => true,
                'isDirectory' => str_ends_with($packagePath, '/'),
                'encrypted' => false,
                'declared' => false,
                'declaredSize' => null,
                'declaredSizeMismatch' => false,
                'byteExposurePolicy' => str_ends_with($packagePath, '/') ? 'directory-entry-no-bytes' : 'attachment-package-bytes-blocked',
            ];
        }

        ksort($candidatesByPath, SORT_STRING);

        $items = [];
        $issueCodes = [];
        $kindCounts = [];
        $groupCounts = [];
        foreach ($candidatesByPath as $packagePath => $entry) {
            $zipEntry = $package->has($packagePath) ? $package->entry($packagePath) : null;
            $isDirectory = str_ends_with($packagePath, '/');
            $exists = $isDirectory || $zipEntry instanceof ZipPackageEntry;
            $encrypted = ($entry['encrypted'] ?? false) === true;
            $declared = ($entry['declared'] ?? false) === true;
            $mediaType = (string) ($entry['mediaType'] ?? '');
            if ($mediaType === '') {
                $mediaType = self::attachmentMediaTypeFromPart($packagePath) ?? '';
            }
            $mediaTypeReport = self::mediaTypeReport($mediaType);
            $missingMediaType = $mediaType === '' && !$isDirectory;
            $mediaTypeValid = $isDirectory
                ? $mediaTypeReport['mediaTypeBase'] === ''
                : (!$missingMediaType && self::isAttachmentMediaType($mediaType));
            $kind = self::attachmentPackagePartKind($packagePath, $mediaType, $isDirectory);
            $group = self::attachmentPackagePartGroup($packagePath);
            $issues = [];
            if (!$exists) {
                $issues[] = 'odf-attachment-package-missing-part';
            }
            if (!$declared) {
                $issues[] = 'odf-attachment-package-undeclared-part';
            }
            if ($encrypted) {
                $issues[] = 'odf-attachment-package-encrypted-part';
            }
            if ($missingMediaType) {
                $issues[] = 'odf-attachment-package-missing-media-type';
            } elseif (!$mediaTypeValid) {
                $issues[] = 'odf-attachment-package-invalid-media-type';
            }
            if (($entry['declaredSizeInvalid'] ?? false) === true) {
                $issues[] = 'odf-attachment-package-invalid-declared-size';
            }
            foreach ($issues as $issue) {
                $issueCodes[$issue] = true;
            }
            $kindCounts[$kind] = ($kindCounts[$kind] ?? 0) + 1;
            if ($group !== null) {
                $groupCounts[$group] = ($groupCounts[$group] ?? 0) + 1;
            }

            $items[] = [
                'fullPath' => $entry['path'] ?? $packagePath,
                'path' => $entry['path'] ?? $packagePath,
                'packagePath' => $packagePath,
                'part' => $packagePath,
                'pathReference' => $entry['pathReference'] ?? null,
                'pathSuffix' => $entry['pathSuffix'] ?? null,
                'pathQuery' => $entry['pathQuery'] ?? null,
                'pathFragment' => $entry['pathFragment'] ?? null,
                'mediaType' => $mediaType === '' ? null : $mediaType,
                'mediaTypeBase' => $mediaTypeReport['mediaTypeBase'],
                'mediaTypeHasParameters' => $mediaTypeReport['mediaTypeHasParameters'],
                'mediaTypeParameterCount' => $mediaTypeReport['mediaTypeParameterCount'],
                'mediaTypeParameters' => $mediaTypeReport['mediaTypeParameters'],
                'mediaTypeParameterMap' => $mediaTypeReport['mediaTypeParameterMap'],
                'kind' => $kind,
                'group' => $group,
                'packageRoot' => 'Attachments',
                'isDirectory' => $isDirectory,
                'exists' => $exists,
                'declared' => $declared,
                'undeclared' => !$declared,
                'encrypted' => $encrypted,
                'valid' => $exists && !$encrypted && $mediaTypeValid,
                'byteLength' => !$isDirectory && !$encrypted && $zipEntry instanceof ZipPackageEntry ? $zipEntry->uncompressedSize : null,
                'compressedByteLength' => $zipEntry instanceof ZipPackageEntry ? $zipEntry->compressedSize : null,
                'compressionMethod' => $zipEntry instanceof ZipPackageEntry ? $zipEntry->compressionMethod : null,
                'compressionMethodName' => $zipEntry instanceof ZipPackageEntry ? self::compressionMethodName($zipEntry->compressionMethod) : null,
                'crc32' => !$isDirectory && !$encrypted && $zipEntry instanceof ZipPackageEntry ? $zipEntry->crc32Hex() : null,
                'storedByteLength' => $zipEntry instanceof ZipPackageEntry ? $zipEntry->uncompressedSize : null,
                'storedCrc32' => $zipEntry instanceof ZipPackageEntry ? $zipEntry->crc32Hex() : null,
                'declaredSize' => $entry['declaredSize'] ?? $entry['size'] ?? null,
                'declaredSizeRaw' => $entry['declaredSizeRaw'] ?? null,
                'declaredSizeValid' => ($entry['declaredSizeValid'] ?? false) === true,
                'declaredSizeInvalid' => ($entry['declaredSizeInvalid'] ?? false) === true,
                'declaredSizeMismatch' => ($entry['declaredSizeMismatch'] ?? false) === true,
                'canExposeBytes' => false,
                'canExposeAsDocumentMedia' => false,
                'byteExposurePolicy' => $entry['byteExposurePolicy'] ?? ($isDirectory ? 'directory-entry-no-bytes' : 'attachment-package-bytes-blocked'),
                'reviewPolicy' => 'attachment-package-metadata-only',
                'encryption' => $entry['encryption'] ?? null,
                'issues' => $issues,
            ];
        }

        ksort($issueCodes, SORT_STRING);
        ksort($kindCounts, SORT_STRING);
        ksort($groupCounts, SORT_STRING);

        return [
            'count' => count($items),
            'readableCount' => count(array_filter(
                $items,
                static fn (array $item): bool => $item['exists'] === true && ($item['byteLength'] ?? null) !== null,
            )),
            'declaredCount' => count(array_filter($items, static fn (array $item): bool => $item['declared'] === true)),
            'undeclaredCount' => count(array_filter($items, static fn (array $item): bool => $item['undeclared'] === true)),
            'missingCount' => count(array_filter($items, static fn (array $item): bool => $item['exists'] !== true)),
            'directoryCount' => count(array_filter($items, static fn (array $item): bool => $item['isDirectory'] === true)),
            'encryptedCount' => count(array_filter($items, static fn (array $item): bool => $item['encrypted'] === true)),
            'missingMediaTypeCount' => count(array_filter($items, static fn (array $item): bool => in_array('odf-attachment-package-missing-media-type', $item['issues'], true))),
            'invalidMediaTypeCount' => count(array_filter($items, static fn (array $item): bool => in_array('odf-attachment-package-invalid-media-type', $item['issues'], true))),
            'invalidDeclaredSizeCount' => count(array_filter($items, static fn (array $item): bool => $item['declaredSizeInvalid'] === true)),
            'issueCount' => count(array_filter($items, static fn (array $item): bool => $item['issues'] !== [])),
            'issueCodes' => array_keys($issueCodes),
            'kindCounts' => $kindCounts,
            'groupCounts' => $groupCounts,
            'byteExposurePolicy' => 'attachment-package-bytes-blocked',
            'reviewPolicy' => 'attachment-package-metadata-only',
            'items' => $items,
        ];
    }

    private static function attachmentMediaTypeFromPart(string $path): ?string
    {
        if (str_ends_with($path, '/')) {
            return '';
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return match ($extension) {
            'odt' => self::TEXT_MIMETYPE,
            'odm' => 'application/vnd.oasis.opendocument.text-master',
            'fodt' => 'application/vnd.oasis.opendocument.text-flat-xml',
            'ott' => 'application/vnd.oasis.opendocument.text-template',
            'ods' => 'application/vnd.oasis.opendocument.spreadsheet',
            'fods' => 'application/vnd.oasis.opendocument.spreadsheet-flat-xml',
            'ots' => 'application/vnd.oasis.opendocument.spreadsheet-template',
            'odp' => 'application/vnd.oasis.opendocument.presentation',
            'fodp' => 'application/vnd.oasis.opendocument.presentation-flat-xml',
            'otp' => 'application/vnd.oasis.opendocument.presentation-template',
            'odg' => 'application/vnd.oasis.opendocument.graphics',
            'fodg' => 'application/vnd.oasis.opendocument.graphics-flat-xml',
            'otg' => 'application/vnd.oasis.opendocument.graphics-template',
            'odf' => 'application/vnd.oasis.opendocument.formula',
            'odc' => 'application/vnd.oasis.opendocument.chart',
            'odb' => 'application/vnd.oasis.opendocument.database',
            'doc', 'dot' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xls', 'xlt' => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'ppt', 'pot' => 'application/vnd.ms-powerpoint',
            'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'xml', 'xhtml', 'html' => 'text/xml',
            'pdf' => 'application/pdf',
            'rtf' => 'application/rtf',
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'svg' => 'image/svg+xml',
            'txt', 'csv' => 'text/plain',
            'zip' => 'application/zip',
            'bin', 'dat' => 'application/octet-stream',
            default => null,
        };
    }

    private static function isAttachmentMediaType(string $mediaType): bool
    {
        $base = self::mediaTypeReport($mediaType)['mediaTypeBase'];

        return self::isXmlMediaTypeBase($base)
            || self::mediaResourceFamilyFromMediaTypeBase($base) !== null
            || self::isAttachmentDocumentMediaTypeBase($base)
            || in_array($base, ['text/plain', 'text/csv', 'application/pdf', 'application/zip', 'application/octet-stream', 'application/binary'], true)
            || str_starts_with($base, 'application/vnd.')
            || str_starts_with($base, 'application/x-');
    }

    private static function attachmentPackagePartKind(string $path, string $mediaType, bool $isDirectory): string
    {
        if ($isDirectory) {
            return 'attachment-directory';
        }

        $basename = strtolower(basename($path));
        $base = self::mediaTypeReport($mediaType)['mediaTypeBase'];
        if (in_array($basename, ['manifest.xml', 'metadata.xml'], true) || self::isXmlMediaTypeBase($base)) {
            return 'attachment-manifest';
        }
        if (self::mediaResourceFamilyFromMediaTypeBase($base) !== null) {
            return 'attachment-media-resource';
        }
        if (in_array($base, ['application/pdf', 'text/plain', 'text/csv'], true)
            || self::isAttachmentDocumentMediaTypeBase($base)
            || str_starts_with($base, 'application/vnd.oasis.opendocument.')
            || str_starts_with($base, 'application/vnd.openxmlformats-officedocument.')
        ) {
            return 'attachment-document-resource';
        }
        if (in_array($base, ['application/octet-stream', 'application/binary'], true)) {
            return 'attachment-binary-resource';
        }

        return 'attachment-resource';
    }

    private static function isAttachmentDocumentMediaTypeBase(string $base): bool
    {
        return in_array($base, [
            'application/msword',
            'application/rtf',
            'text/rtf',
            'application/vnd.ms-excel',
            'application/vnd.ms-powerpoint',
        ], true);
    }

    private static function attachmentPackagePartGroup(string $path): ?string
    {
        $segments = explode('/', trim($path, '/'));
        $group = strtolower($segments[1] ?? '');

        return $group === '' ? null : $group;
    }

    /**
     * @param list<array<string, mixed>> $manifestEntries
     * @param list<array<string, mixed>> $undeclaredPackageEntries
     * @return array<string, mixed>
     */
    private static function packageTemplateMetadata(ZipPackage $package, array $manifestEntries, array $undeclaredPackageEntries): array
    {
        $candidatesByPath = [];
        foreach ($manifestEntries as $entry) {
            $packagePath = $entry['packagePath'] ?? null;
            if (!is_string($packagePath) || $packagePath === '' || !self::isTemplatePackagePartName($packagePath)) {
                continue;
            }

            $entry['declared'] = true;
            $candidatesByPath[$packagePath] = $entry;
        }

        foreach ($undeclaredPackageEntries as $entry) {
            $packagePath = $entry['path'] ?? null;
            if (!is_string($packagePath) || $packagePath === '' || !self::isTemplatePackagePartName($packagePath)) {
                continue;
            }

            $mediaType = self::templateMediaTypeFromPart($packagePath) ?? '';
            $mediaTypeReport = self::mediaTypeReport($mediaType);
            $candidatesByPath[$packagePath] = [
                'path' => $packagePath,
                'packagePath' => $packagePath,
                'pathReference' => $packagePath,
                'pathSuffix' => null,
                'pathQuery' => null,
                'pathFragment' => null,
                'mediaType' => $mediaType,
                'mediaTypeBase' => $mediaTypeReport['mediaTypeBase'],
                'mediaTypeHasParameters' => false,
                'mediaTypeParameterCount' => 0,
                'mediaTypeParameters' => [],
                'mediaTypeParameterMap' => [],
                'exists' => true,
                'isDirectory' => str_ends_with($packagePath, '/'),
                'encrypted' => false,
                'declared' => false,
                'declaredSize' => null,
                'declaredSizeMismatch' => false,
                'byteExposurePolicy' => str_ends_with($packagePath, '/') ? 'directory-entry-no-bytes' : 'template-package-bytes-blocked',
            ];
        }

        ksort($candidatesByPath, SORT_STRING);

        $items = [];
        $issueCodes = [];
        $kindCounts = [];
        $groupCounts = [];
        foreach ($candidatesByPath as $packagePath => $entry) {
            $zipEntry = $package->has($packagePath) ? $package->entry($packagePath) : null;
            $isDirectory = str_ends_with($packagePath, '/');
            $exists = $isDirectory || $zipEntry instanceof ZipPackageEntry;
            $encrypted = ($entry['encrypted'] ?? false) === true;
            $declared = ($entry['declared'] ?? false) === true;
            $mediaType = (string) ($entry['mediaType'] ?? '');
            if ($mediaType === '') {
                $mediaType = self::templateMediaTypeFromPart($packagePath) ?? '';
            }
            $mediaTypeReport = self::mediaTypeReport($mediaType);
            $missingMediaType = $mediaType === '' && !$isDirectory;
            $mediaTypeValid = $isDirectory
                ? $mediaTypeReport['mediaTypeBase'] === ''
                : (!$missingMediaType && self::isTemplateMediaType($mediaType));
            $kind = self::templatePackagePartKind($packagePath, $mediaType, $isDirectory);
            $group = self::templatePackagePartGroup($packagePath);
            $issues = [];
            if (!$exists) {
                $issues[] = 'odf-template-package-missing-part';
            }
            if (!$declared) {
                $issues[] = 'odf-template-package-undeclared-part';
            }
            if ($encrypted) {
                $issues[] = 'odf-template-package-encrypted-part';
            }
            if ($missingMediaType) {
                $issues[] = 'odf-template-package-missing-media-type';
            } elseif (!$mediaTypeValid) {
                $issues[] = 'odf-template-package-invalid-media-type';
            }
            foreach ($issues as $issue) {
                $issueCodes[$issue] = true;
            }
            $kindCounts[$kind] = ($kindCounts[$kind] ?? 0) + 1;
            if ($group !== null) {
                $groupCounts[$group] = ($groupCounts[$group] ?? 0) + 1;
            }

            $items[] = [
                'fullPath' => $entry['path'] ?? $packagePath,
                'path' => $entry['path'] ?? $packagePath,
                'packagePath' => $packagePath,
                'part' => $packagePath,
                'pathReference' => $entry['pathReference'] ?? null,
                'pathSuffix' => $entry['pathSuffix'] ?? null,
                'pathQuery' => $entry['pathQuery'] ?? null,
                'pathFragment' => $entry['pathFragment'] ?? null,
                'mediaType' => $mediaType === '' ? null : $mediaType,
                'mediaTypeBase' => $mediaTypeReport['mediaTypeBase'],
                'mediaTypeHasParameters' => $mediaTypeReport['mediaTypeHasParameters'],
                'mediaTypeParameterCount' => $mediaTypeReport['mediaTypeParameterCount'],
                'mediaTypeParameters' => $mediaTypeReport['mediaTypeParameters'],
                'mediaTypeParameterMap' => $mediaTypeReport['mediaTypeParameterMap'],
                'kind' => $kind,
                'group' => $group,
                'packageRoot' => 'Templates',
                'isDirectory' => $isDirectory,
                'exists' => $exists,
                'declared' => $declared,
                'undeclared' => !$declared,
                'encrypted' => $encrypted,
                'valid' => $exists && !$encrypted && $mediaTypeValid,
                'byteLength' => !$isDirectory && !$encrypted && $zipEntry instanceof ZipPackageEntry ? $zipEntry->uncompressedSize : null,
                'compressedByteLength' => $zipEntry instanceof ZipPackageEntry ? $zipEntry->compressedSize : null,
                'compressionMethod' => $zipEntry instanceof ZipPackageEntry ? $zipEntry->compressionMethod : null,
                'compressionMethodName' => $zipEntry instanceof ZipPackageEntry ? self::compressionMethodName($zipEntry->compressionMethod) : null,
                'crc32' => !$isDirectory && !$encrypted && $zipEntry instanceof ZipPackageEntry ? $zipEntry->crc32Hex() : null,
                'storedByteLength' => $zipEntry instanceof ZipPackageEntry ? $zipEntry->uncompressedSize : null,
                'storedCrc32' => $zipEntry instanceof ZipPackageEntry ? $zipEntry->crc32Hex() : null,
                'declaredSize' => $entry['declaredSize'] ?? $entry['size'] ?? null,
                'declaredSizeMismatch' => ($entry['declaredSizeMismatch'] ?? false) === true,
                'canExposeBytes' => false,
                'canExposeAsDocumentMedia' => false,
                'byteExposurePolicy' => $entry['byteExposurePolicy'] ?? ($isDirectory ? 'directory-entry-no-bytes' : 'template-package-bytes-blocked'),
                'reviewPolicy' => 'template-package-metadata-only',
                'encryption' => $entry['encryption'] ?? null,
                'issues' => $issues,
            ];
        }

        ksort($issueCodes, SORT_STRING);
        ksort($kindCounts, SORT_STRING);
        ksort($groupCounts, SORT_STRING);

        return [
            'count' => count($items),
            'readableCount' => count(array_filter(
                $items,
                static fn (array $item): bool => $item['exists'] === true && ($item['byteLength'] ?? null) !== null,
            )),
            'declaredCount' => count(array_filter($items, static fn (array $item): bool => $item['declared'] === true)),
            'undeclaredCount' => count(array_filter($items, static fn (array $item): bool => $item['undeclared'] === true)),
            'missingCount' => count(array_filter($items, static fn (array $item): bool => $item['exists'] !== true)),
            'directoryCount' => count(array_filter($items, static fn (array $item): bool => $item['isDirectory'] === true)),
            'encryptedCount' => count(array_filter($items, static fn (array $item): bool => $item['encrypted'] === true)),
            'missingMediaTypeCount' => count(array_filter($items, static fn (array $item): bool => in_array('odf-template-package-missing-media-type', $item['issues'], true))),
            'invalidMediaTypeCount' => count(array_filter($items, static fn (array $item): bool => in_array('odf-template-package-invalid-media-type', $item['issues'], true))),
            'issueCount' => count(array_filter($items, static fn (array $item): bool => $item['issues'] !== [])),
            'issueCodes' => array_keys($issueCodes),
            'kindCounts' => $kindCounts,
            'groupCounts' => $groupCounts,
            'byteExposurePolicy' => 'template-package-bytes-blocked',
            'reviewPolicy' => 'template-package-metadata-only',
            'items' => $items,
        ];
    }

    private static function isTemplatePackagePartName(string $path): bool
    {
        return str_starts_with(strtolower(ltrim($path, '/')), 'templates/');
    }

    private static function templateMediaTypeFromPart(string $path): ?string
    {
        if (str_ends_with($path, '/')) {
            return '';
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return match ($extension) {
            'odt' => self::TEXT_MIMETYPE,
            'odm' => 'application/vnd.oasis.opendocument.text-master',
            'ods' => 'application/vnd.oasis.opendocument.spreadsheet',
            'odp' => 'application/vnd.oasis.opendocument.presentation',
            'odg' => 'application/vnd.oasis.opendocument.graphics',
            'odf' => 'application/vnd.oasis.opendocument.formula',
            'odc' => 'application/vnd.oasis.opendocument.chart',
            'ott' => 'application/vnd.oasis.opendocument.text-template',
            'ots' => 'application/vnd.oasis.opendocument.spreadsheet-template',
            'otp' => 'application/vnd.oasis.opendocument.presentation-template',
            'otg' => 'application/vnd.oasis.opendocument.graphics-template',
            'fodt' => 'application/vnd.oasis.opendocument.text-flat-xml',
            'fods' => 'application/vnd.oasis.opendocument.spreadsheet-flat-xml',
            'fodp' => 'application/vnd.oasis.opendocument.presentation-flat-xml',
            'fodg' => 'application/vnd.oasis.opendocument.graphics-flat-xml',
            'odb' => 'application/vnd.oasis.opendocument.database',
            'dotx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.template',
            'xltx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.template',
            'potx' => 'application/vnd.openxmlformats-officedocument.presentationml.template',
            'xml', 'xhtml', 'html' => 'text/xml',
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'svg' => 'image/svg+xml',
            'pdf' => 'application/pdf',
            'txt', 'csv' => 'text/plain',
            'zip' => 'application/zip',
            'bin', 'dat' => 'application/octet-stream',
            default => null,
        };
    }

    private static function isTemplateMediaType(string $mediaType): bool
    {
        $base = self::mediaTypeReport($mediaType)['mediaTypeBase'];

        return self::isXmlMediaTypeBase($base)
            || self::mediaResourceFamilyFromMediaTypeBase($base) !== null
            || in_array($base, ['text/plain', 'text/csv', 'application/pdf', 'application/zip', 'application/octet-stream', 'application/binary'], true)
            || str_starts_with($base, 'application/vnd.oasis.opendocument.')
            || str_starts_with($base, 'application/vnd.')
            || str_starts_with($base, 'application/x-');
    }

    private static function templatePackagePartKind(string $path, string $mediaType, bool $isDirectory): string
    {
        if ($isDirectory) {
            return 'template-directory';
        }

        $basename = strtolower(basename($path));
        $base = self::mediaTypeReport($mediaType)['mediaTypeBase'];
        if (in_array($basename, ['manifest.xml', 'metadata.xml'], true) || self::isXmlMediaTypeBase($base)) {
            return 'template-manifest';
        }
        if (str_starts_with($base, 'application/vnd.oasis.opendocument.')
            || str_starts_with($base, 'application/vnd.openxmlformats-officedocument.')
        ) {
            return 'template-document';
        }
        if (self::mediaResourceFamilyFromMediaTypeBase($base) !== null) {
            return 'template-preview-media';
        }
        if (in_array($base, ['application/pdf', 'text/plain', 'text/csv'], true)) {
            return 'template-document-resource';
        }
        if (in_array($base, ['application/octet-stream', 'application/binary'], true)) {
            return 'template-binary-resource';
        }

        return 'template-resource';
    }

    private static function templatePackagePartGroup(string $path): ?string
    {
        $segments = explode('/', trim($path, '/'));
        $group = strtolower($segments[1] ?? '');

        return $group === '' ? null : $group;
    }

    /**
     * @param list<array<string, mixed>> $manifestEntries
     * @param list<array<string, mixed>> $undeclaredPackageEntries
     * @return array<string, mixed>
     */
    private static function packageDictionaryMetadata(ZipPackage $package, array $manifestEntries, array $undeclaredPackageEntries): array
    {
        $candidatesByPath = [];
        foreach ($manifestEntries as $entry) {
            $packagePath = $entry['packagePath'] ?? null;
            if (!is_string($packagePath) || $packagePath === '' || !self::isDictionaryPackagePartName($packagePath)) {
                continue;
            }

            $entry['declared'] = true;
            $candidatesByPath[$packagePath] = $entry;
        }

        foreach ($undeclaredPackageEntries as $entry) {
            $packagePath = $entry['path'] ?? null;
            if (!is_string($packagePath) || $packagePath === '' || !self::isDictionaryPackagePartName($packagePath)) {
                continue;
            }

            $mediaType = self::dictionaryMediaTypeFromPart($packagePath) ?? '';
            $mediaTypeReport = self::mediaTypeReport($mediaType);
            $candidatesByPath[$packagePath] = [
                'path' => $packagePath,
                'packagePath' => $packagePath,
                'pathReference' => $packagePath,
                'pathSuffix' => null,
                'pathQuery' => null,
                'pathFragment' => null,
                'mediaType' => $mediaType,
                'mediaTypeBase' => $mediaTypeReport['mediaTypeBase'],
                'mediaTypeHasParameters' => false,
                'mediaTypeParameterCount' => 0,
                'mediaTypeParameters' => [],
                'mediaTypeParameterMap' => [],
                'exists' => true,
                'isDirectory' => str_ends_with($packagePath, '/'),
                'encrypted' => false,
                'declared' => false,
                'declaredSize' => null,
                'declaredSizeMismatch' => false,
                'byteExposurePolicy' => str_ends_with($packagePath, '/') ? 'directory-entry-no-bytes' : 'dictionary-package-bytes-blocked',
            ];
        }

        ksort($candidatesByPath, SORT_STRING);

        $items = [];
        $issueCodes = [];
        $kindCounts = [];
        $groupCounts = [];
        foreach ($candidatesByPath as $packagePath => $entry) {
            $zipEntry = $package->has($packagePath) ? $package->entry($packagePath) : null;
            $isDirectory = str_ends_with($packagePath, '/');
            $exists = $isDirectory || $zipEntry instanceof ZipPackageEntry;
            $encrypted = ($entry['encrypted'] ?? false) === true;
            $declared = ($entry['declared'] ?? false) === true;
            $mediaType = (string) ($entry['mediaType'] ?? '');
            if ($mediaType === '') {
                $mediaType = self::dictionaryMediaTypeFromPart($packagePath) ?? '';
            }
            $mediaTypeReport = self::mediaTypeReport($mediaType);
            $missingMediaType = $mediaType === '' && !$isDirectory;
            $mediaTypeValid = $isDirectory
                ? $mediaTypeReport['mediaTypeBase'] === ''
                : (!$missingMediaType && self::isDictionaryMediaType($mediaType));
            $kind = self::dictionaryPackagePartKind($packagePath, $mediaType, $isDirectory);
            $group = self::dictionaryPackagePartGroup($packagePath);
            $issues = [];
            if (!$exists) {
                $issues[] = 'odf-dictionary-package-missing-part';
            }
            if (!$declared) {
                $issues[] = 'odf-dictionary-package-undeclared-part';
            }
            if ($encrypted) {
                $issues[] = 'odf-dictionary-package-encrypted-part';
            }
            if ($missingMediaType) {
                $issues[] = 'odf-dictionary-package-missing-media-type';
            } elseif (!$mediaTypeValid) {
                $issues[] = 'odf-dictionary-package-invalid-media-type';
            }
            foreach ($issues as $issue) {
                $issueCodes[$issue] = true;
            }
            $kindCounts[$kind] = ($kindCounts[$kind] ?? 0) + 1;
            if ($group !== null) {
                $groupCounts[$group] = ($groupCounts[$group] ?? 0) + 1;
            }

            $items[] = [
                'fullPath' => $entry['path'] ?? $packagePath,
                'path' => $entry['path'] ?? $packagePath,
                'packagePath' => $packagePath,
                'part' => $packagePath,
                'pathReference' => $entry['pathReference'] ?? null,
                'pathSuffix' => $entry['pathSuffix'] ?? null,
                'pathQuery' => $entry['pathQuery'] ?? null,
                'pathFragment' => $entry['pathFragment'] ?? null,
                'mediaType' => $mediaType === '' ? null : $mediaType,
                'mediaTypeBase' => $mediaTypeReport['mediaTypeBase'],
                'mediaTypeHasParameters' => $mediaTypeReport['mediaTypeHasParameters'],
                'mediaTypeParameterCount' => $mediaTypeReport['mediaTypeParameterCount'],
                'mediaTypeParameters' => $mediaTypeReport['mediaTypeParameters'],
                'mediaTypeParameterMap' => $mediaTypeReport['mediaTypeParameterMap'],
                'kind' => $kind,
                'group' => $group,
                'packageRoot' => 'Dictionaries',
                'isDirectory' => $isDirectory,
                'exists' => $exists,
                'declared' => $declared,
                'undeclared' => !$declared,
                'encrypted' => $encrypted,
                'valid' => $exists && !$encrypted && $mediaTypeValid,
                'byteLength' => !$isDirectory && !$encrypted && $zipEntry instanceof ZipPackageEntry ? $zipEntry->uncompressedSize : null,
                'compressedByteLength' => $zipEntry instanceof ZipPackageEntry ? $zipEntry->compressedSize : null,
                'compressionMethod' => $zipEntry instanceof ZipPackageEntry ? $zipEntry->compressionMethod : null,
                'compressionMethodName' => $zipEntry instanceof ZipPackageEntry ? self::compressionMethodName($zipEntry->compressionMethod) : null,
                'crc32' => !$isDirectory && !$encrypted && $zipEntry instanceof ZipPackageEntry ? $zipEntry->crc32Hex() : null,
                'storedByteLength' => $zipEntry instanceof ZipPackageEntry ? $zipEntry->uncompressedSize : null,
                'storedCrc32' => $zipEntry instanceof ZipPackageEntry ? $zipEntry->crc32Hex() : null,
                'declaredSize' => $entry['declaredSize'] ?? $entry['size'] ?? null,
                'declaredSizeMismatch' => ($entry['declaredSizeMismatch'] ?? false) === true,
                'canExposeBytes' => false,
                'canExposeAsDocumentMedia' => false,
                'byteExposurePolicy' => $entry['byteExposurePolicy'] ?? ($isDirectory ? 'directory-entry-no-bytes' : 'dictionary-package-bytes-blocked'),
                'reviewPolicy' => 'dictionary-package-metadata-only',
                'encryption' => $entry['encryption'] ?? null,
                'issues' => $issues,
            ];
        }

        ksort($issueCodes, SORT_STRING);
        ksort($kindCounts, SORT_STRING);
        ksort($groupCounts, SORT_STRING);

        return [
            'count' => count($items),
            'readableCount' => count(array_filter(
                $items,
                static fn (array $item): bool => $item['exists'] === true && ($item['byteLength'] ?? null) !== null,
            )),
            'declaredCount' => count(array_filter($items, static fn (array $item): bool => $item['declared'] === true)),
            'undeclaredCount' => count(array_filter($items, static fn (array $item): bool => $item['undeclared'] === true)),
            'missingCount' => count(array_filter($items, static fn (array $item): bool => $item['exists'] !== true)),
            'directoryCount' => count(array_filter($items, static fn (array $item): bool => $item['isDirectory'] === true)),
            'encryptedCount' => count(array_filter($items, static fn (array $item): bool => $item['encrypted'] === true)),
            'missingMediaTypeCount' => count(array_filter($items, static fn (array $item): bool => in_array('odf-dictionary-package-missing-media-type', $item['issues'], true))),
            'invalidMediaTypeCount' => count(array_filter($items, static fn (array $item): bool => in_array('odf-dictionary-package-invalid-media-type', $item['issues'], true))),
            'issueCount' => count(array_filter($items, static fn (array $item): bool => $item['issues'] !== [])),
            'issueCodes' => array_keys($issueCodes),
            'kindCounts' => $kindCounts,
            'groupCounts' => $groupCounts,
            'byteExposurePolicy' => 'dictionary-package-bytes-blocked',
            'reviewPolicy' => 'dictionary-package-metadata-only',
            'items' => $items,
        ];
    }

    private static function isDictionaryPackagePartName(string $path): bool
    {
        return str_starts_with(strtolower(ltrim($path, '/')), 'dictionaries/');
    }

    private static function dictionaryMediaTypeFromPart(string $path): ?string
    {
        if (str_ends_with($path, '/')) {
            return '';
        }

        return match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'dic', 'aff', 'idx', 'dat', 'txt' => 'text/plain',
            'xml', 'xhtml', 'html' => 'text/xml',
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'svg' => 'image/svg+xml',
            'bin' => 'application/octet-stream',
            default => null,
        };
    }

    private static function isDictionaryMediaType(string $mediaType): bool
    {
        $base = self::mediaTypeReport($mediaType)['mediaTypeBase'];

        return self::isXmlMediaTypeBase($base)
            || self::mediaResourceFamilyFromMediaTypeBase($base) !== null
            || in_array($base, ['text/plain', 'text/csv', 'application/octet-stream', 'application/binary'], true)
            || str_starts_with($base, 'application/vnd.')
            || str_starts_with($base, 'application/x-');
    }

    private static function dictionaryPackagePartKind(string $path, string $mediaType, bool $isDirectory): string
    {
        if ($isDirectory) {
            return 'dictionary-directory';
        }

        $basename = strtolower(basename($path));
        $base = self::mediaTypeReport($mediaType)['mediaTypeBase'];
        if (in_array($basename, ['manifest.xml', 'metadata.xml'], true) || self::isXmlMediaTypeBase($base)) {
            return 'dictionary-manifest';
        }
        if (in_array($base, ['text/plain', 'text/csv'], true)) {
            return 'dictionary-word-list';
        }
        if (self::mediaResourceFamilyFromMediaTypeBase($base) !== null) {
            return 'dictionary-preview-media';
        }
        if (in_array($base, ['application/octet-stream', 'application/binary'], true)) {
            return 'dictionary-binary-resource';
        }

        return 'dictionary-resource';
    }

    private static function dictionaryPackagePartGroup(string $path): ?string
    {
        $segments = explode('/', trim($path, '/'));
        $group = strtolower($segments[1] ?? '');

        return $group === '' ? null : $group;
    }

    /**
     * @param list<array<string, mixed>> $manifestEntries
     * @param list<array<string, mixed>> $undeclaredPackageEntries
     * @return array<string, mixed>
     */
    private static function packageEventMetadata(ZipPackage $package, array $manifestEntries, array $undeclaredPackageEntries): array
    {
        $candidatesByPath = [];
        foreach ($manifestEntries as $entry) {
            $packagePath = $entry['packagePath'] ?? null;
            if (!is_string($packagePath) || $packagePath === '' || !self::isEventPackagePartName($packagePath)) {
                continue;
            }

            $entry['declared'] = true;
            $candidatesByPath[$packagePath] = $entry;
        }

        foreach ($undeclaredPackageEntries as $entry) {
            $packagePath = $entry['path'] ?? null;
            if (!is_string($packagePath) || $packagePath === '' || !self::isEventPackagePartName($packagePath)) {
                continue;
            }

            $mediaType = self::eventMediaTypeFromPart($packagePath) ?? '';
            $mediaTypeReport = self::mediaTypeReport($mediaType);
            $candidatesByPath[$packagePath] = [
                'path' => $packagePath,
                'packagePath' => $packagePath,
                'pathReference' => $packagePath,
                'pathSuffix' => null,
                'pathQuery' => null,
                'pathFragment' => null,
                'mediaType' => $mediaType,
                'mediaTypeBase' => $mediaTypeReport['mediaTypeBase'],
                'mediaTypeHasParameters' => false,
                'mediaTypeParameterCount' => 0,
                'mediaTypeParameters' => [],
                'mediaTypeParameterMap' => [],
                'exists' => true,
                'isDirectory' => str_ends_with($packagePath, '/'),
                'encrypted' => false,
                'declared' => false,
                'declaredSize' => null,
                'declaredSizeMismatch' => false,
                'byteExposurePolicy' => str_ends_with($packagePath, '/') ? 'directory-entry-no-bytes' : 'event-package-bytes-blocked',
            ];
        }

        ksort($candidatesByPath, SORT_STRING);

        $items = [];
        $issueCodes = [];
        $kindCounts = [];
        $groupCounts = [];
        foreach ($candidatesByPath as $packagePath => $entry) {
            $zipEntry = $package->has($packagePath) ? $package->entry($packagePath) : null;
            $isDirectory = str_ends_with($packagePath, '/');
            $exists = $isDirectory || $zipEntry instanceof ZipPackageEntry;
            $encrypted = ($entry['encrypted'] ?? false) === true;
            $declared = ($entry['declared'] ?? false) === true;
            $mediaType = (string) ($entry['mediaType'] ?? '');
            if ($mediaType === '') {
                $mediaType = self::eventMediaTypeFromPart($packagePath) ?? '';
            }
            $mediaTypeReport = self::mediaTypeReport($mediaType);
            $missingMediaType = $mediaType === '' && !$isDirectory;
            $mediaTypeValid = $isDirectory
                ? $mediaTypeReport['mediaTypeBase'] === ''
                : (!$missingMediaType && self::isEventMediaType($mediaType));
            $kind = self::eventPackagePartKind($packagePath, $mediaType, $isDirectory);
            $group = self::eventPackagePartGroup($packagePath);
            $issues = [];
            if (!$exists) {
                $issues[] = 'odf-event-package-missing-part';
            }
            if (!$declared) {
                $issues[] = 'odf-event-package-undeclared-part';
            }
            if ($encrypted) {
                $issues[] = 'odf-event-package-encrypted-part';
            }
            if ($missingMediaType) {
                $issues[] = 'odf-event-package-missing-media-type';
            } elseif (!$mediaTypeValid) {
                $issues[] = 'odf-event-package-invalid-media-type';
            }
            foreach ($issues as $issue) {
                $issueCodes[$issue] = true;
            }
            $kindCounts[$kind] = ($kindCounts[$kind] ?? 0) + 1;
            if ($group !== null) {
                $groupCounts[$group] = ($groupCounts[$group] ?? 0) + 1;
            }

            $items[] = [
                'fullPath' => $entry['path'] ?? $packagePath,
                'path' => $entry['path'] ?? $packagePath,
                'packagePath' => $packagePath,
                'part' => $packagePath,
                'pathReference' => $entry['pathReference'] ?? null,
                'pathSuffix' => $entry['pathSuffix'] ?? null,
                'pathQuery' => $entry['pathQuery'] ?? null,
                'pathFragment' => $entry['pathFragment'] ?? null,
                'mediaType' => $mediaType === '' ? null : $mediaType,
                'mediaTypeBase' => $mediaTypeReport['mediaTypeBase'],
                'mediaTypeHasParameters' => $mediaTypeReport['mediaTypeHasParameters'],
                'mediaTypeParameterCount' => $mediaTypeReport['mediaTypeParameterCount'],
                'mediaTypeParameters' => $mediaTypeReport['mediaTypeParameters'],
                'mediaTypeParameterMap' => $mediaTypeReport['mediaTypeParameterMap'],
                'kind' => $kind,
                'group' => $group,
                'packageRoot' => 'Events',
                'isDirectory' => $isDirectory,
                'exists' => $exists,
                'declared' => $declared,
                'undeclared' => !$declared,
                'encrypted' => $encrypted,
                'valid' => $exists && !$encrypted && $mediaTypeValid,
                'byteLength' => !$isDirectory && !$encrypted && $zipEntry instanceof ZipPackageEntry ? $zipEntry->uncompressedSize : null,
                'compressedByteLength' => $zipEntry instanceof ZipPackageEntry ? $zipEntry->compressedSize : null,
                'compressionMethod' => $zipEntry instanceof ZipPackageEntry ? $zipEntry->compressionMethod : null,
                'compressionMethodName' => $zipEntry instanceof ZipPackageEntry ? self::compressionMethodName($zipEntry->compressionMethod) : null,
                'crc32' => !$isDirectory && !$encrypted && $zipEntry instanceof ZipPackageEntry ? $zipEntry->crc32Hex() : null,
                'storedByteLength' => $zipEntry instanceof ZipPackageEntry ? $zipEntry->uncompressedSize : null,
                'storedCrc32' => $zipEntry instanceof ZipPackageEntry ? $zipEntry->crc32Hex() : null,
                'declaredSize' => $entry['declaredSize'] ?? $entry['size'] ?? null,
                'declaredSizeMismatch' => ($entry['declaredSizeMismatch'] ?? false) === true,
                'canExposeBytes' => false,
                'canExposeAsDocumentMedia' => false,
                'byteExposurePolicy' => $entry['byteExposurePolicy'] ?? ($isDirectory ? 'directory-entry-no-bytes' : 'event-package-bytes-blocked'),
                'reviewPolicy' => 'event-package-metadata-only',
                'encryption' => $entry['encryption'] ?? null,
                'issues' => $issues,
            ];
        }

        ksort($issueCodes, SORT_STRING);
        ksort($kindCounts, SORT_STRING);
        ksort($groupCounts, SORT_STRING);

        return [
            'count' => count($items),
            'readableCount' => count(array_filter(
                $items,
                static fn (array $item): bool => $item['exists'] === true && ($item['byteLength'] ?? null) !== null,
            )),
            'declaredCount' => count(array_filter($items, static fn (array $item): bool => $item['declared'] === true)),
            'undeclaredCount' => count(array_filter($items, static fn (array $item): bool => $item['undeclared'] === true)),
            'missingCount' => count(array_filter($items, static fn (array $item): bool => $item['exists'] !== true)),
            'directoryCount' => count(array_filter($items, static fn (array $item): bool => $item['isDirectory'] === true)),
            'encryptedCount' => count(array_filter($items, static fn (array $item): bool => $item['encrypted'] === true)),
            'missingMediaTypeCount' => count(array_filter($items, static fn (array $item): bool => in_array('odf-event-package-missing-media-type', $item['issues'], true))),
            'invalidMediaTypeCount' => count(array_filter($items, static fn (array $item): bool => in_array('odf-event-package-invalid-media-type', $item['issues'], true))),
            'issueCount' => count(array_filter($items, static fn (array $item): bool => $item['issues'] !== [])),
            'issueCodes' => array_keys($issueCodes),
            'kindCounts' => $kindCounts,
            'groupCounts' => $groupCounts,
            'byteExposurePolicy' => 'event-package-bytes-blocked',
            'reviewPolicy' => 'event-package-metadata-only',
            'items' => $items,
        ];
    }

    private static function eventMediaTypeFromPart(string $path): ?string
    {
        if (str_ends_with($path, '/')) {
            return '';
        }

        return match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'xml', 'xhtml', 'html' => 'text/xml',
            'json' => 'application/json',
            'js', 'mjs' => 'application/javascript',
            'txt', 'properties' => 'text/plain',
            'bin', 'dat' => 'application/octet-stream',
            default => null,
        };
    }

    private static function isEventMediaType(string $mediaType): bool
    {
        $base = self::mediaTypeReport($mediaType)['mediaTypeBase'];

        return self::isXmlMediaTypeBase($base)
            || in_array($base, ['application/json', 'application/javascript', 'text/javascript', 'text/plain', 'application/octet-stream', 'application/binary'], true)
            || str_starts_with($base, 'application/vnd.')
            || str_starts_with($base, 'application/x-');
    }

    private static function eventPackagePartKind(string $path, string $mediaType, bool $isDirectory): string
    {
        if ($isDirectory) {
            return 'event-directory';
        }

        $basename = strtolower(basename($path));
        $base = self::mediaTypeReport($mediaType)['mediaTypeBase'];
        if (in_array($basename, ['events.xml', 'event.xml', 'listeners.xml', 'content.xml', 'settings.xml'], true)
            || self::isXmlMediaTypeBase($base)
        ) {
            return 'event-definition';
        }
        if (in_array($base, ['application/javascript', 'text/javascript'], true)) {
            return 'event-script';
        }
        if (in_array($base, ['application/json', 'text/plain'], true)) {
            return 'event-metadata';
        }
        if (in_array($base, ['application/octet-stream', 'application/binary'], true)) {
            return 'event-binary-resource';
        }

        return 'event-resource';
    }

    private static function eventPackagePartGroup(string $path): ?string
    {
        $segments = explode('/', trim($path, '/'));
        $group = strtolower($segments[1] ?? '');

        return $group === '' ? null : $group;
    }

    /**
     * @param list<array<string, mixed>> $manifestEntries
     * @param list<array<string, mixed>> $undeclaredPackageEntries
     * @return array<string, mixed>
     */
    private static function packageDatabaseMetadata(ZipPackage $package, array $manifestEntries, array $undeclaredPackageEntries): array
    {
        $candidatesByPath = [];
        foreach ($manifestEntries as $entry) {
            $packagePath = $entry['packagePath'] ?? null;
            if (!is_string($packagePath) || $packagePath === '' || !self::isDatabasePackagePartName($packagePath)) {
                continue;
            }

            $entry['declared'] = true;
            $candidatesByPath[$packagePath] = $entry;
        }

        foreach ($undeclaredPackageEntries as $entry) {
            $packagePath = $entry['path'] ?? null;
            if (!is_string($packagePath) || $packagePath === '' || !self::isDatabasePackagePartName($packagePath)) {
                continue;
            }

            $mediaType = self::databaseMediaTypeFromPart($packagePath) ?? '';
            $mediaTypeReport = self::mediaTypeReport($mediaType);
            $candidatesByPath[$packagePath] = [
                'path' => $packagePath,
                'packagePath' => $packagePath,
                'pathReference' => $packagePath,
                'pathSuffix' => null,
                'pathQuery' => null,
                'pathFragment' => null,
                'mediaType' => $mediaType,
                'mediaTypeBase' => $mediaTypeReport['mediaTypeBase'],
                'mediaTypeHasParameters' => false,
                'mediaTypeParameterCount' => 0,
                'mediaTypeParameters' => [],
                'mediaTypeParameterMap' => [],
                'exists' => true,
                'encrypted' => false,
                'declared' => false,
                'declaredSize' => null,
                'declaredSizeMismatch' => false,
                'byteExposurePolicy' => 'database-package-bytes-blocked',
            ];
        }

        ksort($candidatesByPath, SORT_STRING);

        $items = [];
        $issueCodes = [];
        $kindCounts = [];
        $groupCounts = [];
        foreach ($candidatesByPath as $packagePath => $entry) {
            $zipEntry = $package->has($packagePath) ? $package->entry($packagePath) : null;
            $isDirectory = str_ends_with($packagePath, '/');
            $exists = $isDirectory || $zipEntry instanceof ZipPackageEntry;
            $encrypted = ($entry['encrypted'] ?? false) === true;
            $declared = ($entry['declared'] ?? false) === true;
            $mediaType = (string) ($entry['mediaType'] ?? '');
            if ($mediaType === '') {
                $mediaType = self::databaseMediaTypeFromPart($packagePath) ?? '';
            }
            $mediaTypeReport = self::mediaTypeReport($mediaType);
            $missingMediaType = $mediaType === '' && !$isDirectory;
            $mediaTypeValid = $isDirectory ? $mediaTypeReport['mediaTypeBase'] === '' : (!$missingMediaType && self::isDatabaseMediaType($mediaType));
            $kind = self::databasePackagePartKind($packagePath, $mediaType, $isDirectory);
            $group = self::databasePackagePartGroup($packagePath);
            $issues = [];
            if (!$exists) {
                $issues[] = 'odf-database-package-missing-part';
            }
            if (!$declared) {
                $issues[] = 'odf-database-package-undeclared-part';
            }
            if ($encrypted) {
                $issues[] = 'odf-database-package-encrypted-part';
            }
            if ($missingMediaType) {
                $issues[] = 'odf-database-package-missing-media-type';
            } elseif (!$mediaTypeValid) {
                $issues[] = 'odf-database-package-invalid-media-type';
            }
            foreach ($issues as $issue) {
                $issueCodes[$issue] = true;
            }
            $kindCounts[$kind] = ($kindCounts[$kind] ?? 0) + 1;
            if ($group !== null) {
                $groupCounts[$group] = ($groupCounts[$group] ?? 0) + 1;
            }

            $items[] = [
                'fullPath' => $entry['path'] ?? $packagePath,
                'path' => $entry['path'] ?? $packagePath,
                'packagePath' => $packagePath,
                'part' => $packagePath,
                'pathReference' => $entry['pathReference'] ?? null,
                'pathSuffix' => $entry['pathSuffix'] ?? null,
                'pathQuery' => $entry['pathQuery'] ?? null,
                'pathFragment' => $entry['pathFragment'] ?? null,
                'mediaType' => $mediaType === '' ? null : $mediaType,
                'mediaTypeBase' => $mediaTypeReport['mediaTypeBase'],
                'mediaTypeHasParameters' => $mediaTypeReport['mediaTypeHasParameters'],
                'mediaTypeParameterCount' => $mediaTypeReport['mediaTypeParameterCount'],
                'mediaTypeParameters' => $mediaTypeReport['mediaTypeParameters'],
                'mediaTypeParameterMap' => $mediaTypeReport['mediaTypeParameterMap'],
                'kind' => $kind,
                'group' => $group,
                'packageRoot' => 'database',
                'isDirectory' => $isDirectory,
                'exists' => $exists,
                'declared' => $declared,
                'undeclared' => !$declared,
                'encrypted' => $encrypted,
                'valid' => $exists && !$encrypted && $mediaTypeValid,
                'byteLength' => !$isDirectory && !$encrypted && $zipEntry instanceof ZipPackageEntry ? $zipEntry->uncompressedSize : null,
                'compressedByteLength' => $zipEntry instanceof ZipPackageEntry ? $zipEntry->compressedSize : null,
                'compressionMethod' => $zipEntry instanceof ZipPackageEntry ? $zipEntry->compressionMethod : null,
                'compressionMethodName' => $zipEntry instanceof ZipPackageEntry ? self::compressionMethodName($zipEntry->compressionMethod) : null,
                'crc32' => !$isDirectory && !$encrypted && $zipEntry instanceof ZipPackageEntry ? $zipEntry->crc32Hex() : null,
                'storedByteLength' => $zipEntry instanceof ZipPackageEntry ? $zipEntry->uncompressedSize : null,
                'storedCrc32' => $zipEntry instanceof ZipPackageEntry ? $zipEntry->crc32Hex() : null,
                'declaredSize' => $entry['declaredSize'] ?? $entry['size'] ?? null,
                'declaredSizeMismatch' => ($entry['declaredSizeMismatch'] ?? false) === true,
                'canExposeBytes' => false,
                'canExposeAsDocumentMedia' => false,
                'byteExposurePolicy' => $entry['byteExposurePolicy'] ?? ($isDirectory ? 'directory-entry-no-bytes' : 'database-package-bytes-blocked'),
                'reviewPolicy' => 'database-package-metadata-only',
                'issues' => $issues,
            ];
        }

        ksort($issueCodes, SORT_STRING);
        ksort($kindCounts, SORT_STRING);
        ksort($groupCounts, SORT_STRING);

        return [
            'count' => count($items),
            'readableCount' => count(array_filter(
                $items,
                static fn (array $item): bool => $item['exists'] === true && ($item['byteLength'] ?? null) !== null,
            )),
            'declaredCount' => count(array_filter($items, static fn (array $item): bool => $item['declared'] === true)),
            'undeclaredCount' => count(array_filter($items, static fn (array $item): bool => $item['undeclared'] === true)),
            'missingCount' => count(array_filter($items, static fn (array $item): bool => $item['exists'] !== true)),
            'directoryCount' => count(array_filter($items, static fn (array $item): bool => $item['isDirectory'] === true)),
            'encryptedCount' => count(array_filter($items, static fn (array $item): bool => $item['encrypted'] === true)),
            'missingMediaTypeCount' => count(array_filter($items, static fn (array $item): bool => in_array('odf-database-package-missing-media-type', $item['issues'], true))),
            'invalidMediaTypeCount' => count(array_filter($items, static fn (array $item): bool => in_array('odf-database-package-invalid-media-type', $item['issues'], true))),
            'issueCount' => count(array_filter($items, static fn (array $item): bool => $item['issues'] !== [])),
            'issueCodes' => array_keys($issueCodes),
            'kindCounts' => $kindCounts,
            'groupCounts' => $groupCounts,
            'byteExposurePolicy' => 'database-package-bytes-blocked',
            'reviewPolicy' => 'database-package-metadata-only',
            'items' => $items,
        ];
    }

    private static function databaseMediaTypeFromPart(string $path): ?string
    {
        if (str_ends_with($path, '/')) {
            return '';
        }

        $basename = strtolower(basename($path));
        if (in_array($basename, ['script', 'properties'], true)) {
            return 'text/plain';
        }
        if (in_array($basename, ['data', 'backup', 'log'], true)) {
            return 'application/octet-stream';
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return match ($extension) {
            'xml', 'xcu', 'xcs' => 'text/xml',
            'sql', 'txt', 'properties' => 'text/plain',
            'db', 'dat', 'odb', 'sqlite', 'sqlite3' => 'application/octet-stream',
            default => null,
        };
    }

    private static function isDatabaseMediaType(string $mediaType): bool
    {
        $base = self::mediaTypeReport($mediaType)['mediaTypeBase'];

        return in_array($base, ['text/xml', 'application/xml', 'text/plain', 'application/octet-stream', 'application/binary'], true)
            || str_starts_with($base, 'application/vnd.')
            || str_starts_with($base, 'application/x-');
    }

    private static function databasePackagePartKind(string $path, string $mediaType, bool $isDirectory): string
    {
        if ($isDirectory) {
            return 'database-directory';
        }

        $basename = strtolower(basename($path));
        if (in_array($basename, ['script', 'properties', 'log'], true)) {
            return 'database-' . $basename;
        }
        if (in_array($basename, ['data', 'backup'], true)) {
            return 'database-binary-store';
        }
        if ($mediaType !== '' && self::isXmlMediaTypeBase(self::mediaTypeReport($mediaType)['mediaTypeBase'])) {
            return 'database-xml';
        }

        return 'database-resource';
    }

    private static function databasePackagePartGroup(string $path): ?string
    {
        $segments = explode('/', trim($path, '/'));
        $group = strtolower($segments[1] ?? '');

        return $group === '' ? null : $group;
    }

    /**
     * @param list<array<string, mixed>> $manifestEntries
     * @param list<array<string, mixed>> $undeclaredPackageEntries
     * @return array<string, mixed>
     */
    private static function packageVersionMetadata(ZipPackage $package, array $manifestEntries, array $undeclaredPackageEntries): array
    {
        $candidatesByPath = [];
        foreach ($manifestEntries as $entry) {
            $packagePath = $entry['packagePath'] ?? null;
            if (!is_string($packagePath) || $packagePath === '' || !self::isVersionPackagePartName($packagePath)) {
                continue;
            }

            $entry['declared'] = true;
            $candidatesByPath[$packagePath] = $entry;
        }

        foreach ($undeclaredPackageEntries as $entry) {
            $packagePath = $entry['path'] ?? null;
            if (!is_string($packagePath) || $packagePath === '' || !self::isVersionPackagePartName($packagePath)) {
                continue;
            }

            $mediaType = self::versionMediaTypeFromPart($packagePath) ?? '';
            $mediaTypeReport = self::mediaTypeReport($mediaType);
            $candidatesByPath[$packagePath] = [
                'path' => $packagePath,
                'packagePath' => $packagePath,
                'pathReference' => $packagePath,
                'pathSuffix' => null,
                'pathQuery' => null,
                'pathFragment' => null,
                'mediaType' => $mediaType,
                'mediaTypeBase' => $mediaTypeReport['mediaTypeBase'],
                'mediaTypeHasParameters' => false,
                'mediaTypeParameterCount' => 0,
                'mediaTypeParameters' => [],
                'mediaTypeParameterMap' => [],
                'exists' => true,
                'encrypted' => false,
                'declared' => false,
                'declaredSize' => null,
                'declaredSizeMismatch' => false,
                'byteExposurePolicy' => 'version-package-bytes-blocked',
            ];
        }

        ksort($candidatesByPath, SORT_STRING);

        $items = [];
        $issueCodes = [];
        $kindCounts = [];
        $versionIdCounts = [];
        foreach ($candidatesByPath as $packagePath => $entry) {
            $zipEntry = $package->has($packagePath) ? $package->entry($packagePath) : null;
            $isDirectory = str_ends_with($packagePath, '/');
            $exists = $isDirectory || $zipEntry instanceof ZipPackageEntry;
            $encrypted = ($entry['encrypted'] ?? false) === true;
            $declared = ($entry['declared'] ?? false) === true;
            $mediaType = (string) ($entry['mediaType'] ?? '');
            if ($mediaType === '') {
                $mediaType = self::versionMediaTypeFromPart($packagePath) ?? '';
            }
            $mediaTypeReport = self::mediaTypeReport($mediaType);
            $missingMediaType = $mediaType === '' && !$isDirectory;
            $mediaTypeValid = $isDirectory
                ? $mediaTypeReport['mediaTypeBase'] === ''
                : (!$missingMediaType && self::isVersionPackageMediaType($mediaType));
            $kind = self::versionPackagePartKind($packagePath, $mediaType, $isDirectory);
            $versionId = self::versionPackagePartVersionId($packagePath);
            $issues = [];
            if (!$exists) {
                $issues[] = 'odf-version-package-missing-part';
            }
            if (!$declared) {
                $issues[] = 'odf-version-package-undeclared-part';
            }
            if ($encrypted) {
                $issues[] = 'odf-version-package-encrypted-part';
            }
            if ($missingMediaType) {
                $issues[] = 'odf-version-package-missing-media-type';
            } elseif (!$mediaTypeValid) {
                $issues[] = 'odf-version-package-invalid-media-type';
            }
            foreach ($issues as $issue) {
                $issueCodes[$issue] = true;
            }
            $kindCounts[$kind] = ($kindCounts[$kind] ?? 0) + 1;
            if ($versionId !== null) {
                $versionIdCounts[$versionId] = ($versionIdCounts[$versionId] ?? 0) + 1;
            }

            $items[] = [
                'fullPath' => $entry['path'] ?? $packagePath,
                'path' => $entry['path'] ?? $packagePath,
                'packagePath' => $packagePath,
                'part' => $packagePath,
                'pathReference' => $entry['pathReference'] ?? null,
                'pathSuffix' => $entry['pathSuffix'] ?? null,
                'pathQuery' => $entry['pathQuery'] ?? null,
                'pathFragment' => $entry['pathFragment'] ?? null,
                'mediaType' => $mediaType === '' ? null : $mediaType,
                'mediaTypeBase' => $mediaTypeReport['mediaTypeBase'],
                'mediaTypeHasParameters' => $mediaTypeReport['mediaTypeHasParameters'],
                'mediaTypeParameterCount' => $mediaTypeReport['mediaTypeParameterCount'],
                'mediaTypeParameters' => $mediaTypeReport['mediaTypeParameters'],
                'mediaTypeParameterMap' => $mediaTypeReport['mediaTypeParameterMap'],
                'kind' => $kind,
                'versionId' => $versionId,
                'packageRoot' => 'Versions',
                'isDirectory' => $isDirectory,
                'exists' => $exists,
                'declared' => $declared,
                'undeclared' => !$declared,
                'encrypted' => $encrypted,
                'valid' => $exists && !$encrypted && $mediaTypeValid,
                'byteLength' => !$isDirectory && !$encrypted && $zipEntry instanceof ZipPackageEntry ? $zipEntry->uncompressedSize : null,
                'compressedByteLength' => $zipEntry instanceof ZipPackageEntry ? $zipEntry->compressedSize : null,
                'compressionMethod' => $zipEntry instanceof ZipPackageEntry ? $zipEntry->compressionMethod : null,
                'compressionMethodName' => $zipEntry instanceof ZipPackageEntry ? self::compressionMethodName($zipEntry->compressionMethod) : null,
                'crc32' => !$isDirectory && !$encrypted && $zipEntry instanceof ZipPackageEntry ? $zipEntry->crc32Hex() : null,
                'storedByteLength' => $zipEntry instanceof ZipPackageEntry ? $zipEntry->uncompressedSize : null,
                'storedCrc32' => $zipEntry instanceof ZipPackageEntry ? $zipEntry->crc32Hex() : null,
                'declaredSize' => $entry['declaredSize'] ?? $entry['size'] ?? null,
                'declaredSizeMismatch' => ($entry['declaredSizeMismatch'] ?? false) === true,
                'canExposeBytes' => false,
                'canExposeAsDocumentMedia' => false,
                'byteExposurePolicy' => $entry['byteExposurePolicy'] ?? ($isDirectory ? 'directory-entry-no-bytes' : 'version-package-bytes-blocked'),
                'reviewPolicy' => 'version-package-metadata-only',
                'issues' => $issues,
            ];
        }

        ksort($issueCodes, SORT_STRING);
        ksort($kindCounts, SORT_STRING);
        ksort($versionIdCounts, SORT_STRING);

        return [
            'count' => count($items),
            'readableCount' => count(array_filter(
                $items,
                static fn (array $item): bool => $item['exists'] === true && ($item['byteLength'] ?? null) !== null,
            )),
            'declaredCount' => count(array_filter($items, static fn (array $item): bool => $item['declared'] === true)),
            'undeclaredCount' => count(array_filter($items, static fn (array $item): bool => $item['undeclared'] === true)),
            'missingCount' => count(array_filter($items, static fn (array $item): bool => $item['exists'] !== true)),
            'directoryCount' => count(array_filter($items, static fn (array $item): bool => $item['isDirectory'] === true)),
            'encryptedCount' => count(array_filter($items, static fn (array $item): bool => $item['encrypted'] === true)),
            'missingMediaTypeCount' => count(array_filter($items, static fn (array $item): bool => in_array('odf-version-package-missing-media-type', $item['issues'], true))),
            'invalidMediaTypeCount' => count(array_filter($items, static fn (array $item): bool => in_array('odf-version-package-invalid-media-type', $item['issues'], true))),
            'issueCount' => count(array_filter($items, static fn (array $item): bool => $item['issues'] !== [])),
            'issueCodes' => array_keys($issueCodes),
            'kindCounts' => $kindCounts,
            'versionIdCounts' => $versionIdCounts,
            'byteExposurePolicy' => 'version-package-bytes-blocked',
            'reviewPolicy' => 'version-package-metadata-only',
            'items' => $items,
        ];
    }

    private static function versionMediaTypeFromPart(string $path): ?string
    {
        if (str_ends_with($path, '/')) {
            return '';
        }

        $basename = strtolower(basename($path));
        if ($basename === 'versionlist.xml') {
            return 'text/xml';
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return match ($extension) {
            'xml', 'rdf' => 'text/xml',
            'odt' => self::TEXT_MIMETYPE,
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'svg' => 'image/svg+xml',
            'txt' => 'text/plain',
            'zip' => 'application/zip',
            default => null,
        };
    }

    private static function isVersionPackageMediaType(string $mediaType): bool
    {
        $base = self::mediaTypeReport($mediaType)['mediaTypeBase'];

        return self::isXmlMediaTypeBase($base)
            || self::isEmbeddedObjectPackageMediaType($base)
            || self::mediaResourceFamilyFromMediaTypeBase($base) !== null
            || in_array($base, ['text/plain', 'application/octet-stream', 'application/binary', 'application/zip'], true)
            || str_starts_with($base, 'application/vnd.');
    }

    private static function versionPackagePartKind(string $path, string $mediaType, bool $isDirectory): string
    {
        if ($isDirectory) {
            return 'version-directory';
        }

        $basename = strtolower(basename($path));
        if ($basename === 'versionlist.xml') {
            return 'version-list';
        }

        $base = self::mediaTypeReport($mediaType)['mediaTypeBase'];
        if (self::isEmbeddedObjectPackageMediaType($base)) {
            return 'version-document-package';
        }
        if (self::mediaResourceFamilyFromMediaTypeBase($base) !== null) {
            return 'version-media-resource';
        }
        if (self::isXmlMediaTypeBase($base)) {
            return 'version-xml';
        }
        if (in_array($base, ['application/octet-stream', 'application/binary', 'application/zip'], true)) {
            return 'version-binary-resource';
        }

        return 'version-resource';
    }

    private static function versionPackagePartVersionId(string $path): ?string
    {
        $segments = explode('/', trim($path, '/'));
        $versionId = $segments[1] ?? '';

        return $versionId === '' || strtolower($versionId) === 'versionlist.xml' ? null : $versionId;
    }

    /**
     * @param list<array<string, mixed>> $manifestEntries
     * @param list<array<string, mixed>> $undeclaredPackageEntries
     * @return array<string, mixed>
     */
    private static function packageScriptMetadata(ZipPackage $package, array $manifestEntries, array $undeclaredPackageEntries): array
    {
        $candidatesByPath = [];
        foreach ($manifestEntries as $entry) {
            $packagePath = $entry['packagePath'] ?? null;
            if (!is_string($packagePath) || $packagePath === '' || !self::isScriptPackagePartName($packagePath)) {
                continue;
            }

            $entry['declared'] = true;
            $candidatesByPath[$packagePath] = $entry;
        }

        foreach ($undeclaredPackageEntries as $entry) {
            $packagePath = $entry['path'] ?? null;
            if (!is_string($packagePath) || $packagePath === '' || str_ends_with($packagePath, '/') || !self::isScriptPackagePartName($packagePath)) {
                continue;
            }

            $mediaType = self::scriptMediaTypeFromPart($packagePath);
            $mediaTypeReport = self::mediaTypeReport($mediaType ?? '');
            $candidatesByPath[$packagePath] = [
                'path' => $packagePath,
                'packagePath' => $packagePath,
                'pathReference' => $packagePath,
                'pathSuffix' => null,
                'pathQuery' => null,
                'pathFragment' => null,
                'mediaType' => $mediaType ?? '',
                'mediaTypeBase' => $mediaTypeReport['mediaTypeBase'],
                'mediaTypeHasParameters' => false,
                'mediaTypeParameterCount' => 0,
                'mediaTypeParameters' => [],
                'mediaTypeParameterMap' => [],
                'exists' => true,
                'encrypted' => false,
                'declared' => false,
                'declaredSize' => null,
                'declaredSizeMismatch' => false,
                'byteExposurePolicy' => 'script-package-bytes-blocked',
            ];
        }

        ksort($candidatesByPath, SORT_STRING);

        $items = [];
        $issueCodes = [];
        $containers = [];
        $scriptKinds = [];
        foreach ($candidatesByPath as $packagePath => $entry) {
            $zipEntry = $package->has($packagePath) ? $package->entry($packagePath) : null;
            $isDirectory = str_ends_with($packagePath, '/');
            $exists = $isDirectory || $zipEntry instanceof ZipPackageEntry;
            $encrypted = ($entry['encrypted'] ?? false) === true;
            $declared = ($entry['declared'] ?? false) === true;
            $mediaType = (string) ($entry['mediaType'] ?? '');
            if ($mediaType === '' && !$isDirectory) {
                $mediaType = self::scriptMediaTypeFromPart($packagePath) ?? '';
            }

            $mediaTypeReport = self::mediaTypeReport($mediaType);
            $pathInfo = self::scriptPackagePathInfo($packagePath, $mediaTypeReport['mediaTypeBase']);
            $mediaTypeValid = $isDirectory
                ? $mediaTypeReport['mediaTypeBase'] === ''
                : self::scriptMediaTypeValid($packagePath, $mediaTypeReport['mediaTypeBase']);
            $issues = [];
            if (!$exists) {
                $issues[] = 'odf-script-missing-package-part';
            }
            if (!$declared) {
                $issues[] = 'odf-script-undeclared-package-part';
            }
            if ($encrypted) {
                $issues[] = 'odf-script-encrypted-package-part';
            }
            if (!$mediaTypeValid) {
                $issues[] = 'odf-script-invalid-media-type';
            }
            foreach ($issues as $issue) {
                $issueCodes[$issue] = true;
            }

            if (($pathInfo['scriptContainer'] ?? null) !== null) {
                $containers[(string) $pathInfo['scriptContainer']] = true;
            }
            if (($pathInfo['scriptKind'] ?? null) !== null) {
                $scriptKinds[(string) $pathInfo['scriptKind']] = true;
            }

            $items[] = [
                'fullPath' => $entry['path'] ?? $packagePath,
                'path' => $entry['path'] ?? $packagePath,
                'packagePath' => $packagePath,
                'part' => $packagePath,
                'pathReference' => $entry['pathReference'] ?? null,
                'pathSuffix' => $entry['pathSuffix'] ?? null,
                'pathQuery' => $entry['pathQuery'] ?? null,
                'pathFragment' => $entry['pathFragment'] ?? null,
                'mediaType' => $mediaType === '' ? null : $mediaType,
                'mediaTypeBase' => $mediaTypeReport['mediaTypeBase'],
                'mediaTypeHasParameters' => $mediaTypeReport['mediaTypeHasParameters'],
                'mediaTypeParameterCount' => $mediaTypeReport['mediaTypeParameterCount'],
                'mediaTypeParameters' => $mediaTypeReport['mediaTypeParameters'],
                'mediaTypeParameterMap' => $mediaTypeReport['mediaTypeParameterMap'],
                'mediaTypeValid' => $mediaTypeValid,
                'scriptContainer' => $pathInfo['scriptContainer'] ?? null,
                'scriptKind' => $pathInfo['scriptKind'] ?? null,
                'scriptPath' => $pathInfo['scriptPath'] ?? null,
                'scriptLibrary' => $pathInfo['scriptLibrary'] ?? null,
                'scriptModule' => $pathInfo['scriptModule'] ?? null,
                'extension' => $pathInfo['extension'] ?? null,
                'isDirectory' => $isDirectory,
                'exists' => $exists,
                'declared' => $declared,
                'undeclared' => !$declared,
                'encrypted' => $encrypted,
                'valid' => $exists && !$encrypted && $mediaTypeValid,
                'byteLength' => !$isDirectory && !$encrypted && $zipEntry instanceof ZipPackageEntry ? $zipEntry->uncompressedSize : null,
                'compressedByteLength' => $zipEntry instanceof ZipPackageEntry ? $zipEntry->compressedSize : null,
                'compressionMethod' => $zipEntry instanceof ZipPackageEntry ? $zipEntry->compressionMethod : null,
                'compressionMethodName' => $zipEntry instanceof ZipPackageEntry ? self::compressionMethodName($zipEntry->compressionMethod) : null,
                'crc32' => !$isDirectory && !$encrypted && $zipEntry instanceof ZipPackageEntry ? $zipEntry->crc32Hex() : null,
                'storedByteLength' => $zipEntry instanceof ZipPackageEntry ? $zipEntry->uncompressedSize : null,
                'storedCrc32' => $zipEntry instanceof ZipPackageEntry ? $zipEntry->crc32Hex() : null,
                'declaredSize' => $entry['declaredSize'] ?? $entry['size'] ?? null,
                'declaredSizeMismatch' => ($entry['declaredSizeMismatch'] ?? false) === true,
                'canExposeBytes' => false,
                'canExposeAsDocumentMedia' => false,
                'byteExposurePolicy' => $entry['byteExposurePolicy'] ?? ($isDirectory ? 'directory-entry-no-bytes' : 'script-package-bytes-blocked'),
                'reviewPolicy' => 'package-script-metadata-only',
                'issues' => $issues,
            ];
        }

        ksort($issueCodes, SORT_STRING);
        ksort($containers, SORT_STRING);
        ksort($scriptKinds, SORT_STRING);

        return [
            'count' => count($items),
            'fileCount' => count(array_filter($items, static fn (array $item): bool => $item['isDirectory'] !== true)),
            'directoryCount' => count(array_filter($items, static fn (array $item): bool => $item['isDirectory'] === true)),
            'storedPartCount' => count(array_filter($items, static fn (array $item): bool => $item['storedByteLength'] !== null)),
            'readableCount' => count(array_filter(
                $items,
                static fn (array $item): bool => $item['exists'] === true && ($item['byteLength'] ?? null) !== null,
            )),
            'declaredCount' => count(array_filter($items, static fn (array $item): bool => $item['declared'] === true)),
            'undeclaredCount' => count(array_filter($items, static fn (array $item): bool => $item['undeclared'] === true)),
            'missingCount' => count(array_filter($items, static fn (array $item): bool => $item['exists'] !== true)),
            'encryptedCount' => count(array_filter($items, static fn (array $item): bool => $item['encrypted'] === true)),
            'invalidMediaTypeCount' => count(array_filter(
                $items,
                static fn (array $item): bool => $item['mediaTypeValid'] !== true,
            )),
            'issueCount' => count(array_filter($items, static fn (array $item): bool => $item['issues'] !== [])),
            'issueCodes' => array_keys($issueCodes),
            'scriptContainers' => array_keys($containers),
            'scriptKinds' => array_keys($scriptKinds),
            'byteExposurePolicy' => 'script-package-bytes-blocked',
            'reviewPolicy' => 'package-script-metadata-only',
            'items' => $items,
        ];
    }

    /**
     * @param list<array<string, mixed>> $manifestEntries
     * @param list<array<string, mixed>> $undeclaredPackageEntries
     * @return array<string, mixed>
     */
    private static function packageConfigurationMetadata(ZipPackage $package, array $manifestEntries, array $undeclaredPackageEntries): array
    {
        $candidatesByPath = [];
        foreach ($manifestEntries as $entry) {
            $packagePath = $entry['packagePath'] ?? null;
            if (!is_string($packagePath) || $packagePath === '' || !self::isConfigurationPackagePartName($packagePath)) {
                continue;
            }

            $entry['declared'] = true;
            $candidatesByPath[$packagePath] = $entry;
        }

        foreach ($undeclaredPackageEntries as $entry) {
            $packagePath = $entry['path'] ?? null;
            if (!is_string($packagePath) || $packagePath === '' || !self::isConfigurationPackagePartName($packagePath)) {
                continue;
            }

            $mediaType = self::configurationMediaTypeFromPart($packagePath);
            $mediaTypeReport = self::mediaTypeReport($mediaType ?? '');
            $candidatesByPath[$packagePath] = [
                'path' => $packagePath,
                'packagePath' => $packagePath,
                'pathReference' => $packagePath,
                'pathSuffix' => null,
                'pathQuery' => null,
                'pathFragment' => null,
                'mediaType' => $mediaType ?? '',
                'mediaTypeBase' => $mediaTypeReport['mediaTypeBase'],
                'mediaTypeHasParameters' => false,
                'mediaTypeParameterCount' => 0,
                'mediaTypeParameters' => [],
                'mediaTypeParameterMap' => [],
                'exists' => true,
                'encrypted' => false,
                'declared' => false,
                'declaredSize' => null,
                'declaredSizeMismatch' => false,
                'byteExposurePolicy' => 'configuration-package-bytes-blocked',
            ];
        }

        ksort($candidatesByPath, SORT_STRING);

        $items = [];
        $issueCodes = [];
        $areas = [];
        $kinds = [];
        foreach ($candidatesByPath as $packagePath => $entry) {
            $isDirectory = str_ends_with($packagePath, '/');
            $zipEntry = $package->has($packagePath) ? $package->entry($packagePath) : null;
            $exists = $isDirectory || $zipEntry instanceof ZipPackageEntry;
            $encrypted = ($entry['encrypted'] ?? false) === true;
            $declared = ($entry['declared'] ?? false) === true;
            $mediaType = (string) ($entry['mediaType'] ?? '');
            if ($mediaType === '') {
                $mediaType = self::configurationMediaTypeFromPart($packagePath) ?? '';
            }

            $mediaTypeReport = self::mediaTypeReport($mediaType);
            $pathInfo = self::configurationPackagePathInfo($packagePath, $mediaTypeReport['mediaTypeBase']);
            $mediaTypeValid = self::configurationMediaTypeValid($packagePath, $mediaTypeReport['mediaTypeBase'], $isDirectory);
            $issues = [];
            if (!$exists) {
                $issues[] = 'odf-configuration-missing-package-part';
            }
            if (!$declared) {
                $issues[] = 'odf-configuration-undeclared-package-part';
            }
            if ($encrypted) {
                $issues[] = 'odf-configuration-encrypted-package-part';
            }
            if (!$mediaTypeValid) {
                $issues[] = 'odf-configuration-invalid-media-type';
            }
            foreach ($issues as $issue) {
                $issueCodes[$issue] = true;
            }

            if (($pathInfo['configurationArea'] ?? null) !== null) {
                $areas[(string) $pathInfo['configurationArea']] = true;
            }
            if (($pathInfo['configurationKind'] ?? null) !== null) {
                $kinds[(string) $pathInfo['configurationKind']] = true;
            }

            $items[] = [
                'fullPath' => $entry['path'] ?? $packagePath,
                'path' => $entry['path'] ?? $packagePath,
                'packagePath' => $packagePath,
                'part' => $packagePath,
                'pathReference' => $entry['pathReference'] ?? null,
                'pathSuffix' => $entry['pathSuffix'] ?? null,
                'pathQuery' => $entry['pathQuery'] ?? null,
                'pathFragment' => $entry['pathFragment'] ?? null,
                'mediaType' => $mediaType === '' ? null : $mediaType,
                'mediaTypeBase' => $mediaTypeReport['mediaTypeBase'],
                'mediaTypeHasParameters' => $mediaTypeReport['mediaTypeHasParameters'],
                'mediaTypeParameterCount' => $mediaTypeReport['mediaTypeParameterCount'],
                'mediaTypeParameters' => $mediaTypeReport['mediaTypeParameters'],
                'mediaTypeParameterMap' => $mediaTypeReport['mediaTypeParameterMap'],
                'packageRoot' => $pathInfo['packageRoot'] ?? null,
                'configurationArea' => $pathInfo['configurationArea'] ?? null,
                'configurationKind' => $pathInfo['configurationKind'] ?? null,
                'configurationPath' => $pathInfo['configurationPath'] ?? null,
                'extension' => $pathInfo['extension'] ?? null,
                'exists' => $exists,
                'isDirectory' => $isDirectory,
                'declared' => $declared,
                'undeclared' => !$declared,
                'encrypted' => $encrypted,
                'valid' => $exists && !$encrypted && $mediaTypeValid,
                'byteLength' => !$isDirectory && !$encrypted && $zipEntry instanceof ZipPackageEntry ? $zipEntry->uncompressedSize : null,
                'compressedByteLength' => $zipEntry instanceof ZipPackageEntry ? $zipEntry->compressedSize : null,
                'compressionMethod' => $zipEntry instanceof ZipPackageEntry ? $zipEntry->compressionMethod : null,
                'compressionMethodName' => $zipEntry instanceof ZipPackageEntry ? self::compressionMethodName($zipEntry->compressionMethod) : null,
                'crc32' => !$isDirectory && !$encrypted && $zipEntry instanceof ZipPackageEntry ? $zipEntry->crc32Hex() : null,
                'storedByteLength' => $zipEntry instanceof ZipPackageEntry ? $zipEntry->uncompressedSize : null,
                'storedCrc32' => $zipEntry instanceof ZipPackageEntry ? $zipEntry->crc32Hex() : null,
                'declaredSize' => $entry['declaredSize'] ?? $entry['size'] ?? null,
                'declaredSizeMismatch' => ($entry['declaredSizeMismatch'] ?? false) === true,
                'canExposeAsDocumentMedia' => false,
                'byteExposurePolicy' => $entry['byteExposurePolicy'] ?? 'configuration-package-bytes-blocked',
                'reviewPolicy' => 'configuration-package-metadata-only',
                'issues' => $issues,
            ];
        }

        ksort($issueCodes, SORT_STRING);
        ksort($areas, SORT_STRING);
        ksort($kinds, SORT_STRING);

        return [
            'count' => count($items),
            'readableCount' => count(array_filter(
                $items,
                static fn (array $item): bool => $item['exists'] === true && ($item['byteLength'] ?? null) !== null,
            )),
            'declaredCount' => count(array_filter($items, static fn (array $item): bool => $item['declared'] === true)),
            'undeclaredCount' => count(array_filter($items, static fn (array $item): bool => $item['undeclared'] === true)),
            'missingCount' => count(array_filter($items, static fn (array $item): bool => $item['exists'] !== true)),
            'directoryCount' => count(array_filter($items, static fn (array $item): bool => $item['isDirectory'] === true)),
            'encryptedCount' => count(array_filter($items, static fn (array $item): bool => $item['encrypted'] === true)),
            'invalidMediaTypeCount' => count(array_filter(
                $items,
                static fn (array $item): bool => in_array('odf-configuration-invalid-media-type', $item['issues'], true),
            )),
            'issueCount' => count(array_filter($items, static fn (array $item): bool => $item['issues'] !== [])),
            'issueCodes' => array_keys($issueCodes),
            'configurationAreas' => array_keys($areas),
            'configurationKinds' => array_keys($kinds),
            'byteExposurePolicy' => 'configuration-package-bytes-blocked',
            'reviewPolicy' => 'configuration-package-metadata-only',
            'items' => $items,
        ];
    }

    private static function configurationMediaTypeFromPart(string $path): ?string
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return match ($extension) {
            'bmp' => 'image/bmp',
            'gif' => 'image/gif',
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'svg' => 'image/svg+xml',
            'tif', 'tiff' => 'image/tiff',
            'webp' => 'image/webp',
            'xml' => 'text/xml',
            default => null,
        };
    }

    private static function configurationMediaTypeValid(string $path, string $mediaTypeBase, bool $isDirectory): bool
    {
        if ($isDirectory) {
            return $mediaTypeBase === '';
        }

        if ($mediaTypeBase === '') {
            return false;
        }

        $expected = self::configurationMediaTypeFromPart($path);
        if ($expected === null) {
            return true;
        }

        $expectedBase = self::mediaTypeReport($expected)['mediaTypeBase'];
        if (str_starts_with($expectedBase, 'image/')) {
            return str_starts_with($mediaTypeBase, 'image/');
        }

        if ($expectedBase === 'text/xml') {
            return in_array($mediaTypeBase, ['text/xml', 'application/xml'], true);
        }

        return $mediaTypeBase === $expectedBase;
    }

    /**
     * @return array<string, string|null>
     */
    private static function configurationPackagePathInfo(string $path, string $mediaTypeBase): array
    {
        $trimmed = trim($path, '/');
        $segments = $trimmed === '' ? [] : explode('/', $trimmed);
        $relativeSegments = array_slice($segments, 1);
        $configurationPath = $relativeSegments === [] ? null : implode('/', $relativeSegments);
        $area = $relativeSegments[0] ?? null;
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $isDirectory = str_ends_with($path, '/');

        $kind = 'configuration-part';
        if ($isDirectory) {
            $kind = $configurationPath === null ? 'configuration-root' : 'configuration-directory';
        } elseif (str_starts_with($mediaTypeBase, 'image/')) {
            $kind = 'configuration-image';
        } elseif (in_array($mediaTypeBase, ['text/xml', 'application/xml'], true) || $extension === 'xml') {
            $kind = 'configuration-xml';
        }

        return [
            'packageRoot' => self::configurationPackageRoot($path),
            'configurationArea' => $area === '' ? null : $area,
            'configurationKind' => $kind,
            'configurationPath' => $configurationPath,
            'extension' => $extension === '' ? null : $extension,
        ];
    }

    private static function scriptMediaTypeFromPart(string $path): ?string
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return match ($extension) {
            'bsh' => 'application/x-beanshell',
            'class' => 'application/java-vm',
            'jar' => 'application/java-archive',
            'js', 'mjs' => 'application/javascript',
            'py' => 'text/x-python',
            'rb' => 'text/x-ruby',
            'xba', 'xdl', 'xlb', 'xml' => 'text/xml',
            default => null,
        };
    }

    private static function scriptMediaTypeValid(string $path, string $mediaTypeBase): bool
    {
        if ($mediaTypeBase === '') {
            return false;
        }

        $expected = self::scriptMediaTypeFromPart($path);
        if ($expected === null) {
            return true;
        }

        $expectedBase = self::mediaTypeReport($expected)['mediaTypeBase'];
        if ($expectedBase === 'application/javascript') {
            return in_array($mediaTypeBase, [
                'application/ecmascript',
                'application/javascript',
                'application/x-javascript',
                'text/ecmascript',
                'text/javascript',
                'text/x-javascript',
            ], true);
        }
        if ($expectedBase === 'text/xml') {
            return in_array($mediaTypeBase, ['text/xml', 'application/xml'], true);
        }
        if ($expectedBase === 'text/x-python') {
            return in_array($mediaTypeBase, ['text/x-python', 'application/x-python'], true);
        }
        if ($expectedBase === 'text/x-ruby') {
            return in_array($mediaTypeBase, ['text/x-ruby', 'application/x-ruby'], true);
        }
        if ($expectedBase === 'application/x-beanshell') {
            return in_array($mediaTypeBase, ['application/x-beanshell', 'text/x-beanshell', 'application/x-bsh'], true);
        }

        return $mediaTypeBase === $expectedBase;
    }

    /**
     * @return array<string, string|null>
     */
    private static function scriptPackagePathInfo(string $path, string $mediaTypeBase): array
    {
        $trimmed = trim($path, '/');
        $segments = $trimmed === '' ? [] : explode('/', $trimmed);
        $container = strtolower($segments[0] ?? '');
        $isDirectory = str_ends_with($path, '/');
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $scriptPath = count($segments) > 1 ? implode('/', array_slice($segments, 1)) : null;
        $module = $isDirectory ? null : ($extension === '' ? basename($path) : basename($path, '.' . $extension));
        $library = null;
        if (in_array($container, ['basic', 'dialogs'], true) && isset($segments[1]) && $segments[1] !== '') {
            $library = $segments[1];
        } elseif ($container === 'scripts' && count($segments) > 2 && isset($segments[1]) && $segments[1] !== '') {
            $library = $segments[1];
        }

        $basename = strtolower(basename($path));
        $kind = $isDirectory ? 'script-directory' : match ($extension) {
            'bsh' => 'beanshell',
            'class' => 'java-class',
            'jar' => 'java-archive',
            'js', 'mjs' => 'javascript',
            'py' => 'python',
            'rb' => 'ruby',
            'xba' => 'basic-module',
            'xdl' => $container === 'dialogs' ? 'basic-dialog' : 'script-xml',
            'xlb' => 'basic-library-index',
            'xml' => in_array($basename, ['script-lb.xml', 'script-lc.xml', 'dialog-lb.xml', 'dialog-lc.xml'], true)
                ? 'basic-library-index'
                : match ($container) {
                    'basic' => 'basic-module',
                    'dialogs' => 'basic-dialog',
                    default => 'script-xml',
                },
            default => match ($mediaTypeBase) {
                'application/ecmascript',
                'application/javascript',
                'application/x-javascript',
                'text/ecmascript',
                'text/javascript',
                'text/x-javascript' => 'javascript',
                'application/x-beanshell', 'text/x-beanshell', 'application/x-bsh' => 'beanshell',
                'application/java-archive' => 'java-archive',
                'application/java-vm' => 'java-class',
                'text/x-python', 'application/x-python' => 'python',
                'text/x-ruby', 'application/x-ruby' => 'ruby',
                default => $container === 'basic' ? 'basic-package-part' : 'script-package-part',
            },
        };

        return [
            'scriptContainer' => $container === '' ? null : $container,
            'scriptKind' => $kind,
            'scriptPath' => $scriptPath,
            'scriptLibrary' => $library,
            'scriptModule' => $module === '' ? null : $module,
            'extension' => $extension === '' ? null : $extension,
        ];
    }

    /**
     * @param list<array<string, mixed>> $manifestEntries
     * @return array<string, mixed>
     */
    private static function embeddedObjectPackageMetadata(ZipPackage $package, array $manifestEntries): array
    {
        $objectPackageRootParts = self::embeddedObjectPackageRootParts($manifestEntries);
        $entriesByPath = [];
        foreach ($package->entries() as $entry) {
            $entriesByPath[$entry->name] = $entry;
        }
        $manifestEntriesByPath = [];
        foreach ($manifestEntries as $entry) {
            $packagePath = $entry['packagePath'] ?? null;
            if (is_string($packagePath) && $packagePath !== '') {
                $manifestEntriesByPath[$packagePath] = $entry;
            }
        }

        $items = [];
        $byRootPart = [];
        $rootParts = [];
        $objectTypes = [];
        $issueCodes = [];
        foreach ($objectPackageRootParts as $rootPart => $rootEntry) {
            $objectPath = rtrim($rootPart, '/');
            $declaredContainedParts = [];
            $declaredContainedPartItems = [];
            $existingDeclaredContainedParts = [];
            $missingDeclaredContainedParts = [];
            $encryptedDeclaredContainedParts = [];
            foreach ($manifestEntries as $entry) {
                $packagePath = $entry['packagePath'] ?? null;
                if (!is_string($packagePath) || $packagePath === '' || $packagePath === $rootPart || !str_starts_with($packagePath, $rootPart)) {
                    continue;
                }

                $declaredContainedParts[$packagePath] = true;
                $summary = self::embeddedObjectManifestPartSummary($entry);
                $declaredContainedPartItems[] = $summary;
                if (($entry['exists'] ?? false) === true) {
                    $existingDeclaredContainedParts[] = $summary;
                } else {
                    $missingDeclaredContainedParts[] = $summary;
                }
                if (($entry['encrypted'] ?? false) === true) {
                    $encryptedDeclaredContainedParts[] = $summary;
                }
            }

            $containedParts = [];
            $undeclaredContainedParts = [];
            $containedByteLength = 0;
            $containedRoleCounts = [];
            $containedRoleByteLengths = [];
            $containedRoleCompressedByteLengths = [];
            $containedMediaFamilyCounts = [];
            $containedMediaFamilyByteLengths = [];
            $containedMediaFamilyCompressedByteLengths = [];
            foreach ($entriesByPath as $path => $entry) {
                if ($path === $rootPart || !str_starts_with($path, $rootPart) || $entry->isDirectory()) {
                    continue;
                }

                $containedManifestEntry = $manifestEntriesByPath[$entry->name] ?? null;
                $containedClassification = self::embeddedObjectContainedPartClassification(
                    $entry->name,
                    $containedManifestEntry
                );
                $containedRole = $containedClassification['containedRole'];
                $containedMediaFamily = $containedClassification['containedMediaFamily'];
                $partSummary = [
                    'path' => $entry->name,
                    'part' => $entry->name,
                    'byteLength' => $entry->uncompressedSize,
                    'compressedByteLength' => $entry->compressedSize,
                    'compressionMethod' => $entry->compressionMethod,
                    'compressionMethodName' => self::compressionMethodName($entry->compressionMethod),
                    'crc32' => $entry->crc32Hex(),
                    'declaredInManifest' => isset($declaredContainedParts[$entry->name]),
                    'manifestMediaType' => is_array($containedManifestEntry) ? ($containedManifestEntry['mediaType'] ?? '') : null,
                    'manifestMediaTypeBase' => is_array($containedManifestEntry) ? ($containedManifestEntry['mediaTypeBase'] ?? '') : null,
                    'manifestMediaTypeHasParameters' => is_array($containedManifestEntry) && ($containedManifestEntry['mediaTypeHasParameters'] ?? false) === true,
                    'manifestMediaTypeParameterCount' => is_array($containedManifestEntry) ? ($containedManifestEntry['mediaTypeParameterCount'] ?? 0) : 0,
                    'manifestMediaTypeParameters' => is_array($containedManifestEntry) ? ($containedManifestEntry['mediaTypeParameters'] ?? []) : [],
                    'manifestMediaTypeParameterMap' => is_array($containedManifestEntry) ? ($containedManifestEntry['mediaTypeParameterMap'] ?? []) : [],
                    'containedRole' => $containedRole,
                    'containedMediaFamily' => $containedMediaFamily,
                ];
                $containedParts[] = $partSummary;
                $containedByteLength += $entry->uncompressedSize;
                $containedRoleCounts[$containedRole] = ($containedRoleCounts[$containedRole] ?? 0) + 1;
                $containedRoleByteLengths[$containedRole] = ($containedRoleByteLengths[$containedRole] ?? 0) + $entry->uncompressedSize;
                $containedRoleCompressedByteLengths[$containedRole] = ($containedRoleCompressedByteLengths[$containedRole] ?? 0) + $entry->compressedSize;
                $containedMediaFamilyCounts[$containedMediaFamily] = ($containedMediaFamilyCounts[$containedMediaFamily] ?? 0) + 1;
                $containedMediaFamilyByteLengths[$containedMediaFamily] = ($containedMediaFamilyByteLengths[$containedMediaFamily] ?? 0) + $entry->uncompressedSize;
                $containedMediaFamilyCompressedByteLengths[$containedMediaFamily] = ($containedMediaFamilyCompressedByteLengths[$containedMediaFamily] ?? 0) + $entry->compressedSize;
                if (!isset($declaredContainedParts[$entry->name])) {
                    $undeclaredContainedParts[] = $partSummary;
                }
            }

            usort($containedParts, static fn (array $left, array $right): int => strcmp((string) $left['part'], (string) $right['part']));
            usort($undeclaredContainedParts, static fn (array $left, array $right): int => strcmp((string) $left['part'], (string) $right['part']));
            ksort($containedRoleCounts, SORT_STRING);
            ksort($containedRoleByteLengths, SORT_STRING);
            ksort($containedRoleCompressedByteLengths, SORT_STRING);
            ksort($containedMediaFamilyCounts, SORT_STRING);
            ksort($containedMediaFamilyByteLengths, SORT_STRING);
            ksort($containedMediaFamilyCompressedByteLengths, SORT_STRING);

            $exists = $containedParts !== [] || isset($entriesByPath[$rootPart]);
            $encrypted = ($rootEntry['encrypted'] ?? false) === true || $encryptedDeclaredContainedParts !== [];
            $issues = [];
            if (!$exists) {
                $issues[] = 'odf-embedded-object-package-missing';
            }
            if ($missingDeclaredContainedParts !== []) {
                $issues[] = 'odf-embedded-object-package-missing-declared-part';
            }
            if ($undeclaredContainedParts !== []) {
                $issues[] = 'odf-embedded-object-package-undeclared-contained-part';
            }
            if ($encrypted) {
                $issues[] = 'odf-embedded-object-package-encrypted';
            }
            foreach ($issues as $issue) {
                $issueCodes[$issue] = true;
            }

            $objectType = self::objectTypeForMediaType((string) ($rootEntry['mediaTypeBase'] ?? $rootEntry['mediaType'] ?? ''));
            $item = [
                'rootPart' => $rootPart,
                'objectPath' => $objectPath,
                'fullPath' => $rootEntry['path'] ?? null,
                'manifestIndex' => $rootEntry['manifestIndex'] ?? null,
                'objectType' => $objectType,
                'mediaType' => $rootEntry['mediaType'] ?? '',
                'mediaTypeBase' => $rootEntry['mediaTypeBase'] ?? '',
                'mediaTypeHasParameters' => ($rootEntry['mediaTypeHasParameters'] ?? false) === true,
                'mediaTypeParameterCount' => $rootEntry['mediaTypeParameterCount'] ?? 0,
                'mediaTypeParameters' => $rootEntry['mediaTypeParameters'] ?? [],
                'mediaTypeParameterMap' => $rootEntry['mediaTypeParameterMap'] ?? [],
                'version' => $rootEntry['version'] ?? null,
                'preferredViewMode' => $rootEntry['preferredViewMode'] ?? null,
                'exists' => $exists,
                'encrypted' => $encrypted,
                'canExposeBytes' => false,
                'byteExposurePolicy' => 'embedded-object-package-bytes-blocked',
                'reviewPolicy' => 'embedded-object-package-metadata-only',
                'containedPartCount' => count($containedParts),
                'containedByteLength' => $containedParts === [] ? null : $containedByteLength,
                'containedRoleCounts' => $containedRoleCounts,
                'containedRoleByteLengths' => $containedRoleByteLengths,
                'containedRoleCompressedByteLengths' => $containedRoleCompressedByteLengths,
                'containedMediaFamilyCounts' => $containedMediaFamilyCounts,
                'containedMediaFamilyByteLengths' => $containedMediaFamilyByteLengths,
                'containedMediaFamilyCompressedByteLengths' => $containedMediaFamilyCompressedByteLengths,
                'containedParts' => $containedParts,
                'declaredContainedPartCount' => count($declaredContainedPartItems),
                'declaredContainedParts' => $declaredContainedPartItems,
                'existingDeclaredContainedPartCount' => count($existingDeclaredContainedParts),
                'missingDeclaredContainedPartCount' => count($missingDeclaredContainedParts),
                'missingDeclaredContainedParts' => $missingDeclaredContainedParts,
                'encryptedDeclaredContainedPartCount' => count($encryptedDeclaredContainedParts),
                'undeclaredContainedPartCount' => count($undeclaredContainedParts),
                'undeclaredContainedParts' => $undeclaredContainedParts,
                'issues' => $issues,
            ];
            $items[] = $item;
            $byRootPart[$rootPart] = $item;
            $rootParts[] = $rootPart;
            if (!in_array($objectType, $objectTypes, true)) {
                $objectTypes[] = $objectType;
            }
        }
        ksort($issueCodes, SORT_STRING);

        return [
            'count' => count($items),
            'existingCount' => count(array_filter($items, static fn (array $item): bool => $item['exists'] === true)),
            'missingCount' => count(array_filter($items, static fn (array $item): bool => in_array('odf-embedded-object-package-missing', $item['issues'], true))),
            'encryptedCount' => count(array_filter($items, static fn (array $item): bool => $item['encrypted'] === true)),
            'containedPartCount' => array_sum(array_map(static fn (array $item): int => (int) $item['containedPartCount'], $items)),
            'containedByteLength' => array_sum(array_map(static fn (array $item): int => (int) ($item['containedByteLength'] ?? 0), $items)),
            'containedRoleCounts' => self::sumEmbeddedObjectPackageBuckets($items, 'containedRoleCounts'),
            'containedRoleByteLengths' => self::sumEmbeddedObjectPackageBuckets($items, 'containedRoleByteLengths'),
            'containedRoleCompressedByteLengths' => self::sumEmbeddedObjectPackageBuckets($items, 'containedRoleCompressedByteLengths'),
            'containedMediaFamilyCounts' => self::sumEmbeddedObjectPackageBuckets($items, 'containedMediaFamilyCounts'),
            'containedMediaFamilyByteLengths' => self::sumEmbeddedObjectPackageBuckets($items, 'containedMediaFamilyByteLengths'),
            'containedMediaFamilyCompressedByteLengths' => self::sumEmbeddedObjectPackageBuckets($items, 'containedMediaFamilyCompressedByteLengths'),
            'declaredContainedPartCount' => array_sum(array_map(static fn (array $item): int => (int) $item['declaredContainedPartCount'], $items)),
            'existingDeclaredContainedPartCount' => array_sum(array_map(static fn (array $item): int => (int) $item['existingDeclaredContainedPartCount'], $items)),
            'missingDeclaredContainedPartCount' => array_sum(array_map(static fn (array $item): int => (int) $item['missingDeclaredContainedPartCount'], $items)),
            'encryptedDeclaredContainedPartCount' => array_sum(array_map(static fn (array $item): int => (int) $item['encryptedDeclaredContainedPartCount'], $items)),
            'undeclaredContainedPartCount' => array_sum(array_map(static fn (array $item): int => (int) $item['undeclaredContainedPartCount'], $items)),
            'issueCount' => count(array_filter($items, static fn (array $item): bool => $item['issues'] !== [])),
            'issueCodes' => array_keys($issueCodes),
            'rootParts' => $rootParts,
            'objectTypes' => $objectTypes,
            'byteExposurePolicy' => 'embedded-object-package-bytes-blocked',
            'reviewPolicy' => 'embedded-object-package-metadata-only',
            'byRootPart' => $byRootPart,
            'items' => $items,
        ];
    }

    /**
     * @param array<string, mixed>|null $manifestEntry
     * @return array{containedRole:string, containedMediaFamily:string}
     */
    private static function embeddedObjectContainedPartClassification(string $part, ?array $manifestEntry): array
    {
        $mediaTypeBase = '';
        if (is_array($manifestEntry)) {
            $mediaTypeBase = (string) ($manifestEntry['mediaTypeBase'] ?? '');
            if ($mediaTypeBase === '') {
                $mediaTypeBase = self::mediaTypeReport((string) ($manifestEntry['mediaType'] ?? ''))['mediaTypeBase'];
            }
        }

        $mediaFamily = self::embeddedObjectContainedMediaFamily($part, $mediaTypeBase);
        $role = match ($mediaFamily) {
            'rdf' => 'rdf-metadata',
            'image', 'audio', 'video' => 'media-resource',
            'xml' => 'document-xml',
            default => 'package-part',
        };

        return [
            'containedRole' => $role,
            'containedMediaFamily' => $mediaFamily,
        ];
    }

    private static function embeddedObjectContainedMediaFamily(string $part, string $mediaTypeBase): string
    {
        $base = strtolower(trim($mediaTypeBase));
        if ($base === 'application/rdf+xml' || self::isRdfPartName($part)) {
            return 'rdf';
        }

        $mediaFamily = self::mediaResourceFamilyFromMediaTypeBase($base);
        if ($mediaFamily !== null) {
            return $mediaFamily;
        }
        if (self::isXmlMediaTypeBase($base) || self::isEmbeddedObjectXmlPartName($part)) {
            return 'xml';
        }

        $packageFamily = self::mediaResourceFamilyFromPackagePart($part);
        if ($packageFamily !== null) {
            return $packageFamily;
        }

        return 'other';
    }

    private static function isXmlMediaTypeBase(string $mediaTypeBase): bool
    {
        $base = strtolower(trim($mediaTypeBase));

        return $base === 'text/xml'
            || $base === 'application/xml'
            || str_ends_with($base, '+xml');
    }

    private static function isEmbeddedObjectXmlPartName(string $part): bool
    {
        $name = strtolower(basename($part));

        return in_array($name, ['content.xml', 'styles.xml', 'meta.xml', 'settings.xml'], true)
            || str_ends_with($name, '.xml');
    }

    private static function mediaResourceFamilyFromMediaTypeBase(string $mediaTypeBase): ?string
    {
        $base = strtolower(trim($mediaTypeBase));
        if (str_starts_with($base, 'image/')) {
            return 'image';
        }
        if (str_starts_with($base, 'audio/')) {
            return 'audio';
        }
        if (str_starts_with($base, 'video/')) {
            return 'video';
        }

        return null;
    }

    private static function mediaResourceFamilyFromPackagePart(string $part): ?string
    {
        $normalized = strtolower(ltrim($part, '/'));
        if (str_starts_with($normalized, 'pictures/')) {
            return 'image';
        }

        return match (strtolower(pathinfo($normalized, PATHINFO_EXTENSION))) {
            'apng', 'avif', 'bmp', 'gif', 'jpe', 'jpeg', 'jpg', 'png', 'svg', 'tif', 'tiff', 'webp' => 'image',
            'aac', 'aif', 'aiff', 'flac', 'm4a', 'mp3', 'oga', 'ogg', 'opus', 'wav', 'weba' => 'audio',
            'avi', 'm4v', 'mov', 'mp4', 'mpeg', 'mpg', 'ogv', 'webm' => 'video',
            default => null,
        };
    }

    /**
     * @param list<array<string, mixed>> $items
     * @return array<string, int>
     */
    private static function sumEmbeddedObjectPackageBuckets(array $items, string $key): array
    {
        $summary = [];
        foreach ($items as $item) {
            $buckets = $item[$key] ?? [];
            if (!is_array($buckets)) {
                continue;
            }
            foreach ($buckets as $bucket => $value) {
                if (!is_string($bucket)) {
                    continue;
                }
                $summary[$bucket] = ($summary[$bucket] ?? 0) + (int) $value;
            }
        }
        ksort($summary, SORT_STRING);

        return $summary;
    }

    /**
     * @param array<string, mixed> $entry
     * @return array<string, mixed>
     */
    private static function embeddedObjectManifestPartSummary(array $entry): array
    {
        return [
            'fullPath' => $entry['path'] ?? null,
            'path' => $entry['path'] ?? null,
            'part' => $entry['packagePath'] ?? null,
            'manifestIndex' => $entry['manifestIndex'] ?? null,
            'mediaType' => $entry['mediaType'] ?? '',
            'mediaTypeBase' => $entry['mediaTypeBase'] ?? '',
            'mediaTypeHasParameters' => ($entry['mediaTypeHasParameters'] ?? false) === true,
            'mediaTypeParameterCount' => $entry['mediaTypeParameterCount'] ?? 0,
            'mediaTypeParameters' => $entry['mediaTypeParameters'] ?? [],
            'mediaTypeParameterMap' => $entry['mediaTypeParameterMap'] ?? [],
            'exists' => ($entry['exists'] ?? false) === true,
            'encrypted' => ($entry['encrypted'] ?? false) === true,
            'canExposeBytes' => ($entry['canExposeBytes'] ?? false) === true,
            'byteExposurePolicy' => $entry['byteExposurePolicy'] ?? null,
            'declaredSize' => $entry['declaredSize'] ?? null,
            'storedByteLength' => $entry['storedByteLength'] ?? null,
            'compressedByteLength' => $entry['compressedByteLength'] ?? null,
            'compressionMethod' => $entry['compressionMethod'] ?? null,
            'compressionMethodName' => $entry['compressionMethodName'] ?? null,
            'storedCrc32' => $entry['storedCrc32'] ?? null,
            'diagnostics' => $entry['diagnostics'] ?? [],
        ];
    }

    private static function isSignaturePackagePartName(string $path): bool
    {
        $normalized = strtolower(ltrim($path, '/'));

        return str_starts_with($normalized, 'meta-inf/')
            && !str_ends_with($normalized, '/')
            && (
                str_ends_with($normalized, 'signatures.xml')
                || self::isSignatureCertificatePackagePartName($normalized)
            );
    }

    private static function isSignatureCertificatePackagePartName(string $path): bool
    {
        $normalized = strtolower(ltrim($path, '/'));

        return str_starts_with($normalized, 'meta-inf/certificates/')
            && !str_ends_with($normalized, '/');
    }

    private static function signaturePackagePartKind(string $path): string
    {
        return self::isSignatureCertificatePackagePartName($path)
            ? 'signature-certificate'
            : 'xml-signature';
    }

    /**
     * @return list<string>
     */
    private static function signatureExpectedMediaTypes(string $kind): array
    {
        if ($kind === 'signature-certificate') {
            return [
                'application/pkix-cert',
                'application/x-x509-ca-cert',
                'application/x-x509-user-cert',
                'application/x-pem-file',
                'application/pem-certificate-chain',
                'application/octet-stream',
                'application/binary',
            ];
        }

        return ['text/xml', 'application/xml'];
    }

    private static function isSignaturePackageMediaType(string $kind, string $mediaTypeBase): bool
    {
        return in_array($mediaTypeBase, self::signatureExpectedMediaTypes($kind), true);
    }

    /**
     * @param list<array<string, mixed>> $entries
     * @param list<array<string, mixed>> $undeclaredPackageEntries
     * @return array<string, mixed>
     */
    private static function manifestReview(array $entries, array $undeclaredPackageEntries = [], array $manifestRootAttributes = []): array
    {
        $summary = [
            'count' => count($entries),
            'existsCount' => 0,
            'missingCount' => 0,
            'directoryCount' => 0,
            'encryptedCount' => 0,
            'declaredSizeMismatchCount' => 0,
            'invalidDeclaredSizeCount' => 0,
            'invalidDeclaredSizeItems' => [],
            'storedByteLength' => 0,
            'compressedByteLength' => 0,
            'exposableByteLength' => 0,
            'declaredSize' => 0,
            'declaredSizeItemCount' => 0,
            'declaredSizeItems' => [],
            'largestDeclaredSizeItemLimit' => self::MANIFEST_DECLARED_SIZE_LARGEST_ITEM_LIMIT,
            'largestDeclaredSizeItemCount' => 0,
            'largestDeclaredSizeItems' => [],
            'storedCompressionMethodCount' => 0,
            'deflatedCompressionMethodCount' => 0,
            'unsupportedCompressionMethodCount' => 0,
            'manifestFileEntryCount' => count($entries),
            'manifestFileEntryOrder' => [],
            'manifestPartReferenceSuffixCount' => 0,
            'manifestPartReferenceQueryCount' => 0,
            'manifestPartReferenceFragmentCount' => 0,
            'manifestPartReferenceSuffixItems' => [],
            'manifestMediaFamilyCounts' => [],
            'manifestMediaFamilyByteLengths' => [],
            'manifestMediaFamilyCompressedByteLengths' => [],
            'manifestMediaFamilyItems' => [],
            'manifestRootAttributeCount' => $manifestRootAttributes['attributeCount'] ?? 0,
            'manifestRootAttributeNames' => $manifestRootAttributes['attributeNames'] ?? [],
            'manifestRootAttributes' => $manifestRootAttributes['attributes'] ?? [],
            'manifestRootCustomAttributeCount' => $manifestRootAttributes['customAttributeCount'] ?? 0,
            'manifestRootCustomAttributeNames' => $manifestRootAttributes['customAttributeNames'] ?? [],
            'manifestRootCustomAttributes' => $manifestRootAttributes['customAttributes'] ?? [],
            'manifestRootCustomAttributeMap' => $manifestRootAttributes['customAttributeMap'] ?? [],
            'manifestRootNamespaceDeclarationCount' => $manifestRootAttributes['namespaceDeclarationCount'] ?? 0,
            'manifestRootNamespaceDeclarationNames' => $manifestRootAttributes['namespaceDeclarationNames'] ?? [],
            'manifestRootNamespaceDeclarations' => $manifestRootAttributes['namespaceDeclarations'] ?? [],
            'manifestRootNamespaceDeclarationMap' => $manifestRootAttributes['namespaceDeclarationMap'] ?? [],
            'manifestCustomAttributeEntryCount' => 0,
            'manifestCustomAttributeCount' => 0,
            'manifestCustomAttributeNames' => [],
            'manifestCustomAttributeItems' => [],
            'manifestCustomChildElementEntryCount' => 0,
            'manifestCustomChildElementCount' => 0,
            'manifestCustomChildElementNames' => [],
            'manifestCustomChildElementItems' => [],
            'uriEncodedPackageReferenceCount' => 0,
            'uriEncodedPackageReferenceItems' => [],
            'zipTimestampEntryCount' => 0,
            'zipTimestampSourceCounts' => [],
            'zipTimestampItems' => [],
            'zipInvalidDosTimestampEntryCount' => 0,
            'zipInvalidDosTimestampItems' => [],
            'packageThumbnailPartCount' => 0,
            'packageThumbnailItems' => [],
            'embeddedObjectPackagePartCount' => 0,
            'embeddedObjectRootCount' => 0,
            'embeddedObjectContainedPartCount' => 0,
            'embeddedObjectPackageItems' => [],
            'objectReplacementPackagePartCount' => 0,
            'objectReplacementPackageItems' => [],
            'scriptPackagePartCount' => 0,
            'scriptPackageItems' => [],
            'configurationPackagePartCount' => 0,
            'configurationPackageItems' => [],
            'fontPackagePartCount' => 0,
            'fontPackageItems' => [],
            'rdfMetadataPartCount' => 0,
            'rdfMetadataItems' => [],
            'layoutCachePartCount' => 0,
            'layoutCacheItems' => [],
            'metaInfSidecarPartCount' => 0,
            'metaInfSidecarItems' => [],
            'linkedResourcePackagePartCount' => 0,
            'linkedResourcePackageItems' => [],
            'databasePackagePartCount' => 0,
            'databasePackageItems' => [],
            'versionPackagePartCount' => 0,
            'versionPackageItems' => [],
            'galleryPackagePartCount' => 0,
            'galleryPackageItems' => [],
            'formPackagePartCount' => 0,
            'formPackageItems' => [],
            'reportPackagePartCount' => 0,
            'reportPackageItems' => [],
            'attachmentPackagePartCount' => 0,
            'attachmentPackageItems' => [],
            'templatePackagePartCount' => 0,
            'templatePackageItems' => [],
            'dictionaryPackagePartCount' => 0,
            'dictionaryPackageItems' => [],
            'eventPackagePartCount' => 0,
            'eventPackageItems' => [],
            'missingMediaTypeCount' => 0,
            'missingMediaTypeItems' => [],
            'diagnosticCount' => 0,
            'diagnosticCodeCounts' => [],
            'diagnostics' => [],
            'undeclaredPackageEntryCount' => count($undeclaredPackageEntries),
            'undeclaredPackageEntries' => $undeclaredPackageEntries,
            'items' => [],
            'missingItems' => [],
            'directoryItems' => [],
            'encryptedItems' => [],
            'declaredSizeMismatches' => [],
        ];

        foreach ($entries as $entry) {
            $item = self::manifestReviewItem($entry);
            $summary['items'][] = $item;
            $summary['manifestFileEntryOrder'][] = self::manifestFileEntryOrderItem($entry);
            if (is_string($entry['pathSuffix'] ?? null)) {
                $summary['manifestPartReferenceSuffixItems'][] = self::manifestPartReferenceSuffixItem($entry);
            }
            if (is_string($entry['pathQuery'] ?? null)) {
                ++$summary['manifestPartReferenceQueryCount'];
            }
            if (is_string($entry['pathFragment'] ?? null)) {
                ++$summary['manifestPartReferenceFragmentCount'];
            }
            if (is_string($entry['manifestMediaFamily'] ?? null)) {
                $family = $entry['manifestMediaFamily'];
                $summary['manifestMediaFamilyCounts'][$family] = ($summary['manifestMediaFamilyCounts'][$family] ?? 0) + 1;
                if (is_int($entry['storedByteLength'] ?? null)) {
                    $summary['manifestMediaFamilyByteLengths'][$family] = ($summary['manifestMediaFamilyByteLengths'][$family] ?? 0) + $entry['storedByteLength'];
                }
                if (is_int($entry['compressedByteLength'] ?? null)) {
                    $summary['manifestMediaFamilyCompressedByteLengths'][$family] = ($summary['manifestMediaFamilyCompressedByteLengths'][$family] ?? 0) + $entry['compressedByteLength'];
                }
                $summary['manifestMediaFamilyItems'][] = self::manifestMediaFamilyItem($entry);
            }
            $customManifestAttributes = is_array($entry['customManifestAttributes'] ?? null)
                ? $entry['customManifestAttributes']
                : [];
            if ($customManifestAttributes !== []) {
                ++$summary['manifestCustomAttributeEntryCount'];
                $summary['manifestCustomAttributeCount'] += count($customManifestAttributes);
                foreach ($entry['customManifestAttributeNames'] ?? [] as $attributeName) {
                    if (is_string($attributeName) && $attributeName !== '' && !in_array($attributeName, $summary['manifestCustomAttributeNames'], true)) {
                        $summary['manifestCustomAttributeNames'][] = $attributeName;
                    }
                }
                $summary['manifestCustomAttributeItems'][] = [
                    'manifestIndex' => $entry['manifestIndex'] ?? null,
                    'fullPath' => $entry['path'],
                    'path' => $entry['path'],
                    'part' => $entry['packagePath'] ?? null,
                    'packagePath' => $entry['packagePath'] ?? null,
                    'customManifestAttributeCount' => count($customManifestAttributes),
                    'customManifestAttributeNames' => $entry['customManifestAttributeNames'] ?? [],
                    'customManifestAttributes' => $customManifestAttributes,
                    'customManifestAttributeMap' => $entry['customManifestAttributeMap'] ?? [],
                    'manifestNamespaceDeclarationCount' => $entry['manifestNamespaceDeclarationCount'] ?? 0,
                    'manifestNamespaceDeclarationNames' => $entry['manifestNamespaceDeclarationNames'] ?? [],
                    'manifestNamespaceDeclarations' => $entry['manifestNamespaceDeclarations'] ?? [],
                    'manifestNamespaceDeclarationMap' => $entry['manifestNamespaceDeclarationMap'] ?? [],
                ];
            }
            $customManifestChildElements = is_array($entry['customManifestChildElements'] ?? null)
                ? $entry['customManifestChildElements']
                : [];
            if ($customManifestChildElements !== []) {
                ++$summary['manifestCustomChildElementEntryCount'];
                $summary['manifestCustomChildElementCount'] += count($customManifestChildElements);
                foreach ($entry['customManifestChildElementNames'] ?? [] as $elementName) {
                    if (is_string($elementName) && $elementName !== '' && !in_array($elementName, $summary['manifestCustomChildElementNames'], true)) {
                        $summary['manifestCustomChildElementNames'][] = $elementName;
                    }
                }
                $summary['manifestCustomChildElementItems'][] = [
                    'manifestIndex' => $entry['manifestIndex'] ?? null,
                    'fullPath' => $entry['path'],
                    'path' => $entry['path'],
                    'part' => $entry['packagePath'] ?? null,
                    'packagePath' => $entry['packagePath'] ?? null,
                    'customManifestChildElementCount' => count($customManifestChildElements),
                    'customManifestChildElementNames' => $entry['customManifestChildElementNames'] ?? [],
                    'customManifestChildElements' => $customManifestChildElements,
                    'manifestChildElementCount' => $entry['manifestChildElementCount'] ?? 0,
                    'manifestChildElementNames' => $entry['manifestChildElementNames'] ?? [],
                ];
            }
            if (($entry['uriEncodedPackageReference'] ?? false) === true) {
                ++$summary['uriEncodedPackageReferenceCount'];
                $summary['uriEncodedPackageReferenceItems'][] = $item;
            }
            if (is_int($entry['zipModifiedAt'] ?? null) || is_string($entry['zipTimestampSource'] ?? null)) {
                ++$summary['zipTimestampEntryCount'];
                $summary['zipTimestampItems'][] = $item;
                $source = (string) ($entry['zipTimestampSource'] ?? '');
                if ($source !== '') {
                    $summary['zipTimestampSourceCounts'][$source] = ($summary['zipTimestampSourceCounts'][$source] ?? 0) + 1;
                }
            }
            if (($entry['zipIsDosTimestampValid'] ?? true) !== true) {
                ++$summary['zipInvalidDosTimestampEntryCount'];
                $summary['zipInvalidDosTimestampItems'][] = $item;
            }
            if (($entry['thumbnailPackagePart'] ?? false) === true) {
                ++$summary['packageThumbnailPartCount'];
                $summary['packageThumbnailItems'][] = $item;
            }
            if (($entry['embeddedObjectPackagePart'] ?? false) === true) {
                ++$summary['embeddedObjectPackagePartCount'];
                $summary['embeddedObjectPackageItems'][] = $item;
                if (($entry['embeddedObjectRoot'] ?? false) === true) {
                    ++$summary['embeddedObjectRootCount'];
                } else {
                    ++$summary['embeddedObjectContainedPartCount'];
                }
            }
            if (($entry['objectReplacementPackagePart'] ?? false) === true) {
                ++$summary['objectReplacementPackagePartCount'];
                $summary['objectReplacementPackageItems'][] = $item;
            }
            if (($entry['scriptPackagePart'] ?? false) === true) {
                ++$summary['scriptPackagePartCount'];
                $summary['scriptPackageItems'][] = $item;
            }
            if (($entry['configurationPackagePart'] ?? false) === true) {
                ++$summary['configurationPackagePartCount'];
                $summary['configurationPackageItems'][] = $item;
            }
            if (($entry['fontPackagePart'] ?? false) === true) {
                ++$summary['fontPackagePartCount'];
                $summary['fontPackageItems'][] = $item;
            }
            if (($entry['rdfMetadataPart'] ?? false) === true) {
                ++$summary['rdfMetadataPartCount'];
                $summary['rdfMetadataItems'][] = $item;
            }
            if (($entry['layoutCachePackagePart'] ?? false) === true) {
                ++$summary['layoutCachePartCount'];
                $summary['layoutCacheItems'][] = $item;
            }
            if (($entry['metaInfSidecarPackagePart'] ?? false) === true) {
                ++$summary['metaInfSidecarPartCount'];
                $summary['metaInfSidecarItems'][] = $item;
            }
            if (($entry['linkedResourcePackagePart'] ?? false) === true) {
                ++$summary['linkedResourcePackagePartCount'];
                $summary['linkedResourcePackageItems'][] = $item;
            }
            if (($entry['databasePackagePart'] ?? false) === true) {
                ++$summary['databasePackagePartCount'];
                $summary['databasePackageItems'][] = $item;
            }
            if (($entry['versionPackagePart'] ?? false) === true) {
                ++$summary['versionPackagePartCount'];
                $summary['versionPackageItems'][] = $item;
            }
            if (($entry['galleryPackagePart'] ?? false) === true) {
                ++$summary['galleryPackagePartCount'];
                $summary['galleryPackageItems'][] = $item;
            }
            if (($entry['formPackagePart'] ?? false) === true) {
                ++$summary['formPackagePartCount'];
                $summary['formPackageItems'][] = $item;
            }
            if (($entry['reportPackagePart'] ?? false) === true) {
                ++$summary['reportPackagePartCount'];
                $summary['reportPackageItems'][] = $item;
            }
            if (($entry['attachmentPackagePart'] ?? false) === true) {
                ++$summary['attachmentPackagePartCount'];
                $summary['attachmentPackageItems'][] = $item;
            }
            if (($entry['templatePackagePart'] ?? false) === true) {
                ++$summary['templatePackagePartCount'];
                $summary['templatePackageItems'][] = $item;
            }
            if (($entry['dictionaryPackagePart'] ?? false) === true) {
                ++$summary['dictionaryPackagePartCount'];
                $summary['dictionaryPackageItems'][] = $item;
            }
            if (($entry['eventPackagePart'] ?? false) === true) {
                ++$summary['eventPackagePartCount'];
                $summary['eventPackageItems'][] = $item;
            }
            if (($entry['missingMediaType'] ?? false) === true) {
                ++$summary['missingMediaTypeCount'];
                $summary['missingMediaTypeItems'][] = $item;
            }
            foreach (is_array($entry['diagnostics'] ?? null) ? $entry['diagnostics'] : [] as $diagnostic) {
                if (!is_string($diagnostic) || $diagnostic === '') {
                    continue;
                }

                $summary['diagnostics'][] = [
                    'code' => $diagnostic,
                    'path' => (string) ($entry['path'] ?? ''),
                    'packagePath' => is_string($entry['packagePath'] ?? null) ? $entry['packagePath'] : null,
                    'canExposeBytes' => ($entry['canExposeBytes'] ?? false) === true,
                ];
                $summary['diagnosticCodeCounts'][$diagnostic] = ($summary['diagnosticCodeCounts'][$diagnostic] ?? 0) + 1;
            }
            if (($entry['exists'] ?? false) === true) {
                ++$summary['existsCount'];
            } else {
                ++$summary['missingCount'];
                $summary['missingItems'][] = $item;
            }
            if (($entry['isDirectory'] ?? false) === true) {
                ++$summary['directoryCount'];
                $summary['directoryItems'][] = $item;
            }
            if (($entry['encrypted'] ?? false) === true) {
                ++$summary['encryptedCount'];
                $summary['encryptedItems'][] = $item;
            }
            if (($entry['declaredSizeMismatch'] ?? false) === true) {
                ++$summary['declaredSizeMismatchCount'];
                $summary['declaredSizeMismatches'][] = $item;
            }
            if (($entry['declaredSizeInvalid'] ?? false) === true) {
                ++$summary['invalidDeclaredSizeCount'];
                $summary['invalidDeclaredSizeItems'][] = $item;
            }
            if (is_int($entry['storedByteLength'] ?? null)) {
                $summary['storedByteLength'] += $entry['storedByteLength'];
            }
            if (is_int($entry['compressedByteLength'] ?? null)) {
                $summary['compressedByteLength'] += $entry['compressedByteLength'];
            }
            if (($entry['canExposeBytes'] ?? false) === true && is_int($entry['byteLength'] ?? null)) {
                $summary['exposableByteLength'] += $entry['byteLength'];
            }
            if (is_int($entry['declaredSize'] ?? null)) {
                $summary['declaredSize'] += $entry['declaredSize'];
                $summary['declaredSizeItems'][] = $item;
            }
            if (($entry['compressionMethod'] ?? null) === 0) {
                ++$summary['storedCompressionMethodCount'];
            } elseif (($entry['compressionMethod'] ?? null) === 8) {
                ++$summary['deflatedCompressionMethodCount'];
            } elseif (is_int($entry['compressionMethod'] ?? null)) {
                ++$summary['unsupportedCompressionMethodCount'];
            }
        }
        $summary['manifestPartReferenceSuffixCount'] = count($summary['manifestPartReferenceSuffixItems']);
        $summary['preferredViewModes'] = self::manifestPreferredViewModeSummary($entries);
        $summary['manifestEncryption'] = self::manifestEncryptionSummary($entries);
        $summary['diagnosticCount'] = count($summary['diagnostics']);
        $summary['declaredSizeItemCount'] = count($summary['declaredSizeItems']);
        $summary['largestDeclaredSizeItems'] = self::largestDeclaredSizeItems(
            $summary['declaredSizeItems'],
            self::MANIFEST_DECLARED_SIZE_LARGEST_ITEM_LIMIT
        );
        $summary['largestDeclaredSizeItemCount'] = count($summary['largestDeclaredSizeItems']);
        sort($summary['manifestCustomAttributeNames'], SORT_STRING);
        sort($summary['manifestCustomChildElementNames'], SORT_STRING);
        ksort($summary['manifestMediaFamilyCounts'], SORT_STRING);
        ksort($summary['manifestMediaFamilyByteLengths'], SORT_STRING);
        ksort($summary['manifestMediaFamilyCompressedByteLengths'], SORT_STRING);
        ksort($summary['zipTimestampSourceCounts'], SORT_STRING);
        ksort($summary['diagnosticCodeCounts'], SORT_STRING);
        $summary['mediaResources'] = self::manifestMediaResourceRoleSummary($entries);

        return $summary;
    }

    /**
     * @param list<array<string, mixed>> $entries
     * @return array<string, mixed>
     */
    private static function manifestMediaResourceRoleSummary(array $entries): array
    {
        $items = [];
        $roleConflictItems = [];
        $pathMediaTypeConflictItems = [];
        $pathMediaTypeConflictPairs = [];
        $missingMediaResourceItems = [];
        $packageRolePrecedenceItems = [];
        $familyCounts = ['image' => 0, 'audio' => 0, 'video' => 0, 'other' => 0];
        $mediaTypeBaseCounts = [];
        $issueCodeCounts = [];
        $mediaResourceCount = 0;
        $mediaResourceExistingCount = 0;
        $mediaResourceMissingCount = 0;
        $mediaResourceCanExposeCount = 0;
        $existingCount = 0;
        $missingCount = 0;
        $roleConflictCount = 0;
        $resourceRoleConflictCount = 0;

        foreach ($entries as $entry) {
            $part = $entry['packagePath'] ?? $entry['path'] ?? null;
            if (!is_string($part) || $part === '' || str_ends_with($part, '/')) {
                continue;
            }

            $mediaType = (string) ($entry['mediaType'] ?? '');
            $mediaTypeBase = (string) ($entry['mediaTypeBase'] ?? self::mediaTypeReport($mediaType)['mediaTypeBase']);
            if ($mediaType === '' || self::isXmlMediaTypeBase($mediaTypeBase)) {
                continue;
            }

            $declaredFamily = self::mediaResourceFamilyFromMediaTypeBase($mediaTypeBase);
            $pathFamily = self::mediaResourceFamilyFromPackagePart($part);
            $roleSources = [];
            if ($declaredFamily !== null) {
                $roleSources[] = 'manifest-media-type';
            }
            if (str_starts_with($part, 'Pictures/')) {
                $roleSources[] = 'pictures-path';
            } elseif ($pathFamily !== null) {
                $roleSources[] = 'package-extension';
            }

            $packageRolePrecedence = self::mediaResourcePackagePrecedenceRoles($entry);
            $mediaResource = $packageRolePrecedence === [] && self::isMediaResourceManifestEntry($entry);
            if (!$mediaResource && $roleSources === [] && $packageRolePrecedence === []) {
                continue;
            }

            $pathMediaTypeConflict = $declaredFamily !== null && $pathFamily !== null && $declaredFamily !== $pathFamily;
            $roleConflict = $pathMediaTypeConflict;
            $pathMediaTypeConflictPair = $pathMediaTypeConflict ? $pathFamily . ':' . $declaredFamily : null;
            $effectiveFamily = $declaredFamily ?? $pathFamily ?? 'other';
            if (!isset($familyCounts[$effectiveFamily])) {
                $effectiveFamily = 'other';
            }
            $exists = ($entry['exists'] ?? false) === true;
            $canExposeBytes = ($entry['canExposeBytes'] ?? false) === true;
            $issueCodes = [];
            if ($roleConflict) {
                $issueCodes[] = 'odf-media-resource-role-conflict';
            }
            if (!$exists) {
                $issueCodes[] = 'odf-media-resource-missing-package-part';
            }
            if ($packageRolePrecedence !== []) {
                $issueCodes[] = 'odf-media-resource-package-role-precedence';
            }

            $resourceItem = self::withoutEmptyValues([
                'manifestIndex' => $entry['manifestIndex'] ?? null,
                'fullPath' => $entry['path'] ?? null,
                'path' => $entry['path'] ?? null,
                'part' => $part,
                'packagePath' => $part,
                'mediaType' => $mediaType,
                'mediaTypeBase' => $mediaTypeBase,
                'declaredMediaFamily' => $declaredFamily,
                'packagePathMediaFamily' => $pathFamily,
                'effectiveMediaFamily' => $effectiveFamily,
                'roleSources' => $roleSources,
                'mediaResource' => $mediaResource,
                'packageRolePrecedence' => $packageRolePrecedence,
                'exists' => $exists,
                'missing' => !$exists,
                'canExposeBytes' => $canExposeBytes,
                'byteExposurePolicy' => $entry['byteExposurePolicy'] ?? null,
                'roleConflict' => $roleConflict,
                'pathMediaTypeConflict' => $pathMediaTypeConflict,
                'pathMediaTypeConflictPair' => $pathMediaTypeConflictPair,
                'issues' => $issueCodes,
            ]);

            $items[] = $resourceItem;
            ++$familyCounts[$effectiveFamily];
            $mediaTypeBaseCounts[$mediaTypeBase] = ($mediaTypeBaseCounts[$mediaTypeBase] ?? 0) + 1;
            if ($mediaResource) {
                ++$mediaResourceCount;
                if ($exists) {
                    ++$mediaResourceExistingCount;
                } else {
                    ++$mediaResourceMissingCount;
                }
                if ($canExposeBytes) {
                    ++$mediaResourceCanExposeCount;
                }
            }
            if ($exists) {
                ++$existingCount;
            } else {
                ++$missingCount;
            }
            if ($roleConflict) {
                ++$roleConflictCount;
                $roleConflictItems[] = $resourceItem;
                $pathMediaTypeConflictItems[] = $resourceItem;
                if (is_string($pathMediaTypeConflictPair)) {
                    if (!isset($pathMediaTypeConflictPairs[$pathMediaTypeConflictPair])) {
                        $pathMediaTypeConflictPairs[$pathMediaTypeConflictPair] = [
                            'pathMediaFamily' => $pathFamily,
                            'declaredMediaFamily' => $declaredFamily,
                            'count' => 0,
                            'parts' => [],
                        ];
                    }
                    ++$pathMediaTypeConflictPairs[$pathMediaTypeConflictPair]['count'];
                    $pathMediaTypeConflictPairs[$pathMediaTypeConflictPair]['parts'][] = $part;
                }
                if ($mediaResource) {
                    ++$resourceRoleConflictCount;
                }
            }
            if (!$exists && $mediaResource) {
                $missingMediaResourceItems[] = $resourceItem;
            }
            if ($packageRolePrecedence !== []) {
                $packageRolePrecedenceItems[] = $resourceItem;
            }
            foreach ($issueCodes as $code) {
                $issueCodeCounts[$code] = ($issueCodeCounts[$code] ?? 0) + 1;
            }
        }

        ksort($mediaTypeBaseCounts, SORT_STRING);
        ksort($issueCodeCounts, SORT_STRING);
        ksort($pathMediaTypeConflictPairs, SORT_STRING);

        return [
            'manifestDeclaredCount' => count($items),
            'mediaResourceCount' => $mediaResourceCount,
            'mediaResourceExistingCount' => $mediaResourceExistingCount,
            'mediaResourceMissingCount' => $mediaResourceMissingCount,
            'mediaResourceCanExposeCount' => $mediaResourceCanExposeCount,
            'existingCount' => $existingCount,
            'missingCount' => $missingCount,
            'familyCounts' => $familyCounts,
            'mediaTypeBaseCounts' => $mediaTypeBaseCounts,
            'roleConflictCount' => $roleConflictCount,
            'resourceRoleConflictCount' => $resourceRoleConflictCount,
            'roleConflictItems' => $roleConflictItems,
            'pathMediaTypeConflictCount' => count($pathMediaTypeConflictItems),
            'pathMediaTypeConflictItems' => $pathMediaTypeConflictItems,
            'pathMediaTypeConflictPairs' => array_values($pathMediaTypeConflictPairs),
            'missingMediaResourceItemCount' => count($missingMediaResourceItems),
            'missingMediaResourceItems' => $missingMediaResourceItems,
            'packageRolePrecedenceCount' => count($packageRolePrecedenceItems),
            'packageRolePrecedenceItems' => $packageRolePrecedenceItems,
            'issueCodeCounts' => $issueCodeCounts,
            'items' => $items,
        ];
    }

    /**
     * @param array<string, mixed> $entry
     * @return list<string>
     */
    private static function mediaResourcePackagePrecedenceRoles(array $entry): array
    {
        $part = $entry['packagePath'] ?? $entry['path'] ?? null;
        if (!is_string($part) || $part === '') {
            return [];
        }

        $mediaType = (string) ($entry['mediaType'] ?? '');
        $roles = [];
        if (($entry['embeddedObjectPackagePart'] ?? false) === true) {
            $roles[] = 'embedded-object-package';
        }
        if (self::isScriptPackagePartName($part)) {
            $roles[] = 'script-package';
        }
        if (self::isConfigurationPackagePartName($part)) {
            $roles[] = 'configuration-package';
        }
        if (self::isThumbnailPackagePartName($part)) {
            $roles[] = 'package-thumbnail';
        }
        if (self::isSignaturePackagePartName($part)) {
            $roles[] = 'package-signature';
        }
        if (self::isFontPackagePart($part, $mediaType)) {
            $roles[] = 'font-package';
        }
        if (($entry['rdfMetadataPart'] ?? false) === true || self::isRdfMetadataPart($part, $mediaType)) {
            $roles[] = 'rdf-metadata';
        }
        if (self::isObjectReplacementPackagePartName($part)) {
            $roles[] = 'object-replacement';
        }
        if (self::isLayoutCachePackagePartName($part)) {
            $roles[] = 'layout-cache';
        }
        if (self::isMetaInfSidecarPackagePartName($part)) {
            $roles[] = 'meta-inf-sidecar';
        }
        if (self::isLinkedResourcePackagePartName($part)) {
            $roles[] = 'linked-resource-package';
        }
        if (self::isDatabasePackagePartName($part)) {
            $roles[] = 'database-package';
        }
        if (self::isVersionPackagePartName($part)) {
            $roles[] = 'version-package';
        }
        if (self::isGalleryPackagePartName($part)) {
            $roles[] = 'gallery-package';
        }
        if (self::isFormPackagePartName($part)) {
            $roles[] = 'form-package';
        }
        if (self::isReportPackagePartName($part)) {
            $roles[] = 'report-package';
        }
        if (self::isAttachmentPackagePartName($part)) {
            $roles[] = 'attachment-package';
        }
        if (self::isTemplatePackagePartName($part)) {
            $roles[] = 'template-package';
        }
        if (self::isDictionaryPackagePartName($part)) {
            $roles[] = 'dictionary-package';
        }
        if (self::isEventPackagePartName($part)) {
            $roles[] = 'event-package';
        }

        return array_values(array_unique($roles));
    }

    /**
     * @param list<array<string, mixed>> $entries
     * @return array<string, mixed>
     */
    private static function manifestEncryptionSummary(array $entries): array
    {
        $items = [];
        $encryptedParts = [];
        $checksumTypeCounts = [];
        $checksumTypeProfileCounts = [];
        $algorithmNameCounts = [];
        $algorithmProfileCounts = [];
        $keyDerivationNameCounts = [];
        $keyDerivationProfileCounts = [];
        $keyDerivationIterationBucketCounts = [];
        $startKeyGenerationNameCounts = [];
        $startKeyGenerationProfileCounts = [];
        $unknownChildNameCounts = [];
        $issueCodeCounts = [];
        $recordCount = 0;
        $unknownChildCount = 0;
        $issueItemCount = 0;
        $base64ValueCount = 0;
        $validBase64ValueCount = 0;
        $invalidBase64ValueCount = 0;

        foreach ($entries as $entry) {
            if (($entry['encrypted'] ?? false) !== true) {
                continue;
            }

            $encryption = is_array($entry['encryption'] ?? null) ? $entry['encryption'] : [];
            $records = is_array($encryption['records'] ?? null) ? $encryption['records'] : [];
            if ($records === [] && $encryption !== []) {
                $records = [$encryption];
            }

            $recordCount += count($records);
            $checksumTypes = [];
            $algorithmNames = [];
            $checksumTypeProfiles = [];
            $algorithmProfiles = [];
            $keyDerivationNames = [];
            $keyDerivationProfiles = [];
            $keyDerivationIterationBuckets = [];
            $startKeyGenerationNames = [];
            $startKeyGenerationProfiles = [];
            $unknownChildNames = [];
            $issueCodes = [];
            $itemBase64ValueCount = 0;
            $itemValidBase64ValueCount = 0;
            $itemInvalidBase64ValueCount = 0;

            foreach ($records as $record) {
                if (!is_array($record)) {
                    continue;
                }

                $recordBase64Counts = self::encryptionBase64ReviewCounts($record);
                $itemBase64ValueCount += $recordBase64Counts['base64ValueCount'];
                $itemValidBase64ValueCount += $recordBase64Counts['validBase64ValueCount'];
                $itemInvalidBase64ValueCount += $recordBase64Counts['invalidBase64ValueCount'];
                $base64ValueCount += $recordBase64Counts['base64ValueCount'];
                $validBase64ValueCount += $recordBase64Counts['validBase64ValueCount'];
                $invalidBase64ValueCount += $recordBase64Counts['invalidBase64ValueCount'];

                $checksumType = $record['checksumType'] ?? null;
                if (is_string($checksumType) && $checksumType !== '') {
                    $checksumTypes[] = $checksumType;
                    $checksumTypeCounts[$checksumType] = ($checksumTypeCounts[$checksumType] ?? 0) + 1;
                }
                $checksumTypeProfile = $record['checksumTypeProfile'] ?? null;
                if (is_string($checksumTypeProfile) && $checksumTypeProfile !== '') {
                    $checksumTypeProfiles[] = $checksumTypeProfile;
                    $checksumTypeProfileCounts[$checksumTypeProfile] = ($checksumTypeProfileCounts[$checksumTypeProfile] ?? 0) + 1;
                }

                foreach (self::encryptionNamedRecords($record, 'algorithm', 'algorithms') as $algorithm) {
                    $name = $algorithm['name'] ?? null;
                    if (is_string($name) && $name !== '') {
                        $algorithmNames[] = $name;
                        $algorithmNameCounts[$name] = ($algorithmNameCounts[$name] ?? 0) + 1;
                    }
                    $profile = $algorithm['profile'] ?? null;
                    if (is_string($profile) && $profile !== '') {
                        $algorithmProfiles[] = $profile;
                        $algorithmProfileCounts[$profile] = ($algorithmProfileCounts[$profile] ?? 0) + 1;
                    }
                }

                foreach (self::encryptionNamedRecords($record, 'keyDerivation', 'keyDerivations') as $keyDerivation) {
                    $name = $keyDerivation['name'] ?? null;
                    if (is_string($name) && $name !== '') {
                        $keyDerivationNames[] = $name;
                        $keyDerivationNameCounts[$name] = ($keyDerivationNameCounts[$name] ?? 0) + 1;
                    }
                    $profile = $keyDerivation['profile'] ?? null;
                    if (is_string($profile) && $profile !== '') {
                        $keyDerivationProfiles[] = $profile;
                        $keyDerivationProfileCounts[$profile] = ($keyDerivationProfileCounts[$profile] ?? 0) + 1;
                    }
                    $iterationBucket = $keyDerivation['iterationCountBucket'] ?? null;
                    if (is_string($iterationBucket) && $iterationBucket !== '') {
                        $keyDerivationIterationBuckets[] = $iterationBucket;
                        $keyDerivationIterationBucketCounts[$iterationBucket] = ($keyDerivationIterationBucketCounts[$iterationBucket] ?? 0) + 1;
                    }
                }

                foreach (self::encryptionNamedRecords($record, 'startKeyGeneration', 'startKeyGenerations') as $startKeyGeneration) {
                    $name = $startKeyGeneration['name'] ?? null;
                    if (is_string($name) && $name !== '') {
                        $startKeyGenerationNames[] = $name;
                        $startKeyGenerationNameCounts[$name] = ($startKeyGenerationNameCounts[$name] ?? 0) + 1;
                    }
                    $profile = $startKeyGeneration['profile'] ?? null;
                    if (is_string($profile) && $profile !== '') {
                        $startKeyGenerationProfiles[] = $profile;
                        $startKeyGenerationProfileCounts[$profile] = ($startKeyGenerationProfileCounts[$profile] ?? 0) + 1;
                    }
                }

                foreach (is_array($record['unknownChildren'] ?? null) ? $record['unknownChildren'] : [] as $unknownChild) {
                    if (!is_array($unknownChild)) {
                        continue;
                    }

                    $name = $unknownChild['name'] ?? null;
                    if (is_string($name) && $name !== '') {
                        $unknownChildNames[] = $name;
                        $unknownChildNameCounts[$name] = ($unknownChildNameCounts[$name] ?? 0) + 1;
                        ++$unknownChildCount;
                    }
                }

                foreach (is_array($record['issueCodes'] ?? null) ? $record['issueCodes'] : [] as $issueCode) {
                    if (is_string($issueCode) && $issueCode !== '') {
                        $issueCodes[] = $issueCode;
                    }
                }
            }

            foreach (is_array($encryption['issueCodes'] ?? null) ? $encryption['issueCodes'] : [] as $issueCode) {
                if (is_string($issueCode) && $issueCode !== '') {
                    $issueCodes[] = $issueCode;
                }
            }

            $issueCodes = array_values(array_unique($issueCodes));
            if ($issueCodes !== []) {
                ++$issueItemCount;
                foreach ($issueCodes as $issueCode) {
                    $issueCodeCounts[$issueCode] = ($issueCodeCounts[$issueCode] ?? 0) + 1;
                }
            }

            $path = (string) ($entry['path'] ?? $entry['fullPath'] ?? '');
            $part = $entry['packagePath'] ?? $entry['part'] ?? null;
            if (is_string($part) && $part !== '') {
                $encryptedParts[] = $part;
            }

            $items[] = self::withoutEmptyValues([
                'manifestIndex' => $entry['manifestIndex'] ?? null,
                'fullPath' => $path,
                'path' => $path,
                'part' => $part,
                'packagePath' => $part,
                'mediaType' => $entry['mediaType'] ?? null,
                'encryptionRecordCount' => count($records),
                'checksumTypes' => array_values(array_unique($checksumTypes)),
                'checksumTypeProfiles' => array_values(array_unique($checksumTypeProfiles)),
                'algorithmNames' => array_values(array_unique($algorithmNames)),
                'algorithmProfiles' => array_values(array_unique($algorithmProfiles)),
                'keyDerivationNames' => array_values(array_unique($keyDerivationNames)),
                'keyDerivationProfiles' => array_values(array_unique($keyDerivationProfiles)),
                'keyDerivationIterationBuckets' => array_values(array_unique($keyDerivationIterationBuckets)),
                'startKeyGenerationNames' => array_values(array_unique($startKeyGenerationNames)),
                'startKeyGenerationProfiles' => array_values(array_unique($startKeyGenerationProfiles)),
                'base64ValueCount' => $itemBase64ValueCount,
                'validBase64ValueCount' => $itemValidBase64ValueCount,
                'invalidBase64ValueCount' => $itemInvalidBase64ValueCount,
                'unknownChildNames' => array_values(array_unique($unknownChildNames)),
                'issueCodes' => $issueCodes,
                'canExposeBytes' => ($entry['canExposeBytes'] ?? false) === true,
                'byteExposurePolicy' => $entry['byteExposurePolicy'] ?? null,
            ]);
        }

        ksort($checksumTypeCounts, SORT_STRING);
        ksort($checksumTypeProfileCounts, SORT_STRING);
        ksort($algorithmNameCounts, SORT_STRING);
        ksort($algorithmProfileCounts, SORT_STRING);
        ksort($keyDerivationNameCounts, SORT_STRING);
        ksort($keyDerivationProfileCounts, SORT_STRING);
        ksort($keyDerivationIterationBucketCounts, SORT_STRING);
        ksort($startKeyGenerationNameCounts, SORT_STRING);
        ksort($startKeyGenerationProfileCounts, SORT_STRING);
        ksort($unknownChildNameCounts, SORT_STRING);
        ksort($issueCodeCounts, SORT_STRING);

        return [
            'count' => count($items),
            'encryptedItemCount' => count($items),
            'recordCount' => $recordCount,
            'encryptedParts' => $encryptedParts,
            'checksumTypeCounts' => $checksumTypeCounts,
            'checksumTypeProfileCounts' => $checksumTypeProfileCounts,
            'algorithmNameCounts' => $algorithmNameCounts,
            'algorithmProfileCounts' => $algorithmProfileCounts,
            'keyDerivationNameCounts' => $keyDerivationNameCounts,
            'keyDerivationProfileCounts' => $keyDerivationProfileCounts,
            'keyDerivationIterationBucketCounts' => $keyDerivationIterationBucketCounts,
            'startKeyGenerationNameCounts' => $startKeyGenerationNameCounts,
            'startKeyGenerationProfileCounts' => $startKeyGenerationProfileCounts,
            'base64ValueCount' => $base64ValueCount,
            'validBase64ValueCount' => $validBase64ValueCount,
            'invalidBase64ValueCount' => $invalidBase64ValueCount,
            'unknownChildCount' => $unknownChildCount,
            'unknownChildNameCounts' => $unknownChildNameCounts,
            'issueItemCount' => $issueItemCount,
            'issueCodeCounts' => $issueCodeCounts,
            'items' => $items,
        ];
    }

    /**
     * @param array<string, mixed> $record
     * @return list<array<string, mixed>>
     */
    private static function encryptionNamedRecords(array $record, string $singleKey, string $pluralKey): array
    {
        $items = [];
        if (is_array($record[$pluralKey] ?? null)) {
            foreach ($record[$pluralKey] as $item) {
                if (is_array($item)) {
                    $items[] = $item;
                }
            }
        } elseif (is_array($record[$singleKey] ?? null)) {
            $items[] = $record[$singleKey];
        }

        return $items;
    }

    /**
     * @param array<string, mixed> $record
     * @return array{base64ValueCount:int,validBase64ValueCount:int,invalidBase64ValueCount:int}
     */
    private static function encryptionBase64ReviewCounts(array $record): array
    {
        $counts = [
            'base64ValueCount' => 0,
            'validBase64ValueCount' => 0,
            'invalidBase64ValueCount' => 0,
        ];

        self::addEncryptionBase64FlagCounts($record['checksumBase64Valid'] ?? null, $counts);
        foreach (self::encryptionNamedRecords($record, 'algorithm', 'algorithms') as $algorithm) {
            self::addEncryptionBase64FlagCounts($algorithm['initialisationVectorBase64Valid'] ?? null, $counts);
        }
        foreach (self::encryptionNamedRecords($record, 'keyDerivation', 'keyDerivations') as $keyDerivation) {
            self::addEncryptionBase64FlagCounts($keyDerivation['saltBase64Valid'] ?? null, $counts);
        }

        return $counts;
    }

    /**
     * @param array{base64ValueCount:int,validBase64ValueCount:int,invalidBase64ValueCount:int} $counts
     */
    private static function addEncryptionBase64FlagCounts(mixed $valid, array &$counts): void
    {
        if (!is_bool($valid)) {
            return;
        }

        ++$counts['base64ValueCount'];
        if ($valid) {
            ++$counts['validBase64ValueCount'];
        } else {
            ++$counts['invalidBase64ValueCount'];
        }
    }

    /**
     * @param list<array<string, mixed>> $entries
     * @return array<string, mixed>
     */
    private static function manifestPreferredViewModeSummary(array $entries): array
    {
        $items = [];
        $nonRootItems = [];
        $invalidTokenItems = [];
        $modeCounts = [];
        $issueCodeCounts = [];
        $rootMode = null;
        $definedModeCount = 0;
        $namespacedTokenCount = 0;

        foreach ($entries as $entry) {
            $mode = trim((string) ($entry['preferredViewMode'] ?? ''));
            if ($mode === '') {
                continue;
            }

            $path = (string) ($entry['path'] ?? '');
            $isRootEntry = $path === '/';
            $classification = self::preferredViewModeClassification($mode);
            $issues = [];
            if (!$isRootEntry) {
                $issues[] = 'odf-preferred-view-mode-non-root-entry';
            }
            if (!$classification['validToken']) {
                $issues[] = 'odf-preferred-view-mode-invalid-token';
            }

            $review = self::withoutEmptyValues([
                'manifestIndex' => $entry['manifestIndex'] ?? null,
                'fullPath' => $path,
                'path' => $path,
                'part' => $entry['packagePath'] ?? null,
                'packagePath' => $entry['packagePath'] ?? null,
                'mediaType' => $entry['mediaType'] ?? null,
                'preferredViewMode' => $mode,
                'applicableToRootEntry' => $isRootEntry,
                'validToken' => $classification['validToken'],
                'definedMode' => $classification['definedMode'],
                'namespacedToken' => $classification['namespacedToken'],
                'modeFamily' => $classification['modeFamily'],
                'issues' => $issues,
            ]);
            $items[] = $review;
            $modeCounts[$mode] = ($modeCounts[$mode] ?? 0) + 1;

            if ($isRootEntry) {
                $rootMode = $mode;
            } else {
                $nonRootItems[] = $review;
            }
            if ($classification['definedMode']) {
                ++$definedModeCount;
            }
            if ($classification['namespacedToken']) {
                ++$namespacedTokenCount;
            }
            if (!$classification['validToken']) {
                $invalidTokenItems[] = $review;
            }
            foreach ($issues as $issue) {
                $issueCodeCounts[$issue] = ($issueCodeCounts[$issue] ?? 0) + 1;
            }
        }

        ksort($modeCounts, SORT_STRING);
        ksort($issueCodeCounts, SORT_STRING);

        return [
            'count' => count($items),
            'itemCount' => count($items),
            'rootMode' => $rootMode,
            'definedModeCount' => $definedModeCount,
            'namespacedTokenCount' => $namespacedTokenCount,
            'invalidTokenCount' => count($invalidTokenItems),
            'nonRootEntryCount' => count($nonRootItems),
            'issueCount' => count(array_filter($items, static fn (array $item): bool => ($item['issues'] ?? []) !== [])),
            'issueCodes' => array_keys($issueCodeCounts),
            'issueCodeCounts' => $issueCodeCounts,
            'modeCounts' => $modeCounts,
            'nonRootItems' => $nonRootItems,
            'invalidTokenItems' => $invalidTokenItems,
            'items' => $items,
        ];
    }

    /**
     * @return array{validToken:bool, definedMode:bool, namespacedToken:bool, modeFamily:string}
     */
    private static function preferredViewModeClassification(string $mode): array
    {
        if (isset(self::PREFERRED_VIEW_MODE_VALUES[$mode])) {
            return [
                'validToken' => true,
                'definedMode' => true,
                'namespacedToken' => false,
                'modeFamily' => 'defined',
            ];
        }

        if (self::isNamespacedToken($mode)) {
            return [
                'validToken' => true,
                'definedMode' => false,
                'namespacedToken' => true,
                'modeFamily' => 'namespaced-token',
            ];
        }

        return [
            'validToken' => false,
            'definedMode' => false,
            'namespacedToken' => false,
            'modeFamily' => 'invalid-token',
        ];
    }

    private static function isNamespacedToken(string $value): bool
    {
        return preg_match('/^[A-Za-z_][A-Za-z0-9._-]*:[A-Za-z_][A-Za-z0-9._-]*$/', $value) === 1;
    }

    /**
     * @param list<array<string, mixed>> $items
     * @return list<array<string, mixed>>
     */
    private static function largestDeclaredSizeItems(array $items, int $limit): array
    {
        usort($items, static function (array $left, array $right): int {
            $byDeclaredSize = ((int) ($right['declaredSize'] ?? 0))
                <=> ((int) ($left['declaredSize'] ?? 0));
            if ($byDeclaredSize !== 0) {
                return $byDeclaredSize;
            }

            $byStoredSize = ((int) ($right['storedByteLength'] ?? 0))
                <=> ((int) ($left['storedByteLength'] ?? 0));
            if ($byStoredSize !== 0) {
                return $byStoredSize;
            }

            return strcmp((string) ($left['path'] ?? ''), (string) ($right['path'] ?? ''));
        });

        return array_slice($items, 0, max(0, $limit));
    }

    /**
     * @param array<string, mixed> $entry
     * @return array<string, mixed>
     */
    private static function manifestMediaFamilyItem(array $entry): array
    {
        return [
            'manifestIndex' => $entry['manifestIndex'] ?? null,
            'fullPath' => $entry['path'],
            'path' => $entry['path'],
            'packagePath' => $entry['packagePath'] ?? null,
            'mediaType' => $entry['mediaType'],
            'mediaTypeBase' => $entry['mediaTypeBase'] ?? null,
            'manifestMediaFamily' => $entry['manifestMediaFamily'] ?? null,
            'exists' => ($entry['exists'] ?? false) === true,
            'isDirectory' => ($entry['isDirectory'] ?? false) === true,
            'encrypted' => ($entry['encrypted'] ?? false) === true,
            'canExposeBytes' => ($entry['canExposeBytes'] ?? false) === true,
            'storedByteLength' => $entry['storedByteLength'] ?? null,
            'compressedByteLength' => $entry['compressedByteLength'] ?? null,
            'declaredSize' => $entry['declaredSize'] ?? null,
            'declaredSizeRaw' => $entry['declaredSizeRaw'] ?? null,
            'declaredSizeValid' => ($entry['declaredSizeValid'] ?? false) === true,
            'declaredSizeInvalid' => ($entry['declaredSizeInvalid'] ?? false) === true,
            'declaredSizeMismatch' => ($entry['declaredSizeMismatch'] ?? false) === true,
            'byteExposurePolicy' => $entry['byteExposurePolicy'] ?? null,
        ];
    }

    /**
     * @param array<string, mixed> $entry
     * @return array<string, mixed>
     */
    private static function manifestReviewItem(array $entry): array
    {
        return [
            'manifestIndex' => $entry['manifestIndex'] ?? null,
            'fullPath' => $entry['path'],
            'path' => $entry['path'],
            'part' => $entry['packagePath'] ?? null,
            'partReference' => $entry['pathReference'] ?? null,
            'partSuffix' => $entry['pathSuffix'] ?? null,
            'partQuery' => $entry['pathQuery'] ?? null,
            'partFragment' => $entry['pathFragment'] ?? null,
            'packagePath' => $entry['packagePath'] ?? null,
            'pathReference' => $entry['pathReference'] ?? null,
            'pathSuffix' => $entry['pathSuffix'] ?? null,
            'pathQuery' => $entry['pathQuery'] ?? null,
            'pathFragment' => $entry['pathFragment'] ?? null,
            'uriEncodedPackageReference' => ($entry['uriEncodedPackageReference'] ?? false) === true,
            'manifestMediaFamily' => $entry['manifestMediaFamily'] ?? null,
            'mediaType' => $entry['mediaType'],
            'mediaTypeBase' => $entry['mediaTypeBase'] ?? null,
            'mediaTypeHasParameters' => ($entry['mediaTypeHasParameters'] ?? false) === true,
            'mediaTypeParameterCount' => $entry['mediaTypeParameterCount'] ?? 0,
            'mediaTypeParameters' => $entry['mediaTypeParameters'] ?? [],
            'mediaTypeParameterMap' => $entry['mediaTypeParameterMap'] ?? [],
            'version' => $entry['version'] ?? null,
            'preferredViewMode' => $entry['preferredViewMode'] ?? null,
            'manifestAttributeCount' => $entry['manifestAttributeCount'] ?? 0,
            'manifestAttributeNames' => $entry['manifestAttributeNames'] ?? [],
            'manifestAttributes' => $entry['manifestAttributes'] ?? [],
            'customManifestAttributeCount' => $entry['customManifestAttributeCount'] ?? 0,
            'customManifestAttributeNames' => $entry['customManifestAttributeNames'] ?? [],
            'customManifestAttributes' => $entry['customManifestAttributes'] ?? [],
            'customManifestAttributeMap' => $entry['customManifestAttributeMap'] ?? [],
            'manifestNamespaceDeclarationCount' => $entry['manifestNamespaceDeclarationCount'] ?? 0,
            'manifestNamespaceDeclarationNames' => $entry['manifestNamespaceDeclarationNames'] ?? [],
            'manifestNamespaceDeclarations' => $entry['manifestNamespaceDeclarations'] ?? [],
            'manifestNamespaceDeclarationMap' => $entry['manifestNamespaceDeclarationMap'] ?? [],
            'manifestChildElementCount' => $entry['manifestChildElementCount'] ?? 0,
            'manifestChildElementNames' => $entry['manifestChildElementNames'] ?? [],
            'manifestChildElements' => $entry['manifestChildElements'] ?? [],
            'customManifestChildElementCount' => $entry['customManifestChildElementCount'] ?? 0,
            'customManifestChildElementNames' => $entry['customManifestChildElementNames'] ?? [],
            'customManifestChildElements' => $entry['customManifestChildElements'] ?? [],
            'exists' => ($entry['exists'] ?? false) === true,
            'isDirectory' => ($entry['isDirectory'] ?? false) === true,
            'encrypted' => ($entry['encrypted'] ?? false) === true,
            'encryption' => $entry['encryption'] ?? null,
            'encryptionRecordCount' => is_array($entry['encryption'] ?? null) ? ($entry['encryption']['recordCount'] ?? 0) : 0,
            'encryptionIssueCodes' => is_array($entry['encryption'] ?? null) ? ($entry['encryption']['issueCodes'] ?? []) : [],
            'embeddedObjectPackagePart' => ($entry['embeddedObjectPackagePart'] ?? false) === true,
            'embeddedObjectRootPart' => $entry['embeddedObjectRootPart'] ?? null,
            'embeddedObjectPath' => $entry['embeddedObjectPath'] ?? null,
            'embeddedObjectType' => $entry['embeddedObjectType'] ?? null,
            'embeddedObjectRoot' => ($entry['embeddedObjectRoot'] ?? false) === true,
            'embeddedObjectContainedPart' => ($entry['embeddedObjectContainedPart'] ?? false) === true,
            'embeddedObjectMediaType' => $entry['embeddedObjectMediaType'] ?? null,
            'objectReplacementPackagePart' => ($entry['objectReplacementPackagePart'] ?? false) === true,
            'thumbnailPackagePart' => ($entry['thumbnailPackagePart'] ?? false) === true,
            'scriptPackagePart' => ($entry['scriptPackagePart'] ?? false) === true,
            'signaturePackagePart' => ($entry['signaturePackagePart'] ?? false) === true,
            'configurationPackagePart' => ($entry['configurationPackagePart'] ?? false) === true,
            'fontPackagePart' => ($entry['fontPackagePart'] ?? false) === true,
            'rdfMetadataPart' => ($entry['rdfMetadataPart'] ?? false) === true,
            'layoutCachePackagePart' => ($entry['layoutCachePackagePart'] ?? false) === true,
            'metaInfSidecarPackagePart' => ($entry['metaInfSidecarPackagePart'] ?? false) === true,
            'linkedResourcePackagePart' => ($entry['linkedResourcePackagePart'] ?? false) === true,
            'databasePackagePart' => ($entry['databasePackagePart'] ?? false) === true,
            'versionPackagePart' => ($entry['versionPackagePart'] ?? false) === true,
            'galleryPackagePart' => ($entry['galleryPackagePart'] ?? false) === true,
            'formPackagePart' => ($entry['formPackagePart'] ?? false) === true,
            'reportPackagePart' => ($entry['reportPackagePart'] ?? false) === true,
            'attachmentPackagePart' => ($entry['attachmentPackagePart'] ?? false) === true,
            'templatePackagePart' => ($entry['templatePackagePart'] ?? false) === true,
            'dictionaryPackagePart' => ($entry['dictionaryPackagePart'] ?? false) === true,
            'eventPackagePart' => ($entry['eventPackagePart'] ?? false) === true,
            'canExposeBytes' => ($entry['canExposeBytes'] ?? false) === true,
            'byteLength' => $entry['byteLength'] ?? null,
            'storedByteLength' => $entry['storedByteLength'] ?? null,
            'compressedByteLength' => $entry['compressedByteLength'] ?? null,
            'compressionMethod' => $entry['compressionMethod'] ?? null,
            'compressionMethodName' => $entry['compressionMethodName'] ?? null,
            'crc32' => $entry['crc32'] ?? null,
            'storedCrc32' => $entry['storedCrc32'] ?? null,
            'byteSha256' => $entry['byteSha256'] ?? null,
            'zipModifiedAt' => $entry['zipModifiedAt'] ?? null,
            'zipTimestampSource' => $entry['zipTimestampSource'] ?? null,
            'zipModifiedDosTime' => $entry['zipModifiedDosTime'] ?? null,
            'zipModifiedDosDate' => $entry['zipModifiedDosDate'] ?? null,
            'zipHasDosTimestamp' => ($entry['zipHasDosTimestamp'] ?? false) === true,
            'zipIsDosTimestampValid' => ($entry['zipIsDosTimestampValid'] ?? true) === true,
            'zipDosModifiedAt' => $entry['zipDosModifiedAt'] ?? null,
            'zipExtendedModifiedAt' => $entry['zipExtendedModifiedAt'] ?? null,
            'zipNtfsModifiedAt' => $entry['zipNtfsModifiedAt'] ?? null,
            'zipCentralModifiedAt' => $entry['zipCentralModifiedAt'] ?? null,
            'zipCentralTimestampSource' => $entry['zipCentralTimestampSource'] ?? null,
            'zipLocalExtendedModifiedAt' => $entry['zipLocalExtendedModifiedAt'] ?? null,
            'zipLocalNtfsModifiedAt' => $entry['zipLocalNtfsModifiedAt'] ?? null,
            'zipLocalModifiedAt' => $entry['zipLocalModifiedAt'] ?? null,
            'zipLocalTimestampSource' => $entry['zipLocalTimestampSource'] ?? null,
            'zipTimestampIssues' => $entry['zipTimestampIssues'] ?? [],
            'declaredSize' => $entry['declaredSize'] ?? null,
            'declaredSizeRaw' => $entry['declaredSizeRaw'] ?? null,
            'declaredSizeValid' => ($entry['declaredSizeValid'] ?? false) === true,
            'declaredSizeInvalid' => ($entry['declaredSizeInvalid'] ?? false) === true,
            'declaredSizeMismatch' => ($entry['declaredSizeMismatch'] ?? false) === true,
            'missingMediaType' => ($entry['missingMediaType'] ?? false) === true,
            'byteExposurePolicy' => $entry['byteExposurePolicy'] ?? null,
            'diagnostics' => $entry['diagnostics'] ?? [],
        ];
    }

    /**
     * @param array<string, mixed> $entry
     * @return array<string, mixed>
     */
    private static function manifestFileEntryOrderItem(array $entry): array
    {
        return [
            'manifestIndex' => $entry['manifestIndex'] ?? null,
            'fullPath' => $entry['path'],
            'path' => $entry['path'],
            'part' => $entry['packagePath'] ?? null,
            'partReference' => $entry['pathReference'] ?? null,
            'partSuffix' => $entry['pathSuffix'] ?? null,
            'partQuery' => $entry['pathQuery'] ?? null,
            'partFragment' => $entry['pathFragment'] ?? null,
            'uriEncodedPackageReference' => ($entry['uriEncodedPackageReference'] ?? false) === true,
            'manifestMediaFamily' => $entry['manifestMediaFamily'] ?? null,
            'mediaType' => $entry['mediaType'],
            'manifestAttributeCount' => $entry['manifestAttributeCount'] ?? 0,
            'manifestAttributeNames' => $entry['manifestAttributeNames'] ?? [],
            'customManifestAttributeCount' => $entry['customManifestAttributeCount'] ?? 0,
            'customManifestAttributeNames' => $entry['customManifestAttributeNames'] ?? [],
            'customManifestAttributes' => $entry['customManifestAttributes'] ?? [],
            'customManifestAttributeMap' => $entry['customManifestAttributeMap'] ?? [],
            'manifestNamespaceDeclarationCount' => $entry['manifestNamespaceDeclarationCount'] ?? 0,
            'manifestNamespaceDeclarationNames' => $entry['manifestNamespaceDeclarationNames'] ?? [],
            'manifestNamespaceDeclarations' => $entry['manifestNamespaceDeclarations'] ?? [],
            'manifestNamespaceDeclarationMap' => $entry['manifestNamespaceDeclarationMap'] ?? [],
            'manifestChildElementCount' => $entry['manifestChildElementCount'] ?? 0,
            'manifestChildElementNames' => $entry['manifestChildElementNames'] ?? [],
            'customManifestChildElementCount' => $entry['customManifestChildElementCount'] ?? 0,
            'customManifestChildElementNames' => $entry['customManifestChildElementNames'] ?? [],
            'customManifestChildElements' => $entry['customManifestChildElements'] ?? [],
            'exists' => ($entry['exists'] ?? false) === true,
            'isDirectory' => ($entry['isDirectory'] ?? false) === true,
            'encrypted' => ($entry['encrypted'] ?? false) === true,
            'embeddedObjectPackagePart' => ($entry['embeddedObjectPackagePart'] ?? false) === true,
            'embeddedObjectRootPart' => $entry['embeddedObjectRootPart'] ?? null,
            'embeddedObjectType' => $entry['embeddedObjectType'] ?? null,
            'embeddedObjectRoot' => ($entry['embeddedObjectRoot'] ?? false) === true,
            'embeddedObjectContainedPart' => ($entry['embeddedObjectContainedPart'] ?? false) === true,
            'thumbnailPackagePart' => ($entry['thumbnailPackagePart'] ?? false) === true,
            'objectReplacementPackagePart' => ($entry['objectReplacementPackagePart'] ?? false) === true,
            'scriptPackagePart' => ($entry['scriptPackagePart'] ?? false) === true,
            'signaturePackagePart' => ($entry['signaturePackagePart'] ?? false) === true,
            'configurationPackagePart' => ($entry['configurationPackagePart'] ?? false) === true,
            'fontPackagePart' => ($entry['fontPackagePart'] ?? false) === true,
            'rdfMetadataPart' => ($entry['rdfMetadataPart'] ?? false) === true,
            'layoutCachePackagePart' => ($entry['layoutCachePackagePart'] ?? false) === true,
            'metaInfSidecarPackagePart' => ($entry['metaInfSidecarPackagePart'] ?? false) === true,
            'linkedResourcePackagePart' => ($entry['linkedResourcePackagePart'] ?? false) === true,
            'databasePackagePart' => ($entry['databasePackagePart'] ?? false) === true,
            'versionPackagePart' => ($entry['versionPackagePart'] ?? false) === true,
            'galleryPackagePart' => ($entry['galleryPackagePart'] ?? false) === true,
            'formPackagePart' => ($entry['formPackagePart'] ?? false) === true,
            'reportPackagePart' => ($entry['reportPackagePart'] ?? false) === true,
            'attachmentPackagePart' => ($entry['attachmentPackagePart'] ?? false) === true,
            'templatePackagePart' => ($entry['templatePackagePart'] ?? false) === true,
            'dictionaryPackagePart' => ($entry['dictionaryPackagePart'] ?? false) === true,
            'eventPackagePart' => ($entry['eventPackagePart'] ?? false) === true,
            'zipModifiedAt' => $entry['zipModifiedAt'] ?? null,
            'zipTimestampSource' => $entry['zipTimestampSource'] ?? null,
            'zipLocalModifiedAt' => $entry['zipLocalModifiedAt'] ?? null,
            'zipLocalTimestampSource' => $entry['zipLocalTimestampSource'] ?? null,
            'zipTimestampIssues' => $entry['zipTimestampIssues'] ?? [],
            'canExposeBytes' => ($entry['canExposeBytes'] ?? false) === true,
            'declaredSize' => $entry['declaredSize'] ?? null,
            'declaredSizeRaw' => $entry['declaredSizeRaw'] ?? null,
            'declaredSizeValid' => ($entry['declaredSizeValid'] ?? false) === true,
            'declaredSizeInvalid' => ($entry['declaredSizeInvalid'] ?? false) === true,
            'declaredSizeMismatch' => ($entry['declaredSizeMismatch'] ?? false) === true,
            'missingMediaType' => ($entry['missingMediaType'] ?? false) === true,
            'diagnostics' => $entry['diagnostics'] ?? [],
        ];
    }

    /**
     * @param array<string, mixed> $entry
     * @return array<string, mixed>
     */
    private static function manifestPartReferenceSuffixItem(array $entry): array
    {
        return [
            'manifestIndex' => $entry['manifestIndex'] ?? null,
            'fullPath' => $entry['path'],
            'path' => $entry['path'],
            'part' => $entry['packagePath'] ?? null,
            'partReference' => $entry['pathReference'] ?? null,
            'partSuffix' => $entry['pathSuffix'] ?? null,
            'partQuery' => $entry['pathQuery'] ?? null,
            'partFragment' => $entry['pathFragment'] ?? null,
            'uriEncodedPackageReference' => ($entry['uriEncodedPackageReference'] ?? false) === true,
            'mediaType' => $entry['mediaType'],
            'exists' => ($entry['exists'] ?? false) === true,
            'isDirectory' => ($entry['isDirectory'] ?? false) === true,
            'encrypted' => ($entry['encrypted'] ?? false) === true,
            'embeddedObjectPackagePart' => ($entry['embeddedObjectPackagePart'] ?? false) === true,
            'embeddedObjectRootPart' => $entry['embeddedObjectRootPart'] ?? null,
            'embeddedObjectType' => $entry['embeddedObjectType'] ?? null,
            'canExposeBytes' => ($entry['canExposeBytes'] ?? false) === true,
        ];
    }

    private static function compressionMethodName(int $method): string
    {
        return match ($method) {
            0 => 'stored',
            8 => 'deflated',
            default => 'unsupported',
        };
    }

    private static function assertTextPackageMimetype(ZipPackage $package): void
    {
        if (!$package->has('mimetype')) {
            throw new \RuntimeException('ODT package is missing mimetype entry');
        }

        $entries = $package->localEntries();
        $first = $entries[0] ?? null;
        if (!$first instanceof ZipPackageEntry || $first->name !== 'mimetype' || $first->compressionMethod !== 0) {
            throw new \RuntimeException('ODT mimetype entry must be first in local-header order and stored without compression');
        }

        $localMimetype = self::localHeaderPreflightEntry($package, 'mimetype');
        if (is_array($localMimetype) && ((int) ($localMimetype['localExtraFieldLength'] ?? 0)) > 0) {
            throw new \RuntimeException('ODT mimetype entry must not contain local header extra fields');
        }

        if ($package->read('mimetype') !== self::TEXT_MIMETYPE) {
            throw new \RuntimeException('ODT mimetype entry must be application/vnd.oasis.opendocument.text');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private static function mimetypeEntryReview(ZipPackage $package): array
    {
        $entry = $package->entry('mimetype');
        $localHeader = self::localHeaderPreflightEntry($package, 'mimetype') ?? [];
        $centralDirectoryIndex = null;
        foreach ($package->entries() as $index => $candidate) {
            if ($candidate->name === 'mimetype') {
                $centralDirectoryIndex = $index;
                break;
            }
        }

        $localHeaderOrder = null;
        foreach ($package->localEntries() as $index => $candidate) {
            if ($candidate->name === 'mimetype') {
                $localHeaderOrder = $index;
                break;
            }
        }

        $centralExtraFields = self::zipExtraFieldReviewRecords($entry->centralExtraFields());
        $localExtraFields = is_array($localHeader['localExtraFieldRecords'] ?? null)
            ? $localHeader['localExtraFieldRecords']
            : [];
        $localExtraFieldLength = is_int($localHeader['localExtraFieldLength'] ?? null)
            ? $localHeader['localExtraFieldLength']
            : 0;

        return [
            'name' => $entry->name,
            'mediaType' => self::TEXT_MIMETYPE,
            'firstLocalEntry' => $localHeaderOrder === 0,
            'firstCentralDirectoryEntry' => $centralDirectoryIndex === 0,
            'localHeaderOrder' => $localHeaderOrder,
            'centralDirectoryIndex' => $centralDirectoryIndex,
            'localHeaderOffset' => $entry->localHeaderOffset,
            'localHeaderLength' => $localHeader['localHeaderLength'] ?? null,
            'localNameLength' => $localHeader['localNameLength'] ?? null,
            'localExtraFieldLength' => $localExtraFieldLength,
            'localExtraFieldRecordCount' => count($localExtraFields),
            'localExtraFieldIds' => $localHeader['localExtraFieldIds'] ?? [],
            'localExtraFieldRecords' => $localExtraFields,
            'centralExtraFieldLength' => strlen($entry->centralExtraFieldData),
            'centralExtraFieldRecordCount' => count($centralExtraFields),
            'centralExtraFieldIds' => array_map(
                static fn (array $field): int => (int) $field['id'],
                $centralExtraFields
            ),
            'centralExtraFieldRecords' => $centralExtraFields,
            'hasLocalExtraFields' => $localExtraFieldLength > 0,
            'hasCentralExtraFields' => $centralExtraFields !== [],
            'compressionMethod' => $entry->compressionMethod,
            'compressionMethodName' => self::compressionMethodName($entry->compressionMethod),
            'byteLength' => $entry->uncompressedSize,
            'compressedByteLength' => $entry->compressedSize,
            'crc32' => $entry->crc32Hex(),
            'canExposeBytes' => false,
            'byteExposurePolicy' => 'odf-mimetype-validation-only',
            'diagnostics' => [],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function localHeaderPreflightEntry(ZipPackage $package, string $name): ?array
    {
        foreach ($package->localHeaderPreflight()['entries'] as $entry) {
            if (($entry['name'] ?? null) === $name) {
                return $entry;
            }
        }

        return null;
    }

    /**
     * @param list<array{id:int, data:string}> $fields
     * @return list<array{id:int, idHex:string, dataLength:int}>
     */
    private static function zipExtraFieldReviewRecords(array $fields): array
    {
        return array_map(
            static fn (array $field): array => [
                'id' => $field['id'],
                'idHex' => sprintf('%04x', $field['id']),
                'dataLength' => strlen($field['data']),
            ],
            $fields
        );
    }

    /**
     * @return array{
     *     version:string|null,
     *     rootAttributes:array<string, mixed>,
     *     entries:list<array{manifestIndex:int, path:string, packagePath:string|null, pathReference:string|null, pathSuffix:string|null, pathQuery:string|null, pathFragment:string|null, mediaType:string, version:string|null, size:int|null, declaredSizeRaw:string|null, declaredSizeValid:bool, declaredSizeInvalid:bool, preferredViewMode:string|null, encrypted:bool, encryption:array<string, mixed>|null}>
     * }
     */
    private static function parseManifest(string $xml): array
    {
        $dom = self::loadXml($xml, 'ODT manifest.xml');
        $root = $dom->documentElement;
        if (!$root instanceof \DOMElement || $root->localName !== 'manifest' || $root->namespaceURI !== self::MANIFEST_NAMESPACE) {
            throw new \InvalidArgumentException('ODF manifest XML must use the manifest:manifest root');
        }

        $entries = [];
        $manifestIndex = 0;
        $manifestVersion = self::optionalString(self::namespacedAttribute($root, self::MANIFEST_NAMESPACE, 'version'));
        $rootAttributes = self::manifestRootAttributeProvenance($root);
        foreach ($root->childNodes as $child) {
            if (!$child instanceof \DOMElement) {
                continue;
            }
            if ($child->namespaceURI !== self::MANIFEST_NAMESPACE || $child->localName !== 'file-entry') {
                throw new \InvalidArgumentException('ODF manifest may only contain manifest:file-entry children');
            }

            $path = self::normalizeManifestPath(self::namespacedAttribute($child, self::MANIFEST_NAMESPACE, 'full-path') ?? '');
            $packageReference = self::manifestPackageReference($path);
            $pathReference = $packageReference['pathReference'];
            $packagePath = $packageReference['packagePath'];
            $uriEncodedPackageReference = is_string($pathReference) && is_string($packagePath) && $pathReference !== $packagePath;
            $mediaType = self::namespacedAttribute($child, self::MANIFEST_NAMESPACE, 'media-type') ?? '';
            $missingMediaType = $mediaType === '' && !str_ends_with($pathReference ?? $path, '/');
            $diagnostics = $missingMediaType ? ['odf-manifest-file-entry-missing-media-type'] : [];
            $mediaTypeReport = self::mediaTypeReport($mediaType);
            $attributeProvenance = self::manifestFileEntryAttributeProvenance($child);
            $childElementProvenance = self::manifestFileEntryChildElementProvenance($child);

            $declaredSize = self::manifestDeclaredSize(
                self::namespacedAttribute($child, self::MANIFEST_NAMESPACE, 'size')
            );
            $encryption = self::manifestEncryption($child);
            $entries[] = [
                'manifestIndex' => $manifestIndex,
                'path' => $path,
                'packagePath' => $packagePath,
                'pathReference' => $pathReference,
                'pathSuffix' => $packageReference['pathSuffix'],
                'pathQuery' => $packageReference['pathQuery'],
                'pathFragment' => $packageReference['pathFragment'],
                'uriEncodedPackageReference' => $uriEncodedPackageReference,
                'mediaType' => $mediaType,
                'mediaTypeBase' => $mediaTypeReport['mediaTypeBase'],
                'mediaTypeHasParameters' => $mediaTypeReport['mediaTypeHasParameters'],
                'mediaTypeParameterCount' => $mediaTypeReport['mediaTypeParameterCount'],
                'mediaTypeParameters' => $mediaTypeReport['mediaTypeParameters'],
                'mediaTypeParameterMap' => $mediaTypeReport['mediaTypeParameterMap'],
                'version' => self::namespacedAttribute($child, self::MANIFEST_NAMESPACE, 'version'),
                'size' => $declaredSize['value'],
                'declaredSizeRaw' => $declaredSize['raw'],
                'declaredSizeValid' => $declaredSize['valid'],
                'declaredSizeInvalid' => $declaredSize['invalid'],
                'preferredViewMode' => self::optionalString(self::namespacedAttribute($child, self::MANIFEST_NAMESPACE, 'preferred-view-mode')),
                'manifestAttributeCount' => $attributeProvenance['attributeCount'],
                'manifestAttributeNames' => $attributeProvenance['attributeNames'],
                'manifestAttributes' => $attributeProvenance['attributes'],
                'customManifestAttributeCount' => $attributeProvenance['customAttributeCount'],
                'customManifestAttributeNames' => $attributeProvenance['customAttributeNames'],
                'customManifestAttributes' => $attributeProvenance['customAttributes'],
                'customManifestAttributeMap' => $attributeProvenance['customAttributeMap'],
                'manifestNamespaceDeclarationCount' => $attributeProvenance['namespaceDeclarationCount'],
                'manifestNamespaceDeclarationNames' => $attributeProvenance['namespaceDeclarationNames'],
                'manifestNamespaceDeclarations' => $attributeProvenance['namespaceDeclarations'],
                'manifestNamespaceDeclarationMap' => $attributeProvenance['namespaceDeclarationMap'],
                'manifestChildElementCount' => $childElementProvenance['childElementCount'],
                'manifestChildElementNames' => $childElementProvenance['childElementNames'],
                'manifestChildElements' => $childElementProvenance['childElements'],
                'customManifestChildElementCount' => $childElementProvenance['customChildElementCount'],
                'customManifestChildElementNames' => $childElementProvenance['customChildElementNames'],
                'customManifestChildElements' => $childElementProvenance['customChildElements'],
                'missingMediaType' => $missingMediaType,
                'encrypted' => $encryption !== null,
                'encryption' => $encryption,
                'diagnostics' => $diagnostics,
            ];
            ++$manifestIndex;
        }

        return [
            'version' => $manifestVersion,
            'rootAttributes' => $rootAttributes,
            'entries' => $entries,
        ];
    }

    /**
     * @return array{
     *     attributeCount:int,
     *     attributeNames:list<string>,
     *     attributes:list<array<string, mixed>>,
     *     customAttributeCount:int,
     *     customAttributeNames:list<string>,
     *     customAttributes:list<array<string, mixed>>,
     *     customAttributeMap:array<string, string>,
     *     namespaceDeclarationCount:int,
     *     namespaceDeclarationNames:list<string>,
     *     namespaceDeclarations:list<array<string, mixed>>,
     *     namespaceDeclarationMap:array<string, string>
     * }
     */
    private static function manifestFileEntryAttributeProvenance(\DOMElement $element): array
    {
        return self::manifestElementAttributeProvenance($element, self::MANIFEST_FILE_ENTRY_STRUCTURAL_ATTRIBUTES);
    }

    /**
     * @return array{
     *     attributeCount:int,
     *     attributeNames:list<string>,
     *     attributes:list<array<string, mixed>>,
     *     customAttributeCount:int,
     *     customAttributeNames:list<string>,
     *     customAttributes:list<array<string, mixed>>,
     *     customAttributeMap:array<string, string>,
     *     namespaceDeclarationCount:int,
     *     namespaceDeclarationNames:list<string>,
     *     namespaceDeclarations:list<array<string, mixed>>,
     *     namespaceDeclarationMap:array<string, string>
     * }
     */
    private static function manifestRootAttributeProvenance(\DOMElement $element): array
    {
        return self::manifestElementAttributeProvenance($element, self::MANIFEST_ROOT_STRUCTURAL_ATTRIBUTES);
    }

    /**
     * @return array{
     *     attributeCount:int,
     *     attributeNames:list<string>,
     *     attributes:list<array<string, mixed>>,
     *     customAttributeCount:int,
     *     customAttributeNames:list<string>,
     *     customAttributes:list<array<string, mixed>>,
     *     customAttributeMap:array<string, string>,
     *     namespaceDeclarationCount:int,
     *     namespaceDeclarationNames:list<string>,
     *     namespaceDeclarations:list<array<string, mixed>>,
     *     namespaceDeclarationMap:array<string, string>
     * }
     */
    private static function documentPartRootAttributeProvenance(\DOMElement $element): array
    {
        return self::manifestElementAttributeProvenance(
            $element,
            self::OFFICE_DOCUMENT_ROOT_STRUCTURAL_ATTRIBUTES,
            self::OFFICE_NAMESPACE
        );
    }

    /**
     * @return array{
     *     childElementCount:int,
     *     childElementNames:list<string>,
     *     childElements:list<array<string, mixed>>,
     *     customChildElementCount:int,
     *     customChildElementNames:list<string>,
     *     customChildElements:list<array<string, mixed>>
     * }
     */
    private static function manifestFileEntryChildElementProvenance(\DOMElement $element): array
    {
        $childElements = [];
        $customChildElements = [];

        foreach (self::childElements($element) as $child) {
            $namespaceUri = (string) $child->namespaceURI;
            $structural = $namespaceUri === self::MANIFEST_NAMESPACE
                && isset(self::MANIFEST_FILE_ENTRY_STRUCTURAL_CHILD_ELEMENTS[$child->localName]);
            $record = [
                'name' => self::qualifiedElementName($child),
                'localName' => $child->localName,
                'structural' => $structural,
                'attributeCount' => $child->hasAttributes() ? $child->attributes->length : 0,
                'childElementCount' => count(self::childElements($child)),
            ];
            if ($namespaceUri !== '') {
                $record['namespaceUri'] = $namespaceUri;
            }
            if ($child->prefix !== '') {
                $record['prefix'] = $child->prefix;
            }
            $namespaceDeclarations = self::manifestElementNamespaceDeclarations($child);
            $namespaceDeclarationMap = array_map(
                static fn (array $declaration): string => $declaration['namespaceUri'],
                $namespaceDeclarations
            );
            ksort($namespaceDeclarations, SORT_STRING);
            ksort($namespaceDeclarationMap, SORT_STRING);
            $record['namespaceDeclarationCount'] = count($namespaceDeclarations);
            $record['namespaceDeclarationNames'] = array_keys($namespaceDeclarations);
            $record['namespaceDeclarations'] = array_values($namespaceDeclarations);
            $record['namespaceDeclarationMap'] = $namespaceDeclarationMap;

            $childElements[] = $record;
            if (!$structural) {
                $customChildElements[] = $record;
            }
        }

        return [
            'childElementCount' => count($childElements),
            'childElementNames' => array_values(array_map(static fn (array $item): string => $item['name'], $childElements)),
            'childElements' => $childElements,
            'customChildElementCount' => count($customChildElements),
            'customChildElementNames' => array_values(array_map(static fn (array $item): string => $item['name'], $customChildElements)),
            'customChildElements' => $customChildElements,
        ];
    }

    /**
     * @param array<string, bool> $structuralAttributes
     * @return array{
     *     attributeCount:int,
     *     attributeNames:list<string>,
     *     attributes:list<array<string, mixed>>,
     *     customAttributeCount:int,
     *     customAttributeNames:list<string>,
     *     customAttributes:list<array<string, mixed>>,
     *     customAttributeMap:array<string, string>,
     *     namespaceDeclarationCount:int,
     *     namespaceDeclarationNames:list<string>,
     *     namespaceDeclarations:list<array<string, mixed>>,
     *     namespaceDeclarationMap:array<string, string>
     * }
     */
    private static function manifestElementAttributeProvenance(
        \DOMElement $element,
        array $structuralAttributes,
        string $structuralNamespace = self::MANIFEST_NAMESPACE
    ): array
    {
        $attributes = [];
        $customAttributes = [];
        $customAttributeMap = [];
        $namespaceDeclarations = self::manifestElementNamespaceDeclarations($element);
        $namespaceDeclarationMap = array_map(
            static fn (array $declaration): string => $declaration['namespaceUri'],
            $namespaceDeclarations
        );
        if ($element->hasAttributes()) {
            foreach ($element->attributes as $attribute) {
                if (!$attribute instanceof \DOMAttr) {
                    continue;
                }

                if ($attribute->prefix !== '' && is_string($attribute->namespaceURI) && $attribute->namespaceURI !== '' && $attribute->prefix !== 'xmlns') {
                    $namespaceName = 'xmlns:' . $attribute->prefix;
                    if (!isset($namespaceDeclarations[$namespaceName])) {
                        $namespaceDeclarations[$namespaceName] = [
                            'name' => $namespaceName,
                            'declaredPrefix' => $attribute->prefix,
                            'namespaceUri' => $attribute->namespaceURI,
                            'default' => false,
                        ];
                        $namespaceDeclarationMap[$namespaceName] = $attribute->namespaceURI;
                    }
                }

                if ($attribute->namespaceURI === self::XMLNS_NAMESPACE) {
                    $name = $attribute->name === 'xmlns'
                        ? 'xmlns'
                        : 'xmlns:' . $attribute->localName;
                    $declaredPrefix = $attribute->name === 'xmlns' ? '' : $attribute->localName;
                    $namespaceDeclarations[$name] = [
                        'name' => $name,
                        'declaredPrefix' => $declaredPrefix,
                        'namespaceUri' => $attribute->value,
                        'default' => $declaredPrefix === '',
                    ];
                    $namespaceDeclarationMap[$name] = $attribute->value;
                    continue;
                }

                $name = $attribute->prefix !== ''
                    ? $attribute->prefix . ':' . $attribute->localName
                    : $attribute->name;
                $structural = $attribute->namespaceURI === $structuralNamespace
                    && isset($structuralAttributes[$attribute->localName]);
                $record = [
                    'name' => $name,
                    'localName' => $attribute->localName,
                    'value' => $attribute->value,
                    'structural' => $structural,
                ];
                $namespaceUri = (string) $attribute->namespaceURI;
                if ($namespaceUri !== '') {
                    $record['namespaceUri'] = $namespaceUri;
                }
                if ($attribute->prefix !== '') {
                    $record['prefix'] = $attribute->prefix;
                }

                $attributes[$name] = $record;
                if (!$structural) {
                    $customAttributes[$name] = $record;
                    $customAttributeMap[$name] = $attribute->value;
                }
            }
        }

        ksort($attributes, SORT_STRING);
        ksort($customAttributes, SORT_STRING);
        ksort($customAttributeMap, SORT_STRING);
        ksort($namespaceDeclarations, SORT_STRING);
        ksort($namespaceDeclarationMap, SORT_STRING);

        return [
            'attributeCount' => count($attributes),
            'attributeNames' => array_keys($attributes),
            'attributes' => array_values($attributes),
            'customAttributeCount' => count($customAttributes),
            'customAttributeNames' => array_keys($customAttributes),
            'customAttributes' => array_values($customAttributes),
            'customAttributeMap' => $customAttributeMap,
            'namespaceDeclarationCount' => count($namespaceDeclarations),
            'namespaceDeclarationNames' => array_keys($namespaceDeclarations),
            'namespaceDeclarations' => array_values($namespaceDeclarations),
            'namespaceDeclarationMap' => $namespaceDeclarationMap,
        ];
    }

    /**
     * @return array{
     *     attributeCount:int,
     *     attributeNames:list<string>,
     *     attributes:list<array<string, mixed>>,
     *     customAttributeCount:int,
     *     customAttributeNames:list<string>,
     *     customAttributes:list<array<string, mixed>>,
     *     customAttributeMap:array<string, string>,
     *     namespaceDeclarationCount:int,
     *     namespaceDeclarationNames:list<string>,
     *     namespaceDeclarations:list<array<string, mixed>>,
     *     namespaceDeclarationMap:array<string, string>
     * }
     */
    private static function emptyAttributeProvenance(): array
    {
        return [
            'attributeCount' => 0,
            'attributeNames' => [],
            'attributes' => [],
            'customAttributeCount' => 0,
            'customAttributeNames' => [],
            'customAttributes' => [],
            'customAttributeMap' => [],
            'namespaceDeclarationCount' => 0,
            'namespaceDeclarationNames' => [],
            'namespaceDeclarations' => [],
            'namespaceDeclarationMap' => [],
        ];
    }

    /**
     * @return array<string, array{name:string, declaredPrefix:string, namespaceUri:string, default:bool}>
     */
    private static function manifestElementNamespaceDeclarations(\DOMElement $element): array
    {
        $xpath = new \DOMXPath($element->ownerDocument);
        $nodes = $xpath->query('namespace::*', $element);
        if ($nodes === false) {
            return [];
        }

        $declarations = [];
        foreach ($nodes as $node) {
            if (!$node instanceof \DOMNameSpaceNode || $node->nodeName === 'xmlns:xml') {
                continue;
            }

            $name = $node->nodeName;
            $declaredPrefix = $name === 'xmlns' ? '' : $node->localName;
            $declarations[$name] = [
                'name' => $name,
                'declaredPrefix' => $declaredPrefix,
                'namespaceUri' => (string) $node->nodeValue,
                'default' => $declaredPrefix === '',
            ];
        }

        ksort($declarations, SORT_STRING);

        return $declarations;
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function manifestEncryption(\DOMElement $entry): ?array
    {
        $encryptionElements = self::childElements($entry, self::MANIFEST_NAMESPACE, 'encryption-data');
        if ($encryptionElements === []) {
            return null;
        }

        $records = array_map(
            static fn (\DOMElement $encryption): array => self::manifestEncryptionData($encryption),
            $encryptionElements
        );
        $data = $records[0] ?? [];
        $data['records'] = $records;
        $data['recordCount'] = count($records);

        if (count($records) > 1) {
            $issueCodes = is_array($data['issueCodes'] ?? null) ? $data['issueCodes'] : [];
            $issueCodes[] = 'odf-manifest-encryption-multiple-encryption-data';
            $issueCodes = array_values(array_unique($issueCodes));
            $data['issueCodes'] = $issueCodes;
            $data['issueCount'] = count($issueCodes);
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private static function manifestEncryptionData(\DOMElement $encryption): array
    {
        $data = self::withoutNulls([
            'checksumType' => self::optionalString(self::namespacedAttribute($encryption, self::MANIFEST_NAMESPACE, 'checksum-type')),
            'checksum' => self::optionalString(self::namespacedAttribute($encryption, self::MANIFEST_NAMESPACE, 'checksum')),
        ]);
        if (is_string($data['checksumType'] ?? null)) {
            $data['checksumTypeProfile'] = self::encryptionChecksumTypeProfile($data['checksumType']);
        }
        if (is_string($data['checksum'] ?? null)) {
            $data += self::base64ReviewFields($data['checksum'], 'checksum');
        }

        $algorithms = array_map(
            static fn (\DOMElement $algorithm): array => self::manifestEncryptionAlgorithm($algorithm),
            self::childElements($encryption, self::MANIFEST_NAMESPACE, 'algorithm')
        );
        if ($algorithms !== []) {
            $data['algorithm'] = $algorithms[0];
            $data['algorithms'] = $algorithms;
            $data['algorithmCount'] = count($algorithms);
        }

        $keyDerivations = array_map(
            static fn (\DOMElement $keyDerivation): array => self::manifestEncryptionKeyDerivation($keyDerivation),
            self::childElements($encryption, self::MANIFEST_NAMESPACE, 'key-derivation')
        );
        if ($keyDerivations !== []) {
            $data['keyDerivation'] = $keyDerivations[0];
            $data['keyDerivations'] = $keyDerivations;
            $data['keyDerivationCount'] = count($keyDerivations);
        }

        $startKeyGenerations = array_map(
            static fn (\DOMElement $startKeyGeneration): array => self::manifestEncryptionStartKeyGeneration($startKeyGeneration),
            self::childElements($encryption, self::MANIFEST_NAMESPACE, 'start-key-generation')
        );
        if ($startKeyGenerations !== []) {
            $data['startKeyGeneration'] = $startKeyGenerations[0];
            $data['startKeyGenerations'] = $startKeyGenerations;
            $data['startKeyGenerationCount'] = count($startKeyGenerations);
        }

        $unknownChildren = self::manifestEncryptionUnknownChildren($encryption);
        if ($unknownChildren !== []) {
            $data['unknownChildCount'] = count($unknownChildren);
            $data['unknownChildren'] = $unknownChildren;
        }

        $issueCodes = [];
        if (count($algorithms) > 1) {
            $issueCodes[] = 'odf-manifest-encryption-multiple-algorithms';
        }
        if (count($keyDerivations) > 1) {
            $issueCodes[] = 'odf-manifest-encryption-multiple-key-derivations';
        }
        if (count($startKeyGenerations) > 1) {
            $issueCodes[] = 'odf-manifest-encryption-multiple-start-key-generations';
        }
        if ($unknownChildren !== []) {
            $issueCodes[] = 'odf-manifest-encryption-unknown-child';
        }
        if ($issueCodes !== []) {
            $data['issueCount'] = count($issueCodes);
            $data['issueCodes'] = $issueCodes;
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private static function manifestEncryptionAlgorithm(\DOMElement $algorithm): array
    {
        $initialisationVector = self::namespacedAttribute($algorithm, self::MANIFEST_NAMESPACE, 'initialisation-vector')
            ?? self::namespacedAttribute($algorithm, self::MANIFEST_NAMESPACE, 'initialization-vector');
        $name = self::optionalString(self::namespacedAttribute($algorithm, self::MANIFEST_NAMESPACE, 'algorithm-name'));

        $data = self::withoutNulls([
            'name' => $name,
            'profile' => self::encryptionAlgorithmProfile($name),
            'initialisationVector' => self::optionalString($initialisationVector),
        ]);
        if (is_string($data['initialisationVector'] ?? null)) {
            $data += self::base64ReviewFields($data['initialisationVector'], 'initialisationVector');
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private static function manifestEncryptionKeyDerivation(\DOMElement $keyDerivation): array
    {
        $name = self::optionalString(self::namespacedAttribute($keyDerivation, self::MANIFEST_NAMESPACE, 'key-derivation-name'));
        $iterationCount = self::optionalInt(self::namespacedAttribute($keyDerivation, self::MANIFEST_NAMESPACE, 'iteration-count'));
        $data = self::withoutNulls([
            'name' => $name,
            'profile' => self::encryptionKeyDerivationProfile($name),
            'keySize' => self::optionalInt(self::namespacedAttribute($keyDerivation, self::MANIFEST_NAMESPACE, 'key-size')),
            'iterationCount' => $iterationCount,
            'iterationCountBucket' => self::encryptionIterationCountBucket($iterationCount),
            'salt' => self::optionalString(self::namespacedAttribute($keyDerivation, self::MANIFEST_NAMESPACE, 'salt')),
        ]);
        if (is_string($data['salt'] ?? null)) {
            $data += self::base64ReviewFields($data['salt'], 'salt');
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private static function manifestEncryptionStartKeyGeneration(\DOMElement $startKeyGeneration): array
    {
        $name = self::optionalString(self::namespacedAttribute($startKeyGeneration, self::MANIFEST_NAMESPACE, 'start-key-generation-name'));

        return self::withoutNulls([
            'name' => $name,
            'profile' => self::encryptionStartKeyGenerationProfile($name),
            'keySize' => self::optionalInt(self::namespacedAttribute($startKeyGeneration, self::MANIFEST_NAMESPACE, 'key-size')),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private static function base64ReviewFields(string $value, string $prefix): array
    {
        $normalized = preg_replace('/\s+/', '', $value) ?? $value;
        $decoded = base64_decode($normalized, true);
        if ($decoded === false) {
            return [
                $prefix . 'Base64Valid' => false,
            ];
        }

        return [
            $prefix . 'Base64Valid' => true,
            $prefix . 'DecodedByteLength' => strlen($decoded),
        ];
    }

    private static function encryptionChecksumTypeProfile(?string $checksumType): ?string
    {
        $normalized = self::encryptionProfileToken($checksumType);
        if ($normalized === '') {
            return null;
        }
        if (str_contains($normalized, 'sha1') && str_contains($normalized, '1k')) {
            return 'sha1-1k';
        }
        if (str_contains($normalized, 'sha256') && str_contains($normalized, '1k')) {
            return 'sha256-1k';
        }
        if (str_contains($normalized, 'sha512') && str_contains($normalized, '1k')) {
            return 'sha512-1k';
        }
        if (str_contains($normalized, 'sha1')) {
            return 'sha1';
        }
        if (str_contains($normalized, 'sha256')) {
            return 'sha256';
        }
        if (str_contains($normalized, 'sha512')) {
            return 'sha512';
        }

        return 'custom';
    }

    private static function encryptionAlgorithmProfile(?string $name): ?string
    {
        $normalized = self::encryptionProfileToken($name);
        if ($normalized === '') {
            return null;
        }
        if (str_contains($normalized, 'aes') && str_contains($normalized, '256')) {
            return 'aes-256';
        }
        if (str_contains($normalized, 'aes') && str_contains($normalized, '128')) {
            return 'aes-128';
        }
        if (str_contains($normalized, 'aes')) {
            return 'aes';
        }
        if (str_contains($normalized, 'blowfish')) {
            return str_contains($normalized, 'cfb') ? 'blowfish-cfb' : 'blowfish';
        }
        if (str_contains($normalized, 'chacha20')) {
            return 'chacha20';
        }
        if (str_contains($normalized, 'twofish')) {
            return 'twofish';
        }

        return 'custom';
    }

    private static function encryptionKeyDerivationProfile(?string $name): ?string
    {
        $normalized = self::encryptionProfileToken($name);
        if ($normalized === '') {
            return null;
        }
        foreach (['pbkdf2', 'argon2id', 'argon2i', 'scrypt', 'pgp'] as $profile) {
            if (str_contains($normalized, $profile)) {
                return $profile;
            }
        }

        return 'custom';
    }

    private static function encryptionStartKeyGenerationProfile(?string $name): ?string
    {
        $normalized = self::encryptionProfileToken($name);
        if ($normalized === '') {
            return null;
        }
        foreach (['sha1', 'sha256', 'sha384', 'sha512'] as $profile) {
            if (str_contains($normalized, $profile)) {
                return $profile;
            }
        }

        return 'custom';
    }

    private static function encryptionIterationCountBucket(?int $iterationCount): ?string
    {
        if ($iterationCount === null) {
            return null;
        }
        if ($iterationCount < 1_000) {
            return 'under-1k';
        }
        if ($iterationCount < 10_000) {
            return '1k-9999';
        }
        if ($iterationCount < 100_000) {
            return '10k-99999';
        }

        return '100k-plus';
    }

    private static function encryptionProfileToken(?string $value): string
    {
        $value = strtolower(trim((string) $value));

        return preg_replace('/[^a-z0-9]+/', '', $value) ?? '';
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function manifestEncryptionUnknownChildren(\DOMElement $encryption): array
    {
        $unknownChildren = [];
        foreach (self::childElements($encryption) as $child) {
            if (
                $child->namespaceURI === self::MANIFEST_NAMESPACE
                && in_array($child->localName, ['algorithm', 'key-derivation', 'start-key-generation'], true)
            ) {
                continue;
            }

            $unknownChildren[] = self::withoutNulls([
                'name' => self::qualifiedElementName($child),
                'namespaceUri' => self::optionalString($child->namespaceURI),
                'localName' => $child->localName,
            ]);
        }

        return $unknownChildren;
    }

    private static function qualifiedElementName(\DOMElement $element): string
    {
        return $element->prefix === '' ? $element->localName : $element->prefix . ':' . $element->localName;
    }

    /**
     * @return array<string, array{name:string, family:string, parent:string|null, displayName:string|null}>
     */
    private static function parseStyles(string $xml): array
    {
        $dom = self::loadXml($xml, 'ODT styles.xml');
        $root = $dom->documentElement;
        if (!$root instanceof \DOMElement || !in_array($root->localName, ['document-styles', 'document'], true) || $root->namespaceURI !== self::OFFICE_NAMESPACE) {
            throw new \InvalidArgumentException('ODT styles.xml must use an office:document-styles or office:document root');
        }

        $styles = [];
        foreach ($dom->getElementsByTagNameNS(self::STYLE_NAMESPACE, 'style') as $style) {
            if (!$style instanceof \DOMElement) {
                continue;
            }

            $name = self::namespacedAttribute($style, self::STYLE_NAMESPACE, 'name');
            $family = self::namespacedAttribute($style, self::STYLE_NAMESPACE, 'family');
            if ($name === null || $name === '' || $family === null || $family === '') {
                continue;
            }

            $styles[$name] = [
                'name' => $name,
                'family' => $family,
                'parent' => self::namespacedAttribute($style, self::STYLE_NAMESPACE, 'parent-style-name'),
                'displayName' => self::namespacedAttribute($style, self::STYLE_NAMESPACE, 'display-name'),
            ];
        }

        return $styles;
    }

    /**
     * @return array<string, mixed>
     */
    private static function parseMetadata(string $xml): array
    {
        $dom = self::loadXml($xml, 'ODT meta.xml');
        $root = $dom->documentElement;
        if (!$root instanceof \DOMElement || !in_array($root->localName, ['document-meta', 'document'], true) || $root->namespaceURI !== self::OFFICE_NAMESPACE) {
            throw new \InvalidArgumentException('ODT meta.xml must use an office:document-meta or office:document root');
        }

        $meta = self::firstElementByPath($root, [[self::OFFICE_NAMESPACE, 'meta']]);
        if (!$meta instanceof \DOMElement) {
            return [];
        }

        $metadata = [];
        $fields = [
            'generator' => [self::META_NAMESPACE, 'generator'],
            'title' => [self::DC_NAMESPACE, 'title'],
            'description' => [self::DC_NAMESPACE, 'description'],
            'subject' => [self::DC_NAMESPACE, 'subject'],
            'language' => [self::DC_NAMESPACE, 'language'],
            'initialCreator' => [self::META_NAMESPACE, 'initial-creator'],
            'creator' => [self::DC_NAMESPACE, 'creator'],
            'creationDate' => [self::META_NAMESPACE, 'creation-date'],
            'date' => [self::DC_NAMESPACE, 'date'],
            'editingDuration' => [self::META_NAMESPACE, 'editing-duration'],
            'editingCycles' => [self::META_NAMESPACE, 'editing-cycles'],
            'modificationDate' => [self::META_NAMESPACE, 'modification-date'],
            'modificationTime' => [self::META_NAMESPACE, 'modification-time'],
            'printedBy' => [self::META_NAMESPACE, 'printed-by'],
            'printDate' => [self::META_NAMESPACE, 'print-date'],
            'printTime' => [self::META_NAMESPACE, 'print-time'],
        ];

        foreach ($fields as $key => [$namespace, $localName]) {
            $value = self::firstChildElementText($meta, $namespace, $localName);
            if ($value !== null && $value !== '') {
                $metadata[$key] = $value;
            }
        }

        $keywords = [];
        foreach (self::childElements($meta, self::META_NAMESPACE, 'keyword') as $keywordElement) {
            $keywordText = trim($keywordElement->textContent);
            if ($keywordText === '') {
                continue;
            }

            foreach (explode(',', $keywordText) as $keyword) {
                $keyword = trim($keyword);
                if ($keyword !== '') {
                    $keywords[] = $keyword;
                }
            }
        }
        if ($keywords !== []) {
            $metadata['keywords'] = $keywords;
        }

        $template = self::metadataChildAttributes($meta, 'template', [
            'href' => [self::XLINK_NAMESPACE, 'href'],
            'title' => [self::XLINK_NAMESPACE, 'title'],
            'type' => [self::XLINK_NAMESPACE, 'type'],
            'actuate' => [self::XLINK_NAMESPACE, 'actuate'],
            'show' => [self::XLINK_NAMESPACE, 'show'],
            'date' => [self::META_NAMESPACE, 'date'],
            'name' => [self::META_NAMESPACE, 'name'],
        ]);
        if ($template !== []) {
            $metadata['template'] = $template;
        }

        $autoReload = self::metadataChildAttributes($meta, 'auto-reload', [
            'href' => [self::XLINK_NAMESPACE, 'href'],
            'type' => [self::XLINK_NAMESPACE, 'type'],
            'actuate' => [self::XLINK_NAMESPACE, 'actuate'],
            'show' => [self::XLINK_NAMESPACE, 'show'],
            'delay' => [self::META_NAMESPACE, 'delay'],
        ]);
        if ($autoReload !== []) {
            $metadata['autoReload'] = $autoReload;
        }

        $hyperlinkBehaviour = self::metadataChildAttributes($meta, 'hyperlink-behaviour', [
            'targetFrameName' => [self::OFFICE_NAMESPACE, 'target-frame-name'],
            'show' => [self::XLINK_NAMESPACE, 'show'],
        ]);
        if ($hyperlinkBehaviour !== []) {
            $metadata['hyperlinkBehaviour'] = $hyperlinkBehaviour;
        }

        $userDefined = [];
        foreach ($meta->childNodes as $child) {
            if (!$child instanceof \DOMElement || $child->namespaceURI !== self::META_NAMESPACE || $child->localName !== 'user-defined') {
                continue;
            }

            $name = self::namespacedAttribute($child, self::META_NAMESPACE, 'name');
            if ($name === null || $name === '') {
                continue;
            }

            $userDefined[$name] = [
                'value' => trim($child->textContent),
                'valueType' => self::namespacedAttribute($child, self::META_NAMESPACE, 'value-type') ?? 'string',
            ];
        }
        if ($userDefined !== []) {
            $metadata['userDefined'] = $userDefined;
        }

        $statistics = self::documentStatistics($meta);
        if ($statistics !== []) {
            $metadata['statistics'] = $statistics;
        }

        return $metadata;
    }

    /**
     * @param array<string, array{0:string, 1:string}> $attributes
     *
     * @return array<string, string>
     */
    private static function metadataChildAttributes(\DOMElement $meta, string $localName, array $attributes): array
    {
        $element = self::firstDirectChildElement($meta, self::META_NAMESPACE, $localName);
        if (!$element instanceof \DOMElement) {
            return [];
        }

        $values = [];
        foreach ($attributes as $key => [$namespace, $attributeName]) {
            $value = self::optionalString(self::namespacedAttribute($element, $namespace, $attributeName));
            if ($value !== null) {
                $values[$key] = $value;
            }
        }

        return $values;
    }

    /**
     * @return array{count:int,itemCount:int,mapEntryCount:int,sets:list<array<string, mixed>>,setsByName:array<string, array<string, mixed>>}
     */
    private static function emptySettings(): array
    {
        return [
            'count' => 0,
            'itemCount' => 0,
            'mapEntryCount' => 0,
            'sets' => [],
            'setsByName' => [],
        ];
    }

    /**
     * @return array{count:int,itemCount:int,mapEntryCount:int,sets:list<array<string, mixed>>,setsByName:array<string, array<string, mixed>>}
     */
    private static function parseSettings(string $xml): array
    {
        $dom = self::loadXml($xml, 'ODT settings.xml');
        $root = $dom->documentElement;
        if (!$root instanceof \DOMElement || !in_array($root->localName, ['document-settings', 'document'], true) || $root->namespaceURI !== self::OFFICE_NAMESPACE) {
            throw new \InvalidArgumentException('ODT settings.xml must use an office:document-settings or office:document root');
        }

        $settingsElement = self::firstElementByPath($root, [[self::OFFICE_NAMESPACE, 'settings']]);
        if (!$settingsElement instanceof \DOMElement) {
            throw new \RuntimeException('ODT settings.xml is missing office:settings');
        }

        $sets = [];
        $setsByName = [];
        $itemCount = 0;
        $mapEntryCount = 0;
        foreach (self::childElements($settingsElement, self::CONFIG_NAMESPACE, 'config-item-set') as $setElement) {
            $name = self::trimmedAttribute($setElement, self::CONFIG_NAMESPACE, 'name');
            if ($name === '') {
                continue;
            }

            $set = self::settingsContainerDefinition($setElement) + ['name' => $name];
            $sets[] = $set;
            $setsByName[$name] = $set;
            $itemCount += (int) ($set['itemCount'] ?? 0);
            $mapEntryCount += (int) ($set['mapEntryCount'] ?? 0);
        }

        return [
            'count' => count($sets),
            'itemCount' => $itemCount,
            'mapEntryCount' => $mapEntryCount,
            'sets' => $sets,
            'setsByName' => $setsByName,
        ];
    }

    /**
     * @return array{itemCount:int,mapEntryCount:int,items:list<array<string, mixed>>,itemsByName:array<string, array<string, mixed>>,maps:list<array<string, mixed>>,mapsByName:array<string, array<string, mixed>>}
     */
    private static function settingsContainerDefinition(\DOMElement $container): array
    {
        $items = [];
        $itemsByName = [];
        $maps = [];
        $mapsByName = [];
        $itemCount = 0;
        $mapEntryCount = 0;

        foreach (self::childElements($container) as $child) {
            if (self::isElement($child, self::CONFIG_NAMESPACE, 'config-item')) {
                $item = self::settingsConfigItemDefinition($child);
                if ($item === []) {
                    continue;
                }

                $items[] = $item;
                $itemsByName[$item['name']] = $item;
                ++$itemCount;
                continue;
            }

            if (self::isElement($child, self::CONFIG_NAMESPACE, 'config-item-map-indexed')
                || self::isElement($child, self::CONFIG_NAMESPACE, 'config-item-map-named')
            ) {
                $map = self::settingsConfigMapDefinition($child);
                if ($map === []) {
                    continue;
                }

                $maps[] = $map;
                $mapsByName[$map['name']] = $map;
                $itemCount += (int) ($map['itemCount'] ?? 0);
                $mapEntryCount += (int) ($map['mapEntryCount'] ?? 0);
            }
        }

        return [
            'itemCount' => $itemCount,
            'mapEntryCount' => $mapEntryCount,
            'items' => $items,
            'itemsByName' => $itemsByName,
            'maps' => $maps,
            'mapsByName' => $mapsByName,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function settingsConfigItemDefinition(\DOMElement $item): array
    {
        $name = self::trimmedAttribute($item, self::CONFIG_NAMESPACE, 'name');
        if ($name === '') {
            return [];
        }

        $type = self::trimmedAttribute($item, self::CONFIG_NAMESPACE, 'type');
        $value = self::normalizedText($item);

        return self::withoutNulls([
            'name' => $name,
            'type' => $type === '' ? null : $type,
            'value' => $value,
            'typedValue' => self::settingsConfigTypedValue($value, $type),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private static function settingsConfigMapDefinition(\DOMElement $map): array
    {
        $name = self::trimmedAttribute($map, self::CONFIG_NAMESPACE, 'name');
        if ($name === '') {
            return [];
        }

        $entries = [];
        $entriesByName = [];
        $itemCount = 0;
        $mapEntryCount = 0;
        foreach (self::childElements($map, self::CONFIG_NAMESPACE, 'config-item-map-entry') as $entryElement) {
            $entry = self::settingsContainerDefinition($entryElement);
            $entry['index'] = count($entries);
            $entryName = self::trimmedAttribute($entryElement, self::CONFIG_NAMESPACE, 'name');
            if ($entryName !== '') {
                $entry['name'] = $entryName;
                $entriesByName[$entryName] = $entry;
            }

            $entries[] = $entry;
            $itemCount += (int) ($entry['itemCount'] ?? 0);
            $mapEntryCount += 1 + (int) ($entry['mapEntryCount'] ?? 0);
        }

        $definition = [
            'name' => $name,
            'type' => $map->localName === 'config-item-map-named' ? 'named' : 'indexed',
            'entryCount' => count($entries),
            'itemCount' => $itemCount,
            'mapEntryCount' => $mapEntryCount,
            'entries' => $entries,
        ];
        if ($entriesByName !== []) {
            $definition['entriesByName'] = $entriesByName;
        }

        return $definition;
    }

    private static function settingsConfigTypedValue(string $value, string $type): mixed
    {
        $type = strtolower(trim($type));
        if (in_array($type, ['boolean', 'bool'], true)) {
            return self::nullableBool($value);
        }
        if (in_array($type, ['int', 'integer', 'long', 'short'], true)) {
            return preg_match('/^-?\d+$/', $value) === 1 ? (int) $value : $value;
        }
        if (in_array($type, ['float', 'double'], true)) {
            return is_numeric($value) ? (float) $value : $value;
        }

        return $value;
    }

    /**
     * @return array<string, int>
     */
    private static function documentStatistics(\DOMElement $meta): array
    {
        $element = null;
        foreach ($meta->childNodes as $child) {
            if ($child instanceof \DOMElement && $child->namespaceURI === self::META_NAMESPACE && $child->localName === 'document-statistic') {
                $element = $child;
                break;
            }
        }

        if (!$element instanceof \DOMElement) {
            return [];
        }

        $statistics = [];
        foreach ([
            'page-count',
            'table-count',
            'image-count',
            'object-count',
            'paragraph-count',
            'word-count',
            'sentence-count',
            'character-count',
            'non-whitespace-character-count',
            'syllable-count',
        ] as $name) {
            $value = self::namespacedAttribute($element, self::META_NAMESPACE, $name);
            if ($value !== null && ctype_digit($value)) {
                $statistics[self::camelCase($name)] = (int) $value;
            }
        }

        return $statistics;
    }

    private static function camelCase(string $name): string
    {
        return preg_replace_callback(
            '/-([a-z])/',
            static fn (array $matches): string => strtoupper($matches[1]),
            $name
        ) ?? $name;
    }

    /**
     * @return list<AstNode>
     */
    private static function inlineNodes(\DOMNode $node): array
    {
        $nodes = [];
        foreach ($node->childNodes as $child) {
            if ($child instanceof \DOMText || $child instanceof \DOMCdataSection) {
                if ($child->nodeValue !== '') {
                    $nodes[] = new AstNode('text', ['text' => $child->nodeValue]);
                }
                continue;
            }

            if (!$child instanceof \DOMElement) {
                continue;
            }

            if ($child->namespaceURI === self::TEXT_NAMESPACE && $child->localName === 's') {
                $nodes[] = new AstNode('text', ['text' => str_repeat(' ', self::intAttribute($child, self::TEXT_NAMESPACE, 'c', 1))]);
                continue;
            }

            if ($child->namespaceURI === self::TEXT_NAMESPACE && $child->localName === 'tab') {
                $nodes[] = new AstNode('text', ['text' => "\t"]);
                continue;
            }

            if ($child->namespaceURI === self::TEXT_NAMESPACE && $child->localName === 'line-break') {
                $nodes[] = new AstNode('linebreak');
                continue;
            }

            if ($child->namespaceURI === self::TEXT_NAMESPACE && $child->localName === 'a') {
                $nodes[] = new AstNode('link', [
                    'url' => self::namespacedAttribute($child, self::XLINK_NAMESPACE, 'href') ?? '',
                    'title' => '',
                ], self::inlineNodes($child));
                continue;
            }

            if ($child->namespaceURI === self::DRAW_NAMESPACE && $child->localName === 'frame') {
                $image = self::firstDescendantElement($child, self::DRAW_NAMESPACE, 'image');
                if ($image instanceof \DOMElement) {
                    $href = self::namespacedAttribute($image, self::XLINK_NAMESPACE, 'href') ?? '';
                    if ($href !== '') {
                        $nodes[] = new AstNode('image', [
                            'url' => $href,
                            'alt' => trim($child->textContent),
                            'title' => '',
                            'mediaType' => null,
                        ]);
                    }
                }
                continue;
            }

            array_push($nodes, ...self::inlineNodes($child));
        }

        return self::mergeAdjacentTextNodes($nodes);
    }

    private static function plainText(\DOMNode $node): string
    {
        $text = '';
        foreach (self::inlineNodes($node) as $inline) {
            if ($inline->type === 'text') {
                $text .= (string) $inline->attr('text', '');
            } elseif ($inline->type === 'linebreak') {
                $text .= "\n";
            } else {
                foreach ($inline->children as $child) {
                    if ($child->type === 'text') {
                        $text .= (string) $child->attr('text', '');
                    }
                }
            }
        }

        return $text;
    }

    /**
     * @param list<AstNode> $nodes
     *
     * @return list<AstNode>
     */
    private static function mergeAdjacentTextNodes(array $nodes): array
    {
        $merged = [];
        foreach ($nodes as $node) {
            $lastKey = array_key_last($merged);
            $previous = $lastKey === null ? null : $merged[$lastKey];
            if ($node->type === 'text' && $previous instanceof AstNode && $previous->type === 'text') {
                array_pop($merged);
                $merged[] = new AstNode('text', [
                    'text' => (string) $previous->attr('text', '') . (string) $node->attr('text', ''),
                ]);
                continue;
            }
            $merged[] = $node;
        }

        return $merged;
    }

    /**
     * @param list<array{0:string, 1:string}> $path
     */
    private static function firstElementByPath(\DOMElement $root, array $path): ?\DOMElement
    {
        $current = $root;
        foreach ($path as [$namespace, $localName]) {
            $next = null;
            foreach ($current->childNodes as $child) {
                if ($child instanceof \DOMElement && $child->namespaceURI === $namespace && $child->localName === $localName) {
                    $next = $child;
                    break;
                }
            }
            if (!$next instanceof \DOMElement) {
                return null;
            }
            $current = $next;
        }

        return $current;
    }

    /**
     * @return list<\DOMElement>
     */
    private static function childElements(\DOMElement $element, ?string $namespace = null, ?string $localName = null): array
    {
        $elements = [];
        foreach ($element->childNodes as $child) {
            if (!$child instanceof \DOMElement) {
                continue;
            }
            if ($namespace !== null && $child->namespaceURI !== $namespace) {
                continue;
            }
            if ($localName !== null && $child->localName !== $localName) {
                continue;
            }
            $elements[] = $child;
        }

        return $elements;
    }

    private static function isElement(\DOMElement $element, string $namespace, string $localName): bool
    {
        return $element->namespaceURI === $namespace && $element->localName === $localName;
    }

    private static function firstDescendantElement(\DOMElement $root, string $namespace, string $localName): ?\DOMElement
    {
        foreach ($root->getElementsByTagNameNS($namespace, $localName) as $element) {
            if ($element instanceof \DOMElement) {
                return $element;
            }
        }

        return null;
    }

    private static function firstChildElementText(\DOMElement $root, string $namespace, string $localName): ?string
    {
        foreach ($root->childNodes as $child) {
            if ($child instanceof \DOMElement && $child->namespaceURI === $namespace && $child->localName === $localName) {
                return trim($child->textContent);
            }
        }

        return null;
    }

    private static function firstDirectChildElement(\DOMElement $root, string $namespace, string $localName): ?\DOMElement
    {
        foreach ($root->childNodes as $child) {
            if ($child instanceof \DOMElement && $child->namespaceURI === $namespace && $child->localName === $localName) {
                return $child;
            }
        }

        return null;
    }

    private static function namespacedAttribute(\DOMElement $element, string $namespace, string $localName): ?string
    {
        return $element->hasAttributeNS($namespace, $localName) ? $element->getAttributeNS($namespace, $localName) : null;
    }

    private static function attributeValue(\DOMElement $element, string $namespace, string $localName): string
    {
        return trim($element->getAttributeNS($namespace, $localName));
    }

    private static function trimmedAttribute(\DOMElement $element, string $namespace, string $localName): string
    {
        return trim(self::namespacedAttribute($element, $namespace, $localName) ?? '');
    }

    private static function optionalString(?string $value): ?string
    {
        $value = $value === null ? '' : trim($value);

        return $value === '' ? null : $value;
    }

    private static function optionalInt(?string $value): ?int
    {
        $value = $value === null ? '' : trim($value);

        return ctype_digit($value) ? (int) $value : null;
    }

    private static function nullableBool(string $value): ?bool
    {
        $value = strtolower(trim($value));
        if ($value === '') {
            return null;
        }

        return in_array($value, ['true', '1', 'yes', 'checked'], true);
    }

    private static function normalizedText(\DOMElement $element): string
    {
        $text = preg_replace('/\s+/u', ' ', $element->textContent) ?? $element->textContent;

        return trim($text);
    }

    /**
     * @return array{raw:string|null,value:int|null,valid:bool,invalid:bool}
     */
    private static function manifestDeclaredSize(?string $value): array
    {
        $value = $value === null ? '' : trim($value);
        if ($value === '') {
            return [
                'raw' => null,
                'value' => null,
                'valid' => false,
                'invalid' => false,
            ];
        }

        if (!ctype_digit($value)) {
            return [
                'raw' => $value,
                'value' => null,
                'valid' => false,
                'invalid' => true,
            ];
        }

        $normalized = ltrim($value, '0');
        $normalized = $normalized === '' ? '0' : $normalized;
        $max = (string) PHP_INT_MAX;
        if (strlen($normalized) > strlen($max) || (strlen($normalized) === strlen($max) && strcmp($normalized, $max) > 0)) {
            return [
                'raw' => $value,
                'value' => null,
                'valid' => false,
                'invalid' => true,
            ];
        }

        return [
            'raw' => $value,
            'value' => (int) $normalized,
            'valid' => true,
            'invalid' => false,
        ];
    }

    /**
     * @return array{mediaTypeBase:string, mediaTypeHasParameters:bool, mediaTypeParameterCount:int, mediaTypeParameters:list<array{name:string, value:string, raw:string}>, mediaTypeParameterMap:array<string, string>}
     */
    private static function mediaTypeReport(string $mediaType): array
    {
        $segments = self::mediaTypeSegments($mediaType);
        $base = strtolower(trim((string) array_shift($segments)));
        $parameters = [];
        $parameterMap = [];

        foreach ($segments as $segment) {
            $raw = trim($segment);
            if ($raw === '') {
                continue;
            }

            $equals = strpos($raw, '=');
            $name = $equals === false ? strtolower(trim($raw)) : strtolower(trim(substr($raw, 0, $equals)));
            if ($name === '') {
                continue;
            }

            $value = $equals === false ? '' : trim(substr($raw, $equals + 1));
            if (strlen($value) >= 2 && $value[0] === '"' && substr($value, -1) === '"') {
                $value = substr($value, 1, -1);
                $value = preg_replace('/\\\\([\x20-\x7E])/', '$1', $value) ?? $value;
            }

            $parameters[] = [
                'name' => $name,
                'value' => $value,
                'raw' => $raw,
            ];
            $parameterMap[$name] = $value;
        }

        return [
            'mediaTypeBase' => $base,
            'mediaTypeHasParameters' => $parameters !== [],
            'mediaTypeParameterCount' => count($parameters),
            'mediaTypeParameters' => $parameters,
            'mediaTypeParameterMap' => $parameterMap,
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
     * @param array<string, mixed> $values
     * @return array<string, mixed>
     */
    private static function withoutNulls(array $values): array
    {
        return array_filter($values, static fn (mixed $value): bool => $value !== null);
    }

    /**
     * @param array<string, mixed> $values
     * @return array<string, mixed>
     */
    private static function withoutEmptyValues(array $values): array
    {
        return array_filter(
            $values,
            static fn (mixed $value): bool => $value !== null && $value !== '' && $value !== []
        );
    }

    private static function intAttribute(\DOMElement $element, string $namespace, string $localName, int $default): int
    {
        $value = self::namespacedAttribute($element, $namespace, $localName);
        if ($value === null || $value === '' || !preg_match('/^\d+$/', $value)) {
            return $default;
        }

        return max(1, (int) $value);
    }

    private static function normalizeManifestPath(string $path): string
    {
        if ($path === '') {
            throw new \InvalidArgumentException('ODF manifest full-path must not be empty');
        }

        if ($path === '/') {
            return '/';
        }

        if (str_starts_with($path, '/') || str_contains($path, '\\') || self::hasAsciiControlByte($path)) {
            throw new \InvalidArgumentException('Unsafe ODF manifest full-path: ' . $path);
        }

        $segments = explode('/', $path);
        foreach ($segments as $index => $segment) {
            $isTrailingDirectorySegment = $index === count($segments) - 1 && $segment === '';
            if ($isTrailingDirectorySegment) {
                continue;
            }

            if ($segment === '' || $segment === '.' || $segment === '..') {
                throw new \InvalidArgumentException('Unsafe ODF manifest full-path: ' . $path);
            }
        }

        return $path;
    }

    private static function manifestPackagePath(string $path): ?string
    {
        if ($path === '/') {
            return null;
        }

        if (preg_match('/%(?![0-9A-Fa-f]{2})/', $path) === 1) {
            throw new \InvalidArgumentException('Malformed percent escape in ODF manifest full-path: ' . $path);
        }

        $decodedPath = rawurldecode($path);
        if ($decodedPath === '' || str_starts_with($decodedPath, '/') || str_contains($decodedPath, '\\') || self::hasAsciiControlByte($decodedPath)) {
            throw new \InvalidArgumentException('Unsafe ODF manifest full-path: ' . $path);
        }

        $segments = explode('/', $decodedPath);
        foreach ($segments as $index => $segment) {
            $isTrailingDirectorySegment = $index === count($segments) - 1 && $segment === '';
            if ($isTrailingDirectorySegment) {
                continue;
            }

            if ($segment === '' || $segment === '.' || $segment === '..') {
                throw new \InvalidArgumentException('Unsafe ODF manifest full-path: ' . $path);
            }
        }

        return $decodedPath;
    }

    private static function hasAsciiControlByte(string $value): bool
    {
        return preg_match('/[\x00-\x1F\x7F]/', $value) === 1;
    }

    /**
     * @return array{packagePath:string|null,pathReference:string|null,pathSuffix:string|null,pathQuery:string|null,pathFragment:string|null}
     */
    private static function manifestPackageReference(string $path): array
    {
        if ($path === '/') {
            return [
                'packagePath' => null,
                'pathReference' => null,
                'pathSuffix' => null,
                'pathQuery' => null,
                'pathFragment' => null,
            ];
        }

        $pathReference = $path;
        $pathSuffix = null;
        $pathQuery = null;
        $pathFragment = null;
        $suffixOffset = strcspn($path, '?#');
        if ($suffixOffset < strlen($path)) {
            $pathReference = substr($path, 0, $suffixOffset);
            $pathSuffix = substr($path, $suffixOffset);
            if (str_starts_with($pathSuffix, '?')) {
                $queryAndFragment = substr($pathSuffix, 1);
                $fragmentOffset = strpos($queryAndFragment, '#');
                if ($fragmentOffset === false) {
                    $pathQuery = $queryAndFragment;
                } else {
                    $pathQuery = substr($queryAndFragment, 0, $fragmentOffset);
                    $pathFragment = substr($queryAndFragment, $fragmentOffset + 1);
                }
            } elseif (str_starts_with($pathSuffix, '#')) {
                $pathFragment = substr($pathSuffix, 1);
            }
        }

        return [
            'packagePath' => self::manifestPackagePath($pathReference),
            'pathReference' => $pathReference,
            'pathSuffix' => $pathSuffix,
            'pathQuery' => $pathQuery,
            'pathFragment' => $pathFragment,
        ];
    }

    private static function loadXml(string $xml, string $label): \DOMDocument
    {
        $previous = libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
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
