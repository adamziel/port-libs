<?php

declare(strict_types=1);

namespace PortLibs\Rclone;

/**
 * Focused native model of rclone's OneDrive delta-backed ListR path.
 *
 * Upstream OneDrive ListR reads `/root/delta`, keeps a directory-id cache,
 * skips duplicate/deleted/out-of-root delta items, and falls back to ordinary
 * folder recursion for remote shared folders because delta does not cover
 * their children reliably.
 */
final class OneDriveListR
{
    public const DRIVE_TYPE_PERSONAL = 'personal';
    public const DRIVE_TYPE_BUSINESS = 'business';
    public const DRIVE_TYPE_SHAREPOINT = 'documentLibrary';

    /**
     * Build a ListR callable compatible with ListDirectory::listRecursiveDirect().
     *
     * $initialDirectories is path => normalized OneDrive ID. $sharedFolderListings
     * is shared-folder remote path => direct child items or a ListP callable used
     * by the conventional List fallback for RemoteItem folders.
     *
     * @param list<array<string, mixed>> $deltaItems
     * @param array<string, string> $initialDirectories
     * @param array<string, list<array<string, mixed>|ObjectInfo>|callable> $sharedFolderListings
     * @return callable(string, callable(list<ObjectInfo>): (null|\Throwable)): (null|\Throwable)
     */
    public static function fromDelta(
        array $deltaItems,
        string $rootId,
        array $initialDirectories = [],
        array $sharedFolderListings = [],
        bool $exposeOneNoteFiles = false,
    ): callable {
        return self::fromDeltaPages(
            [['value' => $deltaItems]],
            $rootId,
            $initialDirectories,
            $sharedFolderListings,
            $exposeOneNoteFiles,
        );
    }

    /**
     * Build a ListR callable from Graph delta response pages.
     *
     * This models upstream _listAll's continuation loop: the first request uses
     * /root/delta with $top, each @odata.nextLink becomes the next RootURL with
     * cleared path/parameters, and final ListHelper.Flush is skipped if a later
     * provider page errors.
     *
     * @param array<int|string, array<string, mixed>> $deltaPages
     * @param array<string, string> $initialDirectories
     * @param array<string, list<array<string, mixed>|ObjectInfo>|callable> $sharedFolderListings
     * @param null|array<string, mixed> $trace
     * @return callable(string, callable(list<ObjectInfo>): (null|\Throwable)): (null|\Throwable)
     */
    public static function fromDeltaPages(
        array $deltaPages,
        string $rootId,
        array $initialDirectories = [],
        array $sharedFolderListings = [],
        bool $exposeOneNoteFiles = false,
        int $listChunk = 200,
        ?array &$trace = null,
    ): callable {
        if ($listChunk < 1) {
            throw new \InvalidArgumentException('OneDrive list chunk must be positive');
        }
        if ($trace === null) {
            $trace = [];
        }

        $pathToId = ['' => $rootId];
        foreach ($initialDirectories as $path => $id) {
            $pathToId[self::normalizePath((string) $path)] = (string) $id;
        }

        return static function (string $dir, callable $callback) use (
            $deltaPages,
            &$pathToId,
            $sharedFolderListings,
            $exposeOneNoteFiles,
            $listChunk,
            &$trace,
        ): ?\Throwable {
            $root = self::normalizePath($dir);
            if (!isset($pathToId[$root])) {
                return new \RuntimeException("Directory not found: {$root}");
            }

            $directoryId = $pathToId[$root];
            $idToPath = array_flip($pathToId);
            $seen = [];

            $helper = new ListHelper(static function (array $entries) use ($callback): void {
                $result = $callback($entries);
                if ($result instanceof \Throwable) {
                    throw $result;
                }
                if ($result !== null) {
                    throw new \InvalidArgumentException('recursive list callback must return null or Throwable');
                }
            });

            $listSharedFolder = null;
            $listSharedFolder = static function (string $remote, mixed $fallbackListing = null) use (
                &$listSharedFolder,
                $sharedFolderListings,
                $exposeOneNoteFiles,
                $helper,
            ): void {
                $listing = $sharedFolderListings[$remote] ?? $fallbackListing ?? [];
                foreach (self::sharedFolderEntries($listing, $remote, $exposeOneNoteFiles) as $entry) {
                    $helper->add($entry);
                    if (ListDirectory::isDirectory($entry)) {
                        $listSharedFolder($entry->path, is_callable($listing) ? $listing : null);
                    }
                }
            };

            try {
                self::listAllPages(
                    $deltaPages,
                    false,
                    false,
                    static function (array $item) use (
                        $root,
                        $directoryId,
                        $exposeOneNoteFiles,
                        $helper,
                        $listSharedFolder,
                        &$pathToId,
                        &$idToPath,
                        &$seen,
                    ): void {
                        self::processDeltaItem(
                            $item,
                            $root,
                            $directoryId,
                            $exposeOneNoteFiles,
                            $helper,
                            $listSharedFolder,
                            $pathToId,
                            $idToPath,
                            $seen,
                        );
                    },
                    $listChunk,
                    $trace,
                );

                $helper->flush();

                return null;
            } catch (\Throwable $throwable) {
                return $throwable;
            }
        };
    }

