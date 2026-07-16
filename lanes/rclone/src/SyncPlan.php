<?php

declare(strict_types=1);

namespace PortLibs\Rclone;

final class SyncPlan
{
    private const DROPBOX_EXPORT_API_FORMATS = [
        'markdown' => 'md',
        'html' => 'html',
    ];
    private const ONEDRIVE_DRIVE_TYPE_PERSONAL = 'personal';
    private const ONEDRIVE_DRIVE_TYPE_BUSINESS = 'business';
    private const ONEDRIVE_DRIVE_TYPE_SHAREPOINT = 'documentLibrary';

    /** @var array<string, bool> */
    private array $interactiveDestructiveSkips = [];

    /**
     * @return list<string>
     */
    public function changedPaths(
        MemoryProvider $source,
        MemoryProvider $target,
        ?FilterRuleSet $filter = null,
        bool $ignoreCaseSync = false,
    ): array
    {
        $changed = [];
        $targetPaths = $ignoreCaseSync ? $this->listedPaths($target, $filter, true) : [];
        $seenSourceKeys = [];
        foreach ($source->list() as $sourceInfo) {
            if ($filter !== null && !$filter->includes($sourceInfo->path)) {
                continue;
            }
            if ($this->skipMarchDuplicateObject($sourceInfo->path, $seenSourceKeys, $ignoreCaseSync)) {
                continue;
            }

            $targetInfo = $ignoreCaseSync
                ? ($targetPaths[$this->syncPathKey($sourceInfo->path)] ?? null)
                : $this->optionalInfo($target, $sourceInfo->path);
            if ($targetInfo === null) {
                $changed[] = $sourceInfo->path;
                continue;
            }
            if (!$this->sameObject($sourceInfo, $targetInfo)) {
                $changed[] = $sourceInfo->path;
            }
        }

        return $changed;
    }

    /**
     * Model the observable fs/march.matchListings partition for object lists.
     *
     * Duplicate same-key objects keep the first listed entry for comparisons
     * and report later entries as ignored diagnostics, matching upstream's
     * duplicate source/destination log boundary without mutating either side.
     * Callers that opt into directories get upstream's DirEntry partition too:
     * a file and directory with the same remote are matched separately and are
     * not treated as duplicates.
     *
     * @return array{
     *     matches: list<array{source: ObjectInfo, destination: ObjectInfo}>,
     *     sourceOnly: list<ObjectInfo>,
     *     destinationOnly: list<ObjectInfo>,
     *     duplicateSources: list<array{path: string, type: string, kept: ObjectInfo, ignored: ObjectInfo, message: string}>,
     *     duplicateDestinations: list<array{path: string, type: string, kept: ObjectInfo, ignored: ObjectInfo, message: string}>
     * }
     */
    public function matchListingDiagnostics(
        MemoryProvider $source,
        MemoryProvider $target,
        ?FilterRuleSet $filter = null,
        bool $ignoreCaseSync = false,
        bool $includeDirectories = false,
    ): array {
        $sourceListing = $this->diagnosticListing($source, $filter, $ignoreCaseSync, 'source', $includeDirectories);
        $targetListing = $this->diagnosticListing($target, $filter, $ignoreCaseSync, 'destination', $includeDirectories);

        return $this->mergeDiagnosticListings($sourceListing, $targetListing);
    }

    /**
     * Model matchListings when callers already have source/destination entries
     * in upstream channel order. Unlike provider-backed diagnostics, this path
     * does not sort before matching: a decreasing key raises the same guard as
     * upstream's "Out of order listing" panic, surfaced as a PHP exception.
     *
     * @param list<ObjectInfo> $sourceEntries
     * @param list<ObjectInfo> $targetEntries
     * @return array{
     *     matches: list<array{source: ObjectInfo, destination: ObjectInfo}>,
     *     sourceOnly: list<ObjectInfo>,
     *     destinationOnly: list<ObjectInfo>,
     *     duplicateSources: list<array{path: string, type: string, kept: ObjectInfo, ignored: ObjectInfo, message: string}>,
     *     duplicateDestinations: list<array{path: string, type: string, kept: ObjectInfo, ignored: ObjectInfo, message: string}>
     * }
     */
    public function matchListingDiagnosticsFromEntries(
        array $sourceEntries,
        array $targetEntries,
        bool $ignoreCaseSync = false,
    ): array {
        $sourceListing = $this->diagnosticListingFromOrderedEntries($sourceEntries, $ignoreCaseSync, 'source');
        $targetListing = $this->diagnosticListingFromOrderedEntries($targetEntries, $ignoreCaseSync, 'destination');

        return $this->mergeDiagnosticListings($sourceListing, $targetListing);
    }

    /**
     * @param array{
     *     paths: array<string, ObjectInfo>,
     *     order: array<string, array{pathKey: string, type: string}>,
     *     duplicates: list<array{path: string, type: string, kept: ObjectInfo, ignored: ObjectInfo, message: string}>
     * } $sourceListing
     * @param array{
     *     paths: array<string, ObjectInfo>,
     *     order: array<string, array{pathKey: string, type: string}>,
     *     duplicates: list<array{path: string, type: string, kept: ObjectInfo, ignored: ObjectInfo, message: string}>
     * } $targetListing
     * @return array{
     *     matches: list<array{source: ObjectInfo, destination: ObjectInfo}>,
     *     sourceOnly: list<ObjectInfo>,
     *     destinationOnly: list<ObjectInfo>,
     *     duplicateSources: list<array{path: string, type: string, kept: ObjectInfo, ignored: ObjectInfo, message: string}>,
     *     duplicateDestinations: list<array{path: string, type: string, kept: ObjectInfo, ignored: ObjectInfo, message: string}>
     * }
     */
    private function mergeDiagnosticListings(array $sourceListing, array $targetListing): array
    {
        $sourcePaths = $sourceListing['paths'];
        $targetPaths = $targetListing['paths'];
        $entryOrder = $sourceListing['order'] + $targetListing['order'];
        $allPaths = array_keys($sourcePaths + $targetPaths);
        usort(
            $allPaths,
            static fn (string $left, string $right): int => $entryOrder[$left]['pathKey'] <=> $entryOrder[$right]['pathKey']
                ?: $entryOrder[$left]['type'] <=> $entryOrder[$right]['type'],
        );

        $matches = [];
        $sourceOnly = [];
        $destinationOnly = [];
        foreach ($allPaths as $path) {
            $sourceInfo = $sourcePaths[$path] ?? null;
            $targetInfo = $targetPaths[$path] ?? null;
            if ($sourceInfo !== null && $targetInfo !== null) {
                $matches[] = [
                    'source' => $sourceInfo,
                    'destination' => $targetInfo,
                ];
                continue;
            }
            if ($sourceInfo !== null) {
                $sourceOnly[] = $sourceInfo;
                continue;
            }
            if ($targetInfo !== null) {
                $destinationOnly[] = $targetInfo;
            }
        }

        return [
            'matches' => $matches,
            'sourceOnly' => $sourceOnly,
            'destinationOnly' => $destinationOnly,
            'duplicateSources' => $sourceListing['duplicates'],
            'duplicateDestinations' => $targetListing['duplicates'],
        ];
    }

    public function check(MemoryProvider $source, MemoryProvider $target, bool $oneWay = false, ?FilterRuleSet $filter = null): CheckResult
    {
        $sourcePaths = $this->listedPaths($source, $filter);
        $targetPaths = $this->listedPaths($target, $filter);
        $allPaths = array_keys($sourcePaths + $targetPaths);
        sort($allPaths, SORT_STRING);

        $matches = [];
        $differ = [];
        $missingOnSource = [];
        $missingOnTarget = [];

        foreach ($allPaths as $path) {
            $sourceHas = isset($sourcePaths[$path]);
            $targetHas = isset($targetPaths[$path]);

            if (!$sourceHas) {
                if (!$oneWay) {
                    $missingOnSource[] = $path;
                }
                continue;
            }

            if (!$targetHas) {
                $missingOnTarget[] = $path;
                continue;
            }

            $sourceInfo = $sourcePaths[$path];
            $targetInfo = $targetPaths[$path];
            if ($sourceInfo->size !== $targetInfo->size || $sourceInfo->sha256 !== $targetInfo->sha256) {
                $differ[] = $path;
            } else {
                $matches[] = $path;
            }
        }

        return new CheckResult($matches, $differ, $missingOnSource, $missingOnTarget);
    }

    public function checkDownload(MemoryProvider $source, MemoryProvider $target, bool $oneWay = false, ?FilterRuleSet $filter = null): CheckResult
    {
        $sourcePaths = $this->listedPaths($source, $filter);
        $targetPaths = $this->listedPaths($target, $filter);
        $allPaths = array_keys($sourcePaths + $targetPaths);
        sort($allPaths, SORT_STRING);

        $matches = [];
        $differ = [];
        $missingOnSource = [];
        $missingOnTarget = [];
        $errors = [];
        $errorMessages = [];

        foreach ($allPaths as $path) {
            $sourceHas = isset($sourcePaths[$path]);
            $targetHas = isset($targetPaths[$path]);

            if (!$sourceHas) {
                if (!$oneWay) {
                    $missingOnSource[] = $path;
                }
                continue;
            }

            if (!$targetHas) {
                $missingOnTarget[] = $path;
                continue;
            }

            $sourceInfo = $sourcePaths[$path];
            $targetInfo = $targetPaths[$path];
            if ($sourceInfo->size !== $targetInfo->size) {
                $differ[] = $path;
                continue;
            }

            $comparison = $this->downloadComparison($source, $target, $path);
            if ($comparison->error !== null) {
                $errors[] = $path;
                $errorMessages[$path] = 'failed to download: ' . $comparison->error->getMessage();
                continue;
            }
            if (!$comparison->equal) {
                $differ[] = $path;
                continue;
            }

            $matches[] = $path;
        }

        return new CheckResult($matches, $differ, $missingOnSource, $missingOnTarget, $errors, $errorMessages);
    }

    /**
     * Model operations.Cat over listed provider objects.
     *
     * Upstream appends the separator after every emitted object, including the
     * final one. A negative offset is resolved against the known object size.
     *
     * @param array{listed: int, opened: int, bytes: int, separators: int, discard: bool}|null $stats
     */
    public function cat(
        MemoryProvider $provider,
        string $prefix = '',
        int $offset = 0,
        int $count = -1,
        string $separator = '',
        ?FilterRuleSet $filter = null,
        bool $discard = false,
        ?array &$stats = null,
    ): string {
        $output = '';
        $listed = 0;
        $opened = 0;
        $bytes = 0;
        $separators = 0;

        foreach ($provider->list($prefix) as $info) {
            if ($filter !== null && !$filter->includes($info->path)) {
                continue;
            }

            $listed++;
            $start = $offset;
            if ($start < 0 && $info->size >= 0) {
                $start += $info->size;
            }
            if ($start < 0) {
                $start = 0;
            }
            $length = $count >= 0 ? $count : null;

            $chunk = $this->readProviderObject($provider, $info->path, $start, $length);
            $opened++;
            $bytes += strlen($chunk);
            if (!$discard) {
                $output .= $chunk;
            }

            if ($separator !== '') {
                $separators++;
                if (!$discard) {
                    $output .= $separator;
                }
            }
        }

        $stats = [
            'listed' => $listed,
            'opened' => $opened,
            'bytes' => $bytes,
            'separators' => $separators,
            'discard' => $discard,
        ];

        return $discard ? '' : $output;
    }

    /**
     * Model cmd/cat flag resolution for head, tail, offset, count, discard,
     * and separator before delegating to operations.Cat.
     *
     * @param array{listed: int, opened: int, bytes: int, separators: int, discard: bool}|null $stats
     */
    public function catCommand(
        MemoryProvider $provider,
        string $prefix = '',
        int $head = 0,
        int $tail = 0,
        int $offset = 0,
        int $count = -1,
        string $separator = '',
        ?FilterRuleSet $filter = null,
        bool $discard = false,
        ?array &$stats = null,
    ): string {
        $usedOffset = $offset !== 0 || $count >= 0;
        $usedHead = $head > 0;
        $usedTail = $tail > 0;
        if (($usedHead && $usedTail) || ($usedHead && $usedOffset) || ($usedTail && $usedOffset)) {
            throw new \RuntimeException('Can only use one of  --head, --tail or --offset with --count');
        }

        if ($usedHead) {
            $offset = 0;
            $count = $head;
        }
        if ($usedTail) {
            $offset = -$tail;
            $count = -1;
        }

        return $this->cat($provider, $prefix, $offset, $count, $separator, $filter, $discard, $stats);
    }

    /**
     * Model operations.Rcat for unknown-size stdin uploads.
     *
     * @param string|resource|object $input
     * @param array<string, string> $metadata
     * @param array{path: string, sizeHint: int, bytesRead: int, uploadMode: string, smallUpload: bool, checksumType: string, checksumIgnored: bool}|null $stats
     */
    public function rcat(
        MemoryProvider $target,
        string $path,
        mixed $input,
        \DateTimeInterface|string|null $modTime = null,
        array $metadata = [],
        int $streamingUploadCutoff = 256 * 1024,
        bool $ignoreChecksum = false,
        ?array &$stats = null,
    ): ObjectInfo {
        return $this->rcatSize(
            $target,
            $path,
            $input,
            -1,
            $modTime,
            $metadata,
            $streamingUploadCutoff,
            $ignoreChecksum,
            $stats,
        );
    }

    /**
     * Model operations.RcatSize: known sizes use Put, unknown sizes delegate
     * to Rcat's small-buffer-or-streaming decision.
     *
     * @param string|resource|object $input
     * @param array<string, string> $metadata
     * @param array{path: string, sizeHint: int, bytesRead: int, uploadMode: string, smallUpload: bool, checksumType: string, checksumIgnored: bool}|null $stats
     */
    public function rcatSize(
        MemoryProvider $target,
        string $path,
        mixed $input,
        int $size,
        \DateTimeInterface|string|null $modTime = null,
        array $metadata = [],
        int $streamingUploadCutoff = 256 * 1024,
        bool $ignoreChecksum = false,
        ?array &$stats = null,
    ): ObjectInfo {
        $bytes = $this->readUploadInput($input);
        $hashType = $ignoreChecksum ? HashType::NONE : $target->supportedHashes()->getOne();
        $hashes = $hashType === HashType::NONE ? [] : MultiHasher::hashBytes($bytes, new HashSet($hashType));

        if ($size >= 0) {
            $stats = [
                'path' => $path,
                'sizeHint' => $size,
                'bytesRead' => strlen($bytes),
                'uploadMode' => 'put',
                'smallUpload' => true,
                'checksumType' => HashType::NONE,
                'checksumIgnored' => $ignoreChecksum,
            ];

            return $target->put($path, $bytes, [
                'modTime' => $modTime,
                'metadata' => $metadata,
            ]);
        }

        $smallUpload = $streamingUploadCutoff > 0 && strlen($bytes) < $streamingUploadCutoff;
        $uploadMode = $smallUpload ? 'put' : 'putStream';
        $options = [
            'modTime' => $modTime,
            'metadata' => $metadata,
            'hashes' => $hashes,
        ];
        $object = $smallUpload
            ? $target->put($path, $bytes, $options)
            : $target->putStream($path, $bytes, $options);

        $stats = [
            'path' => $path,
            'sizeHint' => -1,
            'bytesRead' => strlen($bytes),
            'uploadMode' => $uploadMode,
            'smallUpload' => $smallUpload,
            'checksumType' => $hashType,
            'checksumIgnored' => $ignoreChecksum,
        ];

        return $object;
    }

    /**
     * Model operations.CopyURL with a local response fixture instead of live
     * HTTP. The response array represents the completed HTTP request after
     * redirects; callers can assert request headers through the stats output.
     *
     * @param array{
     *     url: string,
     *     finalUrl?: string,
     *     status?: int,
     *     statusText?: string,
     *     headers?: array<string, string>,
     *     body?: string|resource|object,
     *     contentLength?: int,
     *     onRequest?: callable(array<string, string>): void
     * } $response
     * @param array<string, string> $downloadHeaders
     * @param array<string, mixed>|null $stats
     */
    public function copyUrl(
        MemoryProvider $target,
        string $dstFileName,
        array $response,
        bool $autoFilename = false,
        bool $headerFilename = false,
        bool $noClobber = false,
        array $downloadHeaders = [],
        int $streamingUploadCutoff = 256 * 1024,
        ?array &$stats = null,
    ): ObjectInfo {
        $source = $this->resolveCopyUrlSource($dstFileName, $response, $autoFilename, $headerFilename, $downloadHeaders);
        if ($noClobber && $target->pathExists($source['dstFileName'])) {
            $this->closeCopyUrlBody($source['body']);
            $stats = $source['stats'] + [
                'noClobber' => true,
                'uploadMode' => null,
                'skipped' => true,
            ];
            throw new \RuntimeException('CopyURL failed: file already exist');
        }

        $uploadStats = null;
        $object = $this->rcatSize(
            $target,
            $source['dstFileName'],
            $source['body'],
            $source['contentLength'],
            $source['modTime'],
            [],
            $streamingUploadCutoff,
            false,
            $uploadStats,
        );

        $stats = $source['stats'] + [
            'noClobber' => $noClobber,
            'uploadMode' => $uploadStats['uploadMode'] ?? null,
            'bytesRead' => $uploadStats['bytesRead'] ?? $object->size,
            'skipped' => false,
        ];

        return $object;
    }

    /**
     * Model operations.CopyURLToWriter.
     *
     * @param array{
     *     url: string,
     *     finalUrl?: string,
     *     status?: int,
     *     statusText?: string,
     *     headers?: array<string, string>,
     *     body?: string|resource|object,
     *     contentLength?: int,
     *     onRequest?: callable(array<string, string>): void
     * } $response
     * @param array<string, string> $downloadHeaders
     * @param array<string, mixed>|null $stats
     */
    public function copyUrlToWriter(array $response, array $downloadHeaders = [], ?array &$stats = null): string
    {
        $source = $this->resolveCopyUrlSource('', $response, false, false, $downloadHeaders);
        $output = $this->readUploadInput($source['body']);
        $stats = $source['stats'] + [
            'bytesRead' => strlen($output),
            'stdout' => true,
        ];

        return $output;
    }

    /**
     * Model cmd/copyurl single-URL flag resolution.
     *
     * @param array{
     *     url: string,
     *     finalUrl?: string,
     *     status?: int,
     *     statusText?: string,
     *     headers?: array<string, string>,
     *     body?: string|resource|object,
     *     contentLength?: int,
     *     onRequest?: callable(array<string, string>): void
     * } $response
     * @param array{
     *     autoFilename?: bool,
     *     headerFilename?: bool,
     *     printFilename?: bool,
     *     stdout?: bool,
     *     noClobber?: bool,
     *     downloadHeaders?: array<string, string>,
     *     streamingUploadCutoff?: int
     * } $options
     * @return array{object: ?ObjectInfo, stdout: string, printedFilename: ?string, stats: array<string, mixed>}
     */
    public function copyUrlCommand(
        MemoryProvider $target,
        array $response,
        ?string $destination = null,
        array $options = [],
    ): array {
        $stdout = (bool) ($options['stdout'] ?? false) || $destination === '-';
        if (!$stdout && ($destination === null || $destination === '')) {
            throw new \RuntimeException('need 2 arguments if not using --stdout');
        }

        $downloadHeaders = $options['downloadHeaders'] ?? [];
        if ($stdout) {
            $stats = null;
            $output = $this->copyUrlToWriter($response, $downloadHeaders, $stats);

            return [
                'object' => null,
                'stdout' => $output,
                'printedFilename' => null,
                'stats' => $stats,
            ];
        }

        $autoFilename = (bool) ($options['autoFilename'] ?? false);
        $headerFilename = (bool) ($options['headerFilename'] ?? false);
        $dstFileName = self::normalizePath($destination);
        $filenameSource = 'argument';
        if ($autoFilename) {
            $resolved = $this->copyUrlResolvedFilename('', $response, true, $headerFilename);
            $dstFileName = self::joinPath($dstFileName, $resolved['dstFileName']);
            $filenameSource = $resolved['source'];
        }

        $stats = null;
        $object = $this->copyUrl(
            $target,
            $dstFileName,
            $response,
            false,
            false,
            (bool) ($options['noClobber'] ?? false),
            $downloadHeaders,
            (int) ($options['streamingUploadCutoff'] ?? 256 * 1024),
            $stats,
        );
        $stats['autoFilename'] = $autoFilename;
        $stats['headerFilename'] = $headerFilename;
        $stats['filenameSource'] = $filenameSource;

        return [
            'object' => $object,
            'stdout' => '',
            'printedFilename' => (bool) ($options['printFilename'] ?? false) ? $object->path : null,
            'stats' => $stats,
        ];
    }

    /**
     * Model `copyurl --urls` CSV processing. Missing filenames use the same
     * auto-filename path as upstream, while per-row errors are aggregated.
     *
     * @param array<string, array<string, mixed>> $responsesByUrl
     * @param array{headerFilename?: bool, noClobber?: bool, downloadHeaders?: array<string, string>, stdout?: bool, printFilename?: bool} $options
     * @return array{objects: list<ObjectInfo>, errors: list<string>, stats: list<array<string, mixed>>}
     */
    public function copyUrlsCsvCommand(
        MemoryProvider $target,
        string $csv,
        array $responsesByUrl,
        string $destinationPrefix = '',
        array $options = [],
    ): array {
        if ((bool) ($options['stdout'] ?? false)) {
            throw new \RuntimeException("can't use --stdout with --urls");
        }
        if ((bool) ($options['printFilename'] ?? false)) {
            throw new \RuntimeException("can't use --print-filename with --urls");
        }

        $objects = [];
        $errors = [];
        $allStats = [];
        foreach ($this->parseCopyUrlCsv($csv) as $row) {
            if ($row === []) {
                continue;
            }

            $url = $row[0];
            $filename = $row[1] ?? '';
            $response = $responsesByUrl[$url] ?? ['url' => $url, 'status' => 404, 'statusText' => '404 Not Found', 'body' => ''];
            try {
                if ($filename === '') {
                    $command = $this->copyUrlCommand(
                        $target,
                        $response,
                        $destinationPrefix,
                        [
                            'autoFilename' => true,
                            'headerFilename' => (bool) ($options['headerFilename'] ?? false),
                            'noClobber' => (bool) ($options['noClobber'] ?? false),
                            'downloadHeaders' => $options['downloadHeaders'] ?? [],
                        ],
                    );
                    $objects[] = $command['object'];
                    $allStats[] = $command['stats'];
                    continue;
                }

                $stats = null;
                $objects[] = $this->copyUrl(
                    $target,
                    self::joinPath($destinationPrefix, $filename),
                    $response,
                    false,
                    false,
                    (bool) ($options['noClobber'] ?? false),
                    $options['downloadHeaders'] ?? [],
                    stats: $stats,
                );
                $allStats[] = $stats;
            } catch (\RuntimeException $throwable) {
                $errors[] = sprintf('failed to copy URL "%s": %s', $url, $throwable->getMessage());
            }
        }

        if ($errors !== []) {
            throw new \RuntimeException('not all URLs copied successfully: ' . implode('; ', $errors));
        }

        return [
            'objects' => $objects,
            'errors' => [],
            'stats' => $allStats,
        ];
    }

    /**
     * Model cmd/touch flag resolution before delegating to Touch.
     *
     * @param array{
     *     timestamp?: string|null,
     *     localTime?: bool,
     *     noCreate?: bool,
     *     recursive?: bool,
     *     dryRun?: bool,
     *     metadataSet?: array<string, scalar|null>,
     *     filter?: FilterRuleSet|null,
     *     now?: \DateTimeInterface|string|null
     * } $options
     * @param array<string, mixed>|null $stats
     * @return array{created: ?ObjectInfo, touched: list<ObjectInfo>, skipped: bool, directory: bool, time: string}
     */
    public function touchCommand(
        MemoryProvider $provider,
        string $remote,
        array $options = [],
        ?array &$stats = null,
    ): array {
        $time = $this->touchTimeFromOptions(
            $options['timestamp'] ?? null,
            (bool) ($options['localTime'] ?? false),
            $options['now'] ?? null,
        );

        return $this->touch(
            $provider,
            $remote,
            $time,
            (bool) ($options['noCreate'] ?? false),
            (bool) ($options['recursive'] ?? false),
            (bool) ($options['dryRun'] ?? false),
            $this->normalizeTouchMetadata($options['metadataSet'] ?? []),
            $options['filter'] ?? null,
            $stats,
        );
    }

    /**
     * Model cmd/touch and operations.TouchDir for one in-memory provider.
     *
     * Missing paths create empty files unless --no-create or --recursive is
     * active. Existing directories touch listed files only; non-recursive mode
     * touches direct child files while recursive mode walks the whole subtree.
     * Directory per-object SetModTime failures are counted and logged by
     * upstream without aborting the walk, so this method records them in stats
     * and keeps processing later objects.
     *
     * @param array<string, string> $metadata
     * @param array<string, mixed>|null $stats
     * @return array{created: ?ObjectInfo, touched: list<ObjectInfo>, skipped: bool, directory: bool, time: string}
     */
    public function touch(
        MemoryProvider $provider,
        string $remote,
        \DateTimeInterface|string|null $time = null,
        bool $noCreate = false,
        bool $recursive = false,
        bool $dryRun = false,
        array $metadata = [],
        ?FilterRuleSet $filter = null,
        ?array &$stats = null,
    ): array {
        $this->initTouchStats($stats);
        $remote = self::normalizePath($remote);
        $touchTime = $this->normalizeTouchTime($time ?? new \DateTimeImmutable('now', new \DateTimeZone('UTC')));
        $stats['remote'] = $remote;
        $stats['time'] = $touchTime;
        $stats['recursive'] = $recursive;

        if ($remote !== '') {
            $object = $this->optionalInfo($provider, $remote);
            if ($object !== null) {
                return $this->touchOneObject($provider, $object, $touchTime, $dryRun, $stats);
            }
        }

        if ($remote === '' || $this->directoryExists($provider, $remote)) {
            $stats['directory'] = true;
            $touched = $this->touchDirectory($provider, $remote, $touchTime, $recursive, $dryRun, $filter, $stats);

            return [
                'created' => null,
                'touched' => $touched,
                'skipped' => false,
                'directory' => true,
                'time' => $touchTime,
            ];
        }

        if ($noCreate || $recursive) {
            $stats['notCreated']++;

            return [
                'created' => null,
                'touched' => [],
                'skipped' => true,
                'directory' => false,
                'time' => $touchTime,
            ];
        }

        if ($dryRun) {
            $stats['dryRunSkipped']++;

            return [
                'created' => null,
                'touched' => [],
                'skipped' => true,
                'directory' => false,
                'time' => $touchTime,
            ];
        }

        $created = $provider->put($remote, '', [
            'modTime' => $touchTime,
            'metadata' => $metadata,
        ]);
        $stats['created']++;

        return [
            'created' => $created,
            'touched' => [],
            'skipped' => false,
            'directory' => false,
            'time' => $touchTime,
        ];
    }

    /**
     * Model operations.SetTier/ListFn over listed provider objects.
     *
     * @return list<ObjectInfo>
     */
    public function setTier(MemoryProvider $provider, string $tier, ?FilterRuleSet $filter = null): array
    {
        if (!$provider->supportsSetTier()) {
            throw new \RuntimeException('remote does not support settier');
        }

        $updated = [];
        foreach ($provider->list() as $info) {
            if ($filter !== null && !$filter->includes($info->path)) {
                continue;
            }

            $updated[] = $provider->setListedObjectTier($info, $tier);
        }

        return $updated;
    }

    public function setTierFile(MemoryProvider $provider, string $path, string $tier): ObjectInfo
    {
        if (!$provider->supportsSetTier()) {
            throw new \RuntimeException('remote does not support settier');
        }

        return $provider->setObjectTier($path, $tier);
    }

