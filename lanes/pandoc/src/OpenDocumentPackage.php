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

    /** @var array<string, array<string, mixed>> */
    private array $manifestEntriesByPath;

    /**
     * @param list<array<string, mixed>> $manifestEntries
     * @param array<string, array<string, mixed>> $manifestEntriesByPath
     * @param array<string, array{name:string, family:string, parent:string|null, displayName:string|null}> $stylesByName
     * @param array<string, mixed> $metadata
     * @param array<string, mixed> $settings
     */
    private function __construct(
        private readonly ZipPackage $package,
        private readonly ?string $manifestVersion,
        private readonly array $manifestEntries,
        array $manifestEntriesByPath,
        private readonly array $stylesByName,
        private readonly array $metadata,
        private readonly array $settings,
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

        return new self($package, $manifest['version'], $manifestEntries, $manifestEntriesByPath, $styles, $metadata, $settings);
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
        ], $blocks);
    }

    /**
     * @return array{
     *     mimetype:string,
     *     manifestVersion:string|null,
     *     contentXml:bool,
     *     stylesXml:bool,
     *     metaXml:bool,
     *     settingsXml:bool,
     *     mediaParts:list<array<string, mixed>>,
     *     missingMediaPartCount:int,
     *     missingMediaParts:list<array{path:string, mediaType:string}>,
     *     exposableMediaPartCount:int,
     *     encryptedCount:int,
     *     encryptedParts:list<string>,
     *     manifestReview:array<string, mixed>,
     *     packageInventory:array<string, mixed>,
     *     undeclaredPackageEntryCount:int,
     *     undeclaredPackageEntries:list<array<string, mixed>>,
     *     packageThumbnails:array<string, mixed>,
     *     packageSignatures:array<string, mixed>,
     *     packageConfigurations:array<string, mixed>,
     *     metadata:array<string, mixed>,
     *     settings:array<string, mixed>,
     *     styleNames:list<string>,
     *     contentBlocks:int
     * }
     */
    public function summarize(): array
    {
        $packageInventory = $this->packageInventory();
        $mediaParts = [];
        $missingMediaParts = [];
        $exposableMediaPartCount = 0;
        $encryptedParts = [];
        $undeclaredPackageEntries = $this->undeclaredPackageEntries();
        $packageThumbnails = self::packageThumbnailMetadata($this->package, $this->manifestEntries, $undeclaredPackageEntries);
        $packageSignatures = self::packageSignatureMetadata($this->package, $this->manifestEntries, $undeclaredPackageEntries);
        $packageConfigurations = self::packageConfigurationMetadata($this->package, $this->manifestEntries, $undeclaredPackageEntries);
        foreach ($this->manifestEntries as $entry) {
            if (self::isMediaResourceManifestEntry($entry)) {
                $mediaParts[] = [
                    'path' => $entry['path'],
                    'packagePath' => $entry['packagePath'],
                    'pathReference' => $entry['pathReference'],
                    'pathSuffix' => $entry['pathSuffix'],
                    'pathQuery' => $entry['pathQuery'],
                    'pathFragment' => $entry['pathFragment'],
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
                    'declaredSize' => $entry['size'],
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
            'manifestVersion' => $this->manifestVersion,
            'contentXml' => isset($this->manifestEntriesByPath['content.xml']),
            'stylesXml' => isset($this->manifestEntriesByPath['styles.xml']),
            'metaXml' => isset($this->manifestEntriesByPath['meta.xml']),
            'settingsXml' => isset($this->manifestEntriesByPath['settings.xml']),
            'mediaParts' => $mediaParts,
            'missingMediaPartCount' => count($missingMediaParts),
            'missingMediaParts' => $missingMediaParts,
            'exposableMediaPartCount' => $exposableMediaPartCount,
            'encryptedCount' => count($encryptedParts),
            'encryptedParts' => $encryptedParts,
            'undeclaredPackageEntryCount' => count($undeclaredPackageEntries),
            'undeclaredPackageEntries' => $undeclaredPackageEntries,
            'packageThumbnails' => $packageThumbnails,
            'packageSignatures' => $packageSignatures,
            'packageConfigurations' => $packageConfigurations,
            'manifestReview' => self::manifestReview($this->manifestEntries, $undeclaredPackageEntries),
            'packageInventory' => $packageInventory,
            'metadata' => $this->metadata,
            'settings' => $this->settings,
            'styleNames' => array_keys($this->stylesByName),
            'contentBlocks' => count($this->readContentDocument()->children),
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
        $compressionMethods = $this->package->compressionMethodPreflight();
        $localOrderByName = [];
        foreach ($localHeaderOrder['entries'] as $entry) {
            $name = $entry['name'] ?? null;
            if (is_string($name) && $name !== '') {
                $localOrderByName[$name] = $entry;
            }
        }

        $parts = [];
        $undeclaredEntries = [];
        $packageDirectoryCount = 0;
        $roleCounts = [];
        $undeclaredRoleCounts = [];
        $corePackagePartCount = 0;
        $mediaResourcePartCount = 0;
        $packageThumbnailPartCount = 0;
        $packageSignaturePartCount = 0;
        $packageConfigurationPartCount = 0;
        $scriptPackagePartCount = 0;
        foreach ($this->package->entries() as $centralDirectoryIndex => $entry) {
            $manifestEntry = $this->manifestEntriesByPath[$entry->name] ?? null;
            $isUndeclared = !$entry->isDirectory() && !isset($declaredPackagePaths[$entry->name]);
            $localOrder = $localOrderByName[$entry->name] ?? null;
            if ($entry->isDirectory()) {
                ++$packageDirectoryCount;
            }

            $roles = self::packageEntryRoles($entry, $manifestEntry, $isUndeclared);
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
                'isDirectory' => $entry->isDirectory(),
                'declaredInManifest' => is_array($manifestEntry),
                'manifestIndex' => is_array($manifestEntry) ? $manifestEntry['manifestIndex'] : null,
                'manifestPath' => is_array($manifestEntry) ? $manifestEntry['path'] : null,
                'manifestPathReference' => is_array($manifestEntry) ? $manifestEntry['pathReference'] : null,
                'manifestPathSuffix' => is_array($manifestEntry) ? $manifestEntry['pathSuffix'] : null,
                'manifestPathQuery' => is_array($manifestEntry) ? $manifestEntry['pathQuery'] : null,
                'manifestPathFragment' => is_array($manifestEntry) ? $manifestEntry['pathFragment'] : null,
                'manifestMediaType' => is_array($manifestEntry) ? $manifestEntry['mediaType'] : null,
                'manifestMediaTypeBase' => is_array($manifestEntry) ? $manifestEntry['mediaTypeBase'] : null,
                'manifestMediaTypeHasParameters' => is_array($manifestEntry) ? $manifestEntry['mediaTypeHasParameters'] : false,
                'manifestMediaTypeParameterCount' => is_array($manifestEntry) ? $manifestEntry['mediaTypeParameterCount'] : 0,
                'manifestMediaTypeParameters' => is_array($manifestEntry) ? $manifestEntry['mediaTypeParameters'] : [],
                'manifestMediaTypeParameterMap' => is_array($manifestEntry) ? $manifestEntry['mediaTypeParameterMap'] : [],
                'manifestVersion' => is_array($manifestEntry) ? $manifestEntry['version'] : null,
                'manifestPreferredViewMode' => is_array($manifestEntry) ? $manifestEntry['preferredViewMode'] : null,
                'manifestMissingMediaType' => is_array($manifestEntry) && ($manifestEntry['missingMediaType'] ?? false) === true,
                'manifestDiagnostics' => is_array($manifestEntry) ? ($manifestEntry['diagnostics'] ?? []) : [],
                'manifestEncryption' => is_array($manifestEntry) ? $manifestEntry['encryption'] : null,
                'manifestEncryptionRecordCount' => is_array($manifestEntry) && is_array($manifestEntry['encryption'] ?? null)
                    ? ($manifestEntry['encryption']['recordCount'] ?? 0)
                    : 0,
                'manifestEncryptionIssueCodes' => is_array($manifestEntry) && is_array($manifestEntry['encryption'] ?? null)
                    ? ($manifestEntry['encryption']['issueCodes'] ?? [])
                    : [],
                'configurationPackagePart' => self::isConfigurationPackagePartName($entry->name),
                'scriptPackagePart' => self::isScriptPackagePartName($entry->name),
                'encrypted' => is_array($manifestEntry) && ($manifestEntry['encrypted'] ?? false) === true,
                'canExposeBytes' => is_array($manifestEntry) && ($manifestEntry['canExposeBytes'] ?? false) === true,
                'undeclared' => $isUndeclared,
            ];

            foreach ($roles as $role) {
                $roleCounts[$role] = ($roleCounts[$role] ?? 0) + 1;
                if ($isUndeclared) {
                    $undeclaredRoleCounts[$role] = ($undeclaredRoleCounts[$role] ?? 0) + 1;
                }
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
            if (in_array('package-configuration', $roles, true)) {
                ++$packageConfigurationPartCount;
            }
            if (in_array('script-package', $roles, true)) {
                ++$scriptPackagePartCount;
            }

            $parts[$entry->name] = $item;
            if ($isUndeclared) {
                $undeclaredEntries[] = $item;
            }
        }
        ksort($roleCounts, SORT_STRING);
        ksort($undeclaredRoleCounts, SORT_STRING);

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
            'packageConfigurationPartCount' => $packageConfigurationPartCount,
            'scriptPackagePartCount' => $scriptPackagePartCount,
            'centralDirectoryOrderMatchesLocalHeaderOrder' => !$localHeaderOrder['hasCentralDirectoryOrderMismatch'],
            'localHeaderOrder' => $localHeaderOrder,
            'compressionMethods' => $compressionMethods,
            'parts' => $parts,
        ];
    }

    /**
     * @param array<string, mixed>|null $manifestEntry
     * @return list<string>
     */
    private static function packageEntryRoles(ZipPackageEntry $entry, ?array $manifestEntry, bool $undeclared): array
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
        if (self::isConfigurationPackagePartName($entry->name)) {
            $roles[] = 'package-configuration';
        }
        if (self::isScriptPackagePartName($entry->name)) {
            $roles[] = 'script-package';
        }
        if ($entry->isDirectory()) {
            $roles[] = 'zip-directory';
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
            || str_starts_with($normalized, 'scripts/');
    }

    private static function isConfigurationPackagePartName(string $path): bool
    {
        return str_starts_with(strtolower(ltrim($path, '/')), 'configurations2/');
    }

    /**
     * @param array<string, mixed> $entry
     */
    private static function isMediaResourceManifestEntry(array $entry): bool
    {
        $packagePath = $entry['packagePath'] ?? $entry['path'] ?? '';
        if (is_string($packagePath) && self::isThumbnailPackagePartName($packagePath)) {
            return false;
        }
        if (is_string($packagePath) && self::isSignaturePackagePartName($packagePath)) {
            return false;
        }
        if (is_string($packagePath) && self::isConfigurationPackagePartName($packagePath)) {
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

        return str_starts_with((string) ($entry['path'] ?? ''), 'Pictures/');
    }

    /**
     * @param list<array<string, mixed>> $entries
     * @return list<array<string, mixed>>
     */
    private static function withPackageEntryMetadata(array $entries, ZipPackage $package): array
    {
        $hydrated = [];
        foreach ($entries as $entry) {
            $isRoot = $entry['path'] === '/';
            $packagePath = $entry['packagePath'];
            $isDirectory = is_string($packagePath) && str_ends_with($packagePath, '/');
            $configurationPackagePart = is_string($packagePath) && self::isConfigurationPackagePartName($packagePath);
            $scriptPackagePart = is_string($packagePath) && self::isScriptPackagePartName($packagePath);
            $zipEntry = (!$isRoot && is_string($packagePath) && $package->has($packagePath))
                ? $package->entry($packagePath)
                : null;
            $exists = $isRoot || $isDirectory || $zipEntry instanceof ZipPackageEntry;
            $encrypted = is_array($entry['encryption']);
            $missingMediaType = ($entry['missingMediaType'] ?? false) === true;
            $storedByteLength = $zipEntry instanceof ZipPackageEntry ? $zipEntry->uncompressedSize : null;
            $compressionMethod = $zipEntry instanceof ZipPackageEntry ? $zipEntry->compressionMethod : null;
            $hasSupportedCompression = $compressionMethod === null || $compressionMethod === 0 || $compressionMethod === 8;
            $canExposeBytes = !$isRoot && $exists && !$isDirectory && !$encrypted && !$configurationPackagePart && !$scriptPackagePart && !$missingMediaType && $hasSupportedCompression;
            $declaredSize = is_int($entry['size'] ?? null) ? $entry['size'] : null;
            $declaredSizeMismatch = $declaredSize !== null
                && $storedByteLength !== null
                && !$isDirectory
                && $declaredSize !== $storedByteLength;
            $diagnostics = is_array($entry['diagnostics'] ?? null) ? array_values($entry['diagnostics']) : [];

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

            $hydrated[] = array_merge($entry, [
                'exists' => $exists,
                'isDirectory' => $isDirectory,
                'byteLength' => $canExposeBytes && $zipEntry instanceof ZipPackageEntry ? $zipEntry->uncompressedSize : null,
                'storedByteLength' => $storedByteLength,
                'compressedByteLength' => $zipEntry instanceof ZipPackageEntry ? $zipEntry->compressedSize : null,
                'compressionMethod' => $compressionMethod,
                'compressionMethodName' => $compressionMethod !== null ? self::compressionMethodName($compressionMethod) : null,
                'crc32' => $canExposeBytes && $zipEntry instanceof ZipPackageEntry ? $zipEntry->crc32Hex() : null,
                'storedCrc32' => $zipEntry instanceof ZipPackageEntry ? $zipEntry->crc32Hex() : null,
                'declaredSize' => $declaredSize,
                'declaredSizeMismatch' => $declaredSizeMismatch,
                'configurationPackagePart' => $configurationPackagePart,
                'scriptPackagePart' => $scriptPackagePart,
                'canExposeBytes' => $canExposeBytes,
                'byteExposurePolicy' => self::byteExposurePolicy(
                    $isRoot,
                    $exists,
                    $isDirectory,
                    $encrypted,
                    $configurationPackagePart,
                    $scriptPackagePart,
                    $missingMediaType,
                    $hasSupportedCompression
                ),
                'diagnostics' => $diagnostics,
            ]);
        }

        return $hydrated;
    }

    private static function byteExposurePolicy(
        bool $isRoot,
        bool $exists,
        bool $isDirectory,
        bool $encrypted,
        bool $configurationPackagePart,
        bool $scriptPackagePart,
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
        if ($configurationPackagePart) {
            return 'configuration-package-bytes-blocked';
        }
        if ($scriptPackagePart) {
            return 'script-package-bytes-blocked';
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
                'configurationPackagePart' => self::isConfigurationPackagePartName($path),
                'scriptPackagePart' => self::isScriptPackagePartName($path),
                'canExposeBytes' => false,
                'byteExposurePolicy' => 'undeclared-package-entry-no-bytes',
                'diagnostics' => ['odf-manifest-undeclared-package-entry'],
            ];
        }

        return $entries;
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
                'canExposeAsDocumentMedia' => false,
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
            $mediaTypeValid = $mediaType === null
                || $mediaTypeReport['mediaTypeBase'] === 'text/xml'
                || $mediaTypeReport['mediaTypeBase'] === 'application/xml';
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
                'expectedMediaTypes' => ['text/xml', 'application/xml'],
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
                    && !in_array($item['mediaTypeBase'], ['text/xml', 'application/xml'], true),
            )),
            'issueCount' => count(array_filter($items, static fn (array $item): bool => $item['issues'] !== [])),
            'issueCodes' => array_keys($issueCodes),
            'items' => $items,
        ];
    }

    private static function isSignaturePackagePartName(string $path): bool
    {
        $normalized = strtolower(ltrim($path, '/'));

        return str_starts_with($normalized, 'meta-inf/')
            && str_ends_with($normalized, 'signatures.xml')
            && !str_ends_with($normalized, '/');
    }

    /**
     * @param list<array<string, mixed>> $manifestEntries
     * @param list<array<string, mixed>> $undeclaredPackageEntries
     * @return array{count:int, storedCount:int, declaredCount:int, undeclaredCount:int, missingCount:int, directoryCount:int, encryptedCount:int, issueCount:int, issueCodes:list<string>, items:list<array<string, mixed>>}
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
            $isDirectory = str_ends_with($packagePath, '/');
            $declared = ($entry['declared'] ?? false) === true;
            $encrypted = ($entry['encrypted'] ?? false) === true;
            $exists = $zipEntry instanceof ZipPackageEntry || (($entry['exists'] ?? false) === true && $isDirectory);
            $compressionMethod = $zipEntry instanceof ZipPackageEntry ? $zipEntry->compressionMethod : null;
            $hasSupportedCompression = $compressionMethod === null || $compressionMethod === 0 || $compressionMethod === 8;
            $mediaType = is_string($entry['mediaType'] ?? null) ? (string) $entry['mediaType'] : null;
            $mediaTypeReport = self::mediaTypeReport($mediaType ?? '');
            $issues = [];

            if (!$exists && !$isDirectory) {
                $issues[] = 'odf-configuration-missing-package-part';
            }
            if (!$declared) {
                $issues[] = 'odf-configuration-undeclared-package-part';
            }
            if ($encrypted) {
                $issues[] = 'odf-configuration-encrypted-package-part';
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
                'exists' => $exists,
                'declared' => $declared,
                'undeclared' => !$declared,
                'encrypted' => $encrypted,
                'isDirectory' => $isDirectory,
                'byteLength' => null,
                'compressedByteLength' => $zipEntry instanceof ZipPackageEntry ? $zipEntry->compressedSize : null,
                'compressionMethod' => $compressionMethod,
                'compressionMethodName' => $compressionMethod !== null ? self::compressionMethodName($compressionMethod) : null,
                'crc32' => null,
                'storedByteLength' => $zipEntry instanceof ZipPackageEntry ? $zipEntry->uncompressedSize : null,
                'storedCrc32' => $zipEntry instanceof ZipPackageEntry ? $zipEntry->crc32Hex() : null,
                'declaredSize' => $entry['declaredSize'] ?? $entry['size'] ?? null,
                'declaredSizeMismatch' => ($entry['declaredSizeMismatch'] ?? false) === true,
                'canExposeBytes' => false,
                'canExposeAsDocumentMedia' => false,
                'byteExposurePolicy' => self::byteExposurePolicy(false, $exists, $isDirectory, $encrypted, true, false, false, $hasSupportedCompression),
                'reviewPolicy' => 'package-configuration-metadata-only',
                'issues' => $issues,
            ];
        }

        ksort($issueCodes, SORT_STRING);

        return [
            'count' => count($items),
            'storedCount' => count(array_filter(
                $items,
                static fn (array $item): bool => $item['storedByteLength'] !== null && $item['isDirectory'] !== true,
            )),
            'declaredCount' => count(array_filter($items, static fn (array $item): bool => $item['declared'] === true)),
            'undeclaredCount' => count(array_filter($items, static fn (array $item): bool => $item['undeclared'] === true)),
            'missingCount' => count(array_filter($items, static fn (array $item): bool => $item['exists'] !== true)),
            'directoryCount' => count(array_filter($items, static fn (array $item): bool => $item['isDirectory'] === true)),
            'encryptedCount' => count(array_filter($items, static fn (array $item): bool => $item['encrypted'] === true)),
            'issueCount' => count(array_filter($items, static fn (array $item): bool => $item['issues'] !== [])),
            'issueCodes' => array_keys($issueCodes),
            'items' => $items,
        ];
    }

    /**
     * @param list<array<string, mixed>> $entries
     * @param list<array<string, mixed>> $undeclaredPackageEntries
     * @return array<string, mixed>
     */
    private static function manifestReview(array $entries, array $undeclaredPackageEntries = []): array
    {
        $summary = [
            'count' => count($entries),
            'existsCount' => 0,
            'missingCount' => 0,
            'directoryCount' => 0,
            'encryptedCount' => 0,
            'declaredSizeMismatchCount' => 0,
            'storedByteLength' => 0,
            'compressedByteLength' => 0,
            'exposableByteLength' => 0,
            'declaredSize' => 0,
            'storedCompressionMethodCount' => 0,
            'deflatedCompressionMethodCount' => 0,
            'unsupportedCompressionMethodCount' => 0,
            'manifestFileEntryCount' => count($entries),
            'manifestFileEntryOrder' => [],
            'manifestPartReferenceSuffixCount' => 0,
            'manifestPartReferenceQueryCount' => 0,
            'manifestPartReferenceFragmentCount' => 0,
            'manifestPartReferenceSuffixItems' => [],
            'configurationPackagePartCount' => 0,
            'configurationPackageItems' => [],
            'scriptPackagePartCount' => 0,
            'scriptPackageItems' => [],
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
            if (($entry['configurationPackagePart'] ?? false) === true) {
                ++$summary['configurationPackagePartCount'];
                $summary['configurationPackageItems'][] = $item;
            }
            if (($entry['scriptPackagePart'] ?? false) === true) {
                ++$summary['scriptPackagePartCount'];
                $summary['scriptPackageItems'][] = $item;
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
        $summary['diagnosticCount'] = count($summary['diagnostics']);
        ksort($summary['diagnosticCodeCounts'], SORT_STRING);

        return $summary;
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
            'mediaType' => $entry['mediaType'],
            'mediaTypeBase' => $entry['mediaTypeBase'] ?? null,
            'mediaTypeHasParameters' => ($entry['mediaTypeHasParameters'] ?? false) === true,
            'mediaTypeParameterCount' => $entry['mediaTypeParameterCount'] ?? 0,
            'mediaTypeParameters' => $entry['mediaTypeParameters'] ?? [],
            'mediaTypeParameterMap' => $entry['mediaTypeParameterMap'] ?? [],
            'version' => $entry['version'] ?? null,
            'preferredViewMode' => $entry['preferredViewMode'] ?? null,
            'exists' => ($entry['exists'] ?? false) === true,
            'isDirectory' => ($entry['isDirectory'] ?? false) === true,
            'encrypted' => ($entry['encrypted'] ?? false) === true,
            'encryption' => $entry['encryption'] ?? null,
            'encryptionRecordCount' => is_array($entry['encryption'] ?? null) ? ($entry['encryption']['recordCount'] ?? 0) : 0,
            'encryptionIssueCodes' => is_array($entry['encryption'] ?? null) ? ($entry['encryption']['issueCodes'] ?? []) : [],
            'configurationPackagePart' => ($entry['configurationPackagePart'] ?? false) === true,
            'scriptPackagePart' => ($entry['scriptPackagePart'] ?? false) === true,
            'canExposeBytes' => ($entry['canExposeBytes'] ?? false) === true,
            'byteLength' => $entry['byteLength'] ?? null,
            'storedByteLength' => $entry['storedByteLength'] ?? null,
            'compressedByteLength' => $entry['compressedByteLength'] ?? null,
            'compressionMethod' => $entry['compressionMethod'] ?? null,
            'compressionMethodName' => $entry['compressionMethodName'] ?? null,
            'crc32' => $entry['crc32'] ?? null,
            'storedCrc32' => $entry['storedCrc32'] ?? null,
            'declaredSize' => $entry['declaredSize'] ?? null,
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
            'mediaType' => $entry['mediaType'],
            'exists' => ($entry['exists'] ?? false) === true,
            'isDirectory' => ($entry['isDirectory'] ?? false) === true,
            'encrypted' => ($entry['encrypted'] ?? false) === true,
            'configurationPackagePart' => ($entry['configurationPackagePart'] ?? false) === true,
            'canExposeBytes' => ($entry['canExposeBytes'] ?? false) === true,
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
            'mediaType' => $entry['mediaType'],
            'exists' => ($entry['exists'] ?? false) === true,
            'isDirectory' => ($entry['isDirectory'] ?? false) === true,
            'encrypted' => ($entry['encrypted'] ?? false) === true,
            'configurationPackagePart' => ($entry['configurationPackagePart'] ?? false) === true,
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

        $entries = $package->entries();
        $first = $entries[0] ?? null;
        if (!$first instanceof ZipPackageEntry || $first->name !== 'mimetype' || $first->compressionMethod !== 0) {
            throw new \RuntimeException('ODT mimetype entry must be first and stored without compression');
        }

        if ($package->read('mimetype') !== self::TEXT_MIMETYPE) {
            throw new \RuntimeException('ODT mimetype entry must be application/vnd.oasis.opendocument.text');
        }
    }

    /**
     * @return array{
     *     version:string|null,
     *     entries:list<array{manifestIndex:int, path:string, packagePath:string|null, pathReference:string|null, pathSuffix:string|null, pathQuery:string|null, pathFragment:string|null, mediaType:string, version:string|null, size:int|null, preferredViewMode:string|null, encrypted:bool, encryption:array<string, mixed>|null}>
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
            $mediaType = self::namespacedAttribute($child, self::MANIFEST_NAMESPACE, 'media-type') ?? '';
            $missingMediaType = $mediaType === '' && !str_ends_with($pathReference ?? $path, '/');
            $diagnostics = $missingMediaType ? ['odf-manifest-file-entry-missing-media-type'] : [];
            $mediaTypeReport = self::mediaTypeReport($mediaType);

            $size = self::manifestSize(
                self::namespacedAttribute($child, self::MANIFEST_NAMESPACE, 'size'),
                $path
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
                'mediaType' => $mediaType,
                'mediaTypeBase' => $mediaTypeReport['mediaTypeBase'],
                'mediaTypeHasParameters' => $mediaTypeReport['mediaTypeHasParameters'],
                'mediaTypeParameterCount' => $mediaTypeReport['mediaTypeParameterCount'],
                'mediaTypeParameters' => $mediaTypeReport['mediaTypeParameters'],
                'mediaTypeParameterMap' => $mediaTypeReport['mediaTypeParameterMap'],
                'version' => self::namespacedAttribute($child, self::MANIFEST_NAMESPACE, 'version'),
                'size' => $size,
                'preferredViewMode' => self::optionalString(self::namespacedAttribute($child, self::MANIFEST_NAMESPACE, 'preferred-view-mode')),
                'missingMediaType' => $missingMediaType,
                'encrypted' => $encryption !== null,
                'encryption' => $encryption,
                'diagnostics' => $diagnostics,
            ];
            ++$manifestIndex;
        }

        return [
            'version' => $manifestVersion,
            'entries' => $entries,
        ];
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

        return self::withoutNulls([
            'name' => self::optionalString(self::namespacedAttribute($algorithm, self::MANIFEST_NAMESPACE, 'algorithm-name')),
            'initialisationVector' => self::optionalString($initialisationVector),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private static function manifestEncryptionKeyDerivation(\DOMElement $keyDerivation): array
    {
        return self::withoutNulls([
            'name' => self::optionalString(self::namespacedAttribute($keyDerivation, self::MANIFEST_NAMESPACE, 'key-derivation-name')),
            'keySize' => self::optionalInt(self::namespacedAttribute($keyDerivation, self::MANIFEST_NAMESPACE, 'key-size')),
            'iterationCount' => self::optionalInt(self::namespacedAttribute($keyDerivation, self::MANIFEST_NAMESPACE, 'iteration-count')),
            'salt' => self::optionalString(self::namespacedAttribute($keyDerivation, self::MANIFEST_NAMESPACE, 'salt')),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private static function manifestEncryptionStartKeyGeneration(\DOMElement $startKeyGeneration): array
    {
        return self::withoutNulls([
            'name' => self::optionalString(self::namespacedAttribute($startKeyGeneration, self::MANIFEST_NAMESPACE, 'start-key-generation-name')),
            'keySize' => self::optionalInt(self::namespacedAttribute($startKeyGeneration, self::MANIFEST_NAMESPACE, 'key-size')),
        ]);
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

    private static function manifestSize(?string $value, string $path): ?int
    {
        $value = $value === null ? '' : trim($value);
        if ($value === '') {
            return null;
        }

        if (!ctype_digit($value)) {
            throw new \InvalidArgumentException('ODF manifest:size for ' . $path . ' must be a non-negative integer');
        }

        $normalized = ltrim($value, '0');
        $normalized = $normalized === '' ? '0' : $normalized;
        $max = (string) PHP_INT_MAX;
        if (strlen($normalized) > strlen($max) || (strlen($normalized) === strlen($max) && strcmp($normalized, $max) > 0)) {
            throw new \InvalidArgumentException('ODF manifest:size for ' . $path . ' exceeds platform integer bounds');
        }

        return (int) $normalized;
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

        if (str_starts_with($path, '/') || str_contains($path, '\\') || str_contains($path, "\0")) {
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
        if ($decodedPath === '' || str_starts_with($decodedPath, '/') || str_contains($decodedPath, '\\') || str_contains($decodedPath, "\0")) {
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