    /**
     * Build a ListP callable from Graph child-list response pages.
     *
     * This is the ordinary non-recursive `ListP` path upstream uses inside
     * the shared-folder fallback. The first request targets `/children` with
     * `$top`, continuations switch to the `@odata.nextLink` RootURL, deleted
     * entries are skipped, and directory/file mode filters happen before the
     * caller sees an item.
     *
     * @param array<string, array<int|string, array<string, mixed>>> $childPagesByRemote
     * @param array<string, string> $directoryIdsByRemote
     * @param null|array<string, mixed> $trace
     * @return callable(string, callable(list<ObjectInfo>): (null|\Throwable), bool, bool): (null|\Throwable)
     */
    public static function listPFromChildPages(
        array $childPagesByRemote,
        array $directoryIdsByRemote,
        bool $exposeOneNoteFiles = false,
        int $listChunk = 200,
        ?array &$trace = null,
    ): callable {
        if ($listChunk < 1) {
            throw new \InvalidArgumentException('OneDrive list chunk must be positive');
        }
        if ($trace === null) {
            $trace = [];
        }

        $pagesByRemote = [];
        foreach ($childPagesByRemote as $remote => $pages) {
            $pagesByRemote[self::normalizePath((string) $remote)] = $pages;
        }

        $directoryIds = [];
        foreach ($directoryIdsByRemote as $remote => $id) {
            $directoryIds[self::normalizePath((string) $remote)] = (string) $id;
        }

        return static function (
            string $dir,
            callable $callback,
            bool $directoriesOnly = false,
            bool $filesOnly = false,
        ) use (
            $pagesByRemote,
            &$directoryIds,
            $exposeOneNoteFiles,
            $listChunk,
            &$trace,
        ): ?\Throwable {
            $remote = self::normalizePath($dir);
            $directoryId = $directoryIds[$remote] ?? null;
            if ($directoryId === null || $directoryId === '') {
                return new \RuntimeException("Directory not found: {$remote}");
            }

            $helper = new ListHelper(static function (array $entries) use ($callback): void {
                $result = $callback($entries);
                if ($result instanceof \Throwable) {
                    throw $result;
                }
                if ($result !== null) {
                    throw new \InvalidArgumentException('ListP callback must return null or Throwable');
                }
            });

            try {
                self::listAllPages(
                    $pagesByRemote[$remote] ?? [['value' => []]],
                    $directoriesOnly,
                    $filesOnly,
                    static function (array $item) use ($remote, $exposeOneNoteFiles, $helper, &$directoryIds): void {
                        $entry = self::entryFromItem($item, $remote, $exposeOneNoteFiles);
                        if ($entry !== null) {
                            if (ListDirectory::isDirectory($entry) && $entry->id !== null && $entry->id !== '') {
                                $directoryIds[$entry->path] = $entry->id;
                            }
                            $helper->add($entry);
                        }
                    },
                    $listChunk,
                    $trace,
                    '/children',
                    $directoryId,
                );
                $helper->flush();

                return null;
            } catch (\Throwable $throwable) {
                return $throwable;
            }
        };
    }

