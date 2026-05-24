<?php

declare(strict_types=1);

namespace PortLibs\Rclone;

/**
 * Focused native model of OneDrive permission metadata write planning.
 *
 * Upstream stores permissions as OneDrive JSON metadata, then sorts queued
 * permissions into remove, add, and update queues before making Graph calls.
 * This class models those local decisions without contacting OneDrive.
 */
final class OneDrivePermissionPlanner
{
    public const DRIVE_TYPE_PERSONAL = 'personal';
    public const DRIVE_TYPE_BUSINESS = 'business';
    public const DRIVE_TYPE_SHAREPOINT = 'documentLibrary';

    /**
     * @param list<array<string, mixed>> $currentPermissions
     * @param list<array<string, mixed>|null> $queuedPermissions
     * @param array{driveType?: string, metadataPermissions?: string, metadata_permissions?: string, normalizedId?: string, normalizedID?: string, id?: string, addOnly?: bool, failOk?: bool, operationErrors?: array<string, string>, refreshedPermissions?: list<array<string, mixed>>, refreshError?: string} $options
     * @return array<string, mixed>
     */
    public static function writePermissions(array $currentPermissions, array $queuedPermissions, array $options = []): array
    {
        $mode = (string) ($options['metadataPermissions'] ?? $options['metadata_permissions'] ?? 'write');
        if (!self::modeHas($mode, 'write')) {
            throw new \RuntimeException("can't write permissions without --onedrive-metadata-permissions write");
        }

        $normalizedId = self::optionalString(
            $options['normalizedId']
                ?? $options['normalizedID']
                ?? $options['id']
                ?? null,
        ) ?? '';
        if ($normalizedId === '') {
            throw new \RuntimeException('internal error: normalizedID is missing');
        }

        $failOk = (bool) ($options['failOk'] ?? self::modeHas($mode, 'failok'));
        $plan = self::plan($currentPermissions, $queuedPermissions, [
            'driveType' => $options['driveType'] ?? null,
            'write' => true,
            'addOnly' => (bool) ($options['addOnly'] ?? false),
            'failOk' => false,
            'operationErrors' => is_array($options['operationErrors'] ?? null) ? $options['operationErrors'] : [],
        ]);
        $plan['failOk'] = $failOk;

        $result = [
            'normalizedId' => $normalizedId,
            'driveType' => $plan['driveType'],
            'addOnly' => $plan['addOnly'],
            'failOk' => $failOk,
            'operations' => $plan['operations'],
            'counts' => $plan['counts'],
            'skipped' => $plan['skipped'],
            'processError' => $plan['error'],
            'refreshAttempted' => false,
            'refreshError' => null,
            'permissions' => self::normalizePermissions($currentPermissions),
            'queuedPermissionsCleared' => false,
            'suppressedErrors' => [],
            'error' => null,
        ];

        if ($plan['error'] !== null) {
            if ($failOk) {
                $result['suppressedErrors'][] = $plan['error'];

                return $result;
            }

            $result['error'] = $plan['error'];

            return $result;
        }

        $result['refreshAttempted'] = true;
        $refreshError = self::optionalString($options['refreshError'] ?? null);
        if ($refreshError !== null && $refreshError !== '') {
            $error = 'failed to get permissions: failed to refresh permissions: ' . $refreshError;
            $result['refreshError'] = $error;
            if ($failOk) {
                $result['suppressedErrors'][] = $error;

                return $result;
            }

            $result['error'] = $error;

            return $result;
        }

        $refreshed = is_array($options['refreshedPermissions'] ?? null)
            ? $options['refreshedPermissions']
            : $queuedPermissions;
        $result['permissions'] = self::normalizePermissions($refreshed);
        $result['queuedPermissionsCleared'] = true;

        return $result;
    }

    /**
     * @param array<string, string> $metadata
     * @param array{exists?: bool, directoryId?: string, normalizedId?: string, parentId?: string, parentLookupError?: string, createError?: string, createConflict?: bool, itemToDirEntryError?: string, itemToDirEntryType?: string, childCount?: int, driveType?: string, metadataPermissions?: string, metadata_permissions?: string, currentPermissions?: list<array<string, mixed>>, refreshBeforePermissions?: list<array<string, mixed>>, refreshedPermissions?: list<array<string, mixed>>, refreshBeforeError?: string, refreshError?: string, operationErrors?: array<string, string>, failOk?: bool} $options
     * @return array<string, mixed>
     */
    public static function directoryMetadataFlow(string $remote, array $metadata, array $options = []): array
    {
        $exists = (bool) ($options['exists'] ?? false);
        $mode = (string) ($options['metadataPermissions'] ?? $options['metadata_permissions'] ?? 'write');
        $needsPermissions = array_key_exists('permissions', $metadata) && self::modeHas($mode, 'write');
        $writeable = self::writeableMetadataKeys($metadata, $mode);
        $apiMetadata = array_values(array_intersect($writeable, ['mtime', 'btime']));
        $normalizedId = self::optionalString(
            $options['directoryId']
                ?? $options['normalizedId']
                ?? null,
        ) ?? ($exists ? 'dir:' . $remote : 'created:' . $remote);

        $flow = [
            'remote' => $remote,
            'exists' => $exists,
            'parentId' => self::optionalString($options['parentId'] ?? null),
            'writeableMetadata' => $writeable,
            'apiMetadata' => $apiMetadata,
            'needsUpdatePermissions' => $needsPermissions,
            'sequence' => [],
            'permissionWrite' => null,
            'permissions' => self::normalizePermissions($options['currentPermissions'] ?? []),
            'queuedPermissionsCleared' => false,
            'directoryReturned' => false,
            'systemMetadataSet' => false,
            'conflictBehavior' => null,
            'graphItemConverted' => false,
            'dirCachePut' => null,
            'childCount' => null,
            'error' => null,
        ];

        $flow['sequence'][] = $exists ? 'find-dir:exists' : 'find-dir:missing';
        $queuedPermissions = [];
        if ($needsPermissions) {
            $queuedPermissions = self::decodePermissionsMetadata($metadata['permissions']);
        }

        if (!$exists) {
            $flow['sequence'][] = 'find-parent';
            $parentLookupError = self::optionalString($options['parentLookupError'] ?? null);
            if ($parentLookupError !== null && $parentLookupError !== '') {
                $flow['error'] = $parentLookupError;

                return $flow;
            }

            $flow['parentId'] ??= 'parent:' . dirname($remote);
            $flow['sequence'][] = 'create-dir';
            $flow['conflictBehavior'] = 'fail';
            if ((bool) ($options['createConflict'] ?? false)) {
                $flow['error'] = 'nameAlreadyExists';

                return $flow;
            }

            $createError = self::optionalString($options['createError'] ?? null);
            if ($createError !== null && $createError !== '') {
                $flow['error'] = $createError;

                return $flow;
            }

            if ($apiMetadata !== []) {
                $flow['sequence'][] = 'create-dir:api-metadata';
            }
            if ($needsPermissions && $writeable !== []) {
                $flow['sequence'][] = 'refresh-permissions-before-write';
                $refreshBeforeError = self::optionalString($options['refreshBeforeError'] ?? null);
                if ($refreshBeforeError !== null && $refreshBeforeError !== '') {
                    $flow['error'] = 'failed to refresh permissions: ' . $refreshBeforeError;

                    return $flow;
                }

                $flow['sequence'][] = 'write-permissions';
                $flow = self::applyDirectoryPermissionWrite($flow, $queuedPermissions, $normalizedId, $options);
                if ($flow['error'] !== null) {
                    return $flow;
                }
            }
            $flow['sequence'][] = 'write-directory-metadata-after-create';
            if ($apiMetadata === []) {
                $flow['error'] = $remote . ': no writeable metadata found';

                return $flow;
            }
        } else {
            $flow['sequence'][] = 'update-dir';
            if ($apiMetadata === []) {
                $flow['error'] = $remote . ': no writeable metadata found';

                return $flow;
            }

            $flow['sequence'][] = 'patch-directory-metadata';
            if ($needsPermissions) {
                $flow['sequence'][] = 'write-permissions';
                $flow = self::applyDirectoryPermissionWrite($flow, $queuedPermissions, $normalizedId, $options);
                if ($flow['error'] !== null) {
                    return $flow;
                }
            }
        }

        $flow['sequence'][] = 'item-to-dir-entry';
        $itemToDirEntryError = self::optionalString($options['itemToDirEntryError'] ?? null);
        if ($itemToDirEntryError !== null && $itemToDirEntryError !== '') {
            $flow['error'] = $itemToDirEntryError;

            return $flow;
        }

        $entryType = self::optionalString($options['itemToDirEntryType'] ?? null) ?? 'directory';
        if ($entryType !== 'directory') {
            $flow['error'] = 'internal error: expecting OneDrive item to be a directory';

            return $flow;
        }

        $flow['graphItemConverted'] = true;
        $flow['dirCachePut'] = [
            'remote' => $remote,
            'id' => $normalizedId,
        ];
        $flow['childCount'] = (int) ($options['childCount'] ?? -1);
        $flow['sequence'][] = 'set-system-metadata';
        $flow['directoryReturned'] = true;
        $flow['systemMetadataSet'] = true;

        return $flow;
    }

