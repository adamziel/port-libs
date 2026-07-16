<?php

declare(strict_types=1);

require __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\Rclone\OneDrivePermissionPlanner;

$currentPermissions = [
    [
        'id' => 'site-owner',
        'roles' => ['owner'],
        'grantedToV2' => ['user' => ['id' => 'owner@example.com']],
    ],
    [
        'id' => 'reviewer-direct',
        'roles' => ['read'],
        'grantedToV2' => ['user' => ['id' => 'reviewer@example.com']],
    ],
    [
        'id' => 'link-review',
        'roles' => ['read'],
        'link' => [
            'scope' => 'organization',
            'webUrl' => 'https://share.example/wordpress-review',
        ],
        'grantedToIdentitiesV2' => [
            ['user' => ['id' => 'reviewer@example.com']],
        ],
    ],
    [
        'id' => 'stale-contractor',
        'roles' => ['read'],
        'grantedToV2' => ['user' => ['id' => 'contractor@example.com']],
    ],
];

$queuedPermissions = [
    [
        'id' => 'reviewer-direct',
        'roles' => ['write'],
        'grantedToV2' => ['user' => ['id' => 'reviewer@example.com']],
    ],
    [
        'id' => 'link-review',
        'roles' => ['write'],
        'link' => [
            'scope' => 'organization',
            'webUrl' => 'https://share.example/wordpress-review',
        ],
        'grantedToIdentitiesV2' => [
            ['user' => ['id' => 'reviewer@example.com']],
        ],
    ],
    [
        'roles' => ['read'],
        'grantedToIdentitiesV2' => [
            ['user' => ['id' => 'migration-auditor@example.com']],
        ],
    ],
];

$plan = OneDrivePermissionPlanner::plan($currentPermissions, $queuedPermissions, [
    'driveType' => OneDrivePermissionPlanner::DRIVE_TYPE_BUSINESS,
    'failOk' => true,
    'operationErrors' => [
        'add:link-review' => 'Graph invite throttled',
    ],
]);

$addedEmails = [];
foreach ($plan['operations'] as $operation) {
    if (($operation['action'] ?? null) !== 'add') {
        continue;
    }
    foreach ($operation['recipients'] ?? [] as $recipient) {
        if (isset($recipient['email'])) {
            $addedEmails[] = $recipient['email'];
        }
    }
}

$reviewerUpdate = array_values(array_filter(
    $plan['operations'],
    static fn (array $operation): bool => ($operation['action'] ?? null) === 'update'
        && ($operation['id'] ?? null) === 'reviewer-direct',
))[0] ?? [];

return [
    'source' => 'onedrive-permission-write-plan',
    'actions' => array_column($plan['operations'], 'action'),
    'removedPermissionIds' => array_values(array_map(
        static fn (array $operation): string => (string) $operation['id'],
        array_filter($plan['operations'], static fn (array $operation): bool => ($operation['action'] ?? null) === 'remove'),
    )),
    'addedEmails' => $addedEmails,
    'reviewerUpdateRoles' => $reviewerUpdate['roles'] ?? [],
    'ownerPreserved' => in_array('owner-role-remove-suppressed', array_column($plan['skipped'], 'reason'), true),
    'suppressedErrors' => $plan['suppressedErrors'],
];