    /**
     * @return list<ObjectInfo>
     *
     * @param list<MemoryProvider> $compareDest
     * @param list<MemoryProvider> $copyDest
     * @param array{requested?: bool, enabled: bool, disabledReason?: ?string, noCheckDest: bool, targetListUsed: bool, targetLookups: list<string>, targetMatches: list<string>, targetMisses: list<string>, sourceOnlyDirectories: list<string>}|null $noTraverseStats
     */
    public function copyChanged(
        MemoryProvider $source,
        MemoryProvider $target,
        ?FilterRuleSet $filter = null,
        ?MemoryProvider $backup = null,
        string $backupPrefix = '',
        string $suffix = '',
        bool $suffixKeepExtension = false,
        array $compareDest = [],
        array $copyDest = [],
        bool $noCheckDest = false,
        bool $ignoreExisting = false,
        bool $immutable = false,
        bool $ignoreTimes = false,
        bool $updateOlder = false,
        bool $noUpdateModTime = false,
        int $modifyWindowSeconds = 1,
        bool $checksum = false,
        bool $refreshTimes = false,
        bool $fixCase = false,
        bool $ignoreCaseSync = false,
        bool $noTraverse = false,
        ?array &$noTraverseStats = null,
        ?string $syncDeleteMode = null,
        bool $trackRenamesForSync = false,
    ): array {
        if ($fixCase && !$noCheckDest) {
            $this->fixCase($source, $target, $filter, $immutable);
        }

        $copied = [];
        $noTraverseDisabledReason = $noTraverse
            ? $this->noTraverseDisabledReason($syncDeleteMode, $trackRenamesForSync)
            : null;
        $effectiveNoTraverse = $noTraverse && $noTraverseDisabledReason === null;
        if ($noTraverse) {
            $noTraverseStats = [
                'requested' => true,
                'enabled' => $effectiveNoTraverse,
                'disabledReason' => $noTraverseDisabledReason,
                'noCheckDest' => $noCheckDest,
                'targetListUsed' => !$effectiveNoTraverse && !$noCheckDest,
                'targetLookups' => [],
                'targetMatches' => [],
                'targetMisses' => [],
                'sourceOnlyDirectories' => $effectiveNoTraverse
                    ? $this->noTraverseSourceDirectories($source, $filter)
                    : [],
            ];
        } else {
            $noTraverseStats = null;
        }

        $targetPaths = $ignoreCaseSync && !$noCheckDest && !$effectiveNoTraverse
            ? $this->listedPaths($target, $filter, true)
            : [];
        $seenSourceKeys = [];
        foreach ($source->list() as $sourceInfo) {
            $path = $sourceInfo->path;
            if ($filter !== null && !$filter->includes($path)) {
                continue;
            }
            if ($this->skipMarchDuplicateObject($path, $seenSourceKeys, $ignoreCaseSync)) {
                continue;
            }

            $targetInfo = null;
            if (!$noCheckDest) {
                if ($effectiveNoTraverse) {
                    $targetInfo = $this->noTraverseTargetInfo($target, $path, $noTraverseStats);
                } elseif ($ignoreCaseSync) {
                    $targetInfo = $targetPaths[$this->syncPathKey($path)] ?? null;
                } else {
                    $targetInfo = $this->optionalInfo($target, $path);
                }
            }
            if (!$noCheckDest && $targetInfo !== null && $ignoreExisting) {
                continue;
            }

            if (!$this->needsTransfer(
                $source,
                $target,
                $sourceInfo,
                $targetInfo,
                $ignoreTimes,
                $updateOlder,
                $noUpdateModTime,
                $modifyWindowSeconds,
                $checksum,
                $immutable,
                $refreshTimes,
            )) {
                continue;
            }

            if ($this->findEqualReference($sourceInfo, $targetInfo, $compareDest) !== null) {
                continue;
            }

            $copyDestReference = $this->findEqualReference($sourceInfo, $targetInfo, $copyDest);
            if ($copyDestReference !== null) {
                $destinationPath = $targetInfo?->path ?? $path;
                if ($targetInfo !== null && $this->backupRequested($backup, $backupPrefix, $suffix)) {
                    $this->moveToBackup($target, $targetInfo->path, $backup, $backupPrefix, $suffix, $suffixKeepExtension);
                }
                $copied[] = $copyDestReference['provider']->copyTo($copyDestReference['path'], $target, $destinationPath);
                continue;
            }

            if (!$noCheckDest && $targetInfo !== null && $immutable) {
                throw new \RuntimeException('immutable file modified');
            }

            if ($this->backupRequested($backup, $backupPrefix, $suffix)) {
                if ($targetInfo !== null) {
                    $this->moveToBackup($target, $targetInfo->path, $backup, $backupPrefix, $suffix, $suffixKeepExtension);
                    $targetInfo = null;
                }
            }
            $copied[] = $source->copyTo($path, $target, $targetInfo?->path ?? $path);
        }

        return $copied;
    }

    /**
     * Model the sync-level delete mode orchestration around copyChanged.
     *
     * Upstream --delete-before runs an initial delete-only traversal pass, then
     * a copy-only pass. That second pass may use --no-traverse because delete
     * traversal is no longer active.
     *
     * @return array{copied: list<ObjectInfo>, deleted: list<ObjectInfo>, prunedDirectories: list<ObjectInfo>, deleteMode: string, deletePassNoTraverse: ?array<string, mixed>, deletePassPrunedDirectories: list<ObjectInfo>}
     */
    public function syncWithDeleteMode(
        MemoryProvider $source,
        MemoryProvider $target,
        ?FilterRuleSet $filter = null,
        string $deleteMode = DeleteMode::DEFAULT,
        bool $deleteExcluded = false,
        bool $noTraverse = false,
        ?array &$noTraverseStats = null,
        bool $ignoreCaseSync = false,
        ?MemoryProvider $backup = null,
        string $backupPrefix = '',
        string $suffix = '',
        bool $suffixKeepExtension = false,
        ?int $maxDelete = null,
        ?int $maxDeleteSize = null,
    ): array {
        $deleteMode = DeleteMode::normalize($deleteMode);
        $deleted = [];
        $prunedDirectories = [];
        $deletePassPrunedDirectories = [];
        $deletePassNoTraverse = null;

        if ($deleteMode === DeleteMode::ONLY) {
            if ($noTraverse) {
                $deletePassNoTraverse = $this->disabledNoTraverseStats(DeleteMode::ONLY);
            }
            $noTraverseStats = null;
            $deletePassDirectoryCandidates = $this->destinationOnlyDirectoryCandidates(
                $source,
                $target,
                $filter,
                $deleteExcluded,
                $ignoreCaseSync,
            );
            $deleted = $this->deleteDestinationOnly(
                $source,
                $target,
                $filter,
                DeleteMode::ONLY,
                $deleteExcluded,
                maxDelete: $maxDelete,
                maxDeleteSize: $maxDeleteSize,
                backup: $backup,
                backupPrefix: $backupPrefix,
                suffix: $suffix,
                suffixKeepExtension: $suffixKeepExtension,
                ignoreCaseSync: $ignoreCaseSync,
            );
            $prunedDirectories = $this->pruneEmptyDirectoryCandidates(
                $target,
                $deletePassDirectoryCandidates,
                $backupPrefix,
            );

            return [
                'copied' => [],
                'deleted' => $deleted,
                'prunedDirectories' => $prunedDirectories,
                'deleteMode' => $deleteMode,
                'deletePassNoTraverse' => $deletePassNoTraverse,
                'deletePassPrunedDirectories' => $deletePassPrunedDirectories,
            ];
        }

        $copyDeleteMode = $deleteMode;
        if ($deleteMode === DeleteMode::BEFORE) {
            if ($noTraverse) {
                $deletePassNoTraverse = $this->disabledNoTraverseStats(DeleteMode::ONLY);
            }
            $deletePassDirectoryCandidates = $this->destinationOnlyDirectoryCandidates(
                $source,
                $target,
                $filter,
                $deleteExcluded,
                $ignoreCaseSync,
            );
            $deleted = $this->deleteDestinationOnly(
                $source,
                $target,
                $filter,
                DeleteMode::ONLY,
                $deleteExcluded,
                maxDelete: $maxDelete,
                maxDeleteSize: $maxDeleteSize,
                backup: $backup,
                backupPrefix: $backupPrefix,
                suffix: $suffix,
                suffixKeepExtension: $suffixKeepExtension,
                ignoreCaseSync: $ignoreCaseSync,
            );
            $deletePassPrunedDirectories = $this->pruneEmptyDirectoryCandidates(
                $target,
                $deletePassDirectoryCandidates,
                $backupPrefix,
            );
        }

        $copied = $this->copyChanged(
            $source,
            $target,
            $filter,
            backup: $backup,
            backupPrefix: $backupPrefix,
            suffix: $suffix,
            suffixKeepExtension: $suffixKeepExtension,
            ignoreCaseSync: $ignoreCaseSync,
            noTraverse: $noTraverse,
            noTraverseStats: $noTraverseStats,
            syncDeleteMode: $copyDeleteMode,
        );

        if ($deleteMode !== DeleteMode::OFF && $deleteMode !== DeleteMode::BEFORE) {
            $directoryCandidates = $this->destinationOnlyDirectoryCandidates(
                $source,
                $target,
                $filter,
                $deleteExcluded,
                $ignoreCaseSync,
            );
            $deleted = $this->deleteDestinationOnly(
                $source,
                $target,
                $filter,
                $deleteMode,
                $deleteExcluded,
                maxDelete: $maxDelete,
                maxDeleteSize: $maxDeleteSize,
                backup: $backup,
                backupPrefix: $backupPrefix,
                suffix: $suffix,
                suffixKeepExtension: $suffixKeepExtension,
                ignoreCaseSync: $ignoreCaseSync,
            );
            $prunedDirectories = $this->pruneEmptyDirectoryCandidates(
                $target,
                $directoryCandidates,
                $backupPrefix,
            );
        }

        return [
            'copied' => $copied,
            'deleted' => $deleted,
            'prunedDirectories' => $prunedDirectories,
            'deleteMode' => $deleteMode,
            'deletePassNoTraverse' => $deletePassNoTraverse,
            'deletePassPrunedDirectories' => $deletePassPrunedDirectories,
        ];
    }

    /**
     * @return array{renamed: list<ObjectInfo>, copied: list<ObjectInfo>, deleted: list<ObjectInfo>, trackRenamesEnabled: bool, disabledReason: ?string}
     */
    public function syncWithTrackRenames(
        MemoryProvider $source,
        MemoryProvider $target,
        ?FilterRuleSet $filter = null,
        string $trackRenamesStrategy = 'hash',
        ?MemoryProvider $backup = null,
        string $backupPrefix = '',
        string $suffix = '',
        bool $suffixKeepExtension = false,
        ?int $maxDelete = null,
        ?int $maxDeleteSize = null,
        int $modifyWindowSeconds = 1,
        bool $ignoreCaseSync = false,
        string $deleteMode = DeleteMode::DEFAULT,
        bool $noTraverse = false,
        ?array &$noTraverseStats = null,
    ): array {
        $deleteMode = DeleteMode::normalize($deleteMode);
        if ($deleteMode === DeleteMode::BEFORE) {
            throw new \RuntimeException("can't use --delete-before with --track-renames");
        }

        if ($noTraverse) {
            $noTraverseStats = [
                'requested' => true,
                'enabled' => false,
                'disabledReason' => $this->noTraverseDisabledReason($deleteMode, true),
                'noCheckDest' => false,
                'targetListUsed' => true,
                'targetLookups' => [],
                'targetMatches' => [],
                'targetMisses' => [],
                'sourceOnlyDirectories' => [],
            ];
        } else {
            $noTraverseStats = null;
        }

        $strategy = TrackRenamesStrategy::parse($trackRenamesStrategy);
        $disabledReason = $this->trackRenamesDisabledReason($source, $target, $strategy, $deleteMode);
        if ($disabledReason !== null) {
            $copied = $this->copyChanged(
                $source,
                $target,
                $filter,
                backup: $backup,
                backupPrefix: $backupPrefix,
                suffix: $suffix,
                suffixKeepExtension: $suffixKeepExtension,
                ignoreCaseSync: $ignoreCaseSync,
                noTraverse: $noTraverse,
                noTraverseStats: $noTraverseStats,
                syncDeleteMode: $deleteMode,
            );
            $deleted = $deleteMode === DeleteMode::OFF
                ? []
                : $this->deleteDestinationOnly(
                    $source,
                    $target,
                    $filter,
                    $deleteMode,
                    maxDelete: $maxDelete,
                    maxDeleteSize: $maxDeleteSize,
                    backup: $backup,
                    backupPrefix: $backupPrefix,
                    suffix: $suffix,
                    suffixKeepExtension: $suffixKeepExtension,
                    ignoreCaseSync: $ignoreCaseSync,
                );

            return [
                'renamed' => [],
                'copied' => $copied,
                'deleted' => $deleted,
                'trackRenamesEnabled' => false,
                'disabledReason' => $disabledReason,
            ];
        }

        $sourcePaths = $this->listedPaths($source, $filter, $ignoreCaseSync);
        $targetPaths = $this->listedPaths($target, $filter, $ignoreCaseSync);
        $commonHash = $this->commonHashType($source, $target);

        $sourceOnly = [];
        $targetOnly = [];
        $copied = [];
        $renamed = [];
        foreach ($sourcePaths as $sourceKey => $sourceInfo) {
            $targetInfo = $targetPaths[$sourceKey] ?? null;
            if ($targetInfo === null) {
                $sourceOnly[] = $sourceInfo;
                continue;
            }

            if (!$this->needsTransfer(
                $source,
                $target,
                $sourceInfo,
                $targetInfo,
                false,
                false,
                false,
                $modifyWindowSeconds,
                false,
                false,
                false,
            )) {
                continue;
            }

            if ($this->backupRequested($backup, $backupPrefix, $suffix)) {
                $this->moveToBackup($target, $targetInfo->path, $backup, $backupPrefix, $suffix, $suffixKeepExtension);
                $targetInfo = null;
            }
            $copied[] = $source->copyTo($sourceInfo->path, $target, $targetInfo?->path ?? $sourceInfo->path);
        }

        foreach ($targetPaths as $targetKey => $targetInfo) {
            if (isset($sourcePaths[$targetKey])) {
                continue;
            }
            if ($backupPrefix !== '' && self::pathUnderPrefix($targetInfo->path, $backupPrefix)) {
                continue;
            }
            $targetOnly[$targetInfo->path] = $targetInfo;
        }

        $renameMap = $this->buildRenameMap($sourceOnly, array_values($targetOnly), $target, $strategy, $commonHash);
        foreach ($sourceOnly as $sourceInfo) {
            $renameId = $this->trackRenameId($source, $sourceInfo, $strategy, $commonHash);
            $renameSource = $this->popRenameCandidate($renameMap, $renameId, $sourceInfo, $strategy, $modifyWindowSeconds);
            if ($renameSource !== null) {
                try {
                    $renamed[] = $target->serverSideMoveTo($renameSource->path, $target, $sourceInfo->path);
                    unset($targetOnly[$renameSource->path]);
                    continue;
                } catch (\RuntimeException) {
                    // Upstream tryRename logs the failed server-side rename and lets the
                    // normal upload/delete-after path handle the source and stale target.
                }
            }

            $copied[] = $source->copyTo($sourceInfo->path, $target, $sourceInfo->path);
        }

        ksort($targetOnly, SORT_STRING);
        $deleteCount = 0;
        $deleteBytes = 0;
        $deleted = [];
        foreach (array_keys($targetOnly) as $path) {
            $targetInfo = $target->info($path);
            $deleteSize = max(0, $targetInfo->size);
            $this->assertDeleteWithinLimits($deleteCount, $deleteBytes, $deleteSize, $maxDelete, $maxDeleteSize);
            $deleteCount++;
            $deleteBytes += $deleteSize;
            if ($this->backupRequested($backup, $backupPrefix, $suffix)) {
                $deleted[] = $this->moveToBackup($target, $path, $backup, $backupPrefix, $suffix, $suffixKeepExtension);
            } else {
                $deleted[] = $target->delete($path);
            }
        }

        return [
            'renamed' => $renamed,
            'copied' => $copied,
            'deleted' => $deleted,
            'trackRenamesEnabled' => true,
            'disabledReason' => null,
        ];
    }

    /**
     * @param array{
     *     backup?: MemoryProvider|null,
     *     backupPrefix?: string,
     *     suffix?: string,
     *     suffixKeepExtension?: bool,
     *     compareDest?: list<MemoryProvider>,
     *     copyDest?: list<MemoryProvider>,
     *     noCheckDest?: bool,
     *     ignoreExisting?: bool,
     *     immutable?: bool,
     *     ignoreTimes?: bool,
     *     updateOlder?: bool,
     *     noUpdateModTime?: bool,
     *     modifyWindowSeconds?: int,
     *     checksum?: bool,
     *     refreshTimes?: bool,
     *     partialUploads?: bool,
     *     partialSuffix?: string,
     *     simulatePartialTransferError?: bool,
     *     metadataSet?: array<string, scalar|null>,
     *     maxTransfer?: int,
     *     cutoffMode?: string,
     *     bytesTransferredSoFar?: int,
     *     dryRun?: bool,
     *     interactive?: bool,
     *     interactiveChoice?: mixed
     * } $options
     * @return array{copied: ?ObjectInfo, moved: ?ObjectInfo, deletedSource: ?ObjectInfo, backup: ?ObjectInfo, skipped: bool, caseInsensitiveMove: bool, partialPath: ?string, cleanedPartial: bool}
     */
    public function copyFile(
        MemoryProvider $destination,
        MemoryProvider $source,
        string $destinationPath,
        string $sourcePath,
        array $options = [],
    ): array {
        return $this->moveOrCopyFile($destination, $source, $destinationPath, $sourcePath, true, $options);
    }

    /**
     * @param array{
     *     backup?: MemoryProvider|null,
     *     backupPrefix?: string,
     *     suffix?: string,
     *     suffixKeepExtension?: bool,
     *     compareDest?: list<MemoryProvider>,
     *     copyDest?: list<MemoryProvider>,
     *     noCheckDest?: bool,
     *     ignoreExisting?: bool,
     *     immutable?: bool,
     *     ignoreTimes?: bool,
     *     updateOlder?: bool,
     *     noUpdateModTime?: bool,
     *     modifyWindowSeconds?: int,
     *     checksum?: bool,
     *     refreshTimes?: bool,
     *     partialUploads?: bool,
     *     partialSuffix?: string,
     *     simulatePartialTransferError?: bool,
     *     metadataSet?: array<string, scalar|null>,
     *     maxTransfer?: int,
     *     cutoffMode?: string,
     *     bytesTransferredSoFar?: int,
     *     dryRun?: bool,
     *     interactive?: bool,
     *     interactiveChoice?: mixed
     * } $options
     * @return array{copied: ?ObjectInfo, moved: ?ObjectInfo, deletedSource: ?ObjectInfo, backup: ?ObjectInfo, skipped: bool, caseInsensitiveMove: bool, partialPath: ?string, cleanedPartial: bool}
     */
    public function moveFile(
        MemoryProvider $destination,
        MemoryProvider $source,
        string $destinationPath,
        string $sourcePath,
        array $options = [],
    ): array {
        return $this->moveOrCopyFile($destination, $source, $destinationPath, $sourcePath, false, $options);
    }

    /**
     * Model cmd/copy argument dispatch.
     *
     * File sources are copied into the destination directory using the source
     * leaf name. Directory sources copy the contents of the directory, not the
     * directory name itself, matching upstream CopyDir.
     *
     * @param array{
     *     createEmptySrcDirs?: bool,
     *     filter?: FilterRuleSet|null,
     *     backup?: MemoryProvider|null,
     *     backupPrefix?: string,
     *     suffix?: string,
     *     suffixKeepExtension?: bool,
     *     compareDest?: list<MemoryProvider>,
     *     copyDest?: list<MemoryProvider>,
     *     noCheckDest?: bool,
     *     ignoreExisting?: bool,
     *     immutable?: bool,
     *     ignoreTimes?: bool,
     *     updateOlder?: bool,
     *     noUpdateModTime?: bool,
     *     modifyWindowSeconds?: int,
     *     checksum?: bool,
     *     refreshTimes?: bool,
     *     partialUploads?: bool,
     *     partialSuffix?: string,
     *     simulatePartialTransferError?: bool,
     *     metadataSet?: array<string, scalar|null>,
     *     maxTransfer?: int,
     *     cutoffMode?: string,
     *     bytesTransferredSoFar?: int,
     *     dryRun?: bool,
     *     interactive?: bool,
     *     interactiveChoice?: mixed
     * } $options
     * @param array<string, mixed>|null $stats
     * @return array{command: string, sourceType: string, destinationPath: ?string, file: ?array<string, mixed>, directory: ?array<string, mixed>}
     */
    public function copyCommand(
        MemoryProvider $destination,
        MemoryProvider $source,
        string $sourceRemote,
        string $destinationRemote,
        array $options = [],
        ?array &$stats = null,
    ): array {
        $sourceRemote = self::normalizePath($sourceRemote);
        $destinationRemote = self::normalizePath($destinationRemote);
        $stats = $this->copyCommandStats('copy', $sourceRemote, $destinationRemote);

        $sourceInfo = $this->optionalInfo($source, $sourceRemote);
        if ($sourceInfo !== null) {
            $destinationPath = self::joinPath($destinationRemote, self::pathBase($sourceInfo->path));
            $stats['sourceType'] = 'file';
            $stats['destinationPath'] = $destinationPath;
            $file = $this->copyFile($destination, $source, $destinationPath, $sourceInfo->path, $options);
            $this->recordCopyCommandFileStats($stats, $file);

            return [
                'command' => 'copy',
                'sourceType' => 'file',
                'destinationPath' => $destinationPath,
                'file' => $file,
                'directory' => null,
            ];
        }

        if (!$this->directoryExists($source, $sourceRemote)) {
            throw new \RuntimeException("Object or directory not found: {$sourceRemote}");
        }

        $stats['sourceType'] = 'directory';
        $directory = $this->copyDirectoryCommand(
            $destination,
            $source,
            $sourceRemote,
            $destinationRemote,
            (bool) ($options['createEmptySrcDirs'] ?? false),
            $options['filter'] ?? null,
            $options,
            $stats,
        );

        return [
            'command' => 'copy',
            'sourceType' => 'directory',
            'destinationPath' => $destinationRemote,
            'file' => null,
            'directory' => $directory,
        ];
    }

    /**
     * Model cmd/copyto argument dispatch.
     *
     * File sources copy to the exact destination path. Directory sources share
     * CopyDir semantics with cmd/copy and do not expose copy's empty directory
     * flag.
     *
     * @param array{
     *     filter?: FilterRuleSet|null,
     *     backup?: MemoryProvider|null,
     *     backupPrefix?: string,
     *     suffix?: string,
     *     suffixKeepExtension?: bool,
     *     compareDest?: list<MemoryProvider>,
     *     copyDest?: list<MemoryProvider>,
     *     noCheckDest?: bool,
     *     ignoreExisting?: bool,
     *     immutable?: bool,
     *     ignoreTimes?: bool,
     *     updateOlder?: bool,
     *     noUpdateModTime?: bool,
     *     modifyWindowSeconds?: int,
     *     checksum?: bool,
     *     refreshTimes?: bool,
     *     partialUploads?: bool,
     *     partialSuffix?: string,
     *     simulatePartialTransferError?: bool,
     *     metadataSet?: array<string, scalar|null>,
     *     dryRun?: bool,
     *     interactive?: bool,
     *     interactiveChoice?: mixed
     * } $options
     * @param array<string, mixed>|null $stats
     * @return array{command: string, sourceType: string, destinationPath: ?string, file: ?array<string, mixed>, directory: ?array<string, mixed>}
     */
    public function copytoCommand(
        MemoryProvider $destination,
        MemoryProvider $source,
        string $sourceRemote,
        string $destinationRemote,
        array $options = [],
        ?array &$stats = null,
    ): array {
        $sourceRemote = self::normalizePath($sourceRemote);
        $destinationRemote = self::normalizePath($destinationRemote);
        $stats = $this->copyCommandStats('copyto', $sourceRemote, $destinationRemote);

        $sourceInfo = $this->optionalInfo($source, $sourceRemote);
        if ($sourceInfo !== null) {
            $stats['sourceType'] = 'file';
            $stats['destinationPath'] = $destinationRemote;
            $file = $this->copyFile($destination, $source, $destinationRemote, $sourceInfo->path, $options);
            $this->recordCopyCommandFileStats($stats, $file);

            return [
                'command' => 'copyto',
                'sourceType' => 'file',
                'destinationPath' => $destinationRemote,
                'file' => $file,
                'directory' => null,
            ];
        }

        if (!$this->directoryExists($source, $sourceRemote)) {
            throw new \RuntimeException("Object or directory not found: {$sourceRemote}");
        }

        $stats['sourceType'] = 'directory';
        $directory = $this->copyDirectoryCommand(
            $destination,
            $source,
            $sourceRemote,
            $destinationRemote,
            false,
            $options['filter'] ?? null,
            $options,
            $stats,
        );

        return [
            'command' => 'copyto',
            'sourceType' => 'directory',
            'destinationPath' => $destinationRemote,
            'file' => null,
            'directory' => $directory,
        ];
    }

    /**
     * Model cmd/move argument dispatch.
     *
     * File sources are moved into the destination directory using the source
     * leaf name. Directory sources use the same MoveDir boundary as upstream:
     * try a provider directory move first when possible, then fall back to
     * object-by-object moves with optional empty-directory handling.
     *
     * @param array{
     *     deleteEmptySrcDirs?: bool,
     *     createEmptySrcDirs?: bool,
     *     filter?: FilterRuleSet|null,
     *     backup?: MemoryProvider|null,
     *     backupPrefix?: string,
     *     suffix?: string,
     *     suffixKeepExtension?: bool,
     *     compareDest?: list<MemoryProvider>,
     *     copyDest?: list<MemoryProvider>,
     *     noCheckDest?: bool,
     *     ignoreExisting?: bool,
     *     immutable?: bool,
     *     ignoreTimes?: bool,
     *     updateOlder?: bool,
     *     noUpdateModTime?: bool,
     *     modifyWindowSeconds?: int,
     *     checksum?: bool,
     *     refreshTimes?: bool,
     *     partialUploads?: bool,
     *     partialSuffix?: string,
     *     simulatePartialTransferError?: bool,
     *     metadataSet?: array<string, scalar|null>,
     *     dryRun?: bool,
     *     interactive?: bool,
     *     interactiveChoice?: mixed
     * } $options
     * @param array<string, mixed>|null $stats
     * @return array{command: string, sourceType: string, destinationPath: ?string, file: ?array<string, mixed>, directory: ?array<string, mixed>}
     */
    public function moveCommand(
        MemoryProvider $destination,
        MemoryProvider $source,
        string $sourceRemote,
        string $destinationRemote,
        array $options = [],
        ?array &$stats = null,
    ): array {
        $sourceRemote = self::normalizePath($sourceRemote);
        $destinationRemote = self::normalizePath($destinationRemote);
        $stats = $this->moveCommandStats('move', $sourceRemote, $destinationRemote);

        $sourceInfo = $this->optionalInfo($source, $sourceRemote);
        if ($sourceInfo !== null) {
            $destinationPath = self::joinPath($destinationRemote, self::pathBase($sourceInfo->path));
            $stats['sourceType'] = 'file';
            $stats['destinationPath'] = $destinationPath;
            $file = $this->moveFile($destination, $source, $destinationPath, $sourceInfo->path, $options);
            $this->recordMoveCommandFileStats($stats, $file);

            return [
                'command' => 'move',
                'sourceType' => 'file',
                'destinationPath' => $destinationPath,
                'file' => $file,
                'directory' => null,
            ];
        }

        if (!$this->directoryExists($source, $sourceRemote)) {
            throw new \RuntimeException("Object or directory not found: {$sourceRemote}");
        }

        $stats['sourceType'] = 'directory';
        $directory = $this->moveDirectoryCommand(
            $destination,
            $source,
            $sourceRemote,
            $destinationRemote,
            (bool) ($options['deleteEmptySrcDirs'] ?? false),
            (bool) ($options['createEmptySrcDirs'] ?? false),
            $options['filter'] ?? null,
            $options,
            $stats,
        );

        return [
            'command' => 'move',
            'sourceType' => 'directory',
            'destinationPath' => $destinationRemote,
            'file' => null,
            'directory' => $directory,
        ];
    }