    /**
     * @param array<string, string> $metadata
     * @param array{normalizedId?: string, normalizedID?: string, objectId?: string, id?: string, driveType?: string, metadataPermissions?: string, metadata_permissions?: string, currentPermissions?: list<array<string, mixed>>, refreshBeforePermissions?: list<array<string, mixed>>, refreshedPermissions?: list<array<string, mixed>>, refreshBeforeError?: string, refreshError?: string, operationErrors?: array<string, string>, failOk?: bool, noVersions?: bool, deleteVersionsError?: string} $options
     * @return array<string, mixed>
     */
    public static function objectMetadataFlow(string $remote, array $metadata, array $options = []): array
    {
        $mode = (string) ($options['metadataPermissions'] ?? $options['metadata_permissions'] ?? 'write');
        $needsPermissions = array_key_exists('permissions', $metadata) && self::modeHas($mode, 'write');
        $writeable = self::writeableMetadataKeys($metadata, $mode);
        $apiMetadata = array_values(array_intersect($writeable, ['mtime', 'btime']));
        $normalizedId = self::optionalString(
            $options['normalizedId']
                ?? $options['normalizedID']
                ?? $options['objectId']
                ?? $options['id']
                ?? null,
        ) ?? 'object:' . $remote;
        $permissions = self::normalizePermissions($options['currentPermissions'] ?? []);

        $flow = [
            'remote' => $remote,
            'normalizedId' => $normalizedId,
            'writeableMetadata' => $writeable,
            'apiMetadata' => $apiMetadata,
            'needsUpdatePermissions' => $needsPermissions,
            'sequence' => [
                'get-current-metadata',
            ],
            'permissionWrite' => null,
            'permissions' => $permissions,
            'queuedPermissionsCleared' => false,
            'objectReturned' => false,
            'systemMetadataSet' => false,
            'objectMetadataSet' => false,
            'versionsDeleteAttempted' => false,
            'versionsDeleted' => false,
            'error' => null,
        ];

        if (self::modeHas($mode, 'read')) {
            $flow['sequence'][] = 'refresh-permissions-before-set';
            $refreshBeforeError = self::optionalString($options['refreshBeforeError'] ?? null);
            if ($refreshBeforeError !== null && $refreshBeforeError !== '') {
                $flow['error'] = 'failed to get permissions: ' . $refreshBeforeError;

                return $flow;
            }

            $permissions = is_array($options['refreshBeforePermissions'] ?? null)
                ? self::normalizePermissions($options['refreshBeforePermissions'])
                : $permissions;
            $flow['permissions'] = $permissions;
        }

        $queuedPermissions = [];
        if ($needsPermissions) {
            $queuedPermissions = self::decodePermissionsMetadata($metadata['permissions']);
        }

        $flow['sequence'][] = 'set-metadata';
        if ($writeable === []) {
            $flow['sequence'][] = 'no-writeable-metadata-noop';

            return $flow;
        }

        $flow['sequence'][] = 'write-object-metadata';
        if ($apiMetadata === []) {
            $flow['error'] = $remote . ': no writeable metadata found';

            return $flow;
        }

        if ($needsPermissions) {
            $flow['sequence'][] = 'write-permissions';
            $permissionWrite = self::writePermissions($permissions, $queuedPermissions, [
                'driveType' => $options['driveType'] ?? null,
                'metadataPermissions' => $mode,
                'normalizedId' => $normalizedId,
                'failOk' => (bool) ($options['failOk'] ?? self::modeHas($mode, 'failok')),
                'operationErrors' => is_array($options['operationErrors'] ?? null) ? $options['operationErrors'] : [],
                'refreshedPermissions' => is_array($options['refreshedPermissions'] ?? null)
                    ? $options['refreshedPermissions']
                    : $queuedPermissions,
                'refreshError' => $options['refreshError'] ?? null,
            ]);
            if ($permissionWrite['refreshAttempted']) {
                $flow['sequence'][] = 'refresh-permissions-after-write';
            }
            if ($permissionWrite['queuedPermissionsCleared']) {
                $flow['sequence'][] = 'clear-queued-permissions';
            }

            $flow['permissionWrite'] = $permissionWrite;
            $flow['permissions'] = $permissionWrite['permissions'];
            $flow['queuedPermissionsCleared'] = $permissionWrite['queuedPermissionsCleared'];
            $flow['error'] = $permissionWrite['error'];
            if ($flow['error'] !== null) {
                return $flow;
            }
        }

        $flow['sequence'][] = 'set-system-metadata';
        $flow['sequence'][] = 'set-object-metadata';
        $flow['systemMetadataSet'] = true;
        $flow['objectMetadataSet'] = true;
        $flow['objectReturned'] = true;

        if ((bool) ($options['noVersions'] ?? false)) {
            $flow['sequence'][] = 'delete-versions';
            $flow['versionsDeleteAttempted'] = true;
            $deleteVersionsError = self::optionalString($options['deleteVersionsError'] ?? null);
            if ($deleteVersionsError !== null && $deleteVersionsError !== '') {
                $flow['error'] = $remote . ': Failed to remove versions: ' . $deleteVersionsError;

                return $flow;
            }

            $flow['versionsDeleted'] = true;
        }

        return $flow;
    }

