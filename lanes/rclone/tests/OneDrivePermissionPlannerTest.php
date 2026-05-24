<?php

declare(strict_types=1);

use PortLibs\Rclone\OneDrivePermissionPlanner;

return [
    'onedrive permission planner maps business link update remove add workaround' => static function (TestRunner $t): void {
        $current = [
            [
                'id' => 'owner-perm',
                'roles' => ['owner'],
                'grantedToV2' => ['user' => ['id' => 'owner-user']],
            ],
            [
                'id' => 'viewer-perm',
                'roles' => ['read'],
                'grantedToV2' => ['user' => ['id' => 'reviewer-user']],
            ],
            [
                'id' => 'link-perm',
                'roles' => ['read'],
                'link' => ['scope' => 'organization', 'webUrl' => 'https://share.example/old'],
                'grantedToIdentitiesV2' => [
                    ['user' => ['id' => 'reviewer@example.com']],
                ],
            ],
            [
                'id' => 'contractor-perm',
                'roles' => ['read'],
                'grantedToV2' => ['user' => ['id' => 'contractor@example.com']],
            ],
            [
                'id' => 'unchanged-perm',
                'roles' => ['read'],
            ],
        ];

        $queued = [
            [
                'id' => 'viewer-perm',
                'roles' => ['write'],
                'grantedToV2' => ['user' => ['id' => 'reviewer-user']],
            ],
            [
                'id' => 'link-perm',
                'roles' => ['write'],
                'link' => ['scope' => 'organization', 'webUrl' => 'https://share.example/old'],
                'grantedToIdentitiesV2' => [
                    ['user' => ['id' => 'reviewer@example.com']],
                ],
            ],
            [
                'roles' => ['read'],
                'grantedToIdentitiesV2' => [
                    ['group' => ['id' => 'site-reviewers-group']],
                    ['user' => ['displayName' => 'migration@example.com']],
                ],
            ],
            [
                'id' => 'unchanged-perm',
                'roles' => ['read'],
            ],
        ];

        $plan = OneDrivePermissionPlanner::plan($current, $queued, [
            'driveType' => OneDrivePermissionPlanner::DRIVE_TYPE_BUSINESS,
        ]);

        $t->same(null, $plan['error']);
        $t->same(['remove', 'remove', 'add', 'add', 'update'], array_column($plan['operations'], 'action'));
        $t->same(['link-perm', 'contractor-perm', 'link-perm', null, 'viewer-perm'], array_column($plan['operations'], 'id'));
        $t->same([
            'remove' => 2,
            'add' => 2,
            'update' => 1,
        ], $plan['counts']);
        $t->same('/permissions/link-perm', $plan['operations'][0]['path']);
        $t->same('/invite', $plan['operations'][2]['path']);
        $t->same([['email' => 'reviewer@example.com']], $plan['operations'][2]['recipients']);
        $t->same([
            ['objectId' => 'site-reviewers-group'],
            ['email' => 'migration@example.com'],
        ], $plan['operations'][3]['recipients']);
        $t->same(['write'], $plan['operations'][4]['roles']);
        $t->same(['invalid-update-roles', 'owner-role-remove-suppressed'], array_column($plan['skipped'], 'reason'));
    },
    'onedrive permission planner add only mode never updates or removes copied permissions' => static function (TestRunner $t): void {
        $plan = OneDrivePermissionPlanner::plan(
            [
                ['id' => 'viewer-perm', 'roles' => ['read']],
                ['id' => 'stale-perm', 'roles' => ['read']],
            ],
            [
                [
                    'id' => 'viewer-perm',
                    'roles' => ['write'],
                    'grantedToV2' => ['user' => ['id' => 'viewer@example.com']],
                ],
                [
                    'roles' => ['read'],
                    'grantedToIdentitiesV2' => [
                        ['group' => ['id' => 'wp-reviewers']],
                    ],
                ],
            ],
            [
                'driveType' => OneDrivePermissionPlanner::DRIVE_TYPE_BUSINESS,
                'addOnly' => true,
            ],
        );

        $t->same(['add', 'add'], array_column($plan['operations'], 'action'));
        $t->same(['viewer-perm', null], array_column($plan['operations'], 'id'));
        $t->same([
            'remove' => 0,
            'add' => 2,
            'update' => 0,
        ], $plan['counts']);
        $t->same([], $plan['errors']);
        $t->same([], $plan['skipped']);
    },
    'onedrive permission metadata parsing honors write mode and json errors' => static function (TestRunner $t): void {
        $metadata = [
            'permissions' => '[{"grantedToIdentities":[{"user":{"id":"reader@example.com"}}],"roles":["read"]}]',
        ];

        $readOnly = OneDrivePermissionPlanner::fromMetadata([], $metadata, [
            'metadataPermissions' => 'read',
        ]);
        $write = OneDrivePermissionPlanner::fromMetadata([], $metadata, [
            'metadataPermissions' => 'read,write',
        ]);

        $t->same([], $readOnly['operations']);
        $t->same(['metadata-permissions-write-disabled'], array_column($readOnly['skipped'], 'reason'));
        $t->same(['add'], array_column($write['operations'], 'action'));
        $t->same([['email' => 'reader@example.com']], $write['operations'][0]['recipients']);

        $message = null;
        try {
            OneDrivePermissionPlanner::fromMetadata([], ['permissions' => '{"not":"a-list"}'], [
                'metadataPermissions' => 'write',
            ]);
        } catch (RuntimeException $throwable) {
            $message = $throwable->getMessage();
        }
        $t->same('failed to unmarshal permissions: permissions metadata must be a JSON array', $message);
    },
    'onedrive permission planner suppresses unsafe adds and failok errors' => static function (TestRunner $t): void {
        $queued = [
            [
                'roles' => ['read'],
            ],
            [
                'roles' => ['owner'],
                'grantedTo' => ['user' => ['id' => 'owner@example.com']],
            ],
            [
                'grantedTo' => ['user' => ['id' => 'missing-role@example.com']],
            ],
            [
                'roles' => ['read'],
                'link' => ['scope' => 'anonymous'],
            ],
            [
                'roles' => ['read'],
                'grantedTo' => ['user' => ['id' => 'ok@example.com']],
            ],
        ];

        $strict = OneDrivePermissionPlanner::plan([], $queued);
        $failOk = OneDrivePermissionPlanner::plan([], $queued, ['failOk' => true]);

        $t->same(
            'failed to process permissions: failed to set permissions: at least one role is required to add a permission (choices: read, write, owner, member)',
            $strict['error'],
        );
        $t->same(['add', 'add'], array_column($strict['operations'], 'action'));
        $t->same([false, true], array_column($strict['operations'], 'publicLink'));
        $t->same([true, false], array_column($strict['operations'], 'invite'));
        $t->same([
            'add-owner-role-suppressed',
            'add-missing-recipient',
        ], array_column($strict['skipped'], 'reason'));
        $t->same(null, $failOk['error']);
        $t->same([
            'failed to process permissions: failed to set permissions: at least one role is required to add a permission (choices: read, write, owner, member)',
        ], $failOk['suppressedErrors']);
    },
    'onedrive write permissions refreshes remote state and clears queued permissions' => static function (TestRunner $t): void {
        $result = OneDrivePermissionPlanner::writePermissions(
            [
                [
                    'id' => 'reviewer',
                    'roles' => ['read'],
                    'grantedToV2' => ['user' => ['id' => 'reviewer@example.com']],
                ],
                [
                    'id' => 'stale',
                    'roles' => ['read'],
                    'grantedToV2' => ['user' => ['id' => 'old-reviewer@example.com']],
                ],
            ],
            [
                [
                    'id' => 'reviewer',
                    'roles' => ['write'],
                    'grantedToV2' => ['user' => ['id' => 'reviewer@example.com']],
                ],
                [
                    'roles' => ['read'],
                    'grantedToIdentitiesV2' => [
                        ['user' => ['id' => 'auditor@example.com']],
                    ],
                ],
            ],
            [
                'normalizedId' => 'drive#review-wxr',
                'driveType' => OneDrivePermissionPlanner::DRIVE_TYPE_BUSINESS,
                'refreshedPermissions' => [
                    ['id' => 'reviewer', 'roles' => ['write']],
                    ['id' => 'auditor-created', 'roles' => ['read']],
                ],
            ],
        );

        $t->same('drive#review-wxr', $result['normalizedId']);
        $t->same(['remove', 'add', 'update'], array_column($result['operations'], 'action'));
        $t->same(true, $result['refreshAttempted']);
        $t->same(true, $result['queuedPermissionsCleared']);
        $t->same(null, $result['error']);
        $t->same(['reviewer', 'auditor-created'], array_column($result['permissions'], 'id'));
    },
    'onedrive write permissions failok suppresses process or refresh errors without clearing queued permissions' => static function (TestRunner $t): void {
        $processError = OneDrivePermissionPlanner::writePermissions(
            [],
            [
                [
                    'roles' => ['read'],
                    'grantedTo' => ['user' => ['id' => 'reviewer@example.com']],
                ],
            ],
            [
                'normalizedId' => 'personal#review-wxr',
                'metadataPermissions' => 'write,failok',
                'operationErrors' => ['add:*' => 'Graph invite throttled'],
            ],
        );

        $refreshError = OneDrivePermissionPlanner::writePermissions(
            [],
            [
                [
                    'roles' => ['read'],
                    'grantedTo' => ['user' => ['id' => 'reviewer@example.com']],
                ],
            ],
            [
                'normalizedId' => 'personal#review-wxr',
                'metadataPermissions' => 'write,failok',
                'refreshError' => 'Graph permissions denied',
            ],
        );

        $t->same(null, $processError['error']);
        $t->same(false, $processError['refreshAttempted']);
        $t->same(false, $processError['queuedPermissionsCleared']);
        $t->same([
            'failed to process permissions: failed to set permissions: Graph invite throttled',
        ], $processError['suppressedErrors']);
        $t->same(null, $refreshError['error']);
        $t->same(true, $refreshError['refreshAttempted']);
        $t->same(false, $refreshError['queuedPermissionsCleared']);
        $t->same([
            'failed to get permissions: failed to refresh permissions: Graph permissions denied',
        ], $refreshError['suppressedErrors']);

        $t->throws(RuntimeException::class, static fn (): array => OneDrivePermissionPlanner::writePermissions([], [], [
            'metadataPermissions' => 'write,failok',
        ]));
    },
    'onedrive directory metadata flow maps create refresh write and system metadata order' => static function (TestRunner $t): void {
        $flow = OneDrivePermissionPlanner::directoryMetadataFlow(
            'site-backups/review',
            [
                'mtime' => '2026-05-23T10:00:00Z',
                'permissions' => '[{"roles":["read"],"grantedToIdentitiesV2":[{"user":{"id":"reviewer@example.com"}}]}]',
            ],
            [
                'exists' => false,
                'directoryId' => 'business-drive#review-dir',
                'driveType' => OneDrivePermissionPlanner::DRIVE_TYPE_BUSINESS,
                'refreshBeforePermissions' => [
                    ['id' => 'owner', 'roles' => ['owner']],
                ],
                'refreshedPermissions' => [
                    ['id' => 'owner', 'roles' => ['owner']],
                    ['id' => 'reviewer-created', 'roles' => ['read']],
                ],
            ],
        );

        $t->same([
            'find-dir:missing',
            'find-parent',
            'create-dir',
            'create-dir:api-metadata',
            'refresh-permissions-before-write',
            'write-permissions',
            'refresh-permissions-after-write',
            'clear-queued-permissions',
            'write-directory-metadata-after-create',
            'item-to-dir-entry',
            'set-system-metadata',
        ], $flow['sequence']);
        $t->same(['mtime', 'permissions'], $flow['writeableMetadata']);
        $t->same(['mtime'], $flow['apiMetadata']);
        $t->same(['add'], array_column($flow['permissionWrite']['operations'], 'action'));
        $t->same(true, $flow['directoryReturned']);
        $t->same(true, $flow['systemMetadataSet']);
        $t->same(['owner', 'reviewer-created'], array_column($flow['permissions'], 'id'));
    },
    'onedrive existing directory metadata flow patches metadata before permission writes' => static function (TestRunner $t): void {
        $permissionsOnly = OneDrivePermissionPlanner::directoryMetadataFlow(
            'site-backups/review',
            [
                'permissions' => '[{"roles":["read"],"grantedToIdentitiesV2":[{"user":{"id":"reviewer@example.com"}}]}]',
            ],
            [
                'exists' => true,
                'directoryId' => 'business-drive#review-dir',
                'driveType' => OneDrivePermissionPlanner::DRIVE_TYPE_BUSINESS,
            ],
        );
        $withMetadata = OneDrivePermissionPlanner::directoryMetadataFlow(
            'site-backups/review',
            [
                'btime' => '2026-05-23T09:00:00Z',
                'mtime' => '2026-05-23T10:00:00Z',
                'permissions' => '[{"id":"reviewer","roles":["write"],"grantedToV2":{"user":{"id":"reviewer@example.com"}}}]',
            ],
            [
                'exists' => true,
                'directoryId' => 'business-drive#review-dir',
                'driveType' => OneDrivePermissionPlanner::DRIVE_TYPE_BUSINESS,
                'currentPermissions' => [
                    ['id' => 'reviewer', 'roles' => ['read'], 'grantedToV2' => ['user' => ['id' => 'reviewer@example.com']]],
                ],
                'refreshedPermissions' => [
                    ['id' => 'reviewer', 'roles' => ['write']],
                ],
            ],
        );

        $t->same('site-backups/review: no writeable metadata found', $permissionsOnly['error']);
        $t->same(['find-dir:exists', 'update-dir'], $permissionsOnly['sequence']);
        $t->same([
            'find-dir:exists',
            'update-dir',
            'patch-directory-metadata',
            'write-permissions',
            'refresh-permissions-after-write',
            'clear-queued-permissions',
            'item-to-dir-entry',
            'set-system-metadata',
        ], $withMetadata['sequence']);
        $t->same(['btime', 'mtime', 'permissions'], $withMetadata['writeableMetadata']);
        $t->same(['btime', 'mtime'], $withMetadata['apiMetadata']);
        $t->same(['update'], array_column($withMetadata['permissionWrite']['operations'], 'action'));
        $t->same(true, $withMetadata['queuedPermissionsCleared']);
    },
    'onedrive mkdir metadata creates with fail conflict behavior and caches graph directory id' => static function (TestRunner $t): void {
        $flow = OneDrivePermissionPlanner::directoryMetadataFlow(
            'site-backups/review/exports',
            [
                'mtime' => '2026-05-24T10:00:00Z',
            ],
            [
                'exists' => false,
                'parentId' => 'business-drive#review-dir',
                'directoryId' => 'business-drive#exports-dir',
                'childCount' => 3,
            ],
        );

        $t->same([
            'find-dir:missing',
            'find-parent',
            'create-dir',
            'create-dir:api-metadata',
            'write-directory-metadata-after-create',
            'item-to-dir-entry',
            'set-system-metadata',
        ], $flow['sequence']);
        $t->same('business-drive#review-dir', $flow['parentId']);
        $t->same('fail', $flow['conflictBehavior']);
        $t->same(true, $flow['graphItemConverted']);
        $t->same([
            'remote' => 'site-backups/review/exports',
            'id' => 'business-drive#exports-dir',
        ], $flow['dirCachePut']);
        $t->same(3, $flow['childCount']);
        $t->same(true, $flow['directoryReturned']);
        $t->same(null, $flow['error']);
    },
    'onedrive mkdir metadata surfaces parent create and conversion failures before cache update' => static function (TestRunner $t): void {
        $parentFailure = OneDrivePermissionPlanner::directoryMetadataFlow(
            'site-backups/review/exports',
            [
                'mtime' => '2026-05-24T10:00:00Z',
            ],
            [
                'exists' => false,
                'parentLookupError' => 'directory not found',
            ],
        );
        $nameConflict = OneDrivePermissionPlanner::directoryMetadataFlow(
            'site-backups/review/exports',
            [
                'mtime' => '2026-05-24T10:00:00Z',
            ],
            [
                'exists' => false,
                'parentId' => 'business-drive#review-dir',
                'createConflict' => true,
            ],
        );
        $wrongType = OneDrivePermissionPlanner::directoryMetadataFlow(
            'site-backups/review/exports',
            [
                'mtime' => '2026-05-24T10:00:00Z',
            ],
            [
                'exists' => false,
                'parentId' => 'business-drive#review-dir',
                'directoryId' => 'business-drive#exports-dir',
                'itemToDirEntryType' => 'object',
            ],
        );

        $t->same(['find-dir:missing', 'find-parent'], $parentFailure['sequence']);
        $t->same('directory not found', $parentFailure['error']);
        $t->same(false, $parentFailure['graphItemConverted']);
        $t->same([
            'find-dir:missing',
            'find-parent',
            'create-dir',
        ], $nameConflict['sequence']);
        $t->same('fail', $nameConflict['conflictBehavior']);
        $t->same('nameAlreadyExists', $nameConflict['error']);
        $t->same('item-to-dir-entry', $wrongType['sequence'][5]);
        $t->same('internal error: expecting OneDrive item to be a directory', $wrongType['error']);
        $t->same(null, $wrongType['dirCachePut']);
        $t->same(false, $wrongType['directoryReturned']);
    },
    'onedrive object metadata flow refreshes permissions before set and cleans versions last' => static function (TestRunner $t): void {
        $flow = OneDrivePermissionPlanner::objectMetadataFlow(
            'exports/site.wxr',
            [
                'mtime' => '2026-05-23T10:00:00Z',
                'permissions' => '[{"id":"reviewer","roles":["write"],"grantedToV2":{"user":{"id":"reviewer@example.com"}}}]',
            ],
            [
                'normalizedId' => 'business-drive#site-wxr',
                'driveType' => OneDrivePermissionPlanner::DRIVE_TYPE_BUSINESS,
                'metadataPermissions' => 'read,write',
                'currentPermissions' => [
                    ['id' => 'reviewer', 'roles' => ['read'], 'grantedToV2' => ['user' => ['id' => 'stale@example.com']]],
                ],
                'refreshBeforePermissions' => [
                    ['id' => 'owner', 'roles' => ['owner']],
                    ['id' => 'reviewer', 'roles' => ['read'], 'grantedToV2' => ['user' => ['id' => 'reviewer@example.com']]],
                ],
                'refreshedPermissions' => [
                    ['id' => 'owner', 'roles' => ['owner']],
                    ['id' => 'reviewer', 'roles' => ['write']],
                ],
                'noVersions' => true,
            ],
        );

        $t->same([
            'get-current-metadata',
            'refresh-permissions-before-set',
            'set-metadata',
            'write-object-metadata',
            'write-permissions',
            'refresh-permissions-after-write',
            'clear-queued-permissions',
            'set-system-metadata',
            'set-object-metadata',
            'delete-versions',
        ], $flow['sequence']);
        $t->same(['mtime', 'permissions'], $flow['writeableMetadata']);
        $t->same(['mtime'], $flow['apiMetadata']);
        $t->same(['update'], array_column($flow['permissionWrite']['operations'], 'action'));
        $t->same(['owner', 'reviewer'], array_column($flow['permissions'], 'id'));
        $t->same(true, $flow['objectReturned']);
        $t->same(true, $flow['systemMetadataSet']);
        $t->same(true, $flow['objectMetadataSet']);
        $t->same(true, $flow['versionsDeleteAttempted']);
        $t->same(true, $flow['versionsDeleted']);
        $t->same(null, $flow['error']);
    },
    'onedrive object permissions only metadata reaches no api metadata boundary after refresh' => static function (TestRunner $t): void {
        $object = OneDrivePermissionPlanner::objectMetadataFlow(
            'exports/review.wxr',
            [
                'permissions' => '[{"roles":["read"],"grantedToIdentitiesV2":[{"user":{"id":"reviewer@example.com"}}]}]',
            ],
            [
                'normalizedId' => 'business-drive#review-wxr',
                'driveType' => OneDrivePermissionPlanner::DRIVE_TYPE_BUSINESS,
                'metadataPermissions' => 'read,write',
                'refreshBeforePermissions' => [
                    ['id' => 'owner', 'roles' => ['owner']],
                ],
            ],
        );
        $directory = OneDrivePermissionPlanner::directoryMetadataFlow(
            'site-backups/review',
            [
                'permissions' => '[{"roles":["read"],"grantedToIdentitiesV2":[{"user":{"id":"reviewer@example.com"}}]}]',
            ],
            [
                'exists' => true,
                'directoryId' => 'business-drive#review-dir',
                'driveType' => OneDrivePermissionPlanner::DRIVE_TYPE_BUSINESS,
                'metadataPermissions' => 'read,write',
            ],
        );

        $t->same([
            'get-current-metadata',
            'refresh-permissions-before-set',
            'set-metadata',
            'write-object-metadata',
        ], $object['sequence']);
        $t->same('exports/review.wxr: no writeable metadata found', $object['error']);
        $t->same(null, $object['permissionWrite']);
        $t->same(false, $object['queuedPermissionsCleared']);
        $t->same(['find-dir:exists', 'update-dir'], $directory['sequence']);
        $t->same('site-backups/review: no writeable metadata found', $directory['error']);
    },
    'onedrive object metadata no versions failure happens after metadata cache update' => static function (TestRunner $t): void {
        $flow = OneDrivePermissionPlanner::objectMetadataFlow(
            'exports/site.wxr',
            [
                'btime' => '2026-05-23T09:00:00Z',
                'mtime' => '2026-05-23T10:00:00Z',
            ],
            [
                'metadataPermissions' => 'write',
                'noVersions' => true,
                'deleteVersionsError' => 'Graph versions denied',
            ],
        );

        $t->same([
            'get-current-metadata',
            'set-metadata',
            'write-object-metadata',
            'set-system-metadata',
            'set-object-metadata',
            'delete-versions',
        ], $flow['sequence']);
        $t->same(true, $flow['objectReturned']);
        $t->same(true, $flow['systemMetadataSet']);
        $t->same(true, $flow['objectMetadataSet']);
        $t->same(true, $flow['versionsDeleteAttempted']);
        $t->same(false, $flow['versionsDeleted']);
        $t->same('exports/site.wxr: Failed to remove versions: Graph versions denied', $flow['error']);
    },
    'onedrive object metadata with no writeable fields is a no op after current metadata read' => static function (TestRunner $t): void {
        $flow = OneDrivePermissionPlanner::objectMetadataFlow(
            'exports/site.wxr',
            [
                'content-type' => 'application/rss+xml',
                'description' => 'skipped by upstream',
            ],
            [
                'metadataPermissions' => 'read',
                'refreshBeforePermissions' => [
                    ['id' => 'owner', 'roles' => ['owner']],
                ],
            ],
        );

        $t->same([
            'get-current-metadata',
            'refresh-permissions-before-set',
            'set-metadata',
            'no-writeable-metadata-noop',
        ], $flow['sequence']);
        $t->same([], $flow['writeableMetadata']);
        $t->same(null, $flow['error']);
        $t->same(false, $flow['objectReturned']);
        $t->same(['owner'], array_column($flow['permissions'], 'id'));
    },
    'onedrive fetch metadata wraps source metadata read failures' => static function (TestRunner $t): void {
        $flow = OneDrivePermissionPlanner::fetchAndUpdateMetadataFlow(
            'exports/site.wxr',
            null,
            [
                'sourceMetadataError' => 'metadata mapper failed for source WXR',
            ],
        );

        $t->same(['get-source-metadata-options'], $flow['sequence']);
        $t->same(
            'failed to read metadata from source object: metadata mapper failed for source WXR',
            $flow['error'],
        );
        $t->same(false, $flow['infoReturned']);
        $t->same(false, $flow['modTimeSetAttempted']);
    },
    'onedrive fetch metadata nil source falls back to set modtime and suppresses no versions cleanup errors' => static function (TestRunner $t): void {
        $flow = OneDrivePermissionPlanner::fetchAndUpdateMetadataFlow(
            'exports/site.wxr',
            null,
            [
                'noVersions' => true,
                'deleteVersionsError' => 'Graph versions denied',
            ],
        );

        $t->same([
            'get-source-metadata-options',
            'set-modtime',
            'delete-versions-after-set-modtime',
        ], $flow['sequence']);
        $t->same(true, $flow['modTimeSetAttempted']);
        $t->same(true, $flow['modTimeSet']);
        $t->same(true, $flow['infoReturned']);
        $t->same(true, $flow['versionsDeleteAttempted']);
        $t->same(false, $flow['versionsDeleted']);
        $t->same(['exports/site.wxr: Failed to remove versions: Graph versions denied'], $flow['suppressedErrors']);
        $t->same(null, $flow['error']);
    },
    'onedrive upload singlepart wraps fetch metadata errors and skips final metadata cache update' => static function (TestRunner $t): void {
        $flow = OneDrivePermissionPlanner::uploadSinglepartMetadataFlow(
            'exports/site.wxr',
            null,
            [
                'sourceMetadataError' => 'source metadata read denied',
            ],
        );

        $t->same([
            'upload-singlepart',
            'set-upload-metadata',
            'fetch-and-update-metadata',
            'get-source-metadata-options',
        ], $flow['sequence']);
        $t->same(
            'failed to fetch and update metadata: failed to read metadata from source object: source metadata read denied',
            $flow['error'],
        );
        $t->same(false, $flow['infoReturned']);
        $t->same(false, $flow['fetch']['infoReturned']);
    },
    'onedrive upload multipart updates permissions after chunk metadata and final cache set' => static function (TestRunner $t): void {
        $flow = OneDrivePermissionPlanner::uploadMultipartMetadataFlow(
            'exports/site.wxr',
            [
                'mtime' => '2026-05-23T10:00:00Z',
                'permissions' => '[{"id":"reviewer","roles":["write"],"grantedToV2":{"user":{"id":"reviewer@example.com"}}}]',
            ],
            [
                'size' => 8 * 1024 * 1024,
                'normalizedId' => 'business-drive#site-wxr',
                'driveType' => OneDrivePermissionPlanner::DRIVE_TYPE_BUSINESS,
                'metadataPermissions' => 'read,write',
                'currentPermissions' => [
                    ['id' => 'reviewer', 'roles' => ['read'], 'grantedToV2' => ['user' => ['id' => 'stale@example.com']]],
                ],
                'refreshBeforePermissions' => [
                    ['id' => 'reviewer', 'roles' => ['read'], 'grantedToV2' => ['user' => ['id' => 'reviewer@example.com']]],
                ],
                'refreshedPermissions' => [
                    ['id' => 'reviewer', 'roles' => ['write']],
                ],
            ],
        );

        $t->same([
            'upload-multipart',
            'create-upload-session',
            'get-source-metadata-options',
            'create-session:api-metadata',
            'upload-fragments',
            'set-upload-metadata',
            'update-metadata-for-permissions',
            'get-current-metadata',
            'refresh-permissions-before-set',
            'set-metadata',
            'write-object-metadata',
            'write-permissions',
            'refresh-permissions-after-write',
            'clear-queued-permissions',
            'set-system-metadata',
            'set-object-metadata',
            'set-upload-metadata-after-permission-update',
        ], $flow['sequence']);
        $t->same(true, $flow['needsUpdatePermissions']);
        $t->same(true, $flow['initialMetadataSet']);
        $t->same(true, $flow['finalMetadataSet']);
        $t->same(true, $flow['infoReturned']);
        $t->same(true, $flow['updateReturnedInfo']);
        $t->same(false, $flow['updateErrorIgnored']);
        $t->same(['update'], array_column($flow['metadataUpdate']['permissionWrite']['operations'], 'action'));
        $t->same(null, $flow['error']);
    },
    'onedrive upload multipart skips permission update when metadata permissions write is disabled' => static function (TestRunner $t): void {
        $flow = OneDrivePermissionPlanner::uploadMultipartMetadataFlow(
            'exports/site.wxr',
            [
                'mtime' => '2026-05-23T10:00:00Z',
                'permissions' => '[{"roles":["read"],"grantedToIdentitiesV2":[{"user":{"id":"reviewer@example.com"}}]}]',
            ],
            [
                'metadataPermissions' => 'read',
            ],
        );

        $t->same([
            'upload-multipart',
            'create-upload-session',
            'get-source-metadata-options',
            'create-session:api-metadata',
            'upload-fragments',
            'set-upload-metadata',
        ], $flow['sequence']);
        $t->same(false, $flow['needsUpdatePermissions']);
        $t->same(true, $flow['initialMetadataSet']);
        $t->same(false, $flow['finalMetadataSet']);
        $t->same(true, $flow['infoReturned']);
        $t->same(false, $flow['updateReturnedInfo']);
    },
    'onedrive upload multipart permission only metadata returns update error before final cache set' => static function (TestRunner $t): void {
        $flow = OneDrivePermissionPlanner::uploadMultipartMetadataFlow(
            'exports/review.wxr',
            [
                'permissions' => '[{"roles":["read"],"grantedToIdentitiesV2":[{"user":{"id":"reviewer@example.com"}}]}]',
            ],
            [
                'normalizedId' => 'business-drive#review-wxr',
                'driveType' => OneDrivePermissionPlanner::DRIVE_TYPE_BUSINESS,
                'metadataPermissions' => 'read,write',
                'refreshBeforePermissions' => [
                    ['id' => 'owner', 'roles' => ['owner']],
                ],
            ],
        );

        $t->same([
            'upload-multipart',
            'create-upload-session',
            'get-source-metadata-options',
            'create-session:api-metadata',
            'upload-fragments',
            'set-upload-metadata',
            'update-metadata-for-permissions',
            'get-current-metadata',
            'refresh-permissions-before-set',
            'set-metadata',
            'write-object-metadata',
            'cancel-upload-session',
        ], $flow['sequence']);
        $t->same(true, $flow['needsUpdatePermissions']);
        $t->same(true, $flow['initialMetadataSet']);
        $t->same(false, $flow['finalMetadataSet']);
        $t->same(false, $flow['infoReturned']);
        $t->same(false, $flow['updateReturnedInfo']);
        $t->same(true, $flow['sessionCancelled']);
        $t->same('exports/review.wxr: no writeable metadata found', $flow['error']);
    },
    'onedrive upload multipart ignores permission write error when update returned item info' => static function (TestRunner $t): void {
        $flow = OneDrivePermissionPlanner::uploadMultipartMetadataFlow(
            'exports/site.wxr',
            [
                'mtime' => '2026-05-23T10:00:00Z',
                'permissions' => '[{"roles":["read"],"grantedToIdentitiesV2":[{"user":{"id":"reviewer@example.com"}}]}]',
            ],
            [
                'normalizedId' => 'business-drive#site-wxr',
                'driveType' => OneDrivePermissionPlanner::DRIVE_TYPE_BUSINESS,
                'metadataPermissions' => 'write',
                'operationErrors' => ['add:*' => 'Graph invite throttled'],
            ],
        );

        $t->same('set-upload-metadata-after-permission-update', $flow['sequence'][11]);
        $t->same(true, $flow['updateReturnedInfo']);
        $t->same(true, $flow['updateErrorIgnored']);
        $t->same('failed to process permissions: failed to set permissions: Graph invite throttled', $flow['ignoredUpdateError']);
        $t->same(true, $flow['finalMetadataSet']);
        $t->same(true, $flow['infoReturned']);
        $t->same(false, $flow['sessionCancelled']);
        $t->same(null, $flow['error']);
    },
    'onedrive object update selects singlepart for zero size and propagates source metadata' => static function (TestRunner $t): void {
        $flow = OneDrivePermissionPlanner::objectUpdateUploadFlow(
            'exports/empty-review.wxr',
            [
                'mtime' => '2026-05-23T10:00:00Z',
            ],
            [
                'size' => 0,
                'uploadCutoff' => -1,
                'metadataPermissions' => 'write',
                'hasObjectMetadata' => false,
            ],
        );

        $t->same('singlepart', $flow['selectedUpload']);
        $t->same([
            'object-update',
            'upload-singlepart',
            'set-upload-metadata',
            'fetch-and-update-metadata',
            'get-source-metadata-options',
            'new-object-metadata',
            'get-current-metadata',
            'set-metadata',
            'write-object-metadata',
            'set-system-metadata',
            'set-object-metadata',
            'set-upload-metadata-from-fetch',
        ], $flow['sequence']);
        $t->same(['mtime'], $flow['upload']['fetch']['metadataUpdate']['writeableMetadata']);
        $t->same(true, $flow['upload']['infoReturned']);
        $t->same(null, $flow['error']);
    },
    'onedrive object update selects multipart at cutoff and carries permission metadata' => static function (TestRunner $t): void {
        $flow = OneDrivePermissionPlanner::objectUpdateUploadFlow(
            'exports/site.wxr',
            [
                'mtime' => '2026-05-23T10:00:00Z',
                'permissions' => '[{"id":"reviewer","roles":["write"],"grantedToV2":{"user":{"id":"reviewer@example.com"}}}]',
            ],
            [
                'size' => 4 * 1024 * 1024,
                'uploadCutoff' => 4 * 1024 * 1024,
                'normalizedId' => 'business-drive#site-wxr',
                'driveType' => OneDrivePermissionPlanner::DRIVE_TYPE_BUSINESS,
                'metadataPermissions' => 'read,write',
                'currentPermissions' => [
                    ['id' => 'reviewer', 'roles' => ['read'], 'grantedToV2' => ['user' => ['id' => 'reviewer@example.com']]],
                ],
                'refreshedPermissions' => [
                    ['id' => 'reviewer', 'roles' => ['write']],
                ],
            ],
        );

        $t->same('multipart', $flow['selectedUpload']);
        $t->same('upload-multipart', $flow['sequence'][1]);
        $t->same('update-metadata-for-permissions', $flow['sequence'][7]);
        $t->same(true, $flow['upload']['needsUpdatePermissions']);
        $t->same(true, $flow['upload']['finalMetadataSet']);
        $t->same(['update'], array_column($flow['upload']['metadataUpdate']['permissionWrite']['operations'], 'action'));
        $t->same(null, $flow['error']);
    },
    'onedrive object update rejects onenote and unknown sized sources before upload' => static function (TestRunner $t): void {
        $oneNote = OneDrivePermissionPlanner::objectUpdateUploadFlow(
            'exports/site-notes.one',
            null,
            [
                'size' => 128,
                'hasMetadata' => true,
                'isOneNoteFile' => true,
            ],
        );
        $unknown = OneDrivePermissionPlanner::objectUpdateUploadFlow(
            'exports/streamed.wxr',
            null,
            [
                'size' => -1,
                'hasMetadata' => false,
                'isOneNoteFile' => false,
            ],
        );

        $t->same(['object-update', 'reject-onenote'], $oneNote['sequence']);
        $t->same(null, $oneNote['selectedUpload']);
        $t->same("can't upload content to a OneNote file", $oneNote['error']);
        $t->same(['object-update'], $unknown['sequence']);
        $t->same(null, $unknown['selectedUpload']);
        $t->same('unknown-sized upload not supported', $unknown['error']);
    },
    'onedrive object update no versions cleanup is logged after successful upload' => static function (TestRunner $t): void {
        $flow = OneDrivePermissionPlanner::objectUpdateUploadFlow(
            'exports/site.wxr',
            [
                'content-type' => 'application/rss+xml',
            ],
            [
                'size' => 0,
                'uploadCutoff' => -1,
                'hasMetadata' => true,
                'metadataPermissions' => 'read',
                'noVersions' => true,
                'updateDeleteVersionsError' => 'Graph versions denied',
            ],
        );

        $t->same('singlepart', $flow['selectedUpload']);
        $t->same('delete-versions-after-update', $flow['sequence'][count($flow['sequence']) - 1]);
        $t->same(true, $flow['versionsDeleteAttempted']);
        $t->same(false, $flow['versionsDeleted']);
        $t->same(['exports/site.wxr: Failed to remove versions: Graph versions denied'], $flow['suppressedErrors']);
        $t->same(null, $flow['error']);
    },
    'onedrive put create object finds parent and reuses singlepart upload selection' => static function (TestRunner $t): void {
        $flow = OneDrivePermissionPlanner::putCreateObjectFlow(
            'exports/empty-review.wxr',
            [
                'mtime' => '2026-05-23T10:00:00Z',
            ],
            [
                'size' => 0,
                'uploadCutoff' => -1,
                'parentId' => 'business-drive#exports-dir',
                'metadataPermissions' => 'write',
                'hasObjectMetadata' => false,
            ],
        );

        $t->same('business-drive#exports-dir', $flow['parentId']);
        $t->same(false, $flow['temporaryObjectHasMetadata']);
        $t->same('singlepart', $flow['selectedUpload']);
        $t->same([
            'fs-put',
            'create-object',
            'find-parent',
            'create-temporary-object',
            'upload-singlepart',
            'set-upload-metadata',
            'fetch-and-update-metadata',
            'get-source-metadata-options',
            'new-object-metadata',
            'get-current-metadata',
            'set-metadata',
            'write-object-metadata',
            'set-system-metadata',
            'set-object-metadata',
            'set-upload-metadata-from-fetch',
        ], $flow['sequence']);
        $t->same(true, $flow['upload']['infoReturned']);
        $t->same(null, $flow['error']);
    },
    'onedrive put create object maps parent lookup failure multipart reuse and onenote conflict hint' => static function (TestRunner $t): void {
        $missingParent = OneDrivePermissionPlanner::putCreateObjectFlow(
            'exports/site.wxr',
            null,
            [
                'size' => 0,
                'parentLookupError' => 'directory not found',
            ],
        );
        $multipart = OneDrivePermissionPlanner::putCreateObjectFlow(
            'exports/site.wxr',
            [
                'mtime' => '2026-05-23T10:00:00Z',
                'permissions' => '[{"id":"reviewer","roles":["write"],"grantedToV2":{"user":{"id":"reviewer@example.com"}}}]',
            ],
            [
                'size' => 8 * 1024 * 1024,
                'uploadCutoff' => 4 * 1024 * 1024,
                'metadataPermissions' => 'read,write',
                'normalizedId' => 'business-drive#site-wxr',
                'driveType' => OneDrivePermissionPlanner::DRIVE_TYPE_BUSINESS,
                'currentPermissions' => [
                    ['id' => 'reviewer', 'roles' => ['read'], 'grantedToV2' => ['user' => ['id' => 'reviewer@example.com']]],
                ],
                'refreshedPermissions' => [
                    ['id' => 'reviewer', 'roles' => ['write']],
                ],
            ],
        );
        $nameConflict = OneDrivePermissionPlanner::putCreateObjectFlow(
            'exports/site-notes.one',
            null,
            [
                'size' => 256,
                'uploadCutoff' => 1024,
                'uploadError' => 'nameAlreadyExists',
                'nameAlreadyExists' => true,
            ],
        );

        $t->same([
            'fs-put',
            'create-object',
            'find-parent',
        ], $missingParent['sequence']);
        $t->same("couldn't find parent ID: directory not found", $missingParent['error']);
        $t->same(null, $missingParent['selectedUpload']);
        $t->same(null, $missingParent['upload']);
        $t->same('multipart', $multipart['selectedUpload']);
        $t->same('upload-multipart', $multipart['sequence'][4]);
        $t->same(['update'], array_column($multipart['upload']['metadataUpdate']['permissionWrite']['operations'], 'action'));
        $t->same(null, $multipart['error']);
        $t->same('singlepart', $nameConflict['selectedUpload']);
        $t->same('nameAlreadyExists (OneNote files cannot be overwritten by rclone)', $nameConflict['error']);

        $customHint = OneDrivePermissionPlanner::putCreateObjectFlow(
            'exports/site-notes.one',
            null,
            [
                'size' => 256,
                'uploadCutoff' => 1024,
                'uploadError' => 'nameAlreadyExists',
                'nameAlreadyExists' => true,
                'nameAlreadyExistsHint' => 'This is normally caused by an existing OneNote file with the same name.',
            ],
        );

        $t->same('nameAlreadyExists (This is normally caused by an existing OneNote file with the same name.)', $customHint['error']);
    },
    'wordpress onedrive permission write plan example keeps owner and plans review changes' => static function (TestRunner $t): void {
        $example = require __DIR__ . '/../examples/wordpress-onedrive-permission-write-plan.php';

        $t->same('onedrive-permission-write-plan', $example['source']);
        $t->same(['remove', 'remove', 'add', 'add', 'update'], $example['actions']);
        $t->same(['link-review', 'stale-contractor'], $example['removedPermissionIds']);
        $t->same(['reviewer@example.com', 'migration-auditor@example.com'], $example['addedEmails']);
        $t->same(['write'], $example['reviewerUpdateRoles']);
        $t->same(true, $example['ownerPreserved']);
        $t->same([
            'failed to process permissions: failed to set permissions: Graph invite throttled',
        ], $example['suppressedErrors']);
    },
    'wordpress onedrive directory permission refresh example records create and update sequencing' => static function (TestRunner $t): void {
        $example = require __DIR__ . '/../examples/wordpress-onedrive-permission-refresh-dir-metadata.php';

        $t->same('onedrive-permission-refresh-dir-metadata', $example['source']);
        $t->same('set-system-metadata', $example['createSequence'][10]);
        $t->same('business-drive#site-backups-dir', $example['createParentId']);
        $t->same('fail', $example['createConflictBehavior']);
        $t->same([
            'remote' => 'site-backups/review',
            'id' => 'business-drive#review-dir',
        ], $example['createDirCachePut']);
        $t->same(2, $example['createChildCount']);
        $t->same(['owner', 'reviewer-created'], $example['createdPermissionIds']);
        $t->same('nameAlreadyExists', $example['createConflictError']);
        $t->same(['find-dir:exists', 'update-dir'], $example['permissionsOnlySequence']);
        $t->same('site-backups/review: no writeable metadata found', $example['permissionsOnlyError']);
        $t->same(['update'], $example['updateActions']);
        $t->same(true, $example['updatedQueuedCleared']);
    },
    'wordpress onedrive object metadata update example records refresh and version cleanup' => static function (TestRunner $t): void {
        $example = require __DIR__ . '/../examples/wordpress-onedrive-object-metadata-update.php';

        $t->same('onedrive-object-metadata-update', $example['source']);
        $t->same('refresh-permissions-before-set', $example['updateSequence'][1]);
        $t->same('delete-versions', $example['updateSequence'][9]);
        $t->same(['owner', 'reviewer'], $example['updatedPermissionIds']);
        $t->same(true, $example['versionsDeleted']);
        $t->same([
            'get-current-metadata',
            'refresh-permissions-before-set',
            'set-metadata',
            'write-object-metadata',
        ], $example['permissionsOnlySequence']);
        $t->same('exports/review.wxr: no writeable metadata found', $example['permissionsOnlyError']);
        $t->same('delete-versions', $example['deleteVersionsSequence'][5]);
        $t->same('exports/site.wxr: Failed to remove versions: Graph versions denied', $example['deleteVersionsError']);
        $t->same(true, $example['metadataWasCachedBeforeVersionCleanup']);
    },
    'wordpress onedrive upload metadata fallback example records modtime fallback and wrapped errors' => static function (TestRunner $t): void {
        $example = require __DIR__ . '/../examples/wordpress-onedrive-upload-metadata-fallback.php';

        $t->same('onedrive-upload-metadata-fallback', $example['source']);
        $t->same([
            'get-source-metadata-options',
            'set-modtime',
            'delete-versions-after-set-modtime',
        ], $example['plainFetchSequence']);
        $t->same(true, $example['plainUploadSetsFetchedMetadata']);
        $t->same(['exports/site.wxr: Failed to remove versions: Graph versions denied'], $example['plainSuppressedErrors']);
        $t->same(
            'failed to fetch and update metadata: failed to read metadata from source object: metadata mapper denied site-export.wxr',
            $example['wrappedReadError'],
        );
    },
    'wordpress onedrive multipart upload metadata example records permission boundaries' => static function (TestRunner $t): void {
        $example = require __DIR__ . '/../examples/wordpress-onedrive-multipart-upload-metadata.php';

        $t->same('onedrive-multipart-upload-metadata', $example['source']);
        $t->same('update-metadata-for-permissions', $example['successfulSequence'][6]);
        $t->same('set-upload-metadata-after-permission-update', $example['successfulSequence'][16]);
        $t->same(['update'], $example['successfulPermissionActions']);
        $t->same(true, $example['successfulFinalMetadataSet']);
        $t->same('exports/review.wxr: no writeable metadata found', $example['permissionsOnlyError']);
        $t->same(false, $example['permissionsOnlyFinalMetadataSet']);
        $t->same(true, $example['permissionsOnlyCancelled']);
        $t->same('failed to process permissions: failed to set permissions: Graph invite throttled', $example['ignoredPermissionError']);
        $t->same(true, $example['ignoredPermissionErrorReturnedSuccess']);
        $t->same(true, $example['ignoredPermissionErrorFinalMetadataSet']);
    },
    'wordpress onedrive update upload selection example records preflight boundaries' => static function (TestRunner $t): void {
        $example = require __DIR__ . '/../examples/wordpress-onedrive-update-upload-selection.php';

        $t->same('onedrive-update-upload-selection', $example['source']);
        $t->same('singlepart', $example['zeroByteUpload']);
        $t->same('set-upload-metadata-from-fetch', $example['zeroByteMetadataSequence'][10]);
        $t->same('multipart', $example['largeUpload']);
        $t->same(true, $example['largeFinalMetadataSet']);
        $t->same(['update'], $example['largePermissionActions']);
        $t->same("can't upload content to a OneNote file", $example['oneNoteError']);
        $t->same('unknown-sized upload not supported', $example['unknownSizeError']);
    },
    'wordpress onedrive put create object example records parent and conflict boundaries' => static function (TestRunner $t): void {
        $example = require __DIR__ . '/../examples/wordpress-onedrive-put-create-object.php';

        $t->same('onedrive-put-create-object', $example['source']);
        $t->same('business-drive#exports-dir', $example['zeroByteParentId']);
        $t->same(false, $example['zeroByteTemporaryObjectHasMetadata']);
        $t->same('singlepart', $example['zeroByteUpload']);
        $t->same('create-temporary-object', $example['zeroByteSequence'][3]);
        $t->same('multipart', $example['largeUpload']);
        $t->same(['update'], $example['largePermissionActions']);
        $t->same("couldn't find parent ID: directory not found", $example['missingParentError']);
        $t->same('nameAlreadyExists (OneNote files cannot be overwritten by rclone)', $example['oneNoteNameConflictError']);
    },
];