    /**
     * Model cmd/moveto argument dispatch.
     *
     * File sources move to the exact destination path. Directory sources share
     * MoveDir semantics with cmd/move and intentionally do not expose the
     * move-only empty source directory flags.
     *
     * @param array{
     *     filter?: FilterRuleSet|null,
     *     backup?: MemoryProvider|null,
     *     backupPrefix?: string,
     *     suffix?: string,
     *     suffixKeepExtension?: bool,
     *     compareDest?: list<MemoryProvider>,
     *     copyDest?: list<MemoryProvider>,
     *     noCheckDest?: bool,
     *     ignoreExisting?: bool,
     *     immutable?: bool,
     *     ignoreTimes?: bool,
     *     updateOlder?: bool,
     *     noUpdateModTime?: bool,
     *     modifyWindowSeconds?: int,
     *     checksum?: bool,
     *     refreshTimes?: bool,
     *     partialUploads?: bool,
     *     partialSuffix?: string,
     *     simulatePartialTransferError?: bool,
     *     metadataSet?: array<string, scalar|null>,
     *     dryRun?: bool,
     *     interactive?: bool,
     *     interactiveChoice?: mixed
     * } $options
     * @param array<string, mixed>|null $stats
     * @return array{command: string, sourceType: string, destinationPath: ?string, file: ?array<string, mixed>, directory: ?array<string, mixed>}
     */
    public function movetoCommand(
        MemoryProvider $destination,
        MemoryProvider $source,
        string $sourceRemote,
        string $destinationRemote,
        array $options = [],
        ?array &$stats = null,
    ): array {
        $sourceRemote = self::normalizePath($sourceRemote);
        $destinationRemote = self::normalizePath($destinationRemote);
        $stats = $this->moveCommandStats('moveto', $sourceRemote, $destinationRemote);

        $sourceInfo = $this->optionalInfo($source, $sourceRemote);
        if ($sourceInfo !== null) {
            $stats['sourceType'] = 'file';
            $stats['destinationPath'] = $destinationRemote;
            $file = $this->moveFile($destination, $source, $destinationRemote, $sourceInfo->path, $options);
            $this->recordMoveCommandFileStats($stats, $file);

            return [
                'command' => 'moveto',
                'sourceType' => 'file',
                'destinationPath' => $destinationRemote,
                'file' => $file,
                'directory' => null,
            ];
        }

        if (!$this->directoryExists($source, $sourceRemote)) {
            throw new \RuntimeException("Object or directory not found: {$sourceRemote}");
        }

        $stats['sourceType'] = 'directory';
        $directory = $this->moveDirectoryCommand(
            $destination,
            $source,
            $sourceRemote,
            $destinationRemote,
            false,
            false,
            $options['filter'] ?? null,
            $options,
            $stats,
        );

        return [
            'command' => 'moveto',
            'sourceType' => 'directory',
            'destinationPath' => $destinationRemote,
            'file' => null,
            'directory' => $directory,
        ];
    }

    /**
     * Model `rclone dedupe --by-hash`.
     *
     * Interactive mode is represented by a deterministic chooser callback
     * instead of reading from a terminal.
     *
     * @param null|callable(array<string, mixed>): array<string, mixed>|string $interactiveChoice
     * @return array{hashType: string, groups: list<array{hash: string, objects: list<ObjectInfo>, kept: ?ObjectInfo, deleted: list<ObjectInfo>, skipped: bool, action?: string, quit?: bool}>, quit: bool}
     */
    public function deduplicateByHash(MemoryProvider $provider, string $mode, ?callable $interactiveChoice = null): array
    {
        $mode = DeduplicateMode::normalize($mode);
        $hashType = $provider->supportedHashes()->getOne();
        if ($hashType === HashType::NONE) {
            throw new \RuntimeException('provider has no hashes');
        }
        if ($mode === DeduplicateMode::INTERACTIVE && $interactiveChoice === null) {
            throw new \InvalidArgumentException('interactive dedupe mode requires a caller-supplied choice');
        }
        if ($mode === DeduplicateMode::RENAME) {
            throw new \InvalidArgumentException('dedupe by hash rename mode is not available in this native slice');
        }

        $objectsByHash = [];
        foreach ($provider->list() as $info) {
            $hash = $provider->hashesForObject($info, new HashSet($hashType))[$hashType] ?? '';
            if ($hash === '') {
                continue;
            }
            $objectsByHash[$hash][] = $info;
        }
        ksort($objectsByHash, SORT_STRING);

        $groups = [];
        $quit = false;
        foreach ($objectsByHash as $hash => $objects) {
            if (count($objects) <= 1) {
                continue;
            }

            if ($mode === DeduplicateMode::INTERACTIVE) {
                $decision = $this->interactiveDedupeDecision(
                    $interactiveChoice,
                    [
                        'byHash' => true,
                        'hash' => $hash,
                        'hashType' => $hashType,
                        'objects' => $objects,
                    ],
                    count($objects),
                    true,
                );

                if ($decision['action'] === 'skip' || $decision['action'] === 'quit') {
                    $groups[] = [
                        'hash' => $hash,
                        'objects' => $objects,
                        'kept' => null,
                        'deleted' => [],
                        'skipped' => true,
                        'action' => $decision['action'],
                        'quit' => $decision['action'] === 'quit',
                    ];
                    if ($decision['action'] === 'quit') {
                        $quit = true;
                        break;
                    }
                    continue;
                }

                $choice = $this->deleteDedupeObjectsExcept($provider, $objects, $decision['keepIndex'] ?? 0);
                $groups[] = [
                    'hash' => $hash,
                    'objects' => $objects,
                    'kept' => $choice['kept'],
                    'deleted' => $choice['deleted'],
                    'skipped' => false,
                    'action' => 'keep',
                ];
                continue;
            }

            if ($mode === DeduplicateMode::SKIP || $mode === DeduplicateMode::LIST) {
                $groups[] = [
                    'hash' => $hash,
                    'objects' => $objects,
                    'kept' => null,
                    'deleted' => [],
                    'skipped' => true,
                ];
                continue;
            }

            $ordered = $this->dedupeOrderedObjects($objects, $mode);
            $keepIndex = match ($mode) {
                DeduplicateMode::FIRST, DeduplicateMode::OLDEST, DeduplicateMode::SMALLEST => 0,
                DeduplicateMode::NEWEST, DeduplicateMode::LARGEST => count($ordered) - 1,
                default => 0,
            };
            $kept = $ordered[$keepIndex];
            $deleted = [];
            foreach ($ordered as $index => $info) {
                if ($index === $keepIndex) {
                    continue;
                }
                $deleted[] = $provider->deleteListedObject($info);
            }

            $groups[] = [
                'hash' => $hash,
                'objects' => $objects,
                'kept' => $kept,
                'deleted' => $deleted,
                'skipped' => false,
            ];
        }

        return [
            'hashType' => $hashType,
            'groups' => $groups,
            'quit' => $quit,
        ];
    }

    /**
     * Model `rclone dedupe` by duplicate remote name.
     * Identical duplicates are removed before skip/keep/rename modes, matching
     * upstream's by-name flow.
     *
     * Interactive mode is represented by a deterministic chooser callback
     * instead of reading from a terminal.
     *
     * @param null|callable(array<string, mixed>): array<string, mixed>|string $interactiveChoice
     * @return array{groups: list<array{path: string, objects: list<ObjectInfo>, identicalDeleted: list<ObjectInfo>, remaining: list<ObjectInfo>, kept: ?ObjectInfo, deleted: list<ObjectInfo>, renamed: list<ObjectInfo>, skipped: bool, listed: bool, action?: string, quit?: bool}>, quit: bool}
     */
    public function deduplicateByName(
        MemoryProvider $provider,
        string $mode,
        bool $sizeOnly = false,
        ?callable $interactiveChoice = null,
    ): array
    {
        $mode = DeduplicateMode::normalize($mode);
        if ($mode === DeduplicateMode::INTERACTIVE && $interactiveChoice === null) {
            throw new \InvalidArgumentException('interactive dedupe mode requires a caller-supplied choice');
        }

        $objectsByPath = [];
        foreach ($provider->list() as $info) {
            $objectsByPath[$info->path][] = $info;
        }
        ksort($objectsByPath, SORT_STRING);

        $groups = [];
        $quit = false;
        foreach ($objectsByPath as $path => $objects) {
            if (count($objects) <= 1) {
                continue;
            }

            $remaining = $objects;
            $identicalDeleted = [];
            if ($mode !== DeduplicateMode::LIST) {
                $identical = $this->deleteIdenticalDuplicateNames($provider, $remaining, $sizeOnly);
                $remaining = $identical['remaining'];
                $identicalDeleted = $identical['deleted'];
            }

            $kept = null;
            $deleted = [];
            $renamed = [];
            $skipped = false;
            $listed = false;
            $action = null;
            $groupQuit = false;
            if (count($remaining) > 1) {
                if ($mode === DeduplicateMode::INTERACTIVE) {
                    $decision = $this->interactiveDedupeDecision(
                        $interactiveChoice,
                        [
                            'byHash' => false,
                            'path' => $path,
                            'objects' => $remaining,
                        ],
                        count($remaining),
                        false,
                    );
                    $action = $decision['action'];
                    if ($decision['action'] === 'skip') {
                        $skipped = true;
                    } elseif ($decision['action'] === 'quit') {
                        $skipped = true;
                        $groupQuit = true;
                        $quit = true;
                    } elseif ($decision['action'] === 'rename') {
                        $renamed = $this->renameDuplicateNames($provider, $path, $remaining);
                        $remaining = $renamed;
                    } else {
                        $choice = $this->deleteDedupeObjectsExcept($provider, $remaining, $decision['keepIndex'] ?? 0);
                        $kept = $choice['kept'];
                        $deleted = $choice['deleted'];
                        $remaining = [$kept];
                    }
                } elseif ($mode === DeduplicateMode::SKIP) {
                    $skipped = true;
                } elseif ($mode === DeduplicateMode::LIST) {
                    $listed = true;
                } elseif ($mode === DeduplicateMode::RENAME) {
                    $renamed = $this->renameDuplicateNames($provider, $path, $remaining);
                    $remaining = $renamed;
                } else {
                    $ordered = $this->dedupeOrderedObjects($remaining, $mode);
                    $keepIndex = match ($mode) {
                        DeduplicateMode::FIRST, DeduplicateMode::OLDEST, DeduplicateMode::SMALLEST => 0,
                        DeduplicateMode::NEWEST, DeduplicateMode::LARGEST => count($ordered) - 1,
                        default => 0,
                    };
                    $kept = $ordered[$keepIndex];
                    foreach ($ordered as $index => $info) {
                        if ($index === $keepIndex) {
                            continue;
                        }
                        $deleted[] = $provider->deleteListedObject($info);
                    }
                    $remaining = [$kept];
                }
            }

            $groups[] = [
                'path' => $path,
                'objects' => $objects,
                'identicalDeleted' => $identicalDeleted,
                'remaining' => $remaining,
                'kept' => $kept,
                'deleted' => $deleted,
                'renamed' => $renamed,
                'skipped' => $skipped,
                'listed' => $listed,
                'action' => $action ?? $mode,
                'quit' => $groupQuit,
            ];
            if ($quit) {
                break;
            }
        }

        return [
            'groups' => $groups,
            'quit' => $quit,
        ];
    }

    /**
     * Discover duplicate directory entries the way rclone's dedupe pre-pass
     * does: provider IDs identify directories and ParentIDs build recursive
     * entry counts, while missing IDs fall back to remote paths.
     *
     * @return list<array{path: string, directories: list<ObjectInfo>, counts: list<int>}>
     */
    public function findDuplicateDirectories(MemoryProvider $provider): array
    {
        $directories = $provider->directories();
        $dirsById = [];
        $dirsByPath = [];

        foreach ($directories as $directory) {
            $id = $this->dedupeEntryId($directory);
            $dirsById[$id] ??= [
                'directory' => null,
                'parent' => '',
                'count' => 0,
            ];
            $dirsById[$id]['directory'] = $directory;
            $dirsById[$id]['parent'] = $this->dedupeEntryParentId($directory);
            $dirsByPath[$directory->path][] = $id;
        }

        $entries = array_merge($directories, $provider->list());
        usort(
            $entries,
            static fn (ObjectInfo $a, ObjectInfo $b): int => $a->path <=> $b->path
                ?: ($a->providerKey ?? '') <=> ($b->providerKey ?? ''),
        );

        foreach ($entries as $entry) {
            $this->incrementDedupeDirectoryCount($dirsById, $this->dedupeEntryParentId($entry));
        }

        ksort($dirsByPath, SORT_STRING);
        $groups = [];
        foreach ($dirsByPath as $path => $ids) {
            if (count($ids) <= 1) {
                continue;
            }

            $duplicateDirectories = [];
            $counts = [];
            foreach ($ids as $id) {
                $directory = $dirsById[$id]['directory'] ?? null;
                if (!$directory instanceof ObjectInfo) {
                    continue;
                }
                $duplicateDirectories[] = $directory;
                $counts[] = $dirsById[$id]['count'];
            }

            if (count($duplicateDirectories) > 1) {
                $groups[] = [
                    'path' => $path,
                    'directories' => $duplicateDirectories,
                    'counts' => $counts,
                ];
            }
        }

        return $groups;
    }

    /**
     * Model `rclone dedupe --dedupe-mode list` for duplicate directories.
     *
     * @return array{groups: list<array{path: string, directories: list<ObjectInfo>, counts: list<int>, report: string}>}
     */
    public function listDuplicateDirectories(MemoryProvider $provider): array
    {
        $groups = [];
        foreach ($this->findDuplicateDirectories($provider) as $group) {
            $groups[] = [
                'path' => $group['path'],
                'directories' => $group['directories'],
                'counts' => $group['counts'],
                'report' => sprintf('%s: %d duplicates of this directory', $group['path'], count($group['directories'])),
            ];
        }

        return ['groups' => $groups];
    }

    /**
     * Model the dedupe duplicate-directory merge boundary: non-list modes put
     * the largest recursive directory first before calling the provider
     * MergeDirs feature, while list mode reports duplicates without mutation.
     *
     * @param list<string|ObjectInfo> $directories
     * @return array{listed: bool, ordered: list<string>, target: ?ObjectInfo, merge: ?array{target: ObjectInfo, moved: list<ObjectInfo>, removed: list<ObjectInfo>}}
     */
    public function mergeDuplicateDirectories(MemoryProvider $provider, array $directories, bool $listOnly = false): array
    {
        $items = [];
        foreach ($directories as $index => $directory) {
            $info = $directory instanceof ObjectInfo ? $directory : $provider->directoryInfo($directory);
            $items[] = [
                'index' => $index,
                'path' => $info->path,
                'count' => $provider->directoryEntryCount($info),
                'info' => $info,
            ];
        }

        if ($listOnly || count($items) <= 1) {
            return [
                'listed' => true,
                'ordered' => array_map(static fn (array $item): string => $item['path'], $items),
                'target' => null,
                'merge' => null,
            ];
        }

        usort(
            $items,
            static fn (array $a, array $b): int => $b['count'] <=> $a['count']
                ?: $a['index'] <=> $b['index'],
        );
        $ordered = array_map(static fn (array $item): ObjectInfo => $item['info'], $items);
        $merge = $provider->mergeDirectories($ordered);

        return [
            'listed' => false,
            'ordered' => array_map(static fn (ObjectInfo $info): string => $info->path, $ordered),
            'target' => $merge['target'],
            'merge' => $merge,
        ];
    }

    /**
     * @return array{existed: bool, savedPath: ?string, cleanup: \Closure}
     */
    public function removeExisting(
        MemoryProvider $provider,
        string $path,
        string $operation = 'operation',
        ?string $temporarySuffix = null,
    ): array {
        $path = self::normalizePath($path);
        try {
            $provider->info($path);
        } catch (\RuntimeException) {
            return [
                'existed' => false,
                'savedPath' => null,
                'cleanup' => static function (?\Throwable &$operationError): void {
                },
            ];
        }

        if (!$provider->supportsDirectServerSideMove()) {
            throw new \RuntimeException("{$operation}: destination file exists already and can't rename");
        }

        $temporarySuffix ??= '.' . substr(bin2hex(random_bytes(4)), 0, 8);
        $savedPath = self::temporaryExistingPath($path, $temporarySuffix);
        try {
            $saved = $provider->directServerSideMoveTo($path, $provider, $savedPath);
        } catch (\RuntimeException $throwable) {
            throw new \RuntimeException(
                "{$operation}: failed to rename existing file: " . $throwable->getMessage(),
                0,
                $throwable,
            );
        }

        return [
            'existed' => true,
            'savedPath' => $saved->path,
            'cleanup' => static function (?\Throwable &$operationError) use ($provider, $saved, $path, $operation): void {
                if ($operationError === null) {
                    try {
                        $provider->delete($saved->path);
                    } catch (\RuntimeException $throwable) {
                        $operationError = new \RuntimeException(
                            "{$operation}: failed to remove renamed existing file: " . $throwable->getMessage(),
                            0,
                            $throwable,
                        );
                    }

                    return;
                }

                try {
                    $provider->directServerSideMoveTo($saved->path, $provider, $path);
                } catch (\RuntimeException) {
                    // Upstream logs restore failures and preserves the original operation error.
                }
            },
        ];
    }

    /**
     * Model provider Copy implementations that call RemoveExisting before
     * invoking a remote-side copy API that cannot overwrite safely.
     *
     * @param array{
     *     operation?: string,
     *     temporarySuffix?: string,
     *     guardCaseFoldSameRemote?: bool,
     *     guardCaseFoldAfterRemoveExisting?: bool,
     *     precreateDestination?: bool,
     *     simulateCopyError?: bool|string,
     *     provider?: string,
     *     apiResult?: array<string, mixed>,
     *     providerError?: string|array<string, mixed>
     * } $options
     * @return array{copied: ObjectInfo, savedPath: ?string, precreatedPath: ?string, metadataRefresh: list<string>}
     */
    public function serverSideCopyReplace(
        MemoryProvider $provider,
        string $sourcePath,
        string $destinationPath,
        array $options = [],
    ): array {
        return $this->serverSideCopyReplaceFrom($provider, $provider, $sourcePath, $destinationPath, $options);
    }

    /**
     * Model operations.Copy retrying as a normal streamed copy when a provider
     * server-side copy reports ErrorCantCopy, as OneDrive does for unshared
     * cross-config personal-drive copies after the async job starts.
     *
     * @param array{
     *     operation?: string,
     *     temporarySuffix?: string,
     *     guardCaseFoldSameRemote?: bool,
     *     guardCaseFoldAfterRemoveExisting?: bool,
     *     precreateDestination?: bool,
     *     simulateCopyError?: bool|string,
     *     provider?: string,
     *     apiResult?: array<string, mixed>,
     *     providerError?: string|array<string, mixed>
     * } $serverSideOptions
     * @param array<string, mixed> $copyOptions
     * @param array<string, mixed>|null $stats
     * @return array{copied: ?ObjectInfo, serverSide: bool, fallbackUsed: bool, fallbackReason: ?string, savedPath: ?string, metadataRefresh: list<string>, manual: ?array<string, mixed>}
     */
    public function copyFileWithServerSideFallback(
        MemoryProvider $destination,
        MemoryProvider $source,
        string $destinationPath,
        string $sourcePath,
        array $serverSideOptions = [],
        array $copyOptions = [],
        ?array &$stats = null,
    ): array {
        try {
            $serverSide = $this->serverSideCopyReplaceFrom(
                $source,
                $destination,
                $sourcePath,
                $destinationPath,
                $serverSideOptions,
            );
        } catch (\RuntimeException $throwable) {
            if (!MemoryProvider::isCantCopyException($throwable)) {
                throw $throwable;
            }

            $manual = $this->copyFile($destination, $source, $destinationPath, $sourcePath, $copyOptions);
            $stats = [
                'serverSideAttempted' => true,
                'serverSideSucceeded' => false,
                'fallbackUsed' => true,
                'fallbackReason' => $throwable->getMessage(),
                'manualCopiedPath' => $manual['copied']?->path,
            ];

            return [
                'copied' => $manual['copied'],
                'serverSide' => false,
                'fallbackUsed' => true,
                'fallbackReason' => $throwable->getMessage(),
                'savedPath' => null,
                'metadataRefresh' => [],
                'manual' => $manual,
            ];
        }

        $stats = [
            'serverSideAttempted' => true,
            'serverSideSucceeded' => true,
            'fallbackUsed' => false,
            'fallbackReason' => null,
            'manualCopiedPath' => null,
        ];

        return [
            'copied' => $serverSide['copied'],
            'serverSide' => true,
            'fallbackUsed' => false,
            'fallbackReason' => null,
            'savedPath' => $serverSide['savedPath'],
            'metadataRefresh' => $serverSide['metadataRefresh'],
            'manual' => null,
        ];
    }

    /**
     * @param array{
     *     operation?: string,
     *     temporarySuffix?: string,
     *     guardCaseFoldSameRemote?: bool,
     *     guardCaseFoldAfterRemoveExisting?: bool,
     *     precreateDestination?: bool,
     *     simulateCopyError?: bool|string,
     *     provider?: string,
     *     apiResult?: array<string, mixed>,
     *     providerError?: string|array<string, mixed>
     * } $options
     * @return array{copied: ObjectInfo, savedPath: ?string, precreatedPath: ?string, metadataRefresh: list<string>}
     */
    private function serverSideCopyReplaceFrom(
        MemoryProvider $sourceProvider,
        MemoryProvider $destinationProvider,
        string $sourcePath,
        string $destinationPath,
        array $options = [],
    ): array {
        $sourcePath = self::normalizePath($sourcePath);
        $destinationPath = self::normalizePath($destinationPath);

        if (!$destinationProvider->supportsServerSideCopy()) {
            throw new \RuntimeException(MemoryProvider::ERROR_CANT_COPY);
        }

        $sourceInfo = $sourceProvider->info($sourcePath);
        $guardCaseFoldSameRemote = (bool) ($options['guardCaseFoldSameRemote'] ?? false);
        $guardCaseFoldAfterRemoveExisting = (bool) ($options['guardCaseFoldAfterRemoveExisting'] ?? false);
        $sameProvider = $sourceProvider === $destinationProvider;
        if ($sameProvider && $guardCaseFoldSameRemote && !$guardCaseFoldAfterRemoveExisting) {
            $this->assertNotSameCaseFoldedProviderPath($sourcePath, $destinationPath);
        }
        if (isset($options['provider'])) {
            $this->providerCopyPreflight(
                (string) $options['provider'],
                is_array($options['apiResult'] ?? null) ? $options['apiResult'] : [],
            );
        }

        $operation = (string) ($options['operation'] ?? 'server side copy');
        $cleanup = $this->removeExisting(
            $destinationProvider,
            $destinationPath,
            $operation,
            $options['temporarySuffix'] ?? null,
        );

        $operationError = null;
        $copied = null;
        $metadataRefresh = [];
        try {
            if ($sameProvider && $guardCaseFoldSameRemote && $guardCaseFoldAfterRemoveExisting) {
                $this->assertNotSameCaseFoldedProviderPath($sourcePath, $destinationPath);
            }
            if (array_key_exists('simulateCopyError', $options) && $options['simulateCopyError'] !== false) {
                $message = $options['simulateCopyError'] === true
                    ? 'server side copy failed'
                    : (string) $options['simulateCopyError'];
                throw new \RuntimeException($message);
            }
            if (array_key_exists('providerError', $options)) {
                throw $this->providerCopyFailure(
                    (string) ($options['provider'] ?? ''),
                    $destinationPath,
                    $options['providerError'],
                );
            }

            $copied = $sourceProvider->serverSideCopyTo($sourceInfo->path, $destinationProvider, $destinationPath);
            if (isset($options['provider'])) {
                $providerResult = $this->providerCopyResultOptions(
                    (string) $options['provider'],
                    $sourceInfo,
                    is_array($options['apiResult'] ?? null) ? $options['apiResult'] : [],
                    $destinationPath,
                );
                $metadataRefresh = $providerResult['refresh'];
                if ($providerResult['path'] !== null && $providerResult['path'] !== $copied->path) {
                    $copied = $destinationProvider->renameObject($copied->path, $providerResult['path']);
                }
                $copied = $destinationProvider->updateObjectInfo($copied->path, $providerResult['options']);
            }
        } catch (\RuntimeException $throwable) {
            $operationError = $throwable;
        }

        $cleanupError = $operationError;
        $cleanup['cleanup']($cleanupError);
        if ($operationError !== null) {
            throw $operationError;
        }
        if ($cleanupError !== null) {
            throw $cleanupError;
        }

        return [
            'copied' => $copied,
            'savedPath' => $cleanup['savedPath'],
            'precreatedPath' => (bool) ($options['precreateDestination'] ?? false) ? $destinationPath : null,
            'metadataRefresh' => $metadataRefresh,
        ];
    }

    private function assertNotSameCaseFoldedProviderPath(string $sourcePath, string $destinationPath): void
    {
        if (strtolower($sourcePath) !== strtolower($destinationPath)) {
            return;
        }

        throw new \RuntimeException(
            sprintf('can\'t copy "%s" -> "%s" as are same name when lowercase', $sourcePath, $destinationPath),
        );
    }

    /**
     * @param array<string, mixed> $apiResult
     */
    private function providerCopyPreflight(string $provider, array $apiResult): void
    {
        $provider = strtolower($provider);
        if ($provider === 'dropbox') {
            $this->assertDropboxKnownExportFormats($apiResult);

            return;
        }
        if ($provider !== 'onedrive') {
            return;
        }

        $sourceDriveType = $this->onedriveDriveType(
            $apiResult['sourceDriveType']
                ?? $apiResult['srcDriveType']
                ?? $apiResult['driveType']
                ?? null,
        );
        $destinationDriveType = $this->onedriveDriveType(
            $apiResult['destinationDriveType']
                ?? $apiResult['dstDriveType']
                ?? $apiResult['targetDriveType']
                ?? $sourceDriveType,
        );
        if ($sourceDriveType === null || $destinationDriveType === null) {
            return;
        }

        if (($destinationDriveType === self::ONEDRIVE_DRIVE_TYPE_PERSONAL && $sourceDriveType !== self::ONEDRIVE_DRIVE_TYPE_PERSONAL)
            || ($destinationDriveType !== self::ONEDRIVE_DRIVE_TYPE_PERSONAL && $sourceDriveType === self::ONEDRIVE_DRIVE_TYPE_PERSONAL)) {
            throw new \RuntimeException(MemoryProvider::ERROR_CANT_COPY);
        }

        $sourceDriveId = $this->optionalString(
            $apiResult['sourceDriveId']
                ?? $apiResult['sourceDriveID']
                ?? $apiResult['srcDriveId']
                ?? $apiResult['srcDriveID']
                ?? null,
        );
        $destinationDriveId = $this->optionalString(
            $apiResult['destinationDriveId']
                ?? $apiResult['destinationDriveID']
                ?? $apiResult['dstDriveId']
                ?? $apiResult['dstDriveID']
                ?? $apiResult['targetDriveId']
                ?? $apiResult['targetDriveID']
                ?? null,
        );
        if ($sourceDriveType === self::ONEDRIVE_DRIVE_TYPE_BUSINESS
            && $destinationDriveType === self::ONEDRIVE_DRIVE_TYPE_BUSINESS
            && $sourceDriveId !== null
            && $destinationDriveId !== null
            && strtolower($sourceDriveId) !== strtolower($destinationDriveId)) {
            throw new \RuntimeException(MemoryProvider::ERROR_CANT_COPY);
        }
    }

    private function onedriveDriveType(mixed $value): ?string
    {
        $driveType = $this->optionalString($value);
        if ($driveType === null || $driveType === '') {
            return null;
        }

        return match (strtolower($driveType)) {
            self::ONEDRIVE_DRIVE_TYPE_PERSONAL => self::ONEDRIVE_DRIVE_TYPE_PERSONAL,
            self::ONEDRIVE_DRIVE_TYPE_BUSINESS => self::ONEDRIVE_DRIVE_TYPE_BUSINESS,
            'documentlibrary', 'sharepoint' => self::ONEDRIVE_DRIVE_TYPE_SHAREPOINT,
            default => $driveType,
        };
    }

    /**
     * @param array<string, mixed> $apiResult
     */
    private function assertDropboxKnownExportFormats(array $apiResult): void
    {
        $configured = $apiResult['exportFormats'] ?? $apiResult['export_formats'] ?? null;
        if (!is_array($configured)) {
            return;
        }

        $knownExtensions = array_flip(self::DROPBOX_EXPORT_API_FORMATS);
        foreach ($configured as $extension) {
            $extension = $this->optionalString($extension);
            if ($extension === null || $extension === '') {
                continue;
            }
            if (!isset($knownExtensions[$extension])) {
                throw new \RuntimeException("dropbox: unknown export format '{$extension}'");
            }
        }
    }

    /**
     * @param array<string, mixed> $apiResult
     * @return array{options: array<string, mixed>, refresh: list<string>, path: ?string}
     */
    private function providerCopyResultOptions(
        string $provider,
        ObjectInfo $sourceInfo,
        array $apiResult,
        string $destinationPath,
    ): array
    {
        return match (strtolower($provider)) {
            'dropbox' => $this->dropboxCopyResultOptions($sourceInfo, $apiResult, $destinationPath),
            'onedrive' => $this->onedriveCopyResultOptions($sourceInfo, $apiResult),
            'yandex' => $this->yandexCopyResultOptions($sourceInfo, $apiResult),
            'sugarsync' => $this->sugarsyncCopyResultOptions($sourceInfo, $apiResult),
            default => throw new \InvalidArgumentException("unknown provider copy result profile: {$provider}"),
        };
    }

