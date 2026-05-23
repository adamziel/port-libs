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
        $t->same(['owner', 'reviewer-created'], $example['createdPermissionIds']);
        $t->same(['find-dir:exists', 'update-dir'], $example['permissionsOnlySequence']);
        $t->same('site-backups/review: no writeable metadata found', $example['permissionsOnlyError']);
        $t->same(['update'], $example['updateActions']);
        $t->same(true, $example['updatedQueuedCleared']);
    },
];
