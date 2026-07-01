<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class OpenDocumentPackageByteHandoff
{
    /**
     * @param array<string, array<string, mixed>> $parts
     * @return array<string, mixed>
     */
    public static function summarize(ZipPackage $package, array $parts, string $pathKey, array $manifestEntries = []): array
    {
        $requests = [];
        foreach ($parts as $part) {
            if (($part['canExposeBytes'] ?? false) !== true) {
                continue;
            }

            $path = $part[$pathKey] ?? ($part['path'] ?? ($part['part'] ?? null));
            if (!is_string($path) || $path === '') {
                continue;
            }

            $requests[] = [
                'name' => $path,
                'required' => true,
                'kind' => 'file',
                'role' => self::role($part),
            ];
        }

        $handoff = $package->entryHandoffPreflight($requests);
        $reviewRequests = self::reviewRequests($parts, $manifestEntries, $pathKey);
        $reviewHandoff = $package->entryHandoffPreflight($reviewRequests);
        $selectedSourceReviewFieldBytes = (int) $handoff['selectedSourceLocalReviewFieldBytes']
            + (int) $handoff['selectedSourceCentralDirectoryReviewFieldBytes'];

        return [
            'format' => 'odt',
            'requestCount' => count($requests),
            'requestedEntryCount' => $handoff['requestedEntryCount'],
            'selectedUniqueEntryCount' => $handoff['selectedUniqueEntryCount'],
            'handoffEntryCount' => $handoff['handoffEntryCount'],
            'failedEntryCount' => $handoff['failedEntryCount'],
            'missingEntryCount' => $handoff['missingEntryCount'],
            'isSupportedByBoundedReader' => $handoff['isSupportedByBoundedReader'],
            'issues' => $handoff['issues'],
            'selectedSourceManifestVersion' => $handoff['selectedSourceManifestVersion'],
            'selectedSourceManifestSha256' => $handoff['selectedSourceManifestSha256'],
            'selectedSourceManifest' => $handoff['selectedSourceManifest'],
            'selectedSourceHasArchiveTrailer' => $handoff['selectedSourceHasArchiveTrailer'],
            'selectedSourceArchiveTrailerOffset' => $handoff['selectedSourceArchiveTrailerOffset'],
            'selectedSourceArchiveTrailerBytes' => $handoff['selectedSourceArchiveTrailerBytes'],
            'selectedSourceArchiveTrailerEnd' => $handoff['selectedSourceArchiveTrailerEnd'],
            'selectedSourceArchiveTrailerSha256' => $handoff['selectedSourceArchiveTrailerSha256'],
            'selectedSourceArchiveTrailerReviewFieldBytes' => $handoff['selectedSourceArchiveTrailerReviewFieldBytes'],
            'selectedSourceArchiveTrailerByteExposurePolicy' => $handoff['selectedSourceArchiveTrailerByteExposurePolicy'],
            'selectedSourceArchiveTrailerCanExposeBytes' => $handoff['selectedSourceArchiveTrailerCanExposeBytes'],
            'selectedSourceEndOfCentralDirectoryOffset' => $handoff['selectedSourceEndOfCentralDirectoryOffset'],
            'selectedSourceEndOfCentralDirectoryBytes' => $handoff['selectedSourceEndOfCentralDirectoryBytes'],
            'selectedSourceEndOfCentralDirectorySha256' => $handoff['selectedSourceEndOfCentralDirectorySha256'],
            'selectedSourceEndOfCentralDirectoryFixedHeaderBytes' =>
                $handoff['selectedSourceEndOfCentralDirectoryFixedHeaderBytes'],
            'selectedSourceEndOfCentralDirectoryFixedHeaderSha256' =>
                $handoff['selectedSourceEndOfCentralDirectoryFixedHeaderSha256'],
            'selectedSourcePackageCommentOffset' => $handoff['selectedSourcePackageCommentOffset'],
            'selectedSourcePackageCommentBytes' => $handoff['selectedSourcePackageCommentBytes'],
            'selectedSourcePackageCommentEnd' => $handoff['selectedSourcePackageCommentEnd'],
            'selectedSourcePackageCommentSha256' => $handoff['selectedSourcePackageCommentSha256'],
            'selectedSourcePackageCommentPreviewHex' => $handoff['selectedSourcePackageCommentPreviewHex'],
            'selectedSourcePackageCommentPreviewByteCount' => $handoff['selectedSourcePackageCommentPreviewByteCount'],
            'selectedSourcePackageCommentByteExposurePolicy' => $handoff['selectedSourcePackageCommentByteExposurePolicy'],
            'selectedSourceCanExposePackageCommentBytes' => $handoff['selectedSourceCanExposePackageCommentBytes'],
            'selectedSourceHasPackageComment' => $handoff['selectedSourceHasPackageComment'],
            'selectedSourceArchiveTrailer' => $handoff['selectedSourceArchiveTrailer'],
            'selectedHandoffManifestVersion' => $handoff['selectedHandoffManifestVersion'],
            'selectedHandoffManifestSha256' => $handoff['selectedHandoffManifestSha256'],
            'selectedHandoffManifest' => $handoff['selectedHandoffManifest'],
            'selectedSourceLocalRecordBytes' => $handoff['selectedSourceLocalRecordBytes'],
            'selectedSourceLocalHeaderBytes' => $handoff['selectedSourceLocalHeaderBytes'],
            'selectedSourceLocalHeaderVariableFieldBytes' => $handoff['selectedSourceLocalHeaderVariableFieldBytes'],
            'selectedSourceLocalRawNameBytes' => $handoff['selectedSourceLocalRawNameBytes'],
            'selectedSourceLocalExtraFieldBytes' => $handoff['selectedSourceLocalExtraFieldBytes'],
            'selectedSourceLocalReviewFieldBytes' => $handoff['selectedSourceLocalReviewFieldBytes'],
            'selectedSourceCompressedDataBytes' => $handoff['selectedSourceCompressedDataBytes'],
            'selectedSourceDataDescriptorBytes' => $handoff['selectedSourceDataDescriptorBytes'],
            'selectedSourceCentralDirectoryRecordBytes' => $handoff['selectedSourceCentralDirectoryRecordBytes'],
            'selectedSourceCentralDirectoryFixedHeaderBytes' => $handoff['selectedSourceCentralDirectoryFixedHeaderBytes'],
            'selectedSourceCentralDirectoryVariableFieldBytes' => $handoff['selectedSourceCentralDirectoryVariableFieldBytes'],
            'selectedSourceCentralDirectoryRawNameBytes' => $handoff['selectedSourceCentralDirectoryRawNameBytes'],
            'selectedSourceCentralDirectoryExtraFieldBytes' => $handoff['selectedSourceCentralDirectoryExtraFieldBytes'],
            'selectedSourceCentralDirectoryRawCommentBytes' => $handoff['selectedSourceCentralDirectoryRawCommentBytes'],
            'selectedSourceCentralDirectoryReviewFieldBytes' => $handoff['selectedSourceCentralDirectoryReviewFieldBytes'],
            'selectedSourceReviewFieldBytes' => $selectedSourceReviewFieldBytes,
            'selectedSourceTotalRecordBytes' => $handoff['selectedSourceTotalRecordBytes'],
            'selectedSourceByteSpanBucketCount' => $handoff['selectedSourceByteSpanBucketCount'],
            'selectedSourceByteSpanBuckets' => $handoff['selectedSourceByteSpanBuckets'],
            'roleSummaries' => $handoff['roleSummaries'],
            'handoffEntries' => $handoff['handoffEntries'],
            'reviewRequestCount' => count($reviewRequests),
            'reviewRequestNames' => array_column($reviewRequests, 'name'),
            'reviewRequestedEntryCount' => $reviewHandoff['requestedEntryCount'],
            'reviewSelectedUniqueEntryCount' => $reviewHandoff['selectedUniqueEntryCount'],
            'reviewHandoffEntryCount' => $reviewHandoff['handoffEntryCount'],
            'reviewFailedEntryCount' => $reviewHandoff['failedEntryCount'],
            'reviewMissingEntryCount' => $reviewHandoff['missingEntryCount'],
            'reviewUnreadableEntryCount' => $reviewHandoff['unreadableEntryCount'],
            'reviewSelectedUnsupportedCompressionMethodCount' =>
                $reviewHandoff['selectedUnsupportedCompressionMethodCount'],
            'reviewIsSupportedByBoundedReader' => $reviewHandoff['isSupportedByBoundedReader'],
            'reviewIssues' => $reviewHandoff['issues'],
            'reviewSelectedSourceManifestVersion' => $reviewHandoff['selectedSourceManifestVersion'],
            'reviewSelectedSourceManifestSha256' => $reviewHandoff['selectedSourceManifestSha256'],
            'reviewSelectedSourceManifest' => $reviewHandoff['selectedSourceManifest'],
            'reviewSelectedHandoffManifestVersion' => $reviewHandoff['selectedHandoffManifestVersion'],
            'reviewSelectedHandoffManifestSha256' => $reviewHandoff['selectedHandoffManifestSha256'],
            'reviewSelectedHandoffManifest' => $reviewHandoff['selectedHandoffManifest'],
            'reviewRoleSummaries' => $reviewHandoff['roleSummaries'],
            'reviewSelectedUnsupportedCompressionMethodEntries' =>
                $reviewHandoff['selectedUnsupportedCompressionMethodEntries'],
            'reviewMissingEntries' => $reviewHandoff['missingEntries'],
            'reviewFailedEntries' => $reviewHandoff['failedEntries'],
            'reviewHandoffEntries' => $reviewHandoff['handoffEntries'],
            'reviewEntries' => $reviewHandoff['entries'],
            'reviewByteExposurePolicy' => 'odf-selected-package-byte-handoff-review-metadata-only',
            'reviewCanExposeBytes' => false,
            'byteExposurePolicy' => 'odf-selected-package-byte-handoff-metadata-only',
            'canExposeBytes' => false,
        ];
    }

    /**
     * @param array<string, array<string, mixed>> $parts
     * @param list<array<string, mixed>> $manifestEntries
     * @return list<array{name:string, required:bool, kind:string, role:string}>
     */
    private static function reviewRequests(array $parts, array $manifestEntries, string $pathKey): array
    {
        $requests = [];
        $seen = [];
        $partsByPath = [];
        foreach ($parts as $part) {
            $path = self::path($part, $pathKey);
            if ($path !== null) {
                $partsByPath[$path] = $part;
            }
        }

        foreach (['mimetype', 'META-INF/manifest.xml'] as $controlPath) {
            if (isset($partsByPath[$controlPath])) {
                self::appendReviewRequest($requests, $seen, $controlPath, $partsByPath[$controlPath]);
            }
        }

        foreach ($manifestEntries as $manifestEntry) {
            if (!is_array($manifestEntry)) {
                continue;
            }
            $path = self::path($manifestEntry, $pathKey);
            if ($path === null || $path === '/') {
                continue;
            }
            $reviewPart = $partsByPath[$path] ?? $manifestEntry;
            if (!self::shouldReviewPart($reviewPart, $path)) {
                continue;
            }
            self::appendReviewRequest($requests, $seen, $path, $reviewPart);
        }

        foreach ($parts as $part) {
            $path = self::path($part, $pathKey);
            if ($path === null || !self::shouldReviewPart($part, $path)) {
                continue;
            }
            self::appendReviewRequest($requests, $seen, $path, $part);
        }

        return $requests;
    }

    /**
     * @param list<array{name:string, required:bool, kind:string, role:string}> $requests
     * @param array<string, bool> $seen
     * @param array<string, mixed> $part
     */
    private static function appendReviewRequest(array &$requests, array &$seen, string $path, array $part): void
    {
        if ($path === '' || $path === '/' || isset($seen[$path])) {
            return;
        }

        $seen[$path] = true;
        $requests[] = [
            'name' => $path,
            'required' => false,
            'kind' => str_ends_with($path, '/') ? 'directory' : 'file',
            'role' => self::role($part),
        ];
    }

    /**
     * @param array<string, mixed> $part
     */
    private static function shouldReviewPart(array $part, string $path): bool
    {
        if ($path === 'mimetype' || $path === 'META-INF/manifest.xml') {
            return true;
        }
        if (($part['canExposeBytes'] ?? false) === true) {
            return true;
        }

        $byteExposurePolicy = is_string($part['byteExposurePolicy'] ?? null) ? $part['byteExposurePolicy'] : null;

        return in_array($byteExposurePolicy, [
            'missing-package-part',
            'unsupported-compression-bytes-blocked',
        ], true);
    }

    /**
     * @param array<string, mixed> $part
     */
    private static function path(array $part, string $pathKey): ?string
    {
        foreach ([$pathKey, 'path', 'part', 'packagePath', 'fullPath'] as $key) {
            $path = $part[$key] ?? null;
            if (is_string($path) && $path !== '') {
                return $path;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $part
     */
    private static function role(array $part): string
    {
        $roles = is_array($part['roles'] ?? null)
            ? array_values(array_filter($part['roles'], static fn (mixed $role): bool => is_string($role) && $role !== ''))
            : [];
        foreach (['odf-content', 'odf-styles', 'odf-meta', 'odf-settings', 'media-resource'] as $preferredRole) {
            if (in_array($preferredRole, $roles, true)) {
                return $preferredRole;
            }
        }

        $path = self::path($part, 'path') ?? '';
        return match ($path) {
            'mimetype' => 'odf-mimetype',
            'META-INF/manifest.xml' => 'odf-manifest',
            'content.xml' => 'odf-content',
            'styles.xml' => 'odf-styles',
            'meta.xml' => 'odf-meta',
            'settings.xml' => 'odf-settings',
            default => self::fallbackRole($part, $roles),
        };
    }

    /**
     * @param array<string, mixed> $part
     * @param list<string> $roles
     */
    private static function fallbackRole(array $part, array $roles): string
    {
        $mediaType = strtolower((string) (
            $part['mediaTypeBase']
            ?? $part['mediaType']
            ?? $part['manifestMediaTypeBase']
            ?? $part['manifestMediaType']
            ?? ''
        ));
        $path = self::path($part, 'path') ?? '';
        if (
            str_starts_with($mediaType, 'image/')
            || str_starts_with($mediaType, 'audio/')
            || str_starts_with($mediaType, 'video/')
            || str_starts_with($path, 'Pictures/')
            || str_starts_with($path, 'Media/')
        ) {
            return 'media-resource';
        }

        $byteExposurePolicy = is_string($part['byteExposurePolicy'] ?? null) ? $part['byteExposurePolicy'] : null;
        if ($byteExposurePolicy === 'missing-package-part') {
            return 'missing-package-part';
        }
        if ($byteExposurePolicy === 'unsupported-compression-bytes-blocked') {
            return 'unsupported-compression';
        }

        return is_string($roles[0] ?? null) ? $roles[0] : 'package-bytes-exposable';
    }
}