    /**
     * @param array<int|string, array<string, mixed>> $pages
     * @param callable(array<string, mixed>): void $fn
     * @param array<string, mixed> $trace
     */
    private static function listAllPages(
        array $pages,
        bool $directoriesOnly,
        bool $filesOnly,
        callable $fn,
        int $listChunk,
        array &$trace,
        string $initialPath = '/root/delta',
        ?string $directoryId = null,
    ): void {
        $pageKey = self::firstPageKey($pages);
        $rootUrl = null;
        $path = $initialPath;
        $parameters = ['$top' => [(string) $listChunk]];
        $trace['requests'] ??= [];
        $trace['pages'] ??= [];

        while ($pageKey !== null) {
            $request = [
                'rootUrl' => $rootUrl,
                'path' => $path,
                'parameters' => $parameters,
            ];
            if ($directoryId !== null) {
                $request['directoryId'] = $directoryId;
            }

            $trace['requests'][] = $request;
            $trace['pages'][] = $pageKey;

            $page = $pages[$pageKey] ?? null;
            if (!is_array($page)) {
                throw new \RuntimeException("couldn't list files: missing page for {$pageKey}");
            }

            $error = self::pageError($page);
            if ($error !== null) {
                throw new \RuntimeException("couldn't list files: {$error->getMessage()}", 0, $error);
            }

            $items = self::pageItems($page);
            if ($items === []) {
                break;
            }

            foreach ($items as $item) {
                $isFolder = self::folder($item) !== null;
                if (($isFolder && $filesOnly) || (!$isFolder && $directoriesOnly) || isset($item['deleted'])) {
                    continue;
                }

                $fn($item);
            }

            $nextLink = self::pageNextLink($page);
            if ($nextLink === '') {
                break;
            }

            $nextKey = array_key_exists($nextLink, $pages)
                ? $nextLink
                : self::nextSequentialPageKey($pages, $pageKey);
            if ($nextKey === null) {
                throw new \RuntimeException("couldn't list files: missing page for {$nextLink}");
            }

            $pageKey = $nextKey;
            $rootUrl = $nextLink;
            $path = '';
            $parameters = [];
        }
    }

    /**
     * @param array<int|string, array<string, mixed>> $pages
     */
    private static function firstPageKey(array $pages): int|string|null
    {
        foreach (array_keys($pages) as $key) {
            return $key;
        }

        return null;
    }