    /**
     * @param array<string, string>|null $sourceMetadata Null models --metadata disabled or a source without metadata.
     * @param array{sourceMetadataError?: string, metadataReadError?: string, hasObjectMetadata?: bool, noVersions?: bool, setModTimeError?: string, deleteVersionsError?: string, normalizedId?: string, normalizedID?: string, objectId?: string, id?: string, driveType?: string, metadataPermissions?: string, metadata_permissions?: string, currentPermissions?: list<array<string, mixed>>, refreshBeforePermissions?: list<array<string, mixed>>, refreshedPermissions?: list<array<string, mixed>>, refreshBeforeError?: string, refreshError?: string, operationErrors?: array<string, string>, failOk?: bool} $options
     * @return array<string, mixed>
     */
    public static function fetchAndUpdateMetadataFlow(string $remote, ?array $sourceMetadata, array $options = []): array
    {
        $flow = [
            'remote' => $remote,
            'sourceMetadataPresent' => $sourceMetadata !== null,
            'sequence' => [
                'get-source-metadata-options',
            ],
            'metadataUpdate' => null,
            'infoReturned' => false,
            'modTimeSetAttempted' => false,
            'modTimeSet' => false,
            'versionsDeleteAttempted' => false,
            'versionsDeleted' => false,
            'suppressedErrors' => [],
            'error' => null,
        ];

        $sourceMetadataError = self::optionalString(
            $options['sourceMetadataError']
                ?? $options['metadataReadError']
                ?? null,
        );
        if ($sourceMetadataError !== null && $sourceMetadataError !== '') {
            $flow['error'] = 'failed to read metadata from source object: ' . $sourceMetadataError;

            return $flow;
        }

        if ($sourceMetadata === null) {
            $flow['sequence'][] = 'set-modtime';
            $flow['modTimeSetAttempted'] = true;

            if ((bool) ($options['noVersions'] ?? false)) {
                $flow['sequence'][] = 'delete-versions-after-set-modtime';
                $flow['versionsDeleteAttempted'] = true;
                $deleteVersionsError = self::optionalString($options['deleteVersionsError'] ?? null);
                if ($deleteVersionsError !== null && $deleteVersionsError !== '') {
                    $flow['suppressedErrors'][] = $remote . ': Failed to remove versions: ' . $deleteVersionsError;
                } else {
                    $flow['versionsDeleted'] = true;
                }
            }

            $setModTimeError = self::optionalString($options['setModTimeError'] ?? null);
            if ($setModTimeError !== null && $setModTimeError !== '') {
                $flow['error'] = $setModTimeError;

                return $flow;
            }

            $flow['modTimeSet'] = true;
            $flow['infoReturned'] = true;

            return $flow;
        }

        if (!(bool) ($options['hasObjectMetadata'] ?? true)) {
            $flow['sequence'][] = 'new-object-metadata';
        }

        $metadataUpdate = self::objectMetadataFlow($remote, $sourceMetadata, $options);
        $flow['sequence'] = array_merge($flow['sequence'], $metadataUpdate['sequence']);
        $flow['metadataUpdate'] = $metadataUpdate;
        $flow['infoReturned'] = (bool) $metadataUpdate['objectReturned'];
        $flow['error'] = $metadataUpdate['error'];

        return $flow;
    }

    /**
     * @param array<string, string>|null $sourceMetadata Null models --metadata disabled or a source without metadata.
     * @param array{uploadError?: string, setUploadedMetadataError?: string, setFetchedMetadataError?: string, sourceMetadataError?: string, metadataReadError?: string, hasObjectMetadata?: bool, noVersions?: bool, setModTimeError?: string, deleteVersionsError?: string, normalizedId?: string, normalizedID?: string, objectId?: string, id?: string, driveType?: string, metadataPermissions?: string, metadata_permissions?: string, currentPermissions?: list<array<string, mixed>>, refreshBeforePermissions?: list<array<string, mixed>>, refreshedPermissions?: list<array<string, mixed>>, refreshBeforeError?: string, refreshError?: string, operationErrors?: array<string, string>, failOk?: bool} $options
     * @return array<string, mixed>
     */
    public static function uploadSinglepartMetadataFlow(string $remote, ?array $sourceMetadata, array $options = []): array
    {
        $flow = [
            'remote' => $remote,
            'sequence' => [
                'upload-singlepart',
            ],
            'fetch' => null,
            'infoReturned' => false,
            'versionsDeleteAttempted' => false,
            'versionsDeleted' => false,
            'suppressedErrors' => [],
            'error' => null,
        ];

        $uploadError = self::optionalString($options['uploadError'] ?? null);
        if ($uploadError !== null && $uploadError !== '') {
            $flow['error'] = $uploadError;

            return $flow;
        }

        $flow['sequence'][] = 'set-upload-metadata';
        $setUploadedMetadataError = self::optionalString($options['setUploadedMetadataError'] ?? null);
        if ($setUploadedMetadataError !== null && $setUploadedMetadataError !== '') {
            $flow['error'] = $setUploadedMetadataError;

            return $flow;
        }

        $flow['sequence'][] = 'fetch-and-update-metadata';
        $fetch = self::fetchAndUpdateMetadataFlow($remote, $sourceMetadata, $options);
        $flow['fetch'] = $fetch;
        $flow['sequence'] = array_merge($flow['sequence'], $fetch['sequence']);
        $flow['versionsDeleteAttempted'] = $fetch['versionsDeleteAttempted'];
        $flow['versionsDeleted'] = $fetch['versionsDeleted'];
        $flow['suppressedErrors'] = $fetch['suppressedErrors'];

        if ($fetch['error'] !== null) {
            $flow['error'] = 'failed to fetch and update metadata: ' . $fetch['error'];

            return $flow;
        }

        if ($fetch['infoReturned']) {
            $flow['sequence'][] = 'set-upload-metadata-from-fetch';
            $setFetchedMetadataError = self::optionalString($options['setFetchedMetadataError'] ?? null);
            if ($setFetchedMetadataError !== null && $setFetchedMetadataError !== '') {
                $flow['error'] = $setFetchedMetadataError;

                return $flow;
            }
        }

        $flow['infoReturned'] = $fetch['infoReturned'];

        return $flow;
    }