    /**
     * @param array<string, mixed> $apiResult
     * @return array{options: array<string, mixed>, refresh: list<string>, path: ?string}
     */
    private function dropboxCopyResultOptions(ObjectInfo $sourceInfo, array $apiResult, string $destinationPath): array
    {
        $metadataType = strtolower($this->optionalString($apiResult['metadataType'] ?? $apiResult['metadata_type'] ?? 'file') ?? 'file');
        if ($metadataType !== 'file') {
            throw new \RuntimeException('is not a regular file');
        }

        $metadata = $sourceInfo->metadata + $this->stringMetadata($apiResult['metadata'] ?? []);
        $hashes = $this->stringMetadata($apiResult['hashes'] ?? $sourceInfo->hashes);
        $unknownSize = false;
        $path = null;
        $contentHash = $this->optionalString($apiResult['contentHash'] ?? $apiResult['content_hash'] ?? null);
        if ($contentHash !== null && $contentHash !== '') {
            $metadata['dropbox_content_hash'] = strtolower($contentHash);
        }
        if (!$this->providerBool($apiResult['isDownloadable'] ?? $apiResult['is_downloadable'] ?? true)) {
            $export = $this->dropboxExportResult($apiResult, $destinationPath);
            $metadata['dropbox_is_downloadable'] = 'false';
            $metadata['dropbox_export_type'] = $export['type'];
            if ($export['apiFormat'] !== null) {
                $metadata['dropbox_export_format'] = $export['apiFormat'];
            }
            if ($export['extension'] !== null) {
                $metadata['dropbox_export_extension'] = $export['extension'];
            }
            if ($export['path'] !== null) {
                $metadata['dropbox_exposed_remote'] = $export['path'];
            }
            $hashes = [];
            $unknownSize = true;
            $path = $export['path'];
        }

        return [
            'options' => [
                'unknownSize' => $unknownSize,
                'modTime' => $this->optionalString($apiResult['clientModified'] ?? $apiResult['client_modified'] ?? null) ?? $sourceInfo->modTime,
                'mimeType' => $this->optionalString($apiResult['mimeType'] ?? null) ?? $sourceInfo->mimeType,
                'metadata' => $metadata,
                'id' => $this->optionalString($apiResult['id'] ?? null) ?? $sourceInfo->id,
                'tier' => $sourceInfo->tier,
                'hashes' => $hashes,
            ],
            'refresh' => ['dropbox:relocation-result-metadata'],
            'path' => $path,
        ];
    }

    /**
     * @param array<string, mixed> $apiResult
     * @return array{options: array<string, mixed>, refresh: list<string>, path: ?string}
     */
    private function onedriveCopyResultOptions(ObjectInfo $sourceInfo, array $apiResult): array
    {
        $metadata = $sourceInfo->metadata + $this->stringMetadata($apiResult['metadata'] ?? []);
        $refresh = ['onedrive:async-copy-job', 'onedrive:set-source-modtime'];
        if (isset($metadata['permissions'])) {
            $metadata['onedrive_permissions_mode'] = 'add-only';
            $refresh[] = 'onedrive:metadata-permissions-add-only';

            $permissionsWriteError = $this->optionalString(
                $apiResult['permissionsWriteError']
                    ?? $apiResult['permissionWriteError']
                    ?? null,
            );
            if ($permissionsWriteError !== null && $permissionsWriteError !== '') {
                $permissionsError = 'failed to process permissions: ' . $permissionsWriteError;
                if (!(bool) ($apiResult['permissionsFailOk'] ?? $apiResult['permissionFailOk'] ?? false)) {
                    throw new \RuntimeException($permissionsError);
                }
                $refresh[] = 'onedrive:metadata-permissions-failok';
            }
        }

        $systemMetadata = $this->onedriveSystemMetadata($apiResult);
        if ($systemMetadata !== []) {
            $metadata += $systemMetadata;
            if (array_key_exists('package-type', $systemMetadata)) {
                $refresh[] = 'onedrive:package-metadata';
            }
            if (array_diff_key($systemMetadata, ['package-type' => true]) !== []) {
                $refresh[] = 'onedrive:remoteitem-shared-metadata';
            }
        }

        return [
            'options' => [
                'modTime' => $sourceInfo->modTime,
                'mimeType' => $this->onedriveMimeType($apiResult) ?? $sourceInfo->mimeType,
                'metadata' => $metadata,
                'id' => $this->onedriveNormalizedId($apiResult, $sourceInfo->id),
                'tier' => $sourceInfo->tier,
                'hashes' => $this->onedriveHashes($apiResult) + $sourceInfo->hashes,
            ],
            'refresh' => $refresh,
            'path' => null,
        ];
    }

    /**
     * @param array<string, mixed> $apiResult
     * @return array{options: array<string, mixed>, refresh: list<string>, path: ?string}
     */
    private function yandexCopyResultOptions(ObjectInfo $sourceInfo, array $apiResult): array
    {
        $resourceType = strtolower($this->optionalString(
            $apiResult['resourceType']
                ?? $apiResult['type']
                ?? 'file',
        ) ?? 'file');
        if ($resourceType === 'dir') {
            throw new \RuntimeException('is a directory not a file');
        }
        if ($resourceType !== 'file') {
            throw new \RuntimeException('is not a regular file');
        }

        $customProperties = is_array($apiResult['customProperties'] ?? null)
            ? $apiResult['customProperties']
            : [];
        $modTime = $this->optionalString($customProperties['rclone_modified'] ?? null)
            ?? $this->optionalString($apiResult['modified'] ?? null)
            ?? $sourceInfo->modTime;
        if ($modTime !== null && $modTime !== '') {
            $this->assertRfc3339NanoModTime($modTime);
        }
        $hashes = $sourceInfo->hashes;
        $md5 = $this->optionalString($apiResult['md5'] ?? null);
        if ($md5 !== null && $md5 !== '') {
            $hashes[HashType::MD5] = strtolower($md5);
        }

        return [
            'options' => [
                'modTime' => $modTime,
                'mimeType' => $this->optionalString($apiResult['mimeType'] ?? null) ?? $sourceInfo->mimeType,
                'metadata' => $sourceInfo->metadata + $this->stringMetadata($apiResult['metadata'] ?? []),
                'id' => $this->optionalString($apiResult['id'] ?? null) ?? $sourceInfo->id,
                'tier' => $sourceInfo->tier,
                'hashes' => $hashes,
            ],
            'refresh' => ['yandex:new-object-metadata-read'],
            'path' => null,
        ];
    }

    /**
     * @param array<string, mixed> $apiResult
     * @return array{options: array<string, mixed>, refresh: list<string>, path: ?string}
     */
    private function sugarsyncCopyResultOptions(ObjectInfo $sourceInfo, array $apiResult): array
    {
        $id = $this->optionalString(
            $apiResult['location']
                ?? $apiResult['id']
                ?? $apiResult['ref']
                ?? $apiResult['Ref']
                ?? null,
        );
        if ($id === null || $id === '') {
            throw new \RuntimeException('no ID found in response');
        }

        return [
            'options' => [
                'modTime' => $this->optionalString($apiResult['lastModified'] ?? $apiResult['last_modified'] ?? null) ?? $sourceInfo->modTime,
                'mimeType' => $this->optionalString($apiResult['mimeType'] ?? null) ?? $sourceInfo->mimeType,
                'metadata' => $sourceInfo->metadata + $this->stringMetadata($apiResult['metadata'] ?? []),
                'id' => $id,
                'tier' => $sourceInfo->tier,
                'hashes' => [],
            ],
            'refresh' => ['sugarsync:metadata-read-after-copy'],
            'path' => null,
        ];
    }

    /**
     * @param array<string, mixed> $apiResult
     * @return array{type: string, apiFormat: ?string, extension: ?string, path: ?string}
     */
    private function dropboxExportResult(array $apiResult, string $destinationPath): array
    {
        if ($this->providerBool($apiResult['skipExports'] ?? $apiResult['skip_exports'] ?? false)) {
            return ['type' => 'hidden', 'apiFormat' => null, 'extension' => null, 'path' => null];
        }
        if ($this->providerBool($apiResult['showAllExports'] ?? $apiResult['show_all_exports'] ?? false)) {
            return ['type' => 'list-only', 'apiFormat' => null, 'extension' => null, 'path' => null];
        }

        $exportInfo = is_array($apiResult['exportInfo'] ?? null) ? $apiResult['exportInfo'] : [];
        $formatStrings = [];
        $exportAs = $this->optionalString($apiResult['exportAs'] ?? $apiResult['export_as'] ?? $exportInfo['exportAs'] ?? null);
        if ($exportAs !== null && $exportAs !== '') {
            $formatStrings[] = $exportAs;
        }
        $exportOptions = $apiResult['exportOptions'] ?? $apiResult['export_options'] ?? $exportInfo['exportOptions'] ?? [];
        if (is_array($exportOptions)) {
            foreach ($exportOptions as $format) {
                $format = $this->optionalString($format);
                if ($format !== null && $format !== '') {
                    $formatStrings[] = $format;
                }
            }
        }

        $formatsByExtension = [];
        $dropboxPreferredFormat = null;
        $dropboxPreferredExtension = null;
        foreach ($formatStrings as $format) {
            if (!isset(self::DROPBOX_EXPORT_API_FORMATS[$format])) {
                continue;
            }
            if ($dropboxPreferredFormat === null) {
                $dropboxPreferredFormat = $format;
                $dropboxPreferredExtension = self::DROPBOX_EXPORT_API_FORMATS[$format];
            }
            $formatsByExtension[self::DROPBOX_EXPORT_API_FORMATS[$format]] = $format;
        }

        $preferredExtensions = ['html', 'md'];
        $configured = $apiResult['exportFormats'] ?? $apiResult['export_formats'] ?? null;
        if (is_array($configured) && $configured !== []) {
            $preferredExtensions = [];
            foreach ($configured as $extension) {
                $extension = $this->optionalString($extension);
                if ($extension !== null && $extension !== '') {
                    $preferredExtensions[] = $extension;
                }
            }
        }

        foreach ($preferredExtensions as $extension) {
            if (isset($formatsByExtension[$extension])) {
                $path = preg_replace('/\.paper$/', '', $destinationPath) . '.' . $extension;

                return [
                    'type' => 'exportable',
                    'apiFormat' => $formatsByExtension[$extension],
                    'extension' => $extension,
                    'path' => $path,
                ];
            }
        }

        if ($dropboxPreferredFormat !== null && $dropboxPreferredExtension !== null) {
            $path = preg_replace('/\.paper$/', '', $destinationPath) . '.' . $dropboxPreferredExtension;

            return [
                'type' => 'exportable',
                'apiFormat' => $dropboxPreferredFormat,
                'extension' => $dropboxPreferredExtension,
                'path' => $path,
            ];
        }

        return ['type' => 'hidden', 'apiFormat' => null, 'extension' => null, 'path' => null];
    }

    public function yandexSetRcloneModified(
        MemoryProvider $provider,
        string $path,
        \DateTimeInterface|string $modTime,
    ): ObjectInfo {
        $time = $this->normalizeTouchTime($modTime);
        try {
            $info = $provider->setModTime($path, $time);
        } catch (\RuntimeException $throwable) {
            throw new \RuntimeException('failed to set custom property rclone_modified: ' . $throwable->getMessage(), 0, $throwable);
        }

        return $provider->setObjectMetadata(
            $info->path,
            $info->metadata + ['rclone_modified' => $time],
        );
    }

    /**
     * @param array<string, mixed> $apiResult
     * @return array<string, string>
     */
    private function onedriveHashes(array $apiResult): array
    {
        $remoteItem = is_array($apiResult['remoteItem'] ?? null) ? $apiResult['remoteItem'] : [];
        $remoteFile = is_array($remoteItem['file'] ?? null) ? $remoteItem['file'] : [];
        $hashSource = is_array($apiResult['hashes'] ?? null)
            ? $apiResult['hashes']
            : (is_array($remoteFile['hashes'] ?? null) ? $remoteFile['hashes'] : $apiResult);
        $hashes = [];
        foreach ([
            'sha1Hash' => HashType::SHA1,
            'sha256Hash' => HashType::SHA256,
            'crc32Hash' => HashType::CRC32,
        ] as $apiKey => $hashType) {
            $hash = $this->optionalString($hashSource[$apiKey] ?? null);
            if ($hash !== null && $hash !== '') {
                $hashes[$hashType] = strtolower($hash);
            }
        }
        $quickXorHash = $this->optionalString(
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
     * @param array<string, mixed> $apiResult
     */
    private function onedriveMimeType(array $apiResult): ?string
    {
        $mimeType = $this->optionalString($apiResult['mimeType'] ?? null);
        if ($mimeType !== null && $mimeType !== '') {
            return $mimeType;
        }

        $remoteItem = is_array($apiResult['remoteItem'] ?? null) ? $apiResult['remoteItem'] : [];
        $remoteFile = is_array($remoteItem['file'] ?? null) ? $remoteItem['file'] : [];
        $remoteMimeType = $this->optionalString($remoteFile['mimeType'] ?? null);
        if ($remoteMimeType !== null && $remoteMimeType !== '') {
            return $remoteMimeType;
        }

        $file = is_array($apiResult['file'] ?? null) ? $apiResult['file'] : [];

        return $this->optionalString($file['mimeType'] ?? null);
    }

    /**
     * @param array<string, mixed> $apiResult
     */
    private function onedrivePackageType(array $apiResult): ?string
    {
        $remoteItem = is_array($apiResult['remoteItem'] ?? null) ? $apiResult['remoteItem'] : [];
        $remotePackage = is_array($remoteItem['package'] ?? null) ? $remoteItem['package'] : [];
        $remotePackageType = $this->optionalString($remotePackage['type'] ?? $remotePackage['Type'] ?? null);
        if ($remotePackageType !== null && $remotePackageType !== '') {
            return $remotePackageType;
        }

        $package = is_array($apiResult['package'] ?? null) ? $apiResult['package'] : [];

        return $this->optionalString(
            $package['type']
                ?? $package['Type']
                ?? $apiResult['packageType']
                ?? $apiResult['package_type']
                ?? null,
        );
    }

    /**
     * @param array<string, mixed> $apiResult
     */
    private function onedriveNormalizedId(array $apiResult, ?string $fallback): ?string
    {
        $remoteItem = is_array($apiResult['remoteItem'] ?? null) ? $apiResult['remoteItem'] : [];
        $remoteId = $this->optionalString($remoteItem['id'] ?? null);
        if ($remoteId !== null && $remoteId !== '') {
            return $this->prefixOneDriveDriveId($remoteId, $remoteItem);
        }

        $id = $this->optionalString($apiResult['id'] ?? null);
        if ($id !== null && $id !== '') {
            return $this->prefixOneDriveDriveId($id, $apiResult);
        }

        return $fallback;
    }

    /**
     * @param array<string, mixed> $item
     */
    private function prefixOneDriveDriveId(string $id, array $item): string
    {
        if (str_contains($id, '#')) {
            return $id;
        }

        $parentReference = is_array($item['parentReference'] ?? null) ? $item['parentReference'] : [];
        $driveId = $this->optionalString(
            $parentReference['driveId']
                ?? $parentReference['driveID']
                ?? $item['driveId']
                ?? $item['driveID']
                ?? null,
        );
        if ($driveId === null || $driveId === '') {
            return $id;
        }

        return $driveId . '#' . $id;
    }

    /**
     * @param array<string, mixed> $apiResult
     * @return array<string, string>
     */
    private function onedriveSystemMetadata(array $apiResult): array
    {
        $hasRemoteItem = is_array($apiResult['remoteItem'] ?? null);
        $hasShared = is_array($apiResult['shared'] ?? null);
        $packageType = $this->onedrivePackageType($apiResult);
        if (!$hasRemoteItem && !$hasShared && ($packageType === null || $packageType === '')) {
            return [];
        }

        $metadata = [];
        $normalizedId = $this->onedriveNormalizedId($apiResult, null);
        if ($normalizedId !== null && $normalizedId !== '') {
            $metadata['id'] = $normalizedId;
        }

        $mimeType = $this->onedriveMimeType($apiResult);
        if ($mimeType !== null && $mimeType !== '') {
            $metadata['content-type'] = $mimeType;
        }
        if ($packageType !== null && $packageType !== '') {
            $metadata['package-type'] = $packageType;
        }

        $remoteItem = is_array($apiResult['remoteItem'] ?? null) ? $apiResult['remoteItem'] : [];
        $createdBy = $this->onedriveIdentityUser($remoteItem['createdBy'] ?? $apiResult['createdBy'] ?? null);
        if ($createdBy['id'] !== null && $createdBy['id'] !== '') {
            $metadata['created-by-id'] = $createdBy['id'];
        }
        if ($createdBy['displayName'] !== null && $createdBy['displayName'] !== '') {
            $metadata['created-by-display-name'] = $createdBy['displayName'];
        }

        $lastModifiedBy = $this->onedriveIdentityUser($remoteItem['lastModifiedBy'] ?? $apiResult['lastModifiedBy'] ?? null);
        if ($lastModifiedBy['id'] !== null && $lastModifiedBy['id'] !== '') {
            $metadata['last-modified-by-id'] = $lastModifiedBy['id'];
        }
        if ($lastModifiedBy['displayName'] !== null && $lastModifiedBy['displayName'] !== '') {
            $metadata['last-modified-by-display-name'] = $lastModifiedBy['displayName'];
        }

        $shared = is_array($apiResult['shared'] ?? null) ? $apiResult['shared'] : [];
        if ($shared !== []) {
            $owner = $this->onedriveIdentityUser($shared['owner'] ?? null);
            if ($owner['id'] !== null && $owner['id'] !== '') {
                $metadata['shared-owner-id'] = $owner['id'];
            }

            $sharedBy = $this->onedriveIdentityUser($shared['sharedBy'] ?? $shared['shared_by'] ?? null);
            if ($sharedBy['id'] !== null && $sharedBy['id'] !== '') {
                $metadata['shared-by-id'] = $sharedBy['id'];
            }

            $scope = $this->optionalString($shared['scope'] ?? null);
            if ($scope !== null && $scope !== '') {
                $metadata['shared-scope'] = $scope;
            }

            $sharedTime = $this->optionalString($shared['sharedDateTime'] ?? $shared['shared_date_time'] ?? null);
            if ($sharedTime !== null && $sharedTime !== '') {
                $metadata['shared-time'] = $sharedTime;
            }
        }

        return $metadata;
    }

    /**
     * @return array{id: ?string, displayName: ?string}
     */
    private function onedriveIdentityUser(mixed $identitySet): array
    {
        if (!is_array($identitySet)) {
            return ['id' => null, 'displayName' => null];
        }

        $user = is_array($identitySet['user'] ?? null) ? $identitySet['user'] : [];

        return [
            'id' => $this->optionalString($user['id'] ?? $user['ID'] ?? null),
            'displayName' => $this->optionalString(
                $user['displayName']
                    ?? $user['display_name']
                    ?? $user['DisplayName']
                    ?? null,
            ),
        ];
    }

    /**
     * @param mixed $metadata
     * @return array<string, string>
     */
    private function stringMetadata(mixed $metadata): array
    {
        if (!is_array($metadata)) {
            return [];
        }

        $strings = [];
        foreach ($metadata as $key => $value) {
            if (is_scalar($value) || $value === null) {
                $strings[(string) $key] = (string) $value;
            }
        }

        return $strings;
    }

    private function optionalString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        if (is_scalar($value)) {
            return (string) $value;
        }

        return null;
    }

    private function providerBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if (is_string($value)) {
            return in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true);
        }

