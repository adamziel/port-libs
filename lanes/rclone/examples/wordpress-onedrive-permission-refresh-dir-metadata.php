<?php

declare(strict_types=1);

require __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\Rclone\OneDrivePermissionPlanner;

$reviewPermissions = '[{"roles":["read"],"grantedToIdentitiesV2":[{"user":{"id":"reviewer@example.com"}}]}]';

$createFlow = OneDrivePermissionPlanner::directoryMetadataFlow(
    'site-backups/review',
    [
        'mtime' => '2026-05-23T10:00:00Z',
        'permissions' => $reviewPermissions,
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

$permissionsOnly = OneDrivePermissionPlanner::directoryMetadataFlow(
    'site-backups/review',
    [
        'permissions' => $reviewPermissions,
    ],
    [
        'exists' => true,
        'directoryId' => 'business-drive#review-dir',
        'driveType' => OneDrivePermissionPlanner::DRIVE_TYPE_BUSINESS,
    ],
);

$updateFlow = OneDrivePermissionPlanner::directoryMetadataFlow(
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

return [
    'source' => 'onedrive-permission-refresh-dir-metadata',
    'createSequence' => $createFlow['sequence'],
    'createdPermissionIds' => array_column($createFlow['permissions'], 'id'),
    'permissionsOnlySequence' => $permissionsOnly['sequence'],
    'permissionsOnlyError' => $permissionsOnly['error'],
    'updateSequence' => $updateFlow['sequence'],
    'updateActions' => array_column($updateFlow['permissionWrite']['operations'], 'action'),
    'updatedQueuedCleared' => $updateFlow['queuedPermissionsCleared'],
];