    /**
     * @param array<string, string>|null $sourceMetadata Null models --metadata disabled or a source without metadata.
     * @param array{size?: int, uploadError?: string, createSessionError?: string, uploadFragmentError?: string, setUploadedMetadataError?: string, setFinalMetadataError?: string, sourceMetadataError?: string, metadataReadError?: string, normalizedId?: string, normalizedID?: string, objectId?: string, id?: string, driveType?: string, metadataPermissions?: string, metadata_permissions?: string, currentPermissions?: list<array<string, mixed>>, refreshBeforePermissions?: list<array<string, mixed>>, refreshedPermissions?: list<array<string, mixed>>, refreshBeforeError?: string, refreshError?: string, operationErrors?: array<string, string>, failOk?: bool, noVersions?: bool, deleteVersionsError?: string} $options
     * @return array<string, mixed>
     */
    public static function uploadMultipartMetadataFlow(string $remote, ?array $sourceMetadata, array $options = []): array
    {
        $mode = (string) ($options['metadataPermissions'] ?? $options['metadata_permissions'] ?? 'write');
        $writeable = $sourceMetadata === null ? [] : self::writeableMetadataKeys($sourceMetadata, $mode);
        $apiMetadata = array_values(array_intersect($writeable, ['mtime', 'btime']));
        $needsPermissions = $sourceMetadata !== null
            && array_key_exists('permissions', $sourceMetadata)
            && self::modeHas($mode, 'write');

        $flow = [
            'remote' => $remote,
            'sourceMetadataPresent' => $sourceMetadata !== null,
            'writeableMetadata' => $writeable,
            'apiMetadata' => $apiMetadata,
            'needsUpdatePermissions' => $needsPermissions,
            'sequence' => [
                'upload-multipart',
            ],
            'metadataUpdate' => null,
            'initialMetadataSet' => false,
            'finalMetadataSet' => false,
            'infoReturned' => false,
            'updateReturnedInfo' => false,
            'updateErrorIgnored' => false,
            'ignoredUpdateError' => null,
            'sessionCancelled' => false,
            'error' => null,
        ];

        $size = (int) ($options['size'] ?? 8 * 1024 * 1024);
        if ($size <= 0) {
            $flow['error'] = 'unknown-sized upload not supported';

            return $flow;
        }

        $flow['sequence'][] = 'create-upload-session';
        $flow['sequence'][] = 'get-source-metadata-options';
        $sourceMetadataError = self::optionalString(
            $options['sourceMetadataError']
                ?? $options['metadataReadError']
                ?? null,
        );
        if ($sourceMetadataError !== null && $sourceMetadataError !== '') {
            $flow['error'] = 'failed to read metadata from source object: ' . $sourceMetadataError;

            return $flow;
        }

        if ($writeable !== []) {
            $flow['sequence'][] = 'create-session:api-metadata';
        }

        $createSessionError = self::optionalString($options['createSessionError'] ?? null);
        if ($createSessionError !== null && $createSessionError !== '') {
            $flow['error'] = $createSessionError;

            return $flow;
        }

        $flow['sequence'][] = 'upload-fragments';
        $uploadFragmentError = self::optionalString($options['uploadFragmentError'] ?? $options['uploadError'] ?? null);
        if ($uploadFragmentError !== null && $uploadFragmentError !== '') {
            $flow['sessionCancelled'] = true;
            $flow['sequence'][] = 'cancel-upload-session';
            $flow['error'] = $uploadFragmentError;

            return $flow;
        }

        $flow['sequence'][] = 'set-upload-metadata';
        $setUploadedMetadataError = self::optionalString($options['setUploadedMetadataError'] ?? null);
        if ($setUploadedMetadataError !== null && $setUploadedMetadataError !== '') {
            $flow['infoReturned'] = true;
            $flow['sessionCancelled'] = true;
            $flow['sequence'][] = 'cancel-upload-session';
            $flow['error'] = $setUploadedMetadataError;

            return $flow;
        }
        $flow['initialMetadataSet'] = true;

        if (!$needsPermissions) {
            $flow['infoReturned'] = true;

            return $flow;
        }

        $flow['sequence'][] = 'update-metadata-for-permissions';
        $metadataUpdate = self::objectMetadataFlow($remote, $sourceMetadata, $options);
        $flow['metadataUpdate'] = $metadataUpdate;
        $flow['sequence'] = array_merge($flow['sequence'], $metadataUpdate['sequence']);

        $updateReturnedInfo = (bool) $metadataUpdate['objectReturned'];
        if (!$updateReturnedInfo
            && $metadataUpdate['error'] !== null
            && $metadataUpdate['apiMetadata'] !== []
            && is_array($metadataUpdate['permissionWrite'] ?? null)) {
            $updateReturnedInfo = true;
        }
        $flow['updateReturnedInfo'] = $updateReturnedInfo;

        if (!$updateReturnedInfo) {
            $flow['sessionCancelled'] = $metadataUpdate['error'] !== null;
            if ($flow['sessionCancelled']) {
                $flow['sequence'][] = 'cancel-upload-session';
            }
            $flow['error'] = $metadataUpdate['error'];

            return $flow;
        }

        if ($metadataUpdate['error'] !== null) {
            $flow['updateErrorIgnored'] = true;
            $flow['ignoredUpdateError'] = $metadataUpdate['error'];
        }

        $flow['sequence'][] = 'set-upload-metadata-after-permission-update';
        $setFinalMetadataError = self::optionalString($options['setFinalMetadataError'] ?? null);
        if ($setFinalMetadataError !== null && $setFinalMetadataError !== '') {
            $flow['infoReturned'] = true;
            $flow['sessionCancelled'] = true;
            $flow['sequence'][] = 'cancel-upload-session';
            $flow['error'] = $setFinalMetadataError;

            return $flow;
        }

        $flow['finalMetadataSet'] = true;
        $flow['infoReturned'] = true;

        return $flow;
    }

