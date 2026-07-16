<?php

declare(strict_types=1);

require __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\Rclone\OneDrivePermissionPlanner;

$reviewPermissions = '[{"id":"reviewer","roles":["write"],"grantedToV2":{"user":{"id":"reviewer@example.com"}}}]';

$wxrUpdate = OneDrivePermissionPlanner::objectMetadataFlow(
    'exports/site.wxr',
    [
        'mtime' => '2026-05-23T10:00:00Z',
        'permissions' => $reviewPermissions,
    ],
    [
        'normalizedId' => 'business-drive#site-wxr',
        'driveType' => OneDrivePermissionPlanner::DRIVE_TYPE_BUSINESS,
        'metadataPermissions' => 'read,write',
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

$permissionsOnlyObject = OneDrivePermissionPlanner::objectMetadataFlow(
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

$deleteVersionsFailure = OneDrivePermissionPlanner::objectMetadataFlow(
    'exports/site.wxr',
    [
        'btime' => '2026-05-23T09:00:00Z',
        'mtime' => '2026-05-23T10:00:00Z',
    ],
    [
        'noVersions' => true,
        'deleteVersionsError' => 'Graph versions denied',
    ],
);

return [
    'source' => 'onedrive-object-metadata-update',
    'updateSequence' => $wxrUpdate['sequence'],
    'updatedPermissionIds' => array_column($wxrUpdate['permissions'], 'id'),
    'versionsDeleted' => $wxrUpdate['versionsDeleted'],
    'permissionsOnlySequence' => $permissionsOnlyObject['sequence'],
    'permissionsOnlyError' => $permissionsOnlyObject['error'],
    'deleteVersionsSequence' => $deleteVersionsFailure['sequence'],
    'deleteVersionsError' => $deleteVersionsFailure['error'],
    'metadataWasCachedBeforeVersionCleanup' => $deleteVersionsFailure['objectMetadataSet'],
];