        return (bool) $value;
    }

    private function assertRfc3339NanoModTime(string $modTime): void
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d{1,9})?(?:Z|[+-]\d{2}:\d{2})$/', $modTime)) {
            throw new \RuntimeException(sprintf('failed to parse modtime from %s: cannot parse as RFC3339Nano', json_encode($modTime)));
        }

        $parseable = preg_replace('/\.(\d{6})\d+(?=Z|[+-]\d{2}:\d{2}$)/', '.$1', $modTime);
        try {
            new \DateTimeImmutable($parseable);
        } catch (\Exception) {
            throw new \RuntimeException(sprintf('failed to parse modtime from %s: cannot parse as RFC3339Nano', json_encode($modTime)));
        }
    }

    private function providerCopyFailure(string $provider, string $destinationPath, mixed $failure): \RuntimeException
    {
        if (is_string($failure)) {
            return new \RuntimeException($failure);
        }
        if (!is_array($failure)) {
            return new \RuntimeException('server side copy failed');
        }

        $kind = (string) ($failure['kind'] ?? '');
        $message = (string) ($failure['message'] ?? 'server side copy failed');

        return match (strtolower($provider)) {
            'dropbox' => new \RuntimeException('copy failed: ' . $message),
            'onedrive' => $this->onedriveCopyFailure($destinationPath, $kind, $message, $failure),
            'yandex' => $this->yandexCopyFailure($kind, $message, $failure),
            'sugarsync' => $this->sugarsyncCopyFailure($message, $failure),
            default => new \RuntimeException($message),
        };
    }

    /**
     * @param array<string, mixed> $failure
     */
    private function onedriveCopyFailure(string $destinationPath, string $kind, string $message, array $failure): \RuntimeException
    {
        if ($kind === 'async-access-denied') {
            return new \RuntimeException(MemoryProvider::ERROR_CANT_COPY);
        }
        if ($kind === 'missing-location') {
            return new \RuntimeException("didn't receive location header in copy response");
        }
        if ($kind === 'async-status-not-json') {
            $body = (string) ($failure['body'] ?? '');

            return new \RuntimeException(sprintf('async status result not JSON: %s: %s', json_encode($body), $message));
        }
        if ($kind === 'async-status') {
            $status = (string) ($failure['status'] ?? 'failed');

            return new \RuntimeException(sprintf('%s: async operation returned "%s"', $destinationPath, $status));
        }
        if ($kind === 'async-metadata-read') {
            return new \RuntimeException('async operation completed but readMetaData failed: ' . $message);
        }
        if ($kind === 'async-timeout') {
            $duration = (string) ($failure['duration'] ?? $failure['timeout'] ?? 'timeout');

            return new \RuntimeException(sprintf("async operation didn't complete after %s", $duration));
        }

        return new \RuntimeException($message);
    }

    /**
     * @param array<string, mixed> $failure
     */
    private function yandexCopyFailure(string $kind, string $message, array $failure): \RuntimeException
    {
        if ($kind === 'async-info-not-json') {
            $body = (string) ($failure['body'] ?? '');

            return new \RuntimeException(sprintf('couldn\'t copy file: async info result not JSON: %s: %s', json_encode($body), $message));
        }
        if ($kind === 'async-status-not-json') {
            $body = (string) ($failure['body'] ?? '');

            return new \RuntimeException(sprintf('couldn\'t copy file: async status result not JSON: %s: %s', json_encode($body), $message));
        }
        if ($kind === 'async-failure') {
            return new \RuntimeException('couldn\'t copy file: async operation returned "failure"');
        }
        if ($kind === 'async-timeout') {
            $duration = (string) ($failure['duration'] ?? $failure['timeout'] ?? 'timeout');

            return new \RuntimeException(sprintf("couldn't copy file: async operation didn't complete after %s", $duration));
        }

        return new \RuntimeException('couldn\'t copy file: ' . $message);
    }

    /**
     * @param array<string, mixed> $failure
     */
    private function sugarsyncCopyFailure(string $message, array $failure): \RuntimeException
    {
        if (($failure['kind'] ?? '') === 'html-error') {
            $statusCode = (int) ($failure['status'] ?? 500);
            $statusText = (string) ($failure['statusText'] ?? ($statusCode . ' Error'));

            return new \RuntimeException(sprintf('HTTP error %d (%s): %s', $statusCode, $statusText, $message));
        }

        return new \RuntimeException($message);
    }

    /**
     * @return array{usedDirMove: bool, fallbackReason: ?string, moved: list<ObjectInfo>}
     */
    public function moveDirectory(MemoryProvider $provider, string $sourceDir, string $targetDir): array
    {
        $sourceDir = self::normalizePath($sourceDir);
        $targetDir = self::normalizePath($targetDir);

        try {
            $moved = $provider->serverSideDirMove($sourceDir, $targetDir);

            return [
                'usedDirMove' => true,
                'fallbackReason' => null,
                'moved' => [$moved],
            ];
        } catch (\RuntimeException $throwable) {
            if (
                !MemoryProvider::isCantDirMoveException($throwable)
                && !MemoryProvider::isDirExistsException($throwable)
            ) {
                throw $throwable;
            }
            $fallbackReason = $throwable->getMessage();
        }

        $provider->directoryInfo($sourceDir);

        $directories = array_values(array_filter(
            $provider->directories($sourceDir),
            static fn (ObjectInfo $info): bool => self::pathUnderPrefix($info->path, $sourceDir),
        ));
        usort(
            $directories,
            static fn (ObjectInfo $a, ObjectInfo $b): int => self::pathLevel($a->path) <=> self::pathLevel($b->path),
        );
        foreach ($directories as $directory) {
            $provider->mkdir(self::replacePathPrefix($directory->path, $sourceDir, $targetDir), [
                'modTime' => $directory->modTime,
                'mimeType' => $directory->mimeType,
                'metadata' => $directory->metadata,
                'id' => $directory->id,
            ]);
        }

        $objects = array_values(array_filter(
            $provider->list($sourceDir),
            static fn (ObjectInfo $info): bool => self::pathUnderPrefix($info->path, $sourceDir),
        ));

        $moved = [];
        foreach ($objects as $object) {
            $moved[] = $provider->serverSideMoveTo(
                $object->path,
                $provider,
                self::replacePathPrefix($object->path, $sourceDir, $targetDir),
            );
        }

        usort(
            $directories,
            static fn (ObjectInfo $a, ObjectInfo $b): int => self::pathLevel($b->path) <=> self::pathLevel($a->path),
        );
        foreach ($directories as $directory) {
            try {
                $provider->rmdir($directory->path);
            } catch (\RuntimeException) {
            }
        }

        return [
            'usedDirMove' => false,
            'fallbackReason' => $fallbackReason,
            'moved' => $moved,
        ];
    }

    /**
     * @param array<string, mixed> $fileOptions
     * @param array<string, mixed> $stats
     * @return array{
     *     usedDirMove: bool,
     *     fallbackReason: ?string,
     *     moved: list<ObjectInfo>,
     *     createdDirectories: list<ObjectInfo>,
     *     prunedSourceDirectories: list<ObjectInfo>,
     *     fileResults: list<array<string, mixed>>
     * }
     */
    private function moveDirectoryCommand(
        MemoryProvider $destination,
        MemoryProvider $source,
        string $sourceDir,
        string $destinationDir,
        bool $deleteEmptySrcDirs,
        bool $createEmptySrcDirs,
        ?FilterRuleSet $filter,
        array $fileOptions,
        array &$stats,
    ): array {
        $sourceDir = self::normalizePath($sourceDir);
        $destinationDir = self::normalizePath($destinationDir);
        $stats['destinationPath'] = $destinationDir;
        $stats['deleteEmptySrcDirs'] = $deleteEmptySrcDirs;
        $stats['createEmptySrcDirs'] = $createEmptySrcDirs;
        $dryRun = (bool) ($fileOptions['dryRun'] ?? false);

        if ($source === $destination && $sourceDir === $destinationDir) {
            $stats['skipped'] = true;

            return [
                'usedDirMove' => false,
                'fallbackReason' => 'source and destination are the same',
                'moved' => [],
                'createdDirectories' => [],
                'prunedSourceDirectories' => [],
                'fileResults' => [],
            ];
        }

        if ($filter === null && $source === $destination) {
            if ($dryRun) {
                $source->directoryInfo($sourceDir);
                $stats['dryRunSkipped']++;
                $stats['dryRunDirMove'] = true;

                return [
                    'usedDirMove' => false,
                    'fallbackReason' => null,
                    'moved' => [],
                    'createdDirectories' => [],
                    'prunedSourceDirectories' => [],
                    'fileResults' => [],
                    'dryRunDirMove' => true,
                ];
            }

            try {
                $moved = $source->serverSideDirMove($sourceDir, $destinationDir);
                $stats['usedDirMove'] = true;
                $stats['filesMoved'] = 1;

                return [
                    'usedDirMove' => true,
                    'fallbackReason' => null,
                    'moved' => [$moved],
                    'createdDirectories' => [],
                    'prunedSourceDirectories' => [],
                    'fileResults' => [],
                ];
            } catch (\RuntimeException $throwable) {
                if (
                    !MemoryProvider::isCantDirMoveException($throwable)
                    && !MemoryProvider::isDirExistsException($throwable)
                ) {
                    throw $throwable;
                }
                $stats['fallbackReason'] = $throwable->getMessage();
            }
        }

        $createdDirectories = $createEmptySrcDirs
            ? $this->createCommandDirectoryPlaceholders($destination, $source, $sourceDir, $destinationDir, $filter, $dryRun, $stats)
            : [];
        $stats['createdDirectories'] = count($createdDirectories);

        $moved = [];
        $fileResults = [];
        $sourceObjects = array_values(array_filter(
            $source->list($sourceDir),
            static fn (ObjectInfo $info): bool => self::pathUnderPrefix($info->path, $sourceDir),
        ));
        foreach ($sourceObjects as $sourceInfo) {
            if ($filter !== null && !$filter->includes($sourceInfo->path)) {
                continue;
            }

            $destinationPath = self::joinPath(
                $destinationDir,
                self::relativePathUnderRoot($sourceInfo->path, $sourceDir),
            );
            $fileResult = $this->moveFile($destination, $source, $destinationPath, $sourceInfo->path, $fileOptions);
            $fileResults[] = $fileResult;
            $this->recordMoveCommandFileStats($stats, $fileResult);
            if ($fileResult['moved'] instanceof ObjectInfo) {
                $moved[] = $fileResult['moved'];
            }
        }

        $prunedSourceDirectories = [];
        if (!$dryRun && $deleteEmptySrcDirs && $this->directoryExists($source, $sourceDir)) {
            $prunedSourceDirectories = $this->pruneMovedSourceDirectories($source, $sourceDir);
        }
        $stats['prunedSourceDirectories'] = count($prunedSourceDirectories);

        return [
            'usedDirMove' => false,
            'fallbackReason' => $stats['fallbackReason'],
            'moved' => $moved,
            'createdDirectories' => $createdDirectories,
            'prunedSourceDirectories' => $prunedSourceDirectories,
            'fileResults' => $fileResults,
        ];
    }

    /**
     * @param array<string, mixed> $fileOptions
     * @param array<string, mixed> $stats
     * @return array{
     *     copied: list<ObjectInfo>,
     *     createdDirectories: list<ObjectInfo>,
     *     fileResults: list<array<string, mixed>>
     * }
     */
    private function copyDirectoryCommand(
        MemoryProvider $destination,
        MemoryProvider $source,
        string $sourceDir,
        string $destinationDir,
        bool $createEmptySrcDirs,
        ?FilterRuleSet $filter,
        array $fileOptions,
        array &$stats,
    ): array {
        $sourceDir = self::normalizePath($sourceDir);
        $destinationDir = self::normalizePath($destinationDir);
        $stats['destinationPath'] = $destinationDir;
        $stats['createEmptySrcDirs'] = $createEmptySrcDirs;
        $dryRun = (bool) ($fileOptions['dryRun'] ?? false);

        $createdDirectories = $createEmptySrcDirs
            ? $this->createCommandDirectoryPlaceholders($destination, $source, $sourceDir, $destinationDir, $filter, $dryRun, $stats)
            : [];
        $stats['createdDirectories'] = count($createdDirectories);

        $copied = [];
        $fileResults = [];
        $sourceObjects = array_values(array_filter(
            $source->list($sourceDir),
            static fn (ObjectInfo $info): bool => self::pathUnderPrefix($info->path, $sourceDir),
        ));
        foreach ($sourceObjects as $sourceInfo) {
            if ($filter !== null && !$filter->includes($sourceInfo->path)) {
                continue;
            }

            $destinationPath = self::joinPath(
                $destinationDir,
                self::relativePathUnderRoot($sourceInfo->path, $sourceDir),
            );
            $fileResult = $this->copyFile($destination, $source, $destinationPath, $sourceInfo->path, $fileOptions);
            $fileResults[] = $fileResult;
            $this->recordCopyCommandFileStats($stats, $fileResult);
            if ($fileResult['copied'] instanceof ObjectInfo) {
                $copied[] = $fileResult['copied'];
            }
        }

        return [
            'copied' => $copied,
            'createdDirectories' => $createdDirectories,
            'fileResults' => $fileResults,
        ];
    }

    /**
     * @return list<ObjectInfo>
     */
    private function createCommandDirectoryPlaceholders(
        MemoryProvider $destination,
        MemoryProvider $source,
        string $sourceDir,
        string $destinationDir,
        ?FilterRuleSet $filter,
        bool $dryRun,
        array &$stats,
    ): array {
        $created = [];
        foreach ($source->directories($sourceDir) as $directory) {
            if ($directory->path === $sourceDir || !self::pathUnderPrefix($directory->path, $sourceDir)) {
                continue;
            }
            if (!$this->filterAllowsDirectory($source, $directory->path, $filter)) {
                continue;
            }

            if ($dryRun) {
                $stats['dryRunSkipped']++;
                $stats['dryRunDirectoriesSkipped']++;
                continue;
            }

            $created[] = $destination->mkdir(
                self::joinPath(
                    $destinationDir,
                    self::relativePathUnderRoot($directory->path, $sourceDir),
                ),
                [
                    'modTime' => $directory->modTime,
                    'mimeType' => $directory->mimeType,
                    'metadata' => $directory->metadata,
                    'id' => $directory->id,
                    'parentId' => $directory->parentId,
                ],
            );
        }

        return $created;
    }

    /**
     * @return array<string, mixed>
     */
    private function copyCommandStats(string $command, string $sourceRemote, string $destinationRemote): array
    {
        return [
            'command' => $command,
            'sourceRemote' => $sourceRemote,
            'destinationRemote' => $destinationRemote,
            'sourceType' => null,
            'destinationPath' => null,
            'filesCopied' => 0,
            'filesSkipped' => 0,
            'backupsMoved' => 0,
            'backupRenames' => 0,
            'backupExistingDeletes' => 0,
            'backupExistingDeleteBytes' => 0,
            'backupCheckingTransfers' => 0,
            'createdDirectories' => 0,
            'createEmptySrcDirs' => false,
            'dryRunSkipped' => 0,
            'dryRunDirectoriesSkipped' => 0,
            'destructiveSkipped' => 0,
        ];
    }

    /**
     * @param array<string, mixed> $stats
     * @param array<string, mixed> $file
     */
    private function recordCopyCommandFileStats(array &$stats, array $file): void
    {
        if (($file['copied'] ?? null) instanceof ObjectInfo) {
            $stats['filesCopied']++;
        }
        if ((bool) ($file['skipped'] ?? false)) {
            $stats['filesSkipped']++;
        }
        if (($file['backup'] ?? null) instanceof ObjectInfo) {
            $stats['backupsMoved']++;
        }
        $this->recordBackupAccountingStats($stats, $file);
        $stats['dryRunSkipped'] += $this->dryRunActionCount($file);
        $stats['destructiveSkipped'] += $this->destructiveSkipActionCount($file);
    }

    /**
     * @return list<ObjectInfo>
     */
    private function pruneMovedSourceDirectories(MemoryProvider $source, string $sourceDir): array
    {
        $candidates = array_values(array_filter(
            $source->directories($sourceDir),
            static fn (ObjectInfo $directory): bool => $directory->path !== $sourceDir
                && self::pathUnderPrefix($directory->path, $sourceDir),
        ));

        return $this->pruneEmptyDirectoryCandidates($source, $candidates, '');
    }

    /**
     * @return array<string, mixed>
     */
    private function moveCommandStats(string $command, string $sourceRemote, string $destinationRemote): array
    {
        return [
            'command' => $command,
            'sourceRemote' => $sourceRemote,
            'destinationRemote' => $destinationRemote,
            'sourceType' => null,
            'destinationPath' => null,
            'usedDirMove' => false,
            'fallbackReason' => null,
            'filesMoved' => 0,
            'filesDeletedFromSource' => 0,
            'filesSkipped' => 0,
            'backupsMoved' => 0,
            'backupRenames' => 0,
            'backupExistingDeletes' => 0,
            'backupExistingDeleteBytes' => 0,
            'backupCheckingTransfers' => 0,
            'createdDirectories' => 0,
            'prunedSourceDirectories' => 0,
            'deleteEmptySrcDirs' => false,
            'createEmptySrcDirs' => false,
            'skipped' => false,
            'dryRunSkipped' => 0,
            'dryRunDirectoriesSkipped' => 0,
            'dryRunDirMove' => false,
            'destructiveSkipped' => 0,
        ];
    }

    /**
     * @param array<string, mixed> $stats
     * @param array<string, mixed> $file
     */
    private function recordMoveCommandFileStats(array &$stats, array $file): void
    {
        if (($file['moved'] ?? null) instanceof ObjectInfo || ($file['copied'] ?? null) instanceof ObjectInfo) {
            $stats['filesMoved']++;
        }
        if (($file['deletedSource'] ?? null) instanceof ObjectInfo) {
            $stats['filesDeletedFromSource']++;
        }
        if ((bool) ($file['skipped'] ?? false)) {
            $stats['filesSkipped']++;
        }
        if (($file['backup'] ?? null) instanceof ObjectInfo) {
            $stats['backupsMoved']++;
        }
        $this->recordBackupAccountingStats($stats, $file);
        $stats['dryRunSkipped'] += $this->dryRunActionCount($file);
        $stats['destructiveSkipped'] += $this->destructiveSkipActionCount($file);
    }

    /**
     * @param array<string, mixed> $stats
     * @param array<string, mixed> $file
     */
    private function recordBackupAccountingStats(array &$stats, array $file): void
    {
        $accounting = $file['backupAccounting'] ?? null;
        if (!is_array($accounting)) {
            return;
        }

        $stats['backupRenames'] += (int) ($accounting['renames'] ?? 0);
        $stats['backupExistingDeletes'] += (int) ($accounting['deletedFiles'] ?? 0);
        $stats['backupExistingDeleteBytes'] += (int) ($accounting['deletedBytes'] ?? 0);
        $stats['backupCheckingTransfers'] += (int) ($accounting['checkingTransfers'] ?? 0);
    }

    /**
     * @param array{
     *     backup?: MemoryProvider|null,
     *     backupPrefix?: string,
     *     suffix?: string,
     *     suffixKeepExtension?: bool,
     *     compareDest?: list<MemoryProvider>,
     *     copyDest?: list<MemoryProvider>,
     *     noCheckDest?: bool,
     *     ignoreExisting?: bool,
     *     immutable?: bool,
     *     ignoreTimes?: bool,
     *     updateOlder?: bool,
     *     noUpdateModTime?: bool,
     *     modifyWindowSeconds?: int,
     *     checksum?: bool,
     *     refreshTimes?: bool,
     *     partialUploads?: bool,
     *     partialSuffix?: string,
     *     simulatePartialTransferError?: bool,
     *     metadataSet?: array<string, scalar|null>,
     *     dryRun?: bool,
     *     interactive?: bool,
     *     interactiveChoice?: mixed
     * } $options
     * @return array{copied: ?ObjectInfo, moved: ?ObjectInfo, deletedSource: ?ObjectInfo, backup: ?ObjectInfo, skipped: bool, caseInsensitiveMove: bool, partialPath: ?string, cleanedPartial: bool}
     */
    private function moveOrCopyFile(
        MemoryProvider $destination,
        MemoryProvider $source,
        string $destinationPath,
        string $sourcePath,
        bool $copy,
        array $options,
    ): array {
        $destinationPath = self::normalizePath($destinationPath);
        $sourcePath = self::normalizePath($sourcePath);
        $result = $this->fileOperationResult();
        $dryRun = (bool) ($options['dryRun'] ?? false);

        if ($source === $destination && $sourcePath === $destinationPath) {
            $result['skipped'] = true;
            $result['logEvents'][] = [
                'level' => 'debug',
                'path' => $destinationPath,
                'message' => "don't need to copy/move {$destinationPath}, it is already at target location",
            ];
            $result['loggerEvents'][] = [
                'type' => 'match',
                'sourcePath' => $sourcePath,
                'destinationPath' => $destinationPath,
                'error' => null,
            ];

            return $result;
        }

        $sourceInfo = $source->info($sourcePath);
        if (!$copy && $this->needsCaseInsensitiveFileMove($destination, $source, $destinationPath, $sourcePath)) {
            $action = 'rename to ' . $destinationPath;
            if ($this->skipDestructive($options, $sourceInfo, $action)) {
                return $this->withSkippedDestructiveAction($result, $action, $options);
            }

            $result['moved'] = $this->moveCaseInsensitiveFile($destination, $destinationPath, $sourcePath);
            if (isset($options['metadataSet'])) {
                $result['moved'] = $destination->setObjectMetadata($result['moved']->path, $options['metadataSet']);
            }
            $result['caseInsensitiveMove'] = true;

            return $result;
        }

        $noCheckDest = (bool) ($options['noCheckDest'] ?? false);
        $targetInfo = $noCheckDest ? null : $this->optionalInfo($destination, $destinationPath);
        if (!$noCheckDest && $targetInfo !== null && (bool) ($options['ignoreExisting'] ?? false)) {
            $result['skipped'] = true;

            return $result;
        }

        $needsTransfer = $this->needsTransfer(
            $source,
            $destination,
            $sourceInfo,
            $targetInfo,
            (bool) ($options['ignoreTimes'] ?? false),
            (bool) ($options['updateOlder'] ?? false),
            (bool) ($options['noUpdateModTime'] ?? false),
            (int) ($options['modifyWindowSeconds'] ?? 1),
            (bool) ($options['checksum'] ?? false),
            (bool) ($options['immutable'] ?? false),
            (bool) ($options['refreshTimes'] ?? false),
        );

        if (!$needsTransfer) {
            if (!$copy && $targetInfo !== null && !$this->sameProviderObject($source, $sourceInfo, $destination, $targetInfo)) {
                if ($this->skipDestructive($options, $sourceInfo, 'delete source')) {
                    return $this->withSkippedDestructiveAction($result, 'delete source', $options);
                }

                $result['deletedSource'] = $source->delete($sourceInfo->path);
            } else {
                $result['skipped'] = true;
            }

            return $result;
        }

        if ($this->findEqualReference($sourceInfo, $targetInfo, $options['compareDest'] ?? []) !== null) {
            if (!$copy && $targetInfo !== null && !$this->sameProviderObject($source, $sourceInfo, $destination, $targetInfo)) {
                if ($this->skipDestructive($options, $sourceInfo, 'delete source')) {
                    return $this->withSkippedDestructiveAction($result, 'delete source', $options);
                }

                $result['deletedSource'] = $source->delete($sourceInfo->path);
            } else {
                $result['skipped'] = true;
            }

            return $result;
        }

        $backup = $options['backup'] ?? null;
        $backupPrefix = (string) ($options['backupPrefix'] ?? '');
        $suffix = (string) ($options['suffix'] ?? '');
        $suffixKeepExtension = (bool) ($options['suffixKeepExtension'] ?? false);
        $copyDestReference = $this->findEqualReference($sourceInfo, $targetInfo, $options['copyDest'] ?? []);
        if ($copyDestReference !== null) {
            if ($dryRun) {
                if ($targetInfo !== null && $this->backupRequested($backup, $backupPrefix, $suffix)) {
                    $result = $this->withDryRunAction($result, 'move into backup dir');
                }
                $result = $this->withDryRunAction(
                    $result,
                    $copy ? 'copy from copy-dest to ' . $destinationPath : 'move from copy-dest to ' . $destinationPath,
                );
                if (!$copy) {
                    $result = $this->withDryRunAction($result, 'delete source');
                }

                return $result;
            }

            if ($targetInfo !== null && $this->backupRequested($backup, $backupPrefix, $suffix)) {
                $backupDiagnostics = null;
                $result['backup'] = $this->moveToBackup(
                    $destination,
                    $targetInfo->path,
                    $backup,
                    $backupPrefix,
                    $suffix,
                    $suffixKeepExtension,
                    $backupDiagnostics,
                );
                $result = $this->withBackupDiagnostics($result, $backupDiagnostics);
                $targetInfo = null;
            }
            $this->assertMaxTransferAllows($sourceInfo, $options);
            $copied = $copyDestReference['provider']->copyTo(
                $copyDestReference['path'],
                $destination,
                $targetInfo?->path ?? $destinationPath,
                $options,
            );
            if ($copy) {
                $result['copied'] = $copied;
            } else {
                $result['moved'] = $copied;
                if (!$this->sameProviderObject($source, $sourceInfo, $destination, $copied)) {
                    $result['deletedSource'] = $source->delete($sourceInfo->path);
                }
            }

            return $result;
        }

        if ($targetInfo !== null && (bool) ($options['immutable'] ?? false)) {
            throw new \RuntimeException('immutable file modified');
        }

        if ($dryRun) {
            if ($targetInfo !== null && $this->backupRequested($backup, $backupPrefix, $suffix)) {
                $result = $this->withDryRunAction($result, 'move into backup dir');
            }

            return $this->withDryRunAction(
                $result,
                $copy ? 'copy to ' . $destinationPath : 'move to ' . $destinationPath,
            );
        }

        if ($targetInfo !== null && $this->backupRequested($backup, $backupPrefix, $suffix)) {
            if ($this->skipDestructive($options, $targetInfo, 'move into backup dir')) {
                $result = $this->withSkippedDestructiveAction($result, 'move into backup dir', $options, false);
            } else {
                $backupDiagnostics = null;
                $result['backup'] = $this->moveToBackup(
                    $destination,
                    $targetInfo->path,
                    $backup,
                    $backupPrefix,
                    $suffix,
                    $suffixKeepExtension,
                    $backupDiagnostics,
                );
                $result = $this->withBackupDiagnostics($result, $backupDiagnostics);
            }
            $targetInfo = null;
        }

        $transferAction = $copy ? 'copy to ' . $destinationPath : 'move to ' . $destinationPath;
        if ($this->skipDestructive($options, $sourceInfo, $transferAction)) {
            return $this->withSkippedDestructiveAction($result, $transferAction, $options);
        }

        if ($copy) {
            try {
                $copyResult = $this->copyFileObject(
                    $source,
                    $sourceInfo->path,
                    $destination,
                    $targetInfo?->path ?? $destinationPath,
                    $options,
                );
            } catch (\RuntimeException $throwable) {
                $result['cleanedPartial'] = true;
                throw $throwable;
            }
            $result['copied'] = $copyResult['object'];
            $result['partialPath'] = $copyResult['partialPath'];

            return $result;
        }

        if ($source === $destination) {
            $result['moved'] = $source->serverSideMoveTo(
                $sourceInfo->path,
                $destination,
                $targetInfo?->path ?? $destinationPath,
                $options,
            );

            return $result;
        }

        try {
            $copyResult = $this->copyFileObject(
                $source,
                $sourceInfo->path,
                $destination,
                $targetInfo?->path ?? $destinationPath,
                $options,
            );
        } catch (\RuntimeException $throwable) {
            $result['cleanedPartial'] = true;
            throw $throwable;
        }
        $result['moved'] = $copyResult['object'];
        $result['partialPath'] = $copyResult['partialPath'];
        if (!$this->sameProviderObject($source, $sourceInfo, $destination, $copyResult['object'])) {
            $result['deletedSource'] = $source->delete($sourceInfo->path);
        }

        return $result;
    }

    /**
     * @return array{copied: ?ObjectInfo, moved: ?ObjectInfo, deletedSource: ?ObjectInfo, backup: ?ObjectInfo, skipped: bool, caseInsensitiveMove: bool, partialPath: ?string, cleanedPartial: bool}
     */
    private function fileOperationResult(): array
    {
        return [
            'copied' => null,
            'moved' => null,
            'deletedSource' => null,
            'backup' => null,
            'skipped' => false,
            'caseInsensitiveMove' => false,
            'partialPath' => null,
            'cleanedPartial' => false,
            'dryRun' => false,
            'dryRunActions' => [],
            'skippedDestructive' => false,
            'skippedActions' => [],
            'accounting' => $this->emptyFileAccounting(),
            'backupAccounting' => null,
            'logEvents' => [],
            'loggerEvents' => [],
        ];
    }

    /**
     * @return array{checkingTransfers: int, renames: int, deletedFiles: int, deletedBytes: int, serverSideMoves: int}
     */
    private function emptyFileAccounting(): array
    {
        return [
            'checkingTransfers' => 0,
            'renames' => 0,
            'deletedFiles' => 0,
            'deletedBytes' => 0,
            'serverSideMoves' => 0,
        ];
    }

    /**
     * @param array<string, mixed> $result
     * @param array<string, mixed>|null $diagnostics
     * @return array<string, mixed>
     */
    private function withBackupDiagnostics(array $result, ?array $diagnostics): array
    {
        if ($diagnostics === null) {
            return $result;
        }

        $accounting = $diagnostics['accounting'] ?? $this->emptyFileAccounting();
        $result['backupAccounting'] = $accounting;
        foreach ($accounting as $key => $value) {
            if (array_key_exists($key, $result['accounting'])) {
                $result['accounting'][$key] += (int) $value;
            }
        }

        $result['logEvents'] = array_merge($result['logEvents'], $diagnostics['logEvents'] ?? []);
        $result['loggerEvents'] = array_merge($result['loggerEvents'], $diagnostics['loggerEvents'] ?? []);

        return $result;
    }

    /**
     * @param array<string, mixed> $result
     * @return array<string, mixed>
     */
    private function withDryRunAction(array $result, string $action): array
    {
        $result['dryRun'] = true;
        $result['dryRunActions'][] = $action;

        return $result;
    }

    /**
     * @param array<string, mixed> $result
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    private function withSkippedDestructiveAction(
        array $result,
        string $action,
        array $options,
        bool $operationSkipped = true,
    ): array {
        if ((bool) ($options['dryRun'] ?? false)) {
            return $this->withDryRunAction($result, $action);
        }

        $result['skippedDestructive'] = true;
        $result['skippedActions'][] = $action;
        if ($operationSkipped) {
            $result['skipped'] = true;
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $file
     */
    private function dryRunActionCount(array $file): int
    {
        if (isset($file['dryRunActions']) && is_array($file['dryRunActions'])) {
            return count($file['dryRunActions']);
        }

        return (bool) ($file['dryRun'] ?? false) ? 1 : 0;
    }

    /**
     * @param array<string, mixed> $file
     */
    private function destructiveSkipActionCount(array $file): int
    {
        if (isset($file['skippedActions']) && is_array($file['skippedActions'])) {
            return count($file['skippedActions']);
        }

        return (bool) ($file['skippedDestructive'] ?? false) ? 1 : 0;
    }

    /**
     * @param array<string, mixed> $options
     */
    private function skipDestructive(array $options, ObjectInfo|string|null $subject, string $action): bool
    {
        if ((bool) ($options['dryRun'] ?? false)) {
            return true;
        }
        if (!((bool) ($options['interactive'] ?? false) || array_key_exists('interactiveChoice', $options))) {
            return false;
        }
        if (array_key_exists($action, $this->interactiveDestructiveSkips)) {
            return $this->interactiveDestructiveSkips[$action];
        }
        if (!array_key_exists('interactiveChoice', $options)) {
            throw new \InvalidArgumentException('interactive destructive mode requires a caller-supplied choice');
        }

        return $this->resolveDestructiveChoice(
            $this->destructiveChoice($options['interactiveChoice'], $subject, $action),
            $action,
        );
    }

    private function destructiveChoice(mixed $choiceSource, ObjectInfo|string|null $subject, string $action): mixed
    {
        if (is_callable($choiceSource)) {
            return $choiceSource([
                'action' => $action,
                'subject' => $subject,
                'path' => $subject instanceof ObjectInfo ? $subject->path : (is_string($subject) ? $subject : null),
                'size' => $subject instanceof ObjectInfo ? $subject->size : null,
            ]);
        }
        if (is_array($choiceSource)) {
            return $choiceSource[$action] ?? $choiceSource['default'] ?? 'n';
        }

        return $choiceSource;
    }

    private function resolveDestructiveChoice(mixed $choice, string $action): bool
    {
        if (is_array($choice)) {
            $choice = $choice['choice'] ?? $choice['command'] ?? $choice['action'] ?? '';
        }

        $normalized = strtolower(trim((string) $choice));
        return match ($normalized) {
            'y', 'yes' => false,
            'n', 'no' => true,
            's', 'skip', 'skip-all', 'skip all' => $this->cacheDestructiveChoice($action, true),
            '!', 'do', 'all', 'do-all', 'yes-all' => $this->cacheDestructiveChoice($action, false),
            'q', 'quit', 'exit' => throw new \RuntimeException('interactive destructive choice requested quit'),
            default => true,
        };
    }

    private function cacheDestructiveChoice(string $action, bool $skip): bool
    {
        $this->interactiveDestructiveSkips[$action] = $skip;

        return $skip;
    }

    private function needsCaseInsensitiveFileMove(
        MemoryProvider $destination,
        MemoryProvider $source,
        string $destinationPath,
        string $sourcePath,
    ): bool {
        return $source === $destination
            && $destination->isCaseInsensitive()
            && $destinationPath !== $sourcePath
            && strtolower($destinationPath) === strtolower($sourcePath);
    }

    private function moveCaseInsensitiveFile(MemoryProvider $provider, string $destinationPath, string $sourcePath): ObjectInfo
    {
        $temporaryPath = $destinationPath . '-rclone-move-' . substr(hash('sha256', $sourcePath . "\0" . $destinationPath), 0, 8);
        if ($this->optionalInfo($provider, $temporaryPath) !== null) {
            throw new \RuntimeException('found an already existing file with a randomly generated name. Try the operation again');
        }

        $temporary = $provider->serverSideMoveTo($sourcePath, $provider, $temporaryPath);

        return $provider->serverSideMoveTo($temporary->path, $provider, $destinationPath);
    }

    /**
     * @param array{partialUploads?: bool, partialSuffix?: string, simulatePartialTransferError?: bool, metadataSet?: array<string, scalar|null>, maxTransfer?: int, cutoffMode?: string, bytesTransferredSoFar?: int} $options
     * @return array{object: ObjectInfo, partialPath: ?string}
     */
    private function copyFileObject(
        MemoryProvider $source,
        string $sourcePath,
        MemoryProvider $destination,
        string $destinationPath,
        array $options,
    ): array {
        $sourceInfo = $source->info($sourcePath);
        $this->assertMaxTransferAllows($sourceInfo, $options);

        $partialPath = null;
        $copyPath = $destinationPath;
        if (
            (bool) ($options['partialUploads'] ?? false)
            && $destination->supportsDirectServerSideMove()
            && !str_ends_with($destinationPath, '.rclonelink')
        ) {
            $partialSuffix = (string) ($options['partialSuffix'] ?? '.partial');
            if (strlen($partialSuffix) > 16) {
                throw new \RuntimeException('expecting length of --partial-suffix to be not greater than 16 but got ' . strlen($partialSuffix));
            }
            $partialPath = $this->partialCopyPath($destinationPath, $sourceInfo, $partialSuffix);
            $copyPath = $partialPath;
        }

        $copied = $source->copyTo($sourcePath, $destination, $copyPath, $options);
        if ((bool) ($options['simulatePartialTransferError'] ?? false)) {
            if ($partialPath !== null) {
                $destination->delete($partialPath);
            }
            throw new \RuntimeException('failed to copy: simulated partial transfer error');
        }

        if ($partialPath !== null && $partialPath !== $destinationPath) {
            $copied = $destination->serverSideMoveTo($partialPath, $destination, $destinationPath);
        }

        return [
            'object' => $copied,
            'partialPath' => $partialPath,
        ];
    }

    /**
     * @param array{maxTransfer?: int, cutoffMode?: string, bytesTransferredSoFar?: int} $options
     */
    private function assertMaxTransferAllows(ObjectInfo $sourceInfo, array $options): void
    {
        if (!array_key_exists('maxTransfer', $options)) {
            return;
        }

        $maxTransfer = (int) $options['maxTransfer'];
        if ($maxTransfer < 0) {
            return;
        }

        $mode = $this->normalizeCutoffMode((string) ($options['cutoffMode'] ?? 'hard'));
        $bytesSoFar = max(0, (int) ($options['bytesTransferredSoFar'] ?? 0));
        $sourceSize = max(0, $sourceInfo->size);

        $alreadyAtLimit = $bytesSoFar >= $maxTransfer;
        $wouldPassLimit = $bytesSoFar + $sourceSize > $maxTransfer;
        $wouldReachLimit = $bytesSoFar + $sourceSize >= $maxTransfer;

        if ($alreadyAtLimit || ($mode === 'hard' && $wouldPassLimit) || ($mode === 'cautious' && $wouldReachLimit)) {
            throw new \RuntimeException('max transfer limit reached as set by --max-transfer');
        }
    }

    private function normalizeCutoffMode(string $mode): string
    {
        $mode = strtolower(trim($mode));

        return match ($mode) {
            '', 'hard' => 'hard',
            'soft' => 'soft',
            'cautious' => 'cautious',
            default => throw new \InvalidArgumentException('unknown cutoff mode "' . $mode . '"'),
        };
    }

    private function partialCopyPath(string $destinationPath, ObjectInfo $sourceInfo, string $partialSuffix): string
    {
        $suffix = sprintf(
            '.%08x%s',
            crc32($destinationPath . "\0" . $sourceInfo->size . "\0" . $sourceInfo->sha256),
            $partialSuffix,
        );
        $base = self::pathBase($destinationPath);
        if (strlen($base) <= 100) {
            return $destinationPath . $suffix;
        }

        return substr($destinationPath, 0, max(0, strlen($destinationPath) - strlen($suffix))) . $suffix;
    }

    private static function temporaryExistingPath(string $path, string $suffix): string
    {
        $base = self::pathBase($path);
        if (strlen($base) <= 100) {
            return $path . $suffix;
        }

        return self::truncateValidUtf8($path, max(0, strlen($path) - strlen($suffix))) . $suffix;
    }

    private static function truncateValidUtf8(string $value, int $bytes): string
    {
        $truncated = substr($value, 0, $bytes);
        if (@preg_match('//u', $value) !== 1) {
            return $truncated;
        }

        while ($truncated !== '' && @preg_match('//u', $truncated) !== 1) {
            $truncated = substr($truncated, 0, -1);
        }

        return $truncated;
    }

    private function sameProviderObject(
        MemoryProvider $source,
        ObjectInfo $sourceInfo,
        MemoryProvider $destination,
        ObjectInfo $targetInfo,
    ): bool {
        if ($source === $destination) {
            if ($sourceInfo->path === $targetInfo->path) {
                return true;
            }
            if ($source->isCaseInsensitive() && strtolower($sourceInfo->path) === strtolower($targetInfo->path)) {
                return true;
            }
        }

        return $sourceInfo->id !== null
            && $sourceInfo->id !== ''
            && $sourceInfo->id === $targetInfo->id;
    }

    /**
     * @param callable(array<string, mixed>): array<string, mixed>|string $chooser
     * @param array<string, mixed> $context
     * @return array{action: string, keepIndex: ?int}
     */
    private function interactiveDedupeDecision(callable $chooser, array $context, int $count, bool $byHash): array
    {
        $choice = $chooser($context);
        $keep = null;
        if (is_string($choice)) {
            $action = $choice;
        } elseif (is_array($choice)) {
            $action = (string) ($choice['action'] ?? $choice['command'] ?? '');
            if (array_key_exists('keep', $choice)) {
                $keep = (int) $choice['keep'];
            } elseif (array_key_exists('keepNumber', $choice)) {
                $keep = (int) $choice['keepNumber'];
            } elseif (array_key_exists('index', $choice)) {
                $keep = (int) $choice['index'] + 1;
            }
        } else {
            throw new \InvalidArgumentException('interactive dedupe choice must be a command string or decision array');
        }

        $action = match (strtolower(trim($action))) {
            's', 'skip' => 'skip',
            'k', 'keep' => 'keep',
            'r', 'rename' => 'rename',
            'q', 'quit' => 'quit',
            default => throw new \InvalidArgumentException('unknown interactive dedupe choice "' . (string) $action . '"'),
        };

        if ($action === 'rename' && $byHash) {
            throw new \InvalidArgumentException('interactive dedupe by hash does not offer rename');
        }
        if ($action !== 'keep') {
            return [
                'action' => $action,
                'keepIndex' => null,
            ];
        }
        if ($keep === null) {
            throw new \InvalidArgumentException('interactive keep choice requires a 1-based keep number');
        }
        if ($keep < 1 || $keep > $count) {
            throw new \OutOfRangeException("interactive keep number {$keep} is outside 1..{$count}");
        }

        return [
            'action' => 'keep',
            'keepIndex' => $keep - 1,
        ];
    }

    /**
     * @param list<ObjectInfo> $objects
     * @return array{kept: ObjectInfo, deleted: list<ObjectInfo>}
     */
    private function deleteDedupeObjectsExcept(MemoryProvider $provider, array $objects, int $keepIndex): array
    {
        if (!isset($objects[$keepIndex])) {
            throw new \OutOfRangeException('dedupe keep index is outside the duplicate group');
        }

        $deleted = [];
        foreach ($objects as $index => $info) {
            if ($index === $keepIndex) {
                continue;
            }
            $deleted[] = $provider->deleteListedObject($info);
        }

        return [
            'kept' => $objects[$keepIndex],
            'deleted' => $deleted,
        ];
    }

    /**
     * @param list<ObjectInfo> $objects
     * @return list<ObjectInfo>
     */
    private function dedupeOrderedObjects(array $objects, string $mode): array
    {
        $ordered = $objects;
        if ($mode === DeduplicateMode::NEWEST || $mode === DeduplicateMode::OLDEST) {
            usort(
                $ordered,
                fn (ObjectInfo $a, ObjectInfo $b): int => $this->dedupeObjectTimestamp($a) <=> $this->dedupeObjectTimestamp($b)
                    ?: $a->path <=> $b->path,
            );
        } elseif ($mode === DeduplicateMode::LARGEST || $mode === DeduplicateMode::SMALLEST) {
            usort(
                $ordered,
                static fn (ObjectInfo $a, ObjectInfo $b): int => $a->size <=> $b->size
                    ?: $a->path <=> $b->path,
            );
        }

        return $ordered;
    }

    private function dedupeObjectTimestamp(ObjectInfo $info): float
    {
        return $this->timestamp($info->modTime) ?? 0.0;
    }

    private function dedupeEntryId(ObjectInfo $info): string
    {
        return $info->id !== null && $info->id !== '' ? $info->id : $info->path;
    }

    private function dedupeEntryParentId(ObjectInfo $info): string
    {
        return $info->parentId !== null && $info->parentId !== '' ? $info->parentId : self::parentPath($info->path);
    }

    /**
     * @param array<string, array{directory: ?ObjectInfo, parent: string, count: int}> $dirsById
     */
    private function incrementDedupeDirectoryCount(array &$dirsById, string $parent): void
    {
        while ($parent !== '') {
            $dirsById[$parent] ??= [
                'directory' => null,
                'parent' => '',
                'count' => 0,
            ];
            $dirsById[$parent]['count']++;
            $parent = $dirsById[$parent]['parent'];
        }
    }

    /**
     * @param list<ObjectInfo> $objects
     * @return array{remaining: list<ObjectInfo>, deleted: list<ObjectInfo>}
     */
    private function deleteIdenticalDuplicateNames(MemoryProvider $provider, array $objects, bool $sizeOnly): array
    {
        $idCounts = [];
        foreach ($objects as $info) {
            if ($info->id !== null && $info->id !== '') {
                $idCounts[$info->id] = ($idCounts[$info->id] ?? 0) + 1;
            }
        }

        $eligible = [];
        foreach ($objects as $info) {
            if ($info->id !== null && $info->id !== '' && ($idCounts[$info->id] ?? 0) > 1) {
                continue;
            }
            $eligible[] = $info;
        }

        $hashType = $provider->supportedHashes()->getOne();
        $groups = [];
        $remaining = [];
        foreach ($eligible as $info) {
            $identity = '';
            if ($sizeOnly && $info->size >= 0) {
                $identity = 'size ' . $info->size;
            } elseif ($hashType !== HashType::NONE) {
                $hash = $provider->hashesForObject($info, new HashSet($hashType))[$hashType] ?? '';
                if ($hash !== '') {
                    $identity = $hashType . ' ' . $hash;
                }
            }

            if ($identity === '') {
                $remaining[] = $info;
                continue;
            }

            $groups[$identity][] = $info;
        }

        ksort($groups, SORT_STRING);
        $deleted = [];
        foreach ($groups as $duplicates) {
            $remaining[] = $duplicates[0];
            for ($index = 1; $index < count($duplicates); $index++) {
                $deleted[] = $provider->deleteListedObject($duplicates[$index]);
            }
        }

        usort(
            $remaining,
            static fn (ObjectInfo $a, ObjectInfo $b): int => $a->path <=> $b->path
                ?: ($a->providerKey ?? '') <=> ($b->providerKey ?? ''),
        );

        return [
            'remaining' => $remaining,
            'deleted' => $deleted,
        ];
    }

    /**
     * @param list<ObjectInfo> $objects
     * @return list<ObjectInfo>
     */
    private function renameDuplicateNames(MemoryProvider $provider, string $path, array $objects): array
    {
        [$base, $extension] = $this->splitRemoteExtension($path);
        $renamed = [];
        foreach ($objects as $index => $info) {
            $suffix = 1;
            do {
                if ($suffix > 100) {
                    throw new \RuntimeException("Could not find an available new name for {$path}");
                }
                $newName = sprintf('%s-%d%s', $base, $index + $suffix, $extension);
                $suffix++;
            } while ($provider->pathExists($newName));

            $renamed[] = $provider->renameListedObject($info, $newName);
        }

        return $renamed;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function splitRemoteExtension(string $path): array
    {
        $slash = strrpos($path, '/');
        $leafStart = $slash === false ? 0 : $slash + 1;
        $dot = strrpos($path, '.');
        if ($dot === false || $dot < $leafStart) {
            return [$path, ''];
        }

        return [substr($path, 0, $dot), substr($path, $dot)];
    }

    /**
     * @return list<ObjectInfo>
     */
    public function fixCase(
        MemoryProvider $source,
        MemoryProvider $target,
        ?FilterRuleSet $filter = null,
        bool $immutable = false,
    ): array {
        if ($immutable || !$target->isCaseInsensitive()) {
            return [];
        }

        $renamed = [];
        $sourceDirs = $source->directories();
        usort(
            $sourceDirs,
            static fn (ObjectInfo $a, ObjectInfo $b): int => self::pathLevel($a->path) <=> self::pathLevel($b->path),
        );

        foreach ($sourceDirs as $sourceDir) {
            if ($sourceDir->path === '' || !$this->filterAllowsDirectory($source, $sourceDir->path, $filter)) {
                continue;
            }

            try {
                $targetDir = $target->directoryInfo($sourceDir->path);
            } catch (\RuntimeException) {
                continue;
            }

            if ($this->samePathDifferentCase($sourceDir->path, $targetDir->path)) {
                $renamed[] = $target->renameDirectory($targetDir->path, $sourceDir->path);
            }
        }

        foreach ($source->list() as $sourceInfo) {
            if ($filter !== null && !$filter->includes($sourceInfo->path)) {
                continue;
            }

            try {
                $targetInfo = $target->info($sourceInfo->path);
            } catch (\RuntimeException) {
                continue;
            }

            if ($this->samePathDifferentCase($sourceInfo->path, $targetInfo->path)) {
                $renamed[] = $target->renameObject($targetInfo->path, $sourceInfo->path);
            }
        }

        return $renamed;
    }

    /**
     * @return list<string>
     */
    public function deletePaths(
        MemoryProvider $source,
        MemoryProvider $target,
        ?FilterRuleSet $filter = null,
        string $deleteMode = DeleteMode::DEFAULT,
        bool $deleteExcluded = false,
        bool $ignoreCaseSync = false,
    ): array {
        $deleteMode = DeleteMode::normalize($deleteMode);
        if ($deleteMode === DeleteMode::OFF) {
            return [];
        }

        $sourcePaths = $this->listedPaths($source, $filter, $ignoreCaseSync);
        $targetPaths = $this->listedPaths($target, $deleteExcluded ? null : $filter, $ignoreCaseSync);
        $delete = [];
        foreach ($targetPaths as $path => $targetInfo) {
            if (!isset($sourcePaths[$path])) {
                $delete[] = $targetInfo->path;
            }
        }

        sort($delete, SORT_STRING);

        return $delete;
    }

    /**
     * @return list<ObjectInfo>
     */
    public function deleteDestinationOnly(
        MemoryProvider $source,
        MemoryProvider $target,
        ?FilterRuleSet $filter = null,
        string $deleteMode = DeleteMode::DEFAULT,
        bool $deleteExcluded = false,
        ?int $maxDelete = null,
        ?int $maxDeleteSize = null,
        ?MemoryProvider $backup = null,
        string $backupPrefix = '',
        string $suffix = '',
        bool $suffixKeepExtension = false,
        bool $ignoreCaseSync = false,
    ): array {
        $deleted = [];
        $deleteCount = 0;
        $deleteBytes = 0;
        foreach ($this->deletePaths($source, $target, $filter, $deleteMode, $deleteExcluded, $ignoreCaseSync) as $path) {
            if ($backupPrefix !== '' && self::pathUnderPrefix($path, $backupPrefix)) {
                continue;
            }

            $targetInfo = $target->info($path);
            $deleteSize = max(0, $targetInfo->size);
            $this->assertDeleteWithinLimits($deleteCount, $deleteBytes, $deleteSize, $maxDelete, $maxDeleteSize);
            $deleteCount++;
            $deleteBytes += $deleteSize;
            if ($this->backupRequested($backup, $backupPrefix, $suffix)) {
                $deleted[] = $this->moveToBackup($target, $path, $backup, $backupPrefix, $suffix, $suffixKeepExtension);
            } else {
                $deleted[] = $target->delete($path);
            }
        }

        return $deleted;
    }

    /**
     * Model operations.Delete and the cmd/delete command boundary.
     *
     * Upstream lists objects through ListFn, so include/exclude and object
     * filters are applied before DeleteFiles sees anything. DeleteFiles then
     * accounts every attempted object delete before dry-run/provider removal,
     * keeps processing ordinary per-object failures, and reports an aggregate
     * `failed to delete N files` error after the listed objects are drained.
     *
     * @param null|callable(ObjectInfo): bool $includeObject
     * @param array{listed?: int, deletes?: int, deleteBytes?: int, errors?: int, lastError?: ?string, dryRunObjectSkipped?: int, deletedDirs?: int, dryRunSkipped?: int}|null $stats
     * @return array{deleted: list<ObjectInfo>, prunedDirectories: list<ObjectInfo>}
     */
    public function deleteContents(
        MemoryProvider $provider,
        ?FilterRuleSet $filter = null,
        ?callable $includeObject = null,
        bool $dryRun = false,
        bool $rmdirs = false,
        ?int $maxDelete = null,
        ?int $maxDeleteSize = null,
        ?array &$stats = null,
    ): array {
        $this->initDeleteStats($stats);
        $objects = $this->listDeleteObjects($provider, $filter, $includeObject, $stats);
        $deleted = $this->deleteListedFiles(
            $provider,
            $objects,
            $dryRun,
            $maxDelete,
            $maxDeleteSize,
            $stats,
        );

        $prunedDirectories = [];
        if ($rmdirs) {
            $prunedDirectories = $this->removeEmptyDirectories(
                $provider,
                '',
                true,
                $filter,
                -1,
                $dryRun,
                $stats,
            );
        }

        return [
            'deleted' => $deleted,
            'prunedDirectories' => $prunedDirectories,
        ];
    }

    /**
     * Model operations.TryRmdir.
     *
     * Upstream accounts a deleted-directory attempt before checking dry-run.
     * TryRmdir returns provider errors without counting them; Rmdir is the
     * wrapper that turns those failures into counted errors.
     *
     * @param array{deletedDirs?: int, errors?: int, lastError?: ?string, dryRunSkipped?: int}|null $stats
     */
    public function tryRemoveDirectory(
        MemoryProvider $provider,
        string $dir = '',
        bool $dryRun = false,
        ?array &$stats = null,
    ): ?ObjectInfo {
        $this->initRmdirStats($stats);
        $stats['deletedDirs']++;

        if ($dryRun) {
            $stats['dryRunSkipped']++;

            return null;
        }

        return $provider->rmdir($dir);
    }

    /**
     * Model operations.Rmdir.
     *
     * @param array{deletedDirs?: int, errors?: int, lastError?: ?string, dryRunSkipped?: int}|null $stats
     */
    public function removeDirectory(
        MemoryProvider $provider,
        string $dir = '',
        bool $dryRun = false,
        ?array &$stats = null,
    ): ?ObjectInfo {
        try {
            return $this->tryRemoveDirectory($provider, $dir, $dryRun, $stats);
        } catch (\RuntimeException $throwable) {
            $this->recordRmdirError($stats, $throwable);
            throw $throwable;
        }
    }

    /**
     * Model operations.Purge direct-provider and fallback behavior.
     *
     * Direct provider purge increments the deleted-directory counter once,
     * then dry-run can stop before any provider call. If a direct provider
     * returns ErrorCantPurge, upstream falls back to DeleteFiles plus Rmdirs;
     * other provider errors stay fatal and are counted once.
     *
     * @param array{deletedDirs?: int, deletes?: int, deleteBytes?: int, errors?: int, lastError?: ?string, dryRunSkipped?: int, dryRunObjectSkipped?: int, directPurgeAttempts?: int}|null $stats
     * @return array{objects: list<ObjectInfo>, directories: list<ObjectInfo>, usedDirectPurge: bool, usedFallback: bool, directError: ?string}
     */
    public function purge(
        MemoryProvider $provider,
        string $dir = '',
        bool $dryRun = false,
        ?array &$stats = null,
    ): array {
        $this->initPurgeStats($stats);
        $directError = null;

        if ($provider->supportsDirectPurge()) {
            $stats['directPurgeAttempts']++;
            $stats['deletedDirs']++;

            if ($dryRun) {
                $stats['dryRunSkipped']++;

                return [
                    'objects' => [],
                    'directories' => [],
                    'usedDirectPurge' => true,
                    'usedFallback' => false,
                    'directError' => null,
                ];
            }

            try {
                $purged = $provider->purge($dir);

                return [
                    'objects' => $purged['objects'],
                    'directories' => $purged['directories'],
                    'usedDirectPurge' => true,
                    'usedFallback' => false,
                    'directError' => null,
                ];
            } catch (\RuntimeException $throwable) {
                if (!MemoryProvider::isCantPurgeException($throwable)) {
                    $this->recordPurgeError($stats, $throwable);
                    throw $throwable;
                }

                $directError = $throwable->getMessage();
            }
        }

        $errorsBeforeFallback = $stats['errors'];
        try {
            $fallback = $this->fallbackPurge($provider, $dir, $dryRun, $stats);
        } catch (\RuntimeException $throwable) {
            if ($stats['errors'] === $errorsBeforeFallback) {
                $this->recordPurgeError($stats, $throwable);
            }
            throw $throwable;
        }

        return [
            'objects' => $fallback['objects'],
            'directories' => $fallback['directories'],
            'usedDirectPurge' => false,
            'usedFallback' => true,
            'directError' => $directError,
        ];
    }

    /**
     * Model operations.CleanUp and cmd/cleanup.
     *
     * Upstream checks the optional CleanUp feature before honoring dry-run.
     * A dry-run against a supported remote skips the provider call; unsupported
     * remotes still return the upstream-shaped cleanup unsupported error.
     *
     * @param array{cleanupCalls?: int, dryRunSkipped?: int, cleanedObjects?: int, cleanedDirectories?: int, errors?: int, lastError?: ?string}|null $stats
     * @return array{objects: list<ObjectInfo>, directories: list<ObjectInfo>, dryRun: bool, providerCalled: bool}
     */
    public function cleanUp(
        MemoryProvider $provider,
        bool $dryRun = false,
        ?array &$stats = null,
    ): array {
        $this->initCleanUpStats($stats);

        if (!$provider->supportsCleanUp()) {
            $throwable = new \RuntimeException(MemoryProvider::ERROR_CANT_CLEANUP);
            $this->recordCleanUpError($stats, $throwable);
            throw $throwable;
        }

        if ($dryRun) {
            $stats['dryRunSkipped']++;

            return [
                'objects' => [],
                'directories' => [],
                'dryRun' => true,
                'providerCalled' => false,
            ];
        }

        $stats['cleanupCalls']++;
        try {
            $cleaned = $provider->cleanUp();
        } catch (\RuntimeException $throwable) {
            $this->recordCleanUpError($stats, $throwable);
            throw $throwable;
        }

        $stats['cleanedObjects'] += count($cleaned['objects']);
        $stats['cleanedDirectories'] += count($cleaned['directories']);

        return [
            'objects' => $cleaned['objects'],
            'directories' => $cleaned['directories'],
            'dryRun' => false,
            'providerCalled' => true,
        ];
    }

    /**
     * Model syncCopyMove.deleteEmptyDirectories for destination-only dirs.
     *
     * Upstream records directories that are missing from the source during the
     * destination march, then tries to remove them deepest-first after file
     * deletes. TryRmdir errors are logged and ignored, so non-empty or already
     * vanished synthetic parents do not abort the sync.
     *
     * @return list<ObjectInfo>
     */
    public function pruneEmptyDestinationDirectories(
        MemoryProvider $source,
        MemoryProvider $target,
        ?FilterRuleSet $filter = null,
        bool $deleteExcluded = false,
        string $backupPrefix = '',
        bool $ignoreCaseSync = false,
    ): array {
        return $this->pruneEmptyDirectoryCandidates(
            $target,
            $this->destinationOnlyDirectoryCandidates($source, $target, $filter, $deleteExcluded, $ignoreCaseSync),
            $backupPrefix,
        );
    }

    /**
     * Model operations.Rmdirs for standalone empty-directory pruning.
     *
     * Rclone builds an emptiness map from the filtered walk, then attempts
     * provider Rmdir calls deepest-first. Filtered-out objects can still make
     * provider Rmdir fail; those errors are counted and reported after every
     * same-level candidate has been attempted.
     *
     * @return list<ObjectInfo>
     */
    public function removeEmptyDirectories(
        MemoryProvider $provider,
        string $dir = '',
        bool $leaveRoot = false,
        ?FilterRuleSet $filter = null,
        int $maxDepth = -1,
        bool $dryRun = false,
        ?array &$rmdirStats = null,
    ): array {
        $dir = self::normalizePath($dir);
        $provider->directoryInfo($dir);

        $dirEmpty = [$dir => !$leaveRoot];
        foreach ($provider->directories($dir) as $directory) {
            if (!self::pathIsOrUnderRmdirRoot($directory->path, $dir)) {
                continue;
            }
            if (!$this->withinRmdirsDepth($directory->path, $dir, $maxDepth)) {
                continue;
            }
            if (!array_key_exists($directory->path, $dirEmpty)) {
                $dirEmpty[$directory->path] = true;
            }
        }

        foreach ($provider->list($dir) as $object) {
            if (!self::pathIsOrUnderRmdirRoot($object->path, $dir)) {
                continue;
            }
            if (!$this->withinRmdirsDepth($object->path, $dir, $maxDepth)) {
                continue;
            }
            if ($filter !== null && !$filter->includes($object->path)) {
                continue;
            }

            $parent = $object->path;
            while ($parent !== '') {
                $parent = self::parentPath($parent);
                if (array_key_exists($parent, $dirEmpty) && $dirEmpty[$parent] === false) {
                    break;
                }
                $dirEmpty[$parent] = false;
            }
        }

        $toDelete = [];
        foreach ($dirEmpty as $path => $empty) {
            if (!$empty || !$this->rmdirsFilterIncludesRemote($path, $filter)) {
                continue;
            }

            $toDelete[self::pathLevel($path)][] = $path;
        }

        if ($toDelete === []) {
            return [];
        }

        krsort($toDelete, SORT_NUMERIC);
        $removed = [];
        $errorCount = 0;
        $lastError = null;
        foreach ($toDelete as $paths) {
            sort($paths, SORT_STRING);
            foreach ($paths as $path) {
                try {
                    $removedInfo = $this->tryRemoveDirectory($provider, $path, $dryRun, $rmdirStats);
                    if ($removedInfo !== null) {
                        $removed[] = $removedInfo;
                    }
                } catch (\RuntimeException $throwable) {
                    $this->recordRmdirError($rmdirStats, $throwable);
                    $errorCount++;
                    $lastError = $throwable;
                }
            }
        }

        if ($errorCount === 1 && $lastError !== null) {
            throw new \RuntimeException('failed to remove directories: ' . $lastError->getMessage(), 0, $lastError);
        }
        if ($errorCount > 1 && $lastError !== null) {
            throw new \RuntimeException(
                sprintf('failed to remove directories: %d errors: last error: %s', $errorCount, $lastError->getMessage()),
                0,
                $lastError,
            );
        }

        return $removed;
    }

    /**
     * @param list<ObjectInfo> $candidates
     * @return list<ObjectInfo>
     */
    private function pruneEmptyDirectoryCandidates(MemoryProvider $target, array $candidates, string $backupPrefix): array
    {
        $backupPrefix = self::normalizePath($backupPrefix);
        usort(
            $candidates,
            static fn (ObjectInfo $left, ObjectInfo $right): int => self::pathLevel($right->path) <=> self::pathLevel($left->path)
                ?: $right->path <=> $left->path
                ?: ($right->providerKey ?? '') <=> ($left->providerKey ?? ''),
        );

        $removed = [];
        foreach ($candidates as $directory) {
            if ($backupPrefix !== '' && self::pathUnderPrefix($directory->path, $backupPrefix)) {
                continue;
            }
            try {
                $removed[] = $target->rmdir($directory->path);
            } catch (\RuntimeException) {
                // TryRmdir errors are intentionally non-fatal in sync cleanup.
            }
        }

        return $removed;
    }

    public static function backupPath(
        string $path,
        string $backupPrefix = '',
        string $suffix = '',
        bool $suffixKeepExtension = false,
    ): string {
        $path = self::normalizePath($path);
        $path = self::suffixName($path, $suffix, $suffixKeepExtension);
        $backupPrefix = self::normalizePath($backupPrefix);

        return $backupPrefix === '' ? $path : $backupPrefix . '/' . $path;
    }

    public static function resolveBackupRoot(
        string $destinationRoot,
        string $sourceRoot,
        string $backupRoot = '',
        string $sourceFileName = '',
        string $suffix = '',
        bool $backupSupportsServerSideMove = true,
    ): string {
        $destinationRoot = self::normalizeRoot($destinationRoot);
        $sourceRoot = self::normalizeRoot($sourceRoot);
        $backupRoot = self::normalizeRoot($backupRoot);

        if ($backupRoot !== '') {
            if (!self::sameRootConfig($destinationRoot, $backupRoot)) {
                throw new \RuntimeException('parameter to --backup-dir has to be on the same remote as destination');
            }
            if ($sourceFileName === '') {
                if (self::rootsOverlap($backupRoot, $destinationRoot)) {
                    throw new \RuntimeException("destination and parameter to --backup-dir mustn't overlap");
                }
                if (self::rootsOverlap($backupRoot, $sourceRoot)) {
                    throw new \RuntimeException("source and parameter to --backup-dir mustn't overlap");
                }
            } elseif ($suffix === '') {
                if (self::sameRootPath($destinationRoot, $backupRoot)) {
                    throw new \RuntimeException("destination and parameter to --backup-dir mustn't be the same");
                }
                if (self::sameRootPath($sourceRoot, $backupRoot)) {
                    throw new \RuntimeException("source and parameter to --backup-dir mustn't be the same");
                }
            }
        } elseif ($suffix !== '') {
            $backupRoot = $destinationRoot;
        } else {
            throw new \RuntimeException('internal error: BackupDir called when --backup-dir and --suffix both empty');
        }

        if (!$backupSupportsServerSideMove) {
            throw new \RuntimeException("can't use --backup-dir on a remote which doesn't support server-side move or copy");
        }

        return $backupRoot;
    }

    public function dirsEqual(
        MemoryProvider $source,
        MemoryProvider $target,
        string $sourcePath,
        ?string $targetPath = null,
        bool $setDirModTime = true,
        bool $setDirMetadata = false,
        bool $ignoreTimes = false,
        bool $immutable = false,
        bool $ignoreExisting = false,
        bool $updateOlder = false,
        bool $sizeOnly = false,
        ?int $modifyWindowSeconds = 1,
    ): bool {
        try {
            $sourceInfo = $source->directoryInfo($sourcePath);
            $targetInfo = $target->directoryInfo($targetPath ?? $sourcePath);
        } catch (\RuntimeException) {
            return false;
        }

        if ($sizeOnly || $immutable || $ignoreExisting || $modifyWindowSeconds === null) {
            return true;
        }
        if ($ignoreTimes) {
            return false;
        }
        if (!$setDirModTime && !$setDirMetadata) {
            return true;
        }

        $dt = $this->modTimeDeltaSeconds($sourceInfo, $targetInfo);
        if ($dt === null) {
            return false;
        }
        if ($dt < $modifyWindowSeconds && $dt > -$modifyWindowSeconds) {
            return true;
        }
        if ($updateOlder && $dt >= $modifyWindowSeconds) {
            return true;
        }

        return false;
    }

    /**
     * @param list<string|ObjectInfo> $changedPaths
     * @return list<ObjectInfo>
     */
    public function setDelayedDirectoryModTimes(
        MemoryProvider $source,
        MemoryProvider $target,
        array $changedPaths,
        bool $copyEmptySourceDirs = false,
        bool $setDirModTime = true,
        bool $setDirMetadata = false,
        bool $noUpdateDirModTime = false,
    ): array {
        if (!$setDirModTime || $noUpdateDirModTime) {
            return [];
        }

        $modifiedDirs = [];
        foreach ($changedPaths as $changedPath) {
            $dir = $this->changedPathDirectory($source, $changedPath);
            if ($dir !== '') {
                $modifiedDirs[$dir] = true;
            }
        }
        if ($modifiedDirs === []) {
            return [];
        }

        $queue = [];
        $maxLevel = 0;
        foreach ($source->directories() as $sourceDir) {
            $level = self::pathLevel($sourceDir->path);
            $maxLevel = max($maxLevel, $level);
            $queue[] = [
                'info' => $sourceDir,
                'level' => $level,
            ];
        }

        $updated = [];
        for ($level = $maxLevel; $level >= 0; $level--) {
            foreach ($queue as $item) {
                if ($item['level'] !== $level) {
                    continue;
                }

                $sourceDir = $item['info'];
                if (!isset($modifiedDirs[$sourceDir->path])) {
                    continue;
                }
                if (!$copyEmptySourceDirs && $this->sourceDirectoryIsEmpty($source, $sourceDir->path)) {
                    continue;
                }
                $targetDirExists = $this->directoryExists($target, $sourceDir->path);
                if (!$targetDirExists && !$copyEmptySourceDirs) {
                    continue;
                }
                if (!$targetDirExists) {
                    $target->mkdir($sourceDir->path);
                }

                $updated[] = $this->applyDirectoryUpdate($target, $sourceDir, $setDirMetadata);
                $parent = self::parentPath($sourceDir->path);
                if ($parent !== '') {
                    $modifiedDirs[$parent] = true;
                }
            }
        }

        return $updated;
    }

    /**
     * @return array<string, ObjectInfo>
     */
    private function listedPaths(MemoryProvider $provider, ?FilterRuleSet $filter, bool $ignoreCaseSync = false): array
    {
        $paths = [];
        foreach ($provider->list() as $info) {
            if ($filter !== null && !$filter->includes($info->path)) {
                continue;
            }

            $key = $ignoreCaseSync ? $this->syncPathKey($info->path) : $info->path;
            if (!isset($paths[$key])) {
                $paths[$key] = $info;
            }
        }

        return $paths;
    }

    /**
     * @return array<string, ObjectInfo>
     */
    private function listedDirectoryPaths(MemoryProvider $provider, ?FilterRuleSet $filter, bool $ignoreCaseSync = false): array
    {
        $paths = [];
        foreach ($provider->directories() as $info) {
            if ($info->path === '' || !$this->filterAllowsDirectory($provider, $info->path, $filter)) {
                continue;
            }

            $key = $ignoreCaseSync ? $this->syncPathKey($info->path) : $info->path;
            if (!isset($paths[$key])) {
                $paths[$key] = $info;
            }
        }

        return $paths;
    }

    /**
     * @return list<ObjectInfo>
     */
    private function destinationOnlyDirectoryCandidates(
        MemoryProvider $source,
        MemoryProvider $target,
        ?FilterRuleSet $filter,
        bool $deleteExcluded,
        bool $ignoreCaseSync,
    ): array {
        $sourceDirs = $this->listedDirectoryPaths($source, $filter, $ignoreCaseSync);
        $targetDirs = $this->listedDirectoryPaths($target, $deleteExcluded ? null : $filter, $ignoreCaseSync);

        $candidates = [];
        foreach ($targetDirs as $pathKey => $targetDir) {
            if ($targetDir->path === '' || isset($sourceDirs[$pathKey])) {
                continue;
            }

            $candidates[] = $targetDir;
        }

        return $candidates;
    }

    /**
     * @return array{
     *     paths: array<string, ObjectInfo>,
     *     order: array<string, array{pathKey: string, type: string}>,
     *     duplicates: list<array{path: string, type: string, kept: ObjectInfo, ignored: ObjectInfo, message: string}>
     * }
     */
    private function diagnosticListing(
        MemoryProvider $provider,
        ?FilterRuleSet $filter,
        bool $ignoreCaseSync,
        string $side,
        bool $includeDirectories,
    ): array {
        $paths = [];
        $order = [];
        $duplicates = [];
        foreach ($this->diagnosticEntries($provider, $filter, $ignoreCaseSync, $includeDirectories) as $entry) {
            $entryKey = $entry['pathKey'] . "\0" . $entry['type'];
            if (!isset($paths[$entryKey])) {
                $paths[$entryKey] = $entry['info'];
                $order[$entryKey] = [
                    'pathKey' => $entry['pathKey'],
                    'type' => $entry['type'],
                ];
                continue;
            }

            $message = 'Duplicate ' . $entry['type'] . ' found in ' . $side . ' - ignoring';
            $duplicates[] = [
                'path' => $entry['info']->path,
                'type' => $entry['type'],
                'kept' => $paths[$entryKey],
                'ignored' => $entry['info'],
                'message' => $message,
            ];
        }

        return [
            'paths' => $paths,
            'order' => $order,
            'duplicates' => $duplicates,
        ];
    }

    /**
     * @param list<ObjectInfo> $entries
     * @return array{
     *     paths: array<string, ObjectInfo>,
     *     order: array<string, array{pathKey: string, type: string}>,
     *     duplicates: list<array{path: string, type: string, kept: ObjectInfo, ignored: ObjectInfo, message: string}>
     * }
     */
    private function diagnosticListingFromOrderedEntries(array $entries, bool $ignoreCaseSync, string $side): array
    {
        $paths = [];
        $order = [];
        $duplicates = [];
        $previous = null;
        $sequence = 0;

        foreach ($entries as $info) {
            if (!$info instanceof ObjectInfo) {
                throw new \RuntimeException('unknown object type ' . get_debug_type($info));
            }

            $entry = $this->diagnosticEntry(
                $info,
                ListDirectory::isDirectory($info) ? 'directory' : 'object',
                $ignoreCaseSync,
                $sequence++,
            );
            $entryKey = $entry['pathKey'] . "\0" . $entry['type'];

            if ($previous !== null) {
                $comparison = $this->compareDiagnosticEntryOrder($entry, $previous);
                if ($comparison === 0) {
                    $duplicates[] = [
                        'path' => $entry['info']->path,
                        'type' => $entry['type'],
                        'kept' => $paths[$entryKey],
                        'ignored' => $entry['info'],
                        'message' => 'Duplicate ' . $entry['type'] . ' found in ' . $side . ' - ignoring',
                    ];
                    $previous = $entry;
                    continue;
                }
                if ($comparison < 0) {
                    throw new \RuntimeException('Out of order listing in ' . $side);
                }
            }

            if (!isset($paths[$entryKey])) {
                $paths[$entryKey] = $entry['info'];
                $order[$entryKey] = [
                    'pathKey' => $entry['pathKey'],
                    'type' => $entry['type'],
                ];
            }
            $previous = $entry;
        }

        return [
            'paths' => $paths,
            'order' => $order,
            'duplicates' => $duplicates,
        ];
    }

    /**
     * @return list<array{pathKey: string, type: string, info: ObjectInfo, sequence: int}>
     */
    private function diagnosticEntries(
        MemoryProvider $provider,
        ?FilterRuleSet $filter,
        bool $ignoreCaseSync,
        bool $includeDirectories,
    ): array {
        $entries = [];
        $sequence = 0;
        foreach ($provider->list() as $info) {
            if ($filter !== null && !$filter->includes($info->path)) {
                continue;
            }

            $entries[] = $this->diagnosticEntry($info, 'object', $ignoreCaseSync, $sequence++);
        }

        if ($includeDirectories) {
            foreach ($provider->directories() as $info) {
                if ($info->path === '' || !$this->filterAllowsDirectory($provider, $info->path, $filter)) {
                    continue;
                }

                $entries[] = $this->diagnosticEntry($info, 'directory', $ignoreCaseSync, $sequence++);
            }
        }

        usort(
            $entries,
            static fn (array $left, array $right): int => $left['pathKey'] <=> $right['pathKey']
                ?: $left['type'] <=> $right['type']
                ?: $left['sequence'] <=> $right['sequence'],
        );

        return $entries;
    }

    /**
     * @return array{pathKey: string, type: string, info: ObjectInfo, sequence: int}
     */
    private function diagnosticEntry(ObjectInfo $info, string $type, bool $ignoreCaseSync, int $sequence): array
    {
        return [
            'pathKey' => $ignoreCaseSync ? $this->syncPathKey($info->path) : self::normalizePath($info->path),
            'type' => $type,
            'info' => $info,
            'sequence' => $sequence,
        ];
    }

    /**
     * @param array{pathKey: string, type: string, info: ObjectInfo, sequence: int} $left
     * @param array{pathKey: string, type: string, info: ObjectInfo, sequence: int} $right
     */
    private function compareDiagnosticEntryOrder(array $left, array $right): int
    {
        return $left['pathKey'] <=> $right['pathKey']
            ?: $left['type'] <=> $right['type'];
    }

    /**
     * @return list<string>
     */
    private function noTraverseSourceDirectories(MemoryProvider $source, ?FilterRuleSet $filter): array
    {
        $directories = [];
        foreach ($source->directories() as $directory) {
            if ($directory->path === '') {
                continue;
            }
            if (!$this->filterAllowsDirectory($source, $directory->path, $filter)) {
                continue;
            }

            $directories[] = $directory->path;
        }

        return $directories;
    }

    /**
     * Model fs/march --no-traverse destination matching. Source directories are
     * not probed with NewObject; source objects are checked at their destination
     * path, and lookup failures become source-only transfers.
     *
     * @param array{targetLookups: list<string>, targetMatches: list<string>, targetMisses: list<string>}|null $stats
     */
    private function noTraverseTargetInfo(MemoryProvider $target, string $sourcePath, ?array &$stats): ?ObjectInfo
    {
        $lookup = self::normalizePath($sourcePath);
        if ($stats !== null) {
            $stats['targetLookups'][] = $lookup;
        }

        $targetInfo = $this->optionalInfo($target, $lookup);
        if ($targetInfo === null) {
            if ($stats !== null) {
                $stats['targetMisses'][] = $lookup;
            }

            return null;
        }

        if ($stats !== null) {
            $stats['targetMatches'][] = $targetInfo->path;
        }

        return $targetInfo;
    }

    private function noTraverseDisabledReason(?string $syncDeleteMode, bool $trackRenamesForSync): ?string
    {
        $deleteMode = $syncDeleteMode === null ? null : DeleteMode::normalize($syncDeleteMode);
        if (
            $deleteMode !== null
            && $deleteMode !== DeleteMode::OFF
            && $deleteMode !== DeleteMode::BEFORE
        ) {
            return 'sync delete mode requires destination traversal';
        }
        if ($trackRenamesForSync) {
            return 'track-renames requires destination traversal';
        }

        return null;
    }

    /**
     * @return array{requested: true, enabled: false, disabledReason: string, noCheckDest: false, targetListUsed: true, targetLookups: list<string>, targetMatches: list<string>, targetMisses: list<string>, sourceOnlyDirectories: list<string>}
     */
    private function disabledNoTraverseStats(string $syncDeleteMode): array
    {
        return [
            'requested' => true,
            'enabled' => false,
            'disabledReason' => $this->noTraverseDisabledReason($syncDeleteMode, false)
                ?? 'sync delete mode requires destination traversal',
            'noCheckDest' => false,
            'targetListUsed' => true,
            'targetLookups' => [],
            'targetMatches' => [],
            'targetMisses' => [],
            'sourceOnlyDirectories' => [],
        ];
    }

    /**
     * @param null|callable(ObjectInfo): bool $includeObject
     * @param array{listed?: int, deletes?: int, deleteBytes?: int, errors?: int, lastError?: ?string, dryRunObjectSkipped?: int}|null $stats
     * @return list<ObjectInfo>
     */
    private function listDeleteObjects(
        MemoryProvider $provider,
        ?FilterRuleSet $filter,
        ?callable $includeObject,
        ?array &$stats,
    ): array {
        $this->initDeleteStats($stats);
        $objects = [];
        foreach ($provider->list() as $object) {
            $stats['listed']++;
            if ($filter !== null && !$filter->includes($object->path)) {
                continue;
            }
            if ($includeObject !== null && !(bool) $includeObject($object)) {
                continue;
            }

            $objects[] = $object;
        }

        return $objects;
    }

    /**
     * @param list<ObjectInfo> $objects
     * @param array{listed?: int, deletes?: int, deleteBytes?: int, errors?: int, lastError?: ?string, dryRunObjectSkipped?: int}|null $stats
     * @return list<ObjectInfo>
     */
    private function deleteListedFiles(
        MemoryProvider $provider,
        array $objects,
        bool $dryRun,
        ?int $maxDelete,
        ?int $maxDeleteSize,
        ?array &$stats,
    ): array {
        $deleted = [];
        $errors = 0;
        foreach ($objects as $object) {
            try {
                $deletedInfo = $this->deleteListedFile(
                    $provider,
                    $object,
                    $dryRun,
                    $maxDelete,
                    $maxDeleteSize,
                    $stats,
                );
                if ($deletedInfo !== null) {
                    $deleted[] = $deletedInfo;
                }
            } catch (\RuntimeException $throwable) {
                $errors++;
                $this->recordDeleteError($stats, $throwable);
                if ($this->isFatalDeleteError($throwable)) {
                    break;
                }
            }
        }

        if ($errors > 0) {
            throw new \RuntimeException(sprintf('failed to delete %d files', $errors));
        }

        return $deleted;
    }

    /**
     * @param array{listed?: int, deletes?: int, deleteBytes?: int, errors?: int, lastError?: ?string, dryRunObjectSkipped?: int}|null $stats
     */
    private function deleteListedFile(
        MemoryProvider $provider,
        ObjectInfo $object,
        bool $dryRun,
        ?int $maxDelete,
        ?int $maxDeleteSize,
        ?array &$stats,
    ): ?ObjectInfo {
        $this->initDeleteStats($stats);
        $deleteSize = max(0, $object->size);
        $this->assertDeleteWithinLimits($stats['deletes'], $stats['deleteBytes'], $deleteSize, $maxDelete, $maxDeleteSize);
        $stats['deletes']++;
        $stats['deleteBytes'] += $deleteSize;

        if ($dryRun) {
            $stats['dryRunObjectSkipped']++;

            return null;
        }

        return $provider->deleteListedObject($object);
    }

    /**
     * @param array{listed?: int, deletes?: int, deleteBytes?: int, errors?: int, lastError?: ?string, dryRunObjectSkipped?: int}|null $stats
     */
    private function initDeleteStats(?array &$stats): void
    {
        if ($stats === null) {
            $stats = [];
        }
        $stats += [
            'listed' => 0,
            'deletes' => 0,
            'deleteBytes' => 0,
            'errors' => 0,
            'lastError' => null,
            'dryRunObjectSkipped' => 0,
        ];
    }

    /**
     * @param array{listed?: int, deletes?: int, deleteBytes?: int, errors?: int, lastError?: ?string, dryRunObjectSkipped?: int}|null $stats
     */
    private function recordDeleteError(?array &$stats, \Throwable $throwable): void
    {
        $this->initDeleteStats($stats);
        $stats['errors']++;
        $stats['lastError'] = $throwable->getMessage();
    }

    private function isFatalDeleteError(\Throwable $throwable): bool
    {
        return in_array($throwable->getMessage(), [
            '--max-delete threshold reached',
            '--max-delete-size threshold reached',
        ], true);
    }

    /**
     * @param array<string, true> $seen
     */
    private function skipMarchDuplicateObject(string $path, array &$seen, bool $ignoreCaseSync): bool
    {
        $key = $ignoreCaseSync ? $this->syncPathKey($path) : self::normalizePath($path);
        if (isset($seen[$key])) {
            return true;
        }

        $seen[$key] = true;

        return false;
    }

    private function syncPathKey(string $path): string
    {
        $path = self::normalizePath($path);

        return function_exists('mb_strtolower') ? mb_strtolower($path, 'UTF-8') : strtolower($path);
    }

    /**
     * @param array<string, mixed> $response
     * @param array<string, string> $downloadHeaders
     * @return array{
     *     dstFileName: string,
     *     body: mixed,
     *     contentLength: int,
     *     modTime: ?string,
     *     stats: array<string, mixed>
     * }
     */
    private function resolveCopyUrlSource(
        string $dstFileName,
        array $response,
        bool $autoFilename,
        bool $headerFilename,
        array $downloadHeaders,
    ): array {
        $requestHeaders = $this->normalizeHttpHeaders($downloadHeaders);
        if (is_callable($response['onRequest'] ?? null)) {
            $response['onRequest']($requestHeaders);
        }

        $status = (int) ($response['status'] ?? 200);
        $statusText = $this->copyUrlStatusText($response, $status);
        if ($status < 200 || $status >= 300) {
            $this->closeCopyUrlBody($response['body'] ?? null);
            throw new \RuntimeException('CopyURL failed: ' . $statusText);
        }

        $resolved = $this->copyUrlResolvedFilename($dstFileName, $response, $autoFilename, $headerFilename);
        $body = $response['body'] ?? '';
        $contentLength = array_key_exists('contentLength', $response)
            ? (int) $response['contentLength']
            : (is_string($body) ? strlen($body) : -1);
        $modTime = $this->copyUrlLastModified($response);

        return [
            'dstFileName' => $resolved['dstFileName'],
            'body' => $body,
            'contentLength' => $contentLength,
            'modTime' => $modTime,
            'stats' => [
                'url' => (string) ($response['url'] ?? ''),
                'finalUrl' => (string) ($response['finalUrl'] ?? ($response['url'] ?? '')),
                'status' => $status,
                'statusText' => $statusText,
                'requestHeaders' => $requestHeaders,
                'downloadHeadersSent' => count($requestHeaders),
                'autoFilename' => $autoFilename,
                'headerFilename' => $headerFilename,
                'filenameSource' => $resolved['source'],
                'path' => $resolved['dstFileName'],
                'contentLength' => $contentLength,
                'modTime' => $modTime,
                'modTimeFromHeader' => $modTime !== null,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $response
     * @return array{dstFileName: string, source: string}
     */
    private function copyUrlResolvedFilename(
        string $dstFileName,
        array $response,
        bool $autoFilename,
        bool $headerFilename,
    ): array {
        if (!$autoFilename) {
            return [
                'dstFileName' => self::normalizePath($dstFileName),
                'source' => 'argument',
            ];
        }

        if ($headerFilename) {
            $filename = $this->copyUrlContentDispositionFilename($response);
            if ($filename === null) {
                throw new \RuntimeException('CopyURL failed: filename not found in the Content-Disposition header');
            }

            return [
                'dstFileName' => $filename,
                'source' => 'content-disposition',
            ];
        }

        $url = (string) ($response['finalUrl'] ?? ($response['url'] ?? ''));
        $path = (string) (parse_url($url, PHP_URL_PATH) ?? '');
        $filename = self::pathBase(rawurldecode($path));
        if ($filename === '' || $filename === '.' || $filename === '/') {
            throw new \RuntimeException("CopyURL failed: file name wasn't found in url");
        }

        return [
            'dstFileName' => $filename,
            'source' => 'url',
        ];
    }

    /**
     * @param array<string, mixed> $response
     */
    private function copyUrlContentDispositionFilename(array $response): ?string
    {
        $header = $this->httpHeader($response['headers'] ?? [], 'Content-Disposition');
        if ($header === null || $header === '') {
            return null;
        }

        $filename = null;
        if (preg_match('/(?:^|;)\s*filename\*\s*=\s*("[^"]*"|[^;]*)/i', $header, $match) === 1) {
            $filename = trim($match[1], " \t\n\r\0\x0B\"");
            $parts = explode("''", $filename, 2);
            if (count($parts) === 2) {
                $filename = rawurldecode($parts[1]);
            }
        } elseif (preg_match('/(?:^|;)\s*filename\s*=\s*("[^"]*"|[^;]*)/i', $header, $match) === 1) {
            $filename = trim($match[1], " \t\n\r\0\x0B\"");
        }

        if ($filename === null || $filename === '') {
            return null;
        }

        $filename = self::pathBase(str_replace('\\', '/', $filename));

        return $filename === '' || $filename === '.' || $filename === '/' ? null : $filename;
    }

    /**
     * @param array<string, mixed> $response
     */
    private function copyUrlLastModified(array $response): ?string
    {
        $lastModified = $this->httpHeader($response['headers'] ?? [], 'Last-Modified');
        if ($lastModified === null || $lastModified === '') {
            return null;
        }

        try {
            return (new \DateTimeImmutable($lastModified))
                ->setTimezone(new \DateTimeZone('UTC'))
                ->format('Y-m-d\TH:i:s\Z');
        } catch (\Exception) {
            return null;
        }
    }

    /**
     * @param array<string, mixed> $response
     */
    private function copyUrlStatusText(array $response, int $status): string
    {
        if (isset($response['statusText']) && $response['statusText'] !== '') {
            return (string) $response['statusText'];
        }

        return $status . ' ' . match ($status) {
            200 => 'OK',
            201 => 'Created',
            204 => 'No Content',
            301 => 'Moved Permanently',
            302 => 'Found',
            400 => 'Bad Request',
            401 => 'Unauthorized',
            403 => 'Forbidden',
            404 => 'Not Found',
            500 => 'Internal Server Error',
            502 => 'Bad Gateway',
            503 => 'Service Unavailable',
            default => 'Status',
        };
    }

    /**
     * @param mixed $body
     */
    private function closeCopyUrlBody(mixed $body): void
    {
        if (is_object($body) && method_exists($body, 'close')) {
            $body->close();
        }
    }

    /**
     * @param array<string, mixed> $headers
     */
    private function httpHeader(array $headers, string $name): ?string
    {
        foreach ($headers as $key => $value) {
            if (strcasecmp((string) $key, $name) === 0) {
                return is_array($value) ? (string) reset($value) : (string) $value;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $headers
     * @return array<string, string>
     */
    private function normalizeHttpHeaders(array $headers): array
    {
        $normalized = [];
        foreach ($headers as $key => $value) {
            if (is_scalar($value) || $value === null) {
                $normalized[(string) $key] = (string) $value;
            }
        }

        return $normalized;
    }

    /**
     * @return list<list<string>>
     */
    private function parseCopyUrlCsv(string $csv): array
    {
        $rows = [];
        foreach (preg_split('/\R/', $csv) ?: [] as $line) {
            if ($line === '') {
                $rows[] = [];
                continue;
            }
            $rows[] = array_map(static fn (string $field): string => $field, str_getcsv($line));
        }

        return $rows;
    }

    private function readProviderObject(MemoryProvider $provider, string $path, int $offset, ?int $length): string
    {
        $reader = $provider->openReader($path, $offset, $length);
        $bytes = '';

        try {
            while (true) {
                $chunk = $reader->read(64 * 1024);
                if ($chunk === '') {
                    break;
                }
                $bytes .= $chunk;
            }
        } finally {
            if (method_exists($reader, 'close')) {
                $reader->close();
            }
        }

        return $bytes;
    }

    private function readUploadInput(mixed $input): string
    {
        if (is_string($input)) {
            return $input;
        }

        if (is_resource($input)) {
            $bytes = stream_get_contents($input);
            if ($bytes === false) {
                throw new \RuntimeException('failed to read upload input');
            }

            return $bytes;
        }

        if (!is_object($input) || !method_exists($input, 'read')) {
            throw new \InvalidArgumentException('upload input must be a string, resource, or object with read()');
        }

        $bytes = '';
        try {
            while (true) {
                $chunk = $input->read(64 * 1024);
                if (!is_string($chunk)) {
                    throw new \RuntimeException('upload input read() must return a string');
                }
                if ($chunk === '') {
                    break;
                }
                $bytes .= $chunk;
            }
        } finally {
            if (method_exists($input, 'close')) {
                $input->close();
            }
        }

        return $bytes;
    }

    private function downloadComparison(MemoryProvider $source, MemoryProvider $target, string $path): ReaderComparisonResult
    {
        try {
            $targetReader = $target->openReader($path);
        } catch (\Throwable $throwable) {
            return new ReaderComparisonResult(false, new \RuntimeException(
                'failed to open "' . $path . '": ' . $throwable->getMessage(),
                0,
                $throwable,
            ));
        }

        try {
            $sourceReader = $source->openReader($path);
        } catch (\Throwable $throwable) {
            return new ReaderComparisonResult(false, new \RuntimeException(
                'failed to open "' . $path . '": ' . $throwable->getMessage(),
                0,
                $throwable,
            ));
        }

        return ReaderComparison::checkEqualReaders($targetReader, $sourceReader);
    }

    private function optionalInfo(MemoryProvider $provider, string $path): ?ObjectInfo
    {
        try {
            return $provider->info($path);
        } catch (\RuntimeException) {
            return null;
        }
    }

    private function directoryExists(MemoryProvider $provider, string $path): bool
    {
        try {
            $provider->directoryInfo($path);

            return true;
        } catch (\RuntimeException) {
            return false;
        }
    }

    /**
     * @param array<string, mixed>|null $stats
     */
    private function initTouchStats(?array &$stats): void
    {
        if ($stats === null) {
            $stats = [];
        }
        $stats += [
            'remote' => '',
            'time' => null,
            'recursive' => false,
            'directory' => false,
            'listed' => 0,
            'touched' => 0,
            'created' => 0,
            'notCreated' => 0,
            'dryRunSkipped' => 0,
            'errors' => 0,
            'lastError' => null,
        ];
    }

    /**
     * @param array<string, mixed>|null $stats
     * @return array{created: ?ObjectInfo, touched: list<ObjectInfo>, skipped: bool, directory: bool, time: string}
     */
    private function touchOneObject(
        MemoryProvider $provider,
        ObjectInfo $object,
        string $time,
        bool $dryRun,
        ?array &$stats,
    ): array {
        if ($dryRun) {
            $stats['dryRunSkipped']++;

            return [
                'created' => null,
                'touched' => [],
                'skipped' => true,
                'directory' => false,
                'time' => $time,
            ];
        }

        try {
            $touched = $provider->setListedObjectModTime($object, $time);
        } catch (\RuntimeException $throwable) {
            $error = new \RuntimeException('failed to touch: ' . $throwable->getMessage(), 0, $throwable);
            $this->recordTouchError($stats, $error);
            throw $error;
        }

        $stats['touched']++;

        return [
            'created' => null,
            'touched' => [$touched],
            'skipped' => false,
            'directory' => false,
            'time' => $time,
        ];
    }

    /**
     * @param array<string, mixed>|null $stats
     * @return list<ObjectInfo>
     */
    private function touchDirectory(
        MemoryProvider $provider,
        string $remote,
        string $time,
        bool $recursive,
        bool $dryRun,
        ?FilterRuleSet $filter,
        ?array &$stats,
    ): array {
        $walk = $provider->walk($remote, $recursive ? -1 : 1, true, false);
        $touched = [];

        foreach ($walk['objects'] as $object) {
            if ($filter !== null && !$filter->includes($object->path)) {
                continue;
            }

            $stats['listed']++;
            if ($dryRun) {
                $stats['dryRunSkipped']++;
                continue;
            }

            try {
                $touched[] = $provider->setListedObjectModTime($object, $time);
                $stats['touched']++;
            } catch (\RuntimeException $throwable) {
                $this->recordTouchError(
                    $stats,
                    new \RuntimeException('failed to touch: ' . $throwable->getMessage(), 0, $throwable),
                );
            }
        }

        return $touched;
    }

    /**
     * @param array<string, mixed>|null $stats
     */
    private function recordTouchError(?array &$stats, \Throwable $throwable): void
    {
        $this->initTouchStats($stats);
        $stats['errors']++;
        $stats['lastError'] = $throwable->getMessage();
    }

    private function touchTimeFromOptions(
        ?string $timestamp,
        bool $localTime,
        \DateTimeInterface|string|null $now,
    ): string {
        if ($timestamp !== null && $timestamp !== '') {
            try {
                return $this->parseTouchTimestamp($timestamp, $localTime);
            } catch (\InvalidArgumentException $throwable) {
                throw new \RuntimeException('failed to parse timestamp argument: ' . $throwable->getMessage(), 0, $throwable);
            }
        }

        return $this->normalizeTouchTime($now ?? new \DateTimeImmutable('now', new \DateTimeZone('UTC')));
    }

    private function parseTouchTimestamp(string $timestamp, bool $localTime): string
    {
        if (preg_match('/^\d{6}$/', $timestamp) === 1) {
            $year = (int) substr($timestamp, 0, 2);
            $year += $year >= 69 ? 1900 : 2000;
            $month = (int) substr($timestamp, 2, 2);
            $day = (int) substr($timestamp, 4, 2);
            if (!checkdate($month, $day, $year)) {
                throw new \InvalidArgumentException('invalid YYMMDD timestamp');
            }

            return $this->normalizeTouchTimeFromParts($year, $month, $day, 0, 0, 0, '', $localTime);
        }

        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2}):(\d{2})(\.\d{1,9})?$/', $timestamp, $match) !== 1) {
            throw new \InvalidArgumentException('invalid timestamp layout');
        }

        $year = (int) $match[1];
        $month = (int) $match[2];
        $day = (int) $match[3];
        $hour = (int) $match[4];
        $minute = (int) $match[5];
        $second = (int) $match[6];
        if (
            !checkdate($month, $day, $year)
            || $hour > 23
            || $minute > 59
            || $second > 59
        ) {
            throw new \InvalidArgumentException('invalid timestamp value');
        }

        return $this->normalizeTouchTimeFromParts(
            $year,
            $month,
            $day,
            $hour,
            $minute,
            $second,
            $match[7] ?? '',
            $localTime,
        );
    }

    private function normalizeTouchTimeFromParts(
        int $year,
        int $month,
        int $day,
        int $hour,
        int $minute,
        int $second,
        string $fraction,
        bool $localTime,
    ): string {
        if (!$localTime) {
            return sprintf(
                '%04d-%02d-%02dT%02d:%02d:%02d%sZ',
                $year,
                $month,
                $day,
                $hour,
                $minute,
                $second,
                $fraction,
            );
        }

        $micros = substr(str_pad(ltrim($fraction, '.'), 6, '0'), 0, 6);
        $date = \DateTimeImmutable::createFromFormat(
            '!Y-m-d H:i:s.u',
            sprintf('%04d-%02d-%02d %02d:%02d:%02d.%s', $year, $month, $day, $hour, $minute, $second, $micros),
            new \DateTimeZone(date_default_timezone_get()),
        );
        if (!$date instanceof \DateTimeImmutable) {
            throw new \InvalidArgumentException('invalid local timestamp value');
        }

        return $this->normalizeTouchTime($date);
    }

    private function normalizeTouchTime(\DateTimeInterface|string $time): string
    {
        if (is_string($time)) {
            if ($time === '') {
                throw new \InvalidArgumentException('touch time cannot be empty');
            }

            return $time;
        }

        $date = \DateTimeImmutable::createFromInterface($time)
            ->setTimezone(new \DateTimeZone('UTC'));
        $base = $date->format('Y-m-d\TH:i:s');
        $micros = $date->format('u');

        return $micros === '000000'
            ? $base . 'Z'
            : $base . '.' . rtrim($micros, '0') . 'Z';
    }

    /**
     * @param array<string, scalar|null> $metadata
     * @return array<string, string>
     */
    private function normalizeTouchMetadata(array $metadata): array
    {
        $normalized = [];
        foreach ($metadata as $key => $value) {
            if ($value === null) {
                continue;
            }
            $normalized[(string) $key] = (string) $value;
        }

        return $normalized;
    }

    /**
     * @param array{deletedDirs?: int, errors?: int, lastError?: ?string, dryRunSkipped?: int}|null $stats
     */
    private function initRmdirStats(?array &$stats): void
    {
        if ($stats === null) {
            $stats = [];
        }
        $stats += [
            'deletedDirs' => 0,
            'errors' => 0,
            'lastError' => null,
            'dryRunSkipped' => 0,
        ];
    }

    /**
     * @param array{deletedDirs?: int, errors?: int, lastError?: ?string, dryRunSkipped?: int}|null $stats
     */
    private function recordRmdirError(?array &$stats, \Throwable $throwable): void
    {
        $this->initRmdirStats($stats);
        $stats['errors']++;
        $stats['lastError'] = $throwable->getMessage();
    }

    /**
     * @param array{deletedDirs?: int, deletes?: int, deleteBytes?: int, errors?: int, lastError?: ?string, dryRunSkipped?: int, dryRunObjectSkipped?: int, directPurgeAttempts?: int}|null $stats
     */
    private function initPurgeStats(?array &$stats): void
    {
        $this->initRmdirStats($stats);
        $stats += [
            'deletes' => 0,
            'deleteBytes' => 0,
            'dryRunObjectSkipped' => 0,
            'directPurgeAttempts' => 0,
        ];
    }

    /**
     * @param array{deletedDirs?: int, deletes?: int, deleteBytes?: int, errors?: int, lastError?: ?string, dryRunSkipped?: int, dryRunObjectSkipped?: int, directPurgeAttempts?: int}|null $stats
     */
    private function recordPurgeError(?array &$stats, \Throwable $throwable): void
    {
        $this->initPurgeStats($stats);
        $stats['errors']++;
        $stats['lastError'] = $throwable->getMessage();
    }

    /**
     * @param array{cleanupCalls?: int, dryRunSkipped?: int, cleanedObjects?: int, cleanedDirectories?: int, errors?: int, lastError?: ?string}|null $stats
     */
    private function initCleanUpStats(?array &$stats): void
    {
        if ($stats === null) {
            $stats = [];
        }
        $stats += [
            'cleanupCalls' => 0,
            'dryRunSkipped' => 0,
            'cleanedObjects' => 0,
            'cleanedDirectories' => 0,
            'errors' => 0,
            'lastError' => null,
        ];
    }

    /**
     * @param array{cleanupCalls?: int, dryRunSkipped?: int, cleanedObjects?: int, cleanedDirectories?: int, errors?: int, lastError?: ?string}|null $stats
     */
    private function recordCleanUpError(?array &$stats, \Throwable $throwable): void
    {
        $this->initCleanUpStats($stats);
        $stats['errors']++;
        $stats['lastError'] = $throwable->getMessage();
    }

    /**
     * @param array{deletedDirs?: int, deletes?: int, deleteBytes?: int, errors?: int, lastError?: ?string, dryRunSkipped?: int, dryRunObjectSkipped?: int, directPurgeAttempts?: int}|null $stats
     * @return array{objects: list<ObjectInfo>, directories: list<ObjectInfo>}
     */
    private function fallbackPurge(
        MemoryProvider $provider,
        string $dir,
        bool $dryRun,
        ?array &$stats,
    ): array {
        $dir = self::normalizePath($dir);
        $objects = $provider->walk($dir, -1, true, false)['objects'];
        $deletedObjects = [];

        foreach ($objects as $object) {
            $stats['deletes']++;
            $stats['deleteBytes'] += max(0, $object->size);
            if ($dryRun) {
                $stats['dryRunObjectSkipped']++;
                continue;
            }

            $deletedObjects[] = $provider->deleteListedObject($object);
        }

        $removedDirectories = $this->removeEmptyDirectories(
            $provider,
            $dir,
            false,
            null,
            -1,
            $dryRun,
            $stats,
        );

        return [
            'objects' => $deletedObjects,
            'directories' => $removedDirectories,
        ];
    }

    private function applyDirectoryUpdate(MemoryProvider $target, ObjectInfo $sourceDir, bool $setDirMetadata): ObjectInfo
    {
        if ($setDirMetadata) {
            $metadata = $sourceDir->metadata;
            if ($sourceDir->modTime !== null && ($metadata['mtime'] ?? '') === '') {
                $metadata['mtime'] = $sourceDir->modTime;
            }

            return $target->mkdir($sourceDir->path, [
                'modTime' => $sourceDir->modTime,
                'metadata' => $metadata,
            ]);
        }

        return $target->setDirectoryModTime($sourceDir->path, $sourceDir->modTime);
    }

    /**
     * @param string|ObjectInfo $changedPath
     */
    private function changedPathDirectory(MemoryProvider $source, string|ObjectInfo $changedPath): string
    {
        $path = $changedPath instanceof ObjectInfo ? $changedPath->path : self::normalizePath($changedPath);
        if ($path === '') {
            return '';
        }

        if ($this->optionalInfo($source, $path) !== null) {
            return self::parentPath($path);
        }

        try {
            return $source->directoryInfo($path)->path;
        } catch (\RuntimeException) {
            return self::parentPath($path);
        }
    }

    private function sourceDirectoryIsEmpty(MemoryProvider $source, string $dir): bool
    {
        foreach ($source->list() as $info) {
            if (self::pathUnderPrefix($info->path, $dir)) {
                return false;
            }
        }

        return true;
    }

    private function rmdirsFilterIncludesRemote(string $path, ?FilterRuleSet $filter): bool
    {
        if ($filter === null) {
            return true;
        }

        return $filter->includesRemote($path === '' ? '/' : $path . '/');
    }

    private function withinRmdirsDepth(string $path, string $root, int $maxDepth): bool
    {
        if ($maxDepth < 0) {
            return true;
        }

        return self::relativePathLevel($path, $root) <= $maxDepth;
    }

    private function filterAllowsDirectory(MemoryProvider $source, string $dir, ?FilterRuleSet $filter): bool
    {
        if ($filter === null || $filter->includes($dir)) {
            return true;
        }

        foreach ($source->list() as $info) {
            if (self::pathUnderPrefix($info->path, $dir) && $filter->includes($info->path)) {
                return true;
            }
        }

        return false;
    }

    private function needsTransfer(
        MemoryProvider $source,
        MemoryProvider $target,
        ObjectInfo $sourceInfo,
        ?ObjectInfo $targetInfo,
        bool $ignoreTimes,
        bool $updateOlder,
        bool $noUpdateModTime,
        int $modifyWindowSeconds,
        bool $checksum,
        bool $immutable,
        bool $refreshTimes,
    ): bool
    {
        if ($targetInfo === null) {
            return true;
        }
        if ($ignoreTimes) {
            return true;
        }
        if ($updateOlder) {
            return $this->needsUpdateOlderTransfer(
                $source,
                $target,
                $sourceInfo,
                $targetInfo,
                $noUpdateModTime,
                $modifyWindowSeconds,
                $checksum,
                $immutable,
                $refreshTimes,
            );
        }

        return !$this->objectsEqualOrModTimeUpdated(
            $source,
            $target,
            $sourceInfo,
            $targetInfo,
            $noUpdateModTime,
            $modifyWindowSeconds,
            $checksum,
            $immutable,
            $refreshTimes,
        );
    }

    private function sameObject(ObjectInfo $left, ObjectInfo $right): bool
    {
        return $left->size === $right->size && $left->sha256 === $right->sha256;
    }

    private function needsUpdateOlderTransfer(
        MemoryProvider $source,
        MemoryProvider $target,
        ObjectInfo $sourceInfo,
        ObjectInfo $targetInfo,
        bool $noUpdateModTime,
        int $modifyWindowSeconds,
        bool $checksum,
        bool $immutable,
        bool $refreshTimes,
    ): bool {
        $dt = $this->modTimeDeltaSeconds($sourceInfo, $targetInfo);
        if ($dt === null) {
            return !$this->objectsEqualOrModTimeUpdated(
                $source,
                $target,
                $sourceInfo,
                $targetInfo,
                $noUpdateModTime,
                $modifyWindowSeconds,
                $checksum,
                $immutable,
                $refreshTimes,
            );
        }

        if ($this->modTimesWithinWindow($dt, $modifyWindowSeconds)) {
            if ($checksum) {
                return !$this->sameSizeAndHash($source, $target, $sourceInfo, $targetInfo, true);
            }

            return $sourceInfo->size !== $targetInfo->size;
        }

        if ($dt > 0) {
            return false;
        }

        return !$this->objectsEqualOrModTimeUpdated(
            $source,
            $target,
            $sourceInfo,
            $targetInfo,
            $noUpdateModTime,
            $modifyWindowSeconds,
            $checksum,
            $immutable,
            $refreshTimes,
            true,
        );
    }

    private function objectsEqualOrModTimeUpdated(
        MemoryProvider $source,
        MemoryProvider $target,
        ObjectInfo $sourceInfo,
        ObjectInfo $targetInfo,
        bool $noUpdateModTime,
        int $modifyWindowSeconds,
        bool $checksum,
        bool $immutable,
        bool $refreshTimes,
        bool $forceModTimeMatch = false,
    ): bool {
        if ($sourceInfo->size !== $targetInfo->size) {
            return false;
        }
        if ($checksum) {
            return $this->sameSizeAndHash($source, $target, $sourceInfo, $targetInfo, false);
        }

        $dt = $this->modTimeDeltaSeconds($sourceInfo, $targetInfo);
        if (!$forceModTimeMatch && $dt !== null && $this->modTimesWithinWindow($dt, $modifyWindowSeconds)) {
            return true;
        }

        $sameHash = $this->sameProviderHash($source, $target, $sourceInfo->path, $targetInfo->path);
        if ($sameHash !== true && !($sameHash === null && $refreshTimes)) {
            return false;
        }

        if ($sourceInfo->modTime !== $targetInfo->modTime) {
            if ($immutable) {
                return false;
            }
            if (!$noUpdateModTime) {
                $target->setModTime($targetInfo->path, $sourceInfo->modTime);
            }
        }

        return true;
    }

    private function sameSizeAndHash(
        MemoryProvider $source,
        MemoryProvider $target,
        ObjectInfo $sourceInfo,
        ObjectInfo $targetInfo,
        bool $fallbackToSizeOnly,
    ): bool {
        if ($sourceInfo->size !== $targetInfo->size) {
            return false;
        }

        $sameHash = $this->sameProviderHash($source, $target, $sourceInfo->path, $targetInfo->path);

        return $sameHash ?? $fallbackToSizeOnly;
    }

    private function sameProviderHash(MemoryProvider $source, MemoryProvider $target, string $sourcePath, string $targetPath): ?bool
    {
        $commonHashes = $source->supportedHashes()->overlap($target->supportedHashes());
        if ($commonHashes->count() === 0) {
            return null;
        }

        $hashType = $commonHashes->getOne();

        return ($source->hashes($sourcePath, new HashSet($hashType))[$hashType] ?? null)
            === ($target->hashes($targetPath, new HashSet($hashType))[$hashType] ?? null);
    }

    private function modTimeDeltaSeconds(ObjectInfo $sourceInfo, ObjectInfo $targetInfo): ?float
    {
        $sourceTime = $this->timestamp($sourceInfo->modTime);
        $targetTime = $this->timestamp($targetInfo->modTime);
        if ($sourceTime === null || $targetTime === null) {
            return null;
        }

        return $targetTime - $sourceTime;
    }

    private function timestamp(?string $modTime): ?float
    {
        if ($modTime === null || $modTime === '') {
            return null;
        }

        try {
            $dateTime = new \DateTimeImmutable($modTime);
        } catch (\Exception) {
            return null;
        }

        $seconds = (float) $dateTime->format('U');
        $micros = (float) $dateTime->format('u') / 1_000_000;

        return $seconds + $micros;
    }

    private function modTimesWithinWindow(float $deltaSeconds, int $modifyWindowSeconds): bool
    {
        if ($modifyWindowSeconds <= 0) {
            return $deltaSeconds === 0.0;
        }

        return abs($deltaSeconds) < $modifyWindowSeconds;
    }

    /**
     * @param list<MemoryProvider> $references
     *
     * @return array{provider: MemoryProvider, path: string}|null
     */
    private function findEqualReference(ObjectInfo $sourceInfo, ?ObjectInfo $targetInfo, array $references): ?array
    {
        $referencePath = $targetInfo?->path ?? $sourceInfo->path;
        foreach ($references as $reference) {
            $referenceInfo = $this->optionalInfo($reference, $referencePath);
            if ($referenceInfo !== null && $this->sameObject($sourceInfo, $referenceInfo)) {
                return [
                    'provider' => $reference,
                    'path' => $referenceInfo->path,
                ];
            }
        }

        return null;
    }

    private function trackRenamesDisabledReason(
        MemoryProvider $source,
        MemoryProvider $target,
        TrackRenamesStrategy $strategy,
        string $deleteMode,
    ): ?string {
        if ($deleteMode === DeleteMode::OFF) {
            return "track-renames requires sync delete mode";
        }
        if (!$target->supportsServerSideMove()) {
            return 'destination does not support server-side move or copy';
        }
        if ($strategy->usesHash() && $this->commonHashType($source, $target) === null) {
            return 'source and destination do not have a common hash';
        }

        return null;
    }

    private function commonHashType(MemoryProvider $source, MemoryProvider $target): ?string
    {
        $commonHashes = $source->supportedHashes()->overlap($target->supportedHashes());
        if ($commonHashes->count() === 0) {
            return null;
        }

        return $commonHashes->getOne();
    }

    /**
     * @param list<ObjectInfo> $sourceOnly
     * @param list<ObjectInfo> $targetOnly
     * @return array<string, list<ObjectInfo>>
     */
    private function buildRenameMap(
        array $sourceOnly,
        array $targetOnly,
        MemoryProvider $target,
        TrackRenamesStrategy $strategy,
        ?string $hashType,
    ): array {
        $possibleSizes = [];
        foreach ($sourceOnly as $sourceInfo) {
            $possibleSizes[$sourceInfo->size] = true;
        }

        $renameMap = [];
        foreach ($targetOnly as $targetInfo) {
            if (!isset($possibleSizes[$targetInfo->size])) {
                continue;
            }

            $renameId = $this->trackRenameId($target, $targetInfo, $strategy, $hashType);
            if ($renameId === '') {
                continue;
            }

            $renameMap[$renameId][] = $targetInfo;
        }

        return $renameMap;
    }

    private function trackRenameId(
        MemoryProvider $provider,
        ObjectInfo $info,
        TrackRenamesStrategy $strategy,
        ?string $hashType,
    ): string {
        $id = (string) $info->size;
        if ($strategy->usesHash()) {
            if ($hashType === null) {
                return '';
            }
            $hash = $provider->hashes($info->path, new HashSet($hashType))[$hashType] ?? '';
            if ($hash === '') {
                return '';
            }
            $id .= ',' . $hash;
        }
        if ($strategy->usesLeaf()) {
            $id .= ',' . self::pathBase($info->path);
        }

        return $id;
    }

    /**
     * @param array<string, list<ObjectInfo>> $renameMap
     */
    private function popRenameCandidate(
        array &$renameMap,
        string $renameId,
        ObjectInfo $sourceInfo,
        TrackRenamesStrategy $strategy,
        int $modifyWindowSeconds,
    ): ?ObjectInfo {
        if ($renameId === '' || !isset($renameMap[$renameId]) || $renameMap[$renameId] === []) {
            return null;
        }

        $index = 0;
        if ($strategy->usesModTime()) {
            $index = null;
            foreach ($renameMap[$renameId] as $candidateIndex => $targetInfo) {
                $dt = $this->modTimeDeltaSeconds($sourceInfo, $targetInfo);
                if ($dt !== null && $this->modTimesWithinWindow($dt, $modifyWindowSeconds)) {
                    $index = $candidateIndex;
                    break;
                }
            }
            if ($index === null) {
                return null;
            }
        }

        $candidate = $renameMap[$renameId][$index];
        array_splice($renameMap[$renameId], $index, 1);
        if ($renameMap[$renameId] === []) {
            unset($renameMap[$renameId]);
        }

        return $candidate;
    }

    private function assertDeleteWithinLimits(
        int $deleteCount,
        int $deleteBytes,
        int $nextSize,
        ?int $maxDelete,
        ?int $maxDeleteSize,
    ): void {
        if ($maxDelete !== null && $maxDelete >= 0 && $deleteCount + 1 > $maxDelete) {
            throw new \RuntimeException('--max-delete threshold reached');
        }
        if ($maxDeleteSize !== null && $maxDeleteSize >= 0 && $deleteBytes + $nextSize > $maxDeleteSize) {
            throw new \RuntimeException('--max-delete-size threshold reached');
        }
    }

    private function backupRequested(?MemoryProvider $backup, string $backupPrefix, string $suffix): bool
    {
        return $backup !== null || $backupPrefix !== '' || $suffix !== '';
    }

    private function moveToBackup(
        MemoryProvider $target,
        string $path,
        ?MemoryProvider $backup,
        string $backupPrefix,
        string $suffix,
        bool $suffixKeepExtension,
        ?array &$diagnostics = null,
    ): ObjectInfo {
        $backup ??= $target;
        $backupPath = self::backupPath($path, $backupPrefix, $suffix, $suffixKeepExtension);
        $sourceInfo = $target->info($path);
        $diagnostics = [
            'sourcePath' => $sourceInfo->path,
            'backupPath' => $backupPath,
            'existingBackupPath' => null,
            'accounting' => $this->emptyFileAccounting(),
            'logEvents' => [],
            'loggerEvents' => [],
        ];

        $overwritten = $this->optionalInfo($backup, $backupPath);
        if ($overwritten !== null && !$this->sameProviderObject($target, $sourceInfo, $backup, $overwritten)) {
            $diagnostics['existingBackupPath'] = $overwritten->path;
            $diagnostics['accounting']['checkingTransfers']++;
            $diagnostics['accounting']['deletedFiles']++;
            $diagnostics['accounting']['deletedBytes'] += max(0, $overwritten->size);
            $backup->delete($overwritten->path);
            $diagnostics['logEvents'][] = [
                'level' => 'info',
                'path' => $overwritten->path,
                'message' => 'Deleted',
            ];
        } elseif ($overwritten !== null) {
            $diagnostics['existingBackupPath'] = $overwritten->path;
        }

        $moved = $target->serverSideMoveTo(
            $path,
            $backup,
            $backupPath,
        );
        $diagnostics['accounting']['checkingTransfers']++;
        $diagnostics['accounting']['renames']++;
        $diagnostics['accounting']['serverSideMoves']++;
        $diagnostics['logEvents'][] = [
            'level' => 'info',
            'path' => $sourceInfo->path,
            'message' => $sourceInfo->path === $moved->path
                ? 'Moved (server-side)'
                : 'Moved (server-side) to: ' . $moved->path,
        ];
        $diagnostics['loggerEvents'][] = [
            'type' => 'missing-on-dst',
            'sourcePath' => $sourceInfo->path,
            'destinationPath' => null,
            'error' => null,
        ];

        return $moved;
    }

    private static function suffixName(string $path, string $suffix, bool $suffixKeepExtension): string
    {
        if ($suffix === '') {
            return $path;
        }
        if (!$suffixKeepExtension) {
            return $path . $suffix;
        }

        [$base, $extensions] = self::splitExtension($path);

        return $base . $suffix . $extensions;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private static function splitExtension(string $path): array
    {
        $base = $path;
        $extensions = '';
        $first = true;

        while (($extension = self::pathExtension($base)) !== '') {
            if (!$first && !self::isKnownExtension($extension)) {
                break;
            }

            $base = substr($base, 0, -strlen($extension));
            $extensions = $extension . $extensions;
            $first = false;
        }

        return [$base, $extensions];
    }

    private static function pathExtension(string $path): string
    {
        $slash = strrpos($path, '/');
        $nameStart = $slash === false ? 0 : $slash + 1;
        $dot = strrpos($path, '.');
        if ($dot === false || $dot < $nameStart) {
            return '';
        }

        return substr($path, $dot);
    }

    private static function isKnownExtension(string $extension): bool
    {
        return in_array(strtolower($extension), [
            '.css',
            '.gif',
            '.gz',
            '.htm',
            '.html',
            '.jpeg',
            '.jpg',
            '.js',
            '.json',
            '.mjs',
            '.pdf',
            '.png',
            '.sql',
            '.svg',
            '.tar',
            '.txt',
            '.webp',
            '.wxr',
            '.xml',
            '.zip',
        ], true);
    }

    private static function normalizePath(string $path): string
    {
        return trim(preg_replace('#/+#', '/', $path) ?? $path, '/');
    }

    private static function joinPath(string ...$segments): string
    {
        $joined = '';
        foreach ($segments as $segment) {
            $segment = self::normalizePath($segment);
            if ($segment === '') {
                continue;
            }
            $joined = $joined === '' ? $segment : $joined . '/' . $segment;
        }

        return $joined;
    }

    private static function pathUnderPrefix(string $path, string $prefix): bool
    {
        $path = self::normalizePath($path);
        $prefix = self::normalizePath($prefix);

        return $path === $prefix || str_starts_with($path, $prefix . '/');
    }

    private static function pathIsOrUnderRmdirRoot(string $path, string $root): bool
    {
        $path = self::normalizePath($path);
        $root = self::normalizePath($root);

        return $root === '' || $path === $root || str_starts_with($path, $root . '/');
    }

    private static function parentPath(string $path): string
    {
        $path = self::normalizePath($path);
        if ($path === '' || !str_contains($path, '/')) {
            return '';
        }

        return substr($path, 0, strrpos($path, '/')) ?: '';
    }

    private static function pathBase(string $path): string
    {
        $path = self::normalizePath($path);
        if (!str_contains($path, '/')) {
            return $path;
        }

        return substr($path, strrpos($path, '/') + 1);
    }

    private static function replacePathPrefix(string $path, string $sourcePrefix, string $targetPrefix): string
    {
        $path = self::normalizePath($path);
        $sourcePrefix = self::normalizePath($sourcePrefix);
        $targetPrefix = self::normalizePath($targetPrefix);
        if ($path === $sourcePrefix) {
            return $targetPrefix;
        }

        return $targetPrefix . substr($path, strlen($sourcePrefix));
    }

    private static function relativePathUnderRoot(string $path, string $root): string
    {
        $path = self::normalizePath($path);
        $root = self::normalizePath($root);
        if ($root === '') {
            return $path;
        }
        if ($path === $root) {
            return '';
        }
        if (!str_starts_with($path, $root . '/')) {
            throw new \InvalidArgumentException("path {$path} is not under {$root}");
        }

        return substr($path, strlen($root) + 1);
    }

    private static function pathLevel(string $path): int
    {
        $path = self::normalizePath($path);

        return $path === '' ? 0 : substr_count($path, '/') + 1;
    }

    private static function relativePathLevel(string $path, string $root): int
    {
        $path = self::normalizePath($path);
        $root = self::normalizePath($root);
        if ($path === $root) {
            return 0;
        }
        if ($root !== '' && str_starts_with($path, $root . '/')) {
            $path = substr($path, strlen($root) + 1);
        }

        return self::pathLevel($path);
    }

    private function samePathDifferentCase(string $left, string $right): bool
    {
        $left = self::normalizePath($left);
        $right = self::normalizePath($right);

        return $left !== $right && strtolower($left) === strtolower($right);
    }

    private static function normalizeRoot(string $root): string
    {
        $root = str_replace('\\', '/', trim($root));
        $root = preg_replace('#/+#', '/', $root) ?? $root;
        if (str_contains($root, ':')) {
            [$remote, $path] = explode(':', $root, 2);

            return $remote . ':' . trim($path, '/');
        }

        return trim($root, '/');
    }

    private static function sameRootConfig(string $left, string $right): bool
    {
        return self::splitRoot($left)[0] === self::splitRoot($right)[0];
    }

    private static function sameRootPath(string $left, string $right): bool
    {
        return self::splitRoot($left) === self::splitRoot($right);
    }

    private static function rootsOverlap(string $left, string $right): bool
    {
        [$leftConfig, $leftPath] = self::splitRoot($left);
        [$rightConfig, $rightPath] = self::splitRoot($right);
        if ($leftConfig !== $rightConfig) {
            return false;
        }
        if ($leftPath === '' || $rightPath === '') {
            return true;
        }

        return $leftPath === $rightPath
            || str_starts_with($leftPath, $rightPath . '/')
            || str_starts_with($rightPath, $leftPath . '/');
    }

    /**
     * @return array{0: string, 1: string}
     */
    private static function splitRoot(string $root): array
    {
        if (str_contains($root, ':')) {
            [$config, $path] = explode(':', $root, 2);

            return [$config, trim($path, '/')];
        }

        return ['local', trim($root, '/')];
    }
}