    /**
     * Model backend/onedrive Object.Update upload selection without Graph calls.
     *
     * @param array<string, string>|null $sourceMetadata Null models --metadata disabled or a source without metadata.
     * @param array{size?: int, uploadCutoff?: int, hasMetadata?: bool, hasMetaData?: bool, isOneNoteFile?: bool, noVersions?: bool, deleteVersionsError?: string, updateDeleteVersionsError?: string, uploadError?: string, createSessionError?: string, uploadFragmentError?: string, setUploadedMetadataError?: string, setFetchedMetadataError?: string, setFinalMetadataError?: string, sourceMetadataError?: string, metadataReadError?: string, hasObjectMetadata?: bool, setModTimeError?: string, normalizedId?: string, normalizedID?: string, objectId?: string, id?: string, driveType?: string, metadataPermissions?: string, metadata_permissions?: string, currentPermissions?: list<array<string, mixed>>, refreshBeforePermissions?: list<array<string, mixed>>, refreshedPermissions?: list<array<string, mixed>>, refreshBeforeError?: string, refreshError?: string, operationErrors?: array<string, string>, failOk?: bool} $options
     * @return array<string, mixed>
     */
    public static function objectUpdateUploadFlow(string $remote, ?array $sourceMetadata, array $options = []): array
    {
        $size = (int) ($options['size'] ?? 0);
        $uploadCutoff = (int) ($options['uploadCutoff'] ?? -1);
        $hasMetadata = (bool) ($options['hasMetadata'] ?? $options['hasMetaData'] ?? false);
        $isOneNoteFile = (bool) ($options['isOneNoteFile'] ?? false);

        $flow = [
            'remote' => $remote,
            'sourceSize' => $size,
            'uploadCutoff' => $uploadCutoff,
            'existingHasMetadata' => $hasMetadata,
            'isOneNoteFile' => $isOneNoteFile,
            'selectedUpload' => null,
            'sequence' => [
                'object-update',
            ],
            'upload' => null,
            'versionsDeleteAttempted' => false,
            'versionsDeleted' => false,
            'suppressedErrors' => [],
            'error' => null,
        ];

        if ($hasMetadata && $isOneNoteFile) {
            $flow['sequence'][] = 'reject-onenote';
            $flow['error'] = "can't upload content to a OneNote file";

            return $flow;
        }

        if ($size > 0 && $size >= $uploadCutoff) {
            $flow['selectedUpload'] = 'multipart';
            $upload = self::uploadMultipartMetadataFlow($remote, $sourceMetadata, $options);
        } elseif ($size >= 0) {
            $flow['selectedUpload'] = 'singlepart';
            $upload = self::uploadSinglepartMetadataFlow($remote, $sourceMetadata, $options);
        } else {
            $flow['error'] = 'unknown-sized upload not supported';

            return $flow;
        }

        $flow['upload'] = $upload;
        $flow['sequence'] = array_merge($flow['sequence'], $upload['sequence']);
        if ($upload['error'] !== null) {
            $flow['error'] = $upload['error'];

            return $flow;
        }

        if ((bool) ($options['noVersions'] ?? false) && $hasMetadata) {
            $flow['sequence'][] = 'delete-versions-after-update';
            $flow['versionsDeleteAttempted'] = true;

            $deleteVersionsError = self::optionalString(
                $options['updateDeleteVersionsError']
                    ?? $options['deleteVersionsError']
                    ?? null,
            );
            if ($deleteVersionsError !== null && $deleteVersionsError !== '') {
                $flow['suppressedErrors'][] = $remote . ': Failed to remove versions: ' . $deleteVersionsError;
            } else {
                $flow['versionsDeleted'] = true;
            }
        }

        return $flow;
    }

    /**
     * Model backend/onedrive Fs.Put/createObject upload setup without Graph calls.
     *
     * @param array<string, string>|null $sourceMetadata Null models --metadata disabled or a source without metadata.
     * @param array{size?: int, uploadCutoff?: int, parentId?: string, parentID?: string, parentLookupError?: string, nameAlreadyExists?: bool, nameAlreadyExistsHint?: string, uploadError?: string, createSessionError?: string, uploadFragmentError?: string, setUploadedMetadataError?: string, setFetchedMetadataError?: string, setFinalMetadataError?: string, sourceMetadataError?: string, metadataReadError?: string, hasObjectMetadata?: bool, setModTimeError?: string, normalizedId?: string, normalizedID?: string, objectId?: string, id?: string, driveType?: string, metadataPermissions?: string, metadata_permissions?: string, currentPermissions?: list<array<string, mixed>>, refreshBeforePermissions?: list<array<string, mixed>>, refreshedPermissions?: list<array<string, mixed>>, refreshBeforeError?: string, refreshError?: string, operationErrors?: array<string, string>, failOk?: bool} $options
     * @return array<string, mixed>
     */
    public static function putCreateObjectFlow(string $remote, ?array $sourceMetadata, array $options = []): array
    {
        $size = (int) ($options['size'] ?? 0);
        $uploadCutoff = (int) ($options['uploadCutoff'] ?? -1);
        $parentId = self::optionalString($options['parentId'] ?? $options['parentID'] ?? null);

        $flow = [
            'remote' => $remote,
            'sourceSize' => $size,
            'uploadCutoff' => $uploadCutoff,
            'parentId' => $parentId,
            'temporaryObjectHasMetadata' => false,
            'selectedUpload' => null,
            'sequence' => [
                'fs-put',
                'create-object',
            ],
            'upload' => null,
            'error' => null,
        ];

        $flow['sequence'][] = 'find-parent';
        $parentLookupError = self::optionalString($options['parentLookupError'] ?? null);
        if ($parentLookupError !== null && $parentLookupError !== '') {
            $flow['error'] = "couldn't find parent ID: " . $parentLookupError;

            return $flow;
        }

        $flow['parentId'] = $parentId ?? 'parent:' . self::parentRemote($remote);
        $flow['sequence'][] = 'create-temporary-object';

        if ($size < 0) {
            $flow['error'] = 'unknown-sized upload not supported';

            return $flow;
        }

        if ($size > 0 && $size >= $uploadCutoff) {
            $flow['selectedUpload'] = 'multipart';
            $upload = self::uploadMultipartMetadataFlow($remote, $sourceMetadata, $options);
        } else {
            $flow['selectedUpload'] = 'singlepart';
            $upload = self::uploadSinglepartMetadataFlow($remote, $sourceMetadata, $options);
        }

        if ($upload['error'] !== null && (bool) ($options['nameAlreadyExists'] ?? false)) {
            $hint = self::optionalString($options['nameAlreadyExistsHint'] ?? null)
                ?? 'OneNote files cannot be overwritten by rclone';
            $upload['error'] .= ' (' . $hint . ')';
        }

        $flow['upload'] = $upload;
        $flow['sequence'] = array_merge($flow['sequence'], $upload['sequence']);
        $flow['error'] = $upload['error'];

        return $flow;
    }