    /**
     * @param array<int|string, array<string, mixed>> $pages
     */
    private static function nextSequentialPageKey(array $pages, int|string $currentKey): int|string|null
    {
        $returnNext = false;
        foreach (array_keys($pages) as $key) {
            if ($returnNext) {
                return $key;
            }
            if ($key === $currentKey) {
                $returnNext = true;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $page
     */
    private static function pageError(array $page): ?\Throwable
    {
        $error = $page['error'] ?? $page['Error'] ?? null;
        if ($error instanceof \Throwable) {
            return $error;
        }
        if (is_scalar($error) && (string) $error !== '') {
            return new \RuntimeException((string) $error);
        }

        return null;
    }

    /**
     * @param array<string, mixed> $page
     * @return list<array<string, mixed>>
     */
    private static function pageItems(array $page): array
    {
        $items = $page['value'] ?? $page['Value'] ?? [];
        if (!is_array($items)) {
            return [];
        }

        return array_values(array_filter($items, static fn (mixed $item): bool => is_array($item)));
    }

    /**
     * @param array<string, mixed> $page
     */
    private static function pageNextLink(array $page): string
    {
        return self::optionalString(
            $page['@odata.nextLink']
                ?? $page['nextLink']
                ?? $page['NextLink']
                ?? null,
        ) ?? '';
    }

    /**
     * @param list<array<string, mixed>|ObjectInfo>|callable $listing
     * @return list<ObjectInfo>
     */
    private static function sharedFolderEntries(mixed $listing, string $remote, bool $exposeOneNoteFiles): array
    {
        if (is_callable($listing)) {
            $collected = ListHelper::collectWithListP(
                static function (callable $callback) use ($listing, $remote): void {
                    $result = $listing(
                        $remote,
                        static function (array $batch) use ($callback): null {
                            $callback($batch);

                            return null;
                        },
                    );

                    if ($result instanceof \Throwable) {
                        throw $result;
                    }
                    if ($result !== null) {
                        throw new \InvalidArgumentException('shared-folder ListP must return null or Throwable');
                    }
                },
            );

            if ($collected['error'] !== null) {
                throw $collected['error'];
            }

            return $collected['entries'];
        }

        if (!is_array($listing)) {
            return [];
        }

        $entries = [];
        foreach ($listing as $rawEntry) {
            $entry = $rawEntry instanceof ObjectInfo
                ? $rawEntry
                : self::entryFromItem($rawEntry, $remote, $exposeOneNoteFiles);
            if ($entry !== null) {
                $entries[] = $entry;
            }
        }

        return $entries;
    }

    /**
     * @param array<string, mixed> $item
     * @param array<string, string> $idToPath
     * @param array<string, true> $seen
     * @param callable(string): void $listSharedFolder
     */
    private static function processDeltaItem(
        array $item,
        string $root,
        string $directoryId,
        bool $exposeOneNoteFiles,
        ListHelper $helper,
        callable $listSharedFolder,
        array &$pathToId,
        array &$idToPath,
        array &$seen,
    ): void {
        $id = self::itemId($item);
        if ($id !== '') {
            if (isset($seen[$id])) {
                return;
            }
            $seen[$id] = true;
        }

        if ($id === $directoryId || isset($item['deleted'])) {
            return;
        }

        $parentReference = self::parentReference($item);
        $parentId = self::referenceId($parentReference);
        if (!isset($idToPath[$parentId])) {
            return;
        }

        $parentPath = $idToPath[$parentId];
        $remote = self::joinPath($parentPath, self::itemName($item));
        if ($root !== '' && !str_starts_with($remote, $root . '/')) {
            return;
        }

        $entry = self::entryFromItem($item, $parentPath, $exposeOneNoteFiles);
        if ($entry === null) {
            return;
        }

        if (ListDirectory::isDirectory($entry)) {
            $entryId = $entry->id ?? $entry->path;
            $idToPath[$entryId] = $entry->path;
            $pathToId[$entry->path] = $entryId;
        }

        $helper->add($entry);
        if (self::isRemoteFolder($item)) {
            $listSharedFolder($entry->path);
        }
    }

    /**
     * @param array<string, mixed> $item
     */
    private static function entryFromItem(array $item, string $parentPath, bool $exposeOneNoteFiles): ?ObjectInfo
    {
        $conversionError = self::conversionError($item);
        if ($conversionError !== null) {
            throw $conversionError;
        }

        $packageType = self::packageType($item);
        if (!$exposeOneNoteFiles && $packageType === 'oneNote') {
            return null;
        }

        $remote = self::joinPath($parentPath, self::itemName($item));
        $id = self::itemId($item);
        $parentId = self::referenceId(self::parentReference($item));
        $metadata = self::systemMetadata($item);
        $metadataError = self::metadataError($item);
        $modTime = self::modTime($item);
        $mimeType = self::mimeType($item);

        if (self::folder($item) !== null) {
            return new ObjectInfo(
                $remote,
                -1,
                '',
                $modTime,
                $mimeType,
                $metadata,
                $id !== '' ? $id : null,
                null,
                [],
                null,
                $parentId !== '' ? $parentId : null,
                $metadataError,
            );
        }

        $hashes = self::hashes($item);

        return new ObjectInfo(
            $remote,
            self::size($item),
            $hashes[HashType::SHA256] ?? hash('sha256', ($id !== '' ? $id : $remote) . "\0" . $remote),
            $modTime,
            $mimeType,
            $metadata,
            $id !== '' ? $id : null,
            null,
            $hashes,
            null,
            $parentId !== '' ? $parentId : null,
            $metadataError,
        );
    }

    /**
     * @param array<string, mixed> $item
     */
    private static function conversionError(array $item): ?\Throwable
    {
        $error = self::errorFromValue($item['conversionError'] ?? $item['itemConversionError'] ?? null);
        if ($error === null) {
            return null;
        }

        return new \RuntimeException("couldn't convert list item: {$error->getMessage()}", 0, $error);
    }

    /**
     * @param array<string, mixed> $item
     */
    private static function metadataError(array $item): ?\Throwable
    {
        if (self::permissionsReadEnabled($item)) {
            $permissionError = self::errorFromValue(
                $item['metadataPermissionsError']
                    ?? $item['metadata_permissions_error']
                    ?? $item['permissionsError']
                    ?? null,
            );
            if ($permissionError !== null) {
                return new \RuntimeException("failed to get permissions: {$permissionError->getMessage()}", 0, $permissionError);
            }

            $permissions = self::permissionsMetadata($item);
            if ($permissions['error'] !== null) {
                return $permissions['error'];
            }
        }

        return self::errorFromValue($item['metadataError'] ?? $item['metadata_error'] ?? null);
    }

    /**
     * @param array<string, mixed> $item
     * @return array{json: ?string, error: ?\Throwable}
     */
    private static function permissionsMetadata(array $item): array
    {
        if (!self::permissionsReadEnabled($item)) {
            return ['json' => null, 'error' => null];
        }

        $marshalError = self::errorFromValue(
            $item['metadataPermissionsMarshalError']
                ?? $item['metadata_permissions_marshal_error']
                ?? $item['permissionsMarshalError']
                ?? null,
        );
        if ($marshalError !== null) {
            return [
                'json' => null,
                'error' => new \RuntimeException("failed to marshal permissions: {$marshalError->getMessage()}", 0, $marshalError),
            ];
        }

        $permissions = $item['metadataPermissions']
            ?? $item['metadata_permissions']
            ?? $item['permissionsMetadata']
            ?? null;
        if (!is_array($permissions) || $permissions === []) {
            return ['json' => null, 'error' => null];
        }

        $permissions = array_is_list($permissions) ? $permissions : [$permissions];
        $json = json_encode($permissions, JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            return [
                'json' => null,
                'error' => new \RuntimeException('failed to marshal permissions: ' . json_last_error_msg()),
            ];
        }

        return ['json' => $json, 'error' => null];
    }

    /**
     * @param array<string, mixed> $item
     */
    private static function permissionsReadEnabled(array $item): bool
    {
        $mode = $item['metadataPermissionsMode']
            ?? $item['metadata_permissions_mode']
            ?? $item['permissionsMode']
            ?? null;
        if ($mode === null) {
            return array_key_exists('metadataPermissions', $item)
                || array_key_exists('metadata_permissions', $item)
                || array_key_exists('permissionsMetadata', $item)
                || array_key_exists('metadataPermissionsError', $item)
                || array_key_exists('metadata_permissions_error', $item)
                || array_key_exists('permissionsError', $item)
                || array_key_exists('metadataPermissionsMarshalError', $item)
                || array_key_exists('metadata_permissions_marshal_error', $item)
                || array_key_exists('permissionsMarshalError', $item);
        }
        if (is_bool($mode)) {
            return $mode;
        }

        $normalized = strtolower(trim((string) $mode));
        if ($normalized === '' || in_array($normalized, ['off', 'false', '0', 'none'], true)) {
            return false;
        }

        foreach (preg_split('/[|,\s+]+/', $normalized) ?: [] as $part) {
            if ($part === 'read') {
                return true;
            }
        }

        return false;
    }

    private static function errorFromValue(mixed $value): ?\Throwable
    {
        if ($value instanceof \Throwable) {
            return $value;
        }
        if (is_scalar($value) && (string) $value !== '') {
            return new \RuntimeException((string) $value);
        }

        return null;
    }

    /**
     * @param array<string, mixed> $item
     * @return array<string, string>
     */
    private static function systemMetadata(array $item): array
    {
        $metadata = [];
        $id = self::itemId($item);
        if ($id !== '') {
            $metadata['id'] = $id;
        }

        $mimeType = self::mimeType($item);
        if ($mimeType !== null && $mimeType !== '') {
            $metadata['content-type'] = $mimeType;
        }

        $packageType = self::packageType($item);
        if ($packageType !== null && $packageType !== '') {
            $metadata['package-type'] = $packageType;
        }

        foreach ([
            'createdBy' => 'created-by',
            'lastModifiedBy' => 'last-modified-by',
        ] as $key => $prefix) {
            $identity = self::identityUser(self::remoteItem($item)[$key] ?? $item[$key] ?? null);
            if (($identity['id'] ?? '') !== '') {
                $metadata[$prefix . '-id'] = $identity['id'];
            }
            if (($identity['displayName'] ?? '') !== '') {
                $metadata[$prefix . '-display-name'] = $identity['displayName'];
            }
        }

        $shared = is_array($item['shared'] ?? null) ? $item['shared'] : [];
        if ($shared !== []) {
            $owner = self::identityUser($shared['owner'] ?? null);
            if (($owner['id'] ?? '') !== '') {
                $metadata['shared-owner-id'] = $owner['id'];
            }

            $sharedBy = self::identityUser($shared['sharedBy'] ?? $shared['shared_by'] ?? null);
            if (($sharedBy['id'] ?? '') !== '') {
                $metadata['shared-by-id'] = $sharedBy['id'];
            }

            foreach ([
                'scope' => 'shared-scope',
                'sharedDateTime' => 'shared-time',
                'shared_date_time' => 'shared-time',
            ] as $source => $target) {
                $value = self::optionalString($shared[$source] ?? null);
                if ($value !== null && $value !== '') {
                    $metadata[$target] = $value;
                }
            }
        }

        $permissions = self::permissionsMetadata($item);
        if ($permissions['json'] !== null) {
            $metadata['permissions'] = $permissions['json'];
        }

        return $metadata;
    }

    /**
     * @param array<string, mixed> $item
     * @return array<string, string>
     */
    private static function hashes(array $item): array
    {
        $file = self::file($item) ?? [];
        $hashSource = is_array($file['hashes'] ?? null) ? $file['hashes'] : [];
        $hashes = [];
        foreach ([
            'sha1Hash' => HashType::SHA1,
            'sha256Hash' => HashType::SHA256,
            'crc32Hash' => HashType::CRC32,
        ] as $key => $hashType) {
            $hash = self::optionalString($hashSource[$key] ?? null);
            if ($hash !== null && $hash !== '') {
                $hashes[$hashType] = strtolower($hash);
            }
        }

        $quickXorHash = self::optionalString(
            $hashSource['quickXorHash']
                ?? $hashSource['QuickXorHash']
                ?? $hashSource['quickxorHash']
                ?? null,
        );
        if ($quickXorHash !== null && $quickXorHash !== '') {
            $decoded = base64_decode($quickXorHash, true);
            if ($decoded !== false && strlen($decoded) === 20) {
                $hashes[HashType::QUICKXOR] = bin2hex($decoded);
            }
        }

        return $hashes;
    }

    /**
     * @param array<string, mixed> $item
     */
    private static function itemId(array $item): string
    {
        $remoteItem = self::remoteItem($item);
        $remoteId = self::optionalString($remoteItem['id'] ?? null);
        if ($remoteId !== null && $remoteId !== '') {
            return self::prefixDriveId($remoteId, is_array($remoteItem['parentReference'] ?? null) ? $remoteItem['parentReference'] : []);
        }

        $id = self::optionalString($item['id'] ?? null) ?? '';
        if ($id !== '' && !str_contains($id, '#') && is_array($item['parentReference'] ?? null)) {
            return self::prefixDriveId($id, $item['parentReference']);
        }

        return $id;
    }

    /**
     * @param array<string, mixed> $item
     */
    private static function itemName(array $item): string
    {
        $remoteItem = self::remoteItem($item);
        $remoteName = self::optionalString($remoteItem['name'] ?? null);
        if ($remoteName !== null && $remoteName !== '') {
            return $remoteName;
        }

        return self::optionalString($item['name'] ?? null) ?? '';
    }

    /**
     * @param array<string, mixed> $item
     * @return array<string, mixed>
     */
    private static function parentReference(array $item): array
    {
        if (is_array($item['parentReference'] ?? null)) {
            return $item['parentReference'];
        }

        $remoteItem = self::remoteItem($item);

        return is_array($remoteItem['parentReference'] ?? null) ? $remoteItem['parentReference'] : [];
    }

    /**
     * @param array<string, mixed> $reference
     */
    private static function referenceId(array $reference): string
    {
        $id = self::optionalString($reference['id'] ?? null) ?? '';
        if ($id === '') {
            return '';
        }

        return self::prefixDriveId($id, $reference);
    }

    /**
     * @param array<string, mixed> $reference
     */
    private static function prefixDriveId(string $id, array $reference): string
    {
        if (str_contains($id, '#')) {
            return $id;
        }

        $driveId = self::optionalString(
            $reference['driveId']
                ?? $reference['driveID']
                ?? null,
        ) ?? '';

        return $driveId . '#' . $id;
    }

    /**
     * @param array<string, mixed> $item
     * @return null|array<string, mixed>
     */
    private static function folder(array $item): ?array
    {
        $remoteItem = self::remoteItem($item);
        if (is_array($remoteItem['folder'] ?? null)) {
            return $remoteItem['folder'];
        }

        return is_array($item['folder'] ?? null) ? $item['folder'] : null;
    }

    /**
     * @param array<string, mixed> $item
     * @return null|array<string, mixed>
     */
    private static function file(array $item): ?array
    {
        $remoteItem = self::remoteItem($item);
        if (is_array($remoteItem['file'] ?? null)) {
            return $remoteItem['file'];
        }

        return is_array($item['file'] ?? null) ? $item['file'] : null;
    }

    /**
     * @param array<string, mixed> $item
     */
    private static function isRemoteFolder(array $item): bool
    {
        $remoteItem = self::remoteItem($item);

        return $remoteItem !== [] && is_array($remoteItem['folder'] ?? null);
    }

    /**
     * @param array<string, mixed> $item
     * @return array<string, mixed>
     */
    private static function remoteItem(array $item): array
    {
        return is_array($item['remoteItem'] ?? null) ? $item['remoteItem'] : [];
    }

    /**
     * @param array<string, mixed> $item
     */
    private static function size(array $item): int
    {
        $remoteItem = self::remoteItem($item);
        if (array_key_exists('size', $remoteItem) && is_numeric($remoteItem['size'])) {
            return max(0, (int) $remoteItem['size']);
        }
        if (array_key_exists('size', $item) && is_numeric($item['size'])) {
            return max(0, (int) $item['size']);
        }

        return 0;
    }

    /**
     * @param array<string, mixed> $item
     */
    private static function mimeType(array $item): ?string
    {
        $file = self::file($item);
        if ($file === null) {
            return null;
        }

        return self::optionalString($file['mimeType'] ?? null);
    }

    /**
     * @param array<string, mixed> $item
     */
    private static function packageType(array $item): ?string
    {
        $remoteItem = self::remoteItem($item);
        $remotePackage = is_array($remoteItem['package'] ?? null) ? $remoteItem['package'] : [];
        $package = is_array($item['package'] ?? null) ? $item['package'] : [];

        return self::optionalString(
            $remotePackage['type']
                ?? $remotePackage['Type']
                ?? $package['type']
                ?? $package['Type']
                ?? $item['packageType']
                ?? $item['package_type']
                ?? null,
        );
    }

    /**
     * @param array<string, mixed> $item
     */
    private static function modTime(array $item): ?string
    {
        $remoteItem = self::remoteItem($item);
        foreach ([
            $remoteItem['fileSystemInfo']['lastModifiedDateTime'] ?? null,
            $item['fileSystemInfo']['lastModifiedDateTime'] ?? null,
            $remoteItem['lastModifiedDateTime'] ?? null,
            $item['lastModifiedDateTime'] ?? null,
        ] as $value) {
            $modTime = self::optionalString($value);
            if ($modTime !== null && $modTime !== '') {
                return $modTime;
            }
        }

        return null;
    }

    /**
     * @return array{id: ?string, displayName: ?string}
     */
    private static function identityUser(mixed $identitySet): array
    {
        if (!is_array($identitySet)) {
            return ['id' => null, 'displayName' => null];
        }

        $user = is_array($identitySet['user'] ?? null) ? $identitySet['user'] : [];

        return [
            'id' => self::optionalString($user['id'] ?? $user['ID'] ?? null),
            'displayName' => self::optionalString(
                $user['displayName']
                    ?? $user['display_name']
                    ?? $user['DisplayName']
                    ?? null,
            ),
        ];
    }

    private static function optionalString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        if (is_scalar($value)) {
            return (string) $value;
        }

        return null;
    }

    private static function joinPath(string $dir, string $name): string
    {
        $name = self::normalizePath($name);
        if ($dir === '') {
            return $name;
        }
        if ($name === '') {
            return $dir;
        }

        return self::normalizePath($dir . '/' . $name);
    }

    private static function normalizePath(string $path): string
    {
        return trim(preg_replace('#/+#', '/', $path) ?? $path, '/');
    }
}
