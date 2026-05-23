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
];