    /**
     * @param list<array<string, mixed>> $currentPermissions
     * @param array<string, mixed> $metadata
     * @param array{driveType?: string, metadataPermissions?: string, addOnly?: bool, operationErrors?: array<string, string>} $options
     * @return array<string, mixed>
     */
    public static function fromMetadata(array $currentPermissions, array $metadata, array $options = []): array
    {
        if (!array_key_exists('permissions', $metadata)) {
            return self::emptyPlan(self::driveType($options['driveType'] ?? null), (bool) ($options['addOnly'] ?? false), false);
        }

        $mode = (string) ($options['metadataPermissions'] ?? $options['metadata_permissions'] ?? 'write');
        if (!self::modeHas($mode, 'write')) {
            $plan = self::emptyPlan(self::driveType($options['driveType'] ?? null), (bool) ($options['addOnly'] ?? false), self::modeHas($mode, 'failok'));
            $plan['skipped'][] = [
                'reason' => 'metadata-permissions-write-disabled',
                'id' => null,
            ];

            return $plan;
        }

        $queued = self::decodePermissionsMetadata($metadata['permissions']);
        $options['failOk'] = (bool) ($options['failOk'] ?? self::modeHas($mode, 'failok'));
        $options['write'] = true;

        return self::plan($currentPermissions, $queued, $options);
    }

    /**
     * @param list<array<string, mixed>> $currentPermissions
     * @param list<array<string, mixed>|null> $queuedPermissions
     * @param array{driveType?: string, write?: bool, addOnly?: bool, failOk?: bool, operationErrors?: array<string, string>} $options
     * @return array<string, mixed>
     */
    public static function plan(array $currentPermissions, array $queuedPermissions, array $options = []): array
    {
        $driveType = self::driveType($options['driveType'] ?? null);
        $addOnly = (bool) ($options['addOnly'] ?? false);
        $failOk = (bool) ($options['failOk'] ?? false);
        $plan = self::emptyPlan($driveType, $addOnly, $failOk);

        if (!(bool) ($options['write'] ?? true)) {
            $plan['skipped'][] = [
                'reason' => 'metadata-permissions-write-disabled',
                'id' => null,
            ];

            return $plan;
        }

        $current = self::normalizePermissions($currentPermissions);
        $queued = self::normalizePermissions($queuedPermissions);
        [$add, $update, $remove] = self::sortPermissions($current, $queued, $driveType, $addOnly, $plan['skipped']);
        $operationErrors = is_array($options['operationErrors'] ?? null) ? $options['operationErrors'] : [];

        foreach ($remove as $permission) {
            $id = self::permissionId($permission);
            $operation = [
                'action' => 'remove',
                'method' => 'DELETE',
                'path' => '/permissions/' . $id,
                'id' => $id,
                'roles' => self::roles($permission),
            ];
            $plan['operations'][] = $operation;
            $plan['counts']['remove']++;
            self::appendOperationError($operationErrors, $operation, $plan);
        }

        foreach ($add as $permission) {
            $operation = self::addOperation($permission, $driveType, $plan);
            if ($operation === null) {
                continue;
            }

            $plan['operations'][] = $operation;
            $plan['counts']['add']++;
            self::appendOperationError($operationErrors, $operation, $plan);
        }

        foreach ($update as $permission) {
            $operation = self::updateOperation($permission, $plan);
            if ($operation === null) {
                continue;
            }

            $plan['operations'][] = $operation;
            $plan['counts']['update']++;
            self::appendOperationError($operationErrors, $operation, $plan);
        }

        if ($plan['errors'] !== []) {
            $error = self::aggregateErrors($plan['errors']);
            if ($failOk) {
                $plan['suppressedErrors'][] = $error;
                $plan['errors'] = [];
            } else {
                $plan['error'] = $error;
            }
        }

        return $plan;
    }

    /**
     * @return array<string, mixed>
     */
    private static function emptyPlan(string $driveType, bool $addOnly, bool $failOk): array
    {
        return [
            'driveType' => $driveType,
            'addOnly' => $addOnly,
            'failOk' => $failOk,
            'counts' => [
                'remove' => 0,
                'add' => 0,
                'update' => 0,
            ],
            'operations' => [],
            'skipped' => [],
            'errors' => [],
            'suppressedErrors' => [],
            'error' => null,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function decodePermissionsMetadata(mixed $permissions): array
    {
        if (is_array($permissions)) {
            if ($permissions === []) {
                return [];
            }
            if (!array_is_list($permissions)) {
                throw new \RuntimeException('failed to unmarshal permissions: permissions metadata must be a JSON array');
            }

            return self::normalizePermissions($permissions);
        }

        $decoded = json_decode((string) $permissions, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('failed to unmarshal permissions: ' . json_last_error_msg());
        }
        if (!is_array($decoded)) {
            throw new \RuntimeException('failed to unmarshal permissions: permissions metadata must be a JSON array');
        }
        if ($decoded !== [] && !array_is_list($decoded)) {
            throw new \RuntimeException('failed to unmarshal permissions: permissions metadata must be a JSON array');
        }

        return self::normalizePermissions($decoded);
    }

    /**
     * @param array<string, mixed> $flow
     * @param list<array<string, mixed>> $queuedPermissions
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    private static function applyDirectoryPermissionWrite(
        array $flow,
        array $queuedPermissions,
        string $normalizedId,
        array $options
    ): array {
        $current = is_array($options['refreshBeforePermissions'] ?? null)
            ? $options['refreshBeforePermissions']
            : (is_array($options['currentPermissions'] ?? null) ? $options['currentPermissions'] : []);
        $permissionWrite = self::writePermissions($current, $queuedPermissions, [
            'driveType' => $options['driveType'] ?? null,
            'metadataPermissions' => $options['metadataPermissions'] ?? $options['metadata_permissions'] ?? 'write',
            'normalizedId' => $normalizedId,
            'failOk' => (bool) (
                $options['failOk']
                    ?? self::modeHas((string) ($options['metadataPermissions'] ?? $options['metadata_permissions'] ?? 'write'), 'failok')
            ),
            'operationErrors' => is_array($options['operationErrors'] ?? null) ? $options['operationErrors'] : [],
            'refreshedPermissions' => is_array($options['refreshedPermissions'] ?? null)
                ? $options['refreshedPermissions']
                : $queuedPermissions,
            'refreshError' => $options['refreshError'] ?? null,
        ]);

        if ($permissionWrite['refreshAttempted']) {
            $flow['sequence'][] = 'refresh-permissions-after-write';
        }
        if ($permissionWrite['queuedPermissionsCleared']) {
            $flow['sequence'][] = 'clear-queued-permissions';
        }

        $flow['permissionWrite'] = $permissionWrite;
        $flow['permissions'] = $permissionWrite['permissions'];
        $flow['queuedPermissionsCleared'] = $permissionWrite['queuedPermissionsCleared'];
        $flow['error'] = $permissionWrite['error'];

        return $flow;
    }

    /**
     * @param array<string, string> $metadata
     * @return list<string>
     */
    private static function writeableMetadataKeys(array $metadata, string $mode): array
    {
        $keys = [];
        foreach ($metadata as $key => $_value) {
            if ($key === 'mtime' || $key === 'btime') {
                $keys[] = $key;
                continue;
            }
            if ($key === 'permissions' && self::modeHas($mode, 'write')) {
                $keys[] = $key;
            }
        }

        return $keys;
    }

    private static function driveType(mixed $value): string
    {
        $driveType = self::optionalString($value);
        if ($driveType === null || $driveType === '') {
            return self::DRIVE_TYPE_PERSONAL;
        }

        return match (strtolower($driveType)) {
            self::DRIVE_TYPE_PERSONAL => self::DRIVE_TYPE_PERSONAL,
            self::DRIVE_TYPE_BUSINESS => self::DRIVE_TYPE_BUSINESS,
            'documentlibrary', 'sharepoint' => self::DRIVE_TYPE_SHAREPOINT,
            default => $driveType,
        };
    }

    private static function modeHas(string $mode, string $flag): bool
    {
        $mode = strtolower(trim($mode));
        if ($mode === '' || in_array($mode, ['off', 'false', '0', 'none'], true)) {
            return false;
        }

        return in_array($flag, preg_split('/[|,\s+]+/', $mode) ?: [], true);
    }

    /**
     * @param array<int, array<string, mixed>|null> $permissions
     * @return list<array<string, mixed>>
     */
    private static function normalizePermissions(array $permissions): array
    {
        $normalized = [];
        foreach ($permissions as $permission) {
            if (is_array($permission)) {
                $normalized[] = $permission;
            }
        }

        return $normalized;
    }

    /**
     * @param list<array<string, mixed>> $current
     * @param list<array<string, mixed>> $queued
     * @param list<array{reason: string, id: ?string, roles?: list<string>}> $skipped
     * @return array{0: list<array<string, mixed>>, 1: list<array<string, mixed>>, 2: list<array<string, mixed>>}
     */
    private static function sortPermissions(array $current, array $queued, string $driveType, bool $addOnly, array &$skipped): array
    {
        if ($current === [] || $addOnly) {
            self::orderPermissions($queued, $driveType);

            return [$queued, [], []];
        }

        $add = [];
        $update = [];
        $remove = [];

        foreach ($queued as $permission) {
            $id = self::permissionId($permission);
            if ($id !== '') {
                if (!self::hasValidRoleUpdate($permission, $current)) {
                    $skipped[] = [
                        'reason' => 'invalid-update-roles',
                        'id' => $id,
                        'roles' => self::roles($permission),
                    ];
                    continue;
                }

                if ($driveType !== self::DRIVE_TYPE_PERSONAL && self::linkWebUrl($permission) !== '') {
                    $remove[] = $permission;
                    $add[] = $permission;
                    continue;
                }

                $update[] = $permission;
                continue;
            }

            $add[] = $permission;
        }

        foreach ($current as $permission) {
            $id = self::permissionId($permission);
            if (self::hasRole($permission, 'owner')) {
                $skipped[] = [
                    'reason' => 'owner-role-remove-suppressed',
                    'id' => $id !== '' ? $id : null,
                    'roles' => self::roles($permission),
                ];
                continue;
            }

            if ($id !== '' && !self::queuedHasId($queued, $id)) {
                $remove[] = $permission;
            }
        }

        self::orderPermissions($add, $driveType);
        self::orderPermissions($update, $driveType);
        self::orderPermissions($remove, $driveType);

        return [$add, $update, $remove];
    }

    /**
     * @param array<string, mixed> $permission
     * @param list<array<string, mixed>> $current
     */
    private static function hasValidRoleUpdate(array $permission, array $current): bool
    {
        $id = self::permissionId($permission);
        $newRoles = self::roles($permission);
        foreach ($current as $old) {
            $oldRoles = self::roles($old);
            if (self::permissionId($old) === $id
                && $oldRoles !== $newRoles
                && $oldRoles !== []
                && $newRoles !== []
                && !in_array('owner', $oldRoles, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<array<string, mixed>> $permissions
     */
    private static function queuedHasId(array $permissions, string $id): bool
    {
        foreach ($permissions as $permission) {
            if (self::permissionId($permission) === $id) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<array<string, mixed>> $permissions
     */
    private static function orderPermissions(array &$permissions, string $driveType): void
    {
        usort(
            $permissions,
            static function (array $left, array $right) use ($driveType): int {
                $leftHasUser = self::hasUserIdentity($left, $driveType);
                $rightHasUser = self::hasUserIdentity($right, $driveType);
                if ($leftHasUser && !$rightHasUser) {
                    return -1;
                }
                if (!$leftHasUser && $rightHasUser) {
                    return 1;
                }

                return 0;
            },
        );
    }

    /**
     * @param array<string, mixed> $permission
     * @param array<string, mixed> $plan
     * @return null|array<string, mixed>
     */
    private static function addOperation(array $permission, string $driveType, array &$plan): ?array
    {
        $roles = self::roles($permission);
        $recipients = self::recipients($permission, $driveType);
        $publicLink = self::linkScope($permission) === 'anonymous';

        if ($publicLink) {
            $webUrl = self::linkWebUrl($permission);
            $permission['link']['webUrl'] = $webUrl !== '' ? $webUrl : 'public-link';
        }

        if ($recipients === [] && !$publicLink) {
            $plan['skipped'][] = [
                'reason' => 'add-missing-recipient',
                'id' => self::nullablePermissionId($permission),
                'roles' => $roles,
            ];

            return null;
        }
        if ($roles === []) {
            $plan['errors'][] = 'at least one role is required to add a permission (choices: read, write, owner, member)';

            return null;
        }
        if (in_array('owner', $roles, true)) {
            $plan['skipped'][] = [
                'reason' => 'add-owner-role-suppressed',
                'id' => self::nullablePermissionId($permission),
                'roles' => $roles,
            ];

            return null;
        }

        return [
            'action' => 'add',
            'method' => 'POST',
            'path' => '/invite',
            'id' => self::nullablePermissionId($permission),
            'roles' => $roles,
            'recipients' => $recipients,
            'publicLink' => $publicLink,
            'invite' => $recipients !== [],
            'linkScope' => self::linkScope($permission),
        ];
    }

    /**
     * @param array<string, mixed> $permission
     * @param array<string, mixed> $plan
     * @return null|array<string, mixed>
     */
    private static function updateOperation(array $permission, array &$plan): ?array
    {
        $roles = self::roles($permission);
        if ($roles === []) {
            $plan['errors'][] = 'at least one role is required to update a permission (choices: read, write, owner, member)';

            return null;
        }

        $id = self::permissionId($permission);

        return [
            'action' => 'update',
            'method' => 'PATCH',
            'path' => '/permissions/' . $id,
            'id' => $id,
            'roles' => $roles,
        ];
    }

    /**
     * @param array<string, string> $operationErrors
     * @param array<string, mixed> $operation
     * @param array<string, mixed> $plan
     */
    private static function appendOperationError(array $operationErrors, array $operation, array &$plan): void
    {
        $id = self::optionalString($operation['id'] ?? null) ?? '*';
        $keys = [
            ($operation['action'] ?? '') . ':' . $id,
            ($operation['action'] ?? '') . ':*',
        ];
        foreach ($keys as $key) {
            if (isset($operationErrors[$key]) && $operationErrors[$key] !== '') {
                $plan['errors'][] = $operationErrors[$key];

                return;
            }
        }
    }

    /**
     * @param array<string, mixed> $permission
     * @return list<array<string, string>>
     */
    private static function recipients(array $permission, string $driveType): array
    {
        $recipients = [];
        $seen = [];
        foreach (self::recipientIdentitySets($permission, $driveType) as $identitySet) {
            foreach (['user', 'siteUser', 'group', 'siteGroup', 'application', 'device'] as $key) {
                $identity = is_array($identitySet[$key] ?? null) ? $identitySet[$key] : [];
                if ($identity === []) {
                    continue;
                }

                $recipient = self::recipientFromIdentity($identity);
                if ($recipient === null) {
                    continue;
                }
                $dedupe = $recipient['email'] ?? $recipient['objectId'] ?? '';
                if ($dedupe === '' || isset($seen[$dedupe])) {
                    continue;
                }

                $seen[$dedupe] = true;
                $recipients[] = $recipient;
            }
        }

        return $recipients;
    }

    /**
     * @param array<string, mixed> $permission
     * @return list<array<string, mixed>>
     */
    private static function recipientIdentitySets(array $permission, string $driveType): array
    {
        $identitiesKey = $driveType === self::DRIVE_TYPE_PERSONAL ? 'grantedToIdentities' : 'grantedToIdentitiesV2';
        $singleKey = $driveType === self::DRIVE_TYPE_PERSONAL ? 'grantedTo' : 'grantedToV2';
        $identitySets = self::identitySetList($permission[$identitiesKey] ?? null);
        if (is_array($permission[$singleKey] ?? null)) {
            $identitySets[] = $permission[$singleKey];
        }

        return $identitySets;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function identitySetList(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }
        if ($value === []) {
            return [];
        }
        if (!array_is_list($value)) {
            return [$value];
        }

        return array_values(array_filter($value, static fn (mixed $item): bool => is_array($item)));
    }

    /**
     * @param array<string, mixed> $identity
     * @return null|array<string, string>
     */
    private static function recipientFromIdentity(array $identity): ?array
    {
        $email = self::optionalString($identity['email'] ?? $identity['Email'] ?? null);
        $id = self::optionalString($identity['id'] ?? $identity['ID'] ?? null) ?? '';
        $displayName = self::optionalString($identity['displayName'] ?? $identity['display_name'] ?? $identity['DisplayName'] ?? null) ?? '';

        if ($email !== null && str_contains($email, '@')) {
            return ['email' => $email];
        }
        if (str_contains($id, '@')) {
            return ['email' => $id];
        }
        if (str_contains($displayName, '@')) {
            return ['email' => $displayName];
        }
        if ($id !== '') {
            return ['objectId' => $id];
        }

        return null;
    }

    /**
     * @param array<string, mixed> $permission
     */
    private static function hasUserIdentity(array $permission, string $driveType): bool
    {
        foreach (self::recipientIdentitySets($permission, $driveType) as $identitySet) {
            $user = is_array($identitySet['user'] ?? null) ? $identitySet['user'] : [];
            if ($user === []) {
                continue;
            }
            foreach (['id', 'ID', 'displayName', 'display_name', 'DisplayName', 'email', 'Email', 'loginName', 'LoginName'] as $key) {
                $value = self::optionalString($user[$key] ?? null);
                if ($value !== null && $value !== '') {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $permission
     */
    private static function permissionId(array $permission): string
    {
        return self::optionalString($permission['id'] ?? $permission['ID'] ?? null) ?? '';
    }

    /**
     * @param array<string, mixed> $permission
     */
    private static function nullablePermissionId(array $permission): ?string
    {
        $id = self::permissionId($permission);

        return $id !== '' ? $id : null;
    }

    /**
     * @param array<string, mixed> $permission
     * @return list<string>
     */
    private static function roles(array $permission): array
    {
        $roles = $permission['roles'] ?? $permission['Roles'] ?? [];
        if (is_scalar($roles)) {
            return [(string) $roles];
        }
        if (!is_array($roles)) {
            return [];
        }

        $strings = [];
        foreach ($roles as $role) {
            if (is_scalar($role) && (string) $role !== '') {
                $strings[] = (string) $role;
            }
        }

        return $strings;
    }

    /**
     * @param array<string, mixed> $permission
     */
    private static function hasRole(array $permission, string $role): bool
    {
        return in_array($role, self::roles($permission), true);
    }

    /**
     * @param array<string, mixed> $permission
     */
    private static function linkScope(array $permission): ?string
    {
        $link = is_array($permission['link'] ?? null) ? $permission['link'] : [];

        return self::optionalString($link['scope'] ?? $link['Scope'] ?? null);
    }

    /**
     * @param array<string, mixed> $permission
     */
    private static function linkWebUrl(array $permission): string
    {
        $link = is_array($permission['link'] ?? null) ? $permission['link'] : [];

        return self::optionalString($link['webUrl'] ?? $link['webURL'] ?? $link['WebURL'] ?? null) ?? '';
    }

    /**
     * @param list<string> $errors
     */
    private static function aggregateErrors(array $errors): string
    {
        $last = end($errors);
        $last = is_string($last) ? $last : 'unknown permission error';
        if (count($errors) === 1) {
            return 'failed to process permissions: failed to set permissions: ' . $last;
        }

        return sprintf(
            'failed to process permissions: failed to set permissions: %d errors: last error: %s',
            count($errors),
            $last,
        );
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

    private static function parentRemote(string $remote): string
    {
        $remote = trim($remote, '/');
        $slash = strrpos($remote, '/');
        if ($slash === false) {
            return '';
        }

        return substr($remote, 0, $slash);
    }
}
