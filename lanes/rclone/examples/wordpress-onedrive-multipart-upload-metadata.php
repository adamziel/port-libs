<?php

declare(strict_types=1);

require __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\Rclone\OneDrivePermissionPlanner;

$successful = OneDrivePermissionPlanner::uploadMultipartMetadataFlow(
    'exports/site.wxr',
    [
        'mtime' => '2026-05-23T10:00:00Z',
        'permissions' => '[{"id":"reviewer","roles":["write"],"grantedToV2":{"user":{"id":"reviewer@example.com"}}}]',
    ],
    [
        'normalizedId' => 'business-drive#site-wxr',
        'driveType' => OneDrivePermissionPlanner::DRIVE_TYPE_BUSINESS,
        'metadataPermissions' => 'read,write',
        'refreshBeforePermissions' => [
            ['id' => 'reviewer', 'roles' => ['read'], 'grantedToV2' => ['user' => ['id' => 'reviewer@example.com']]],
        ],
        'refreshedPermissions' => [
            ['id' => 'reviewer', 'roles' => ['write']],
        ],
    ],
);

$permissionsOnly = OneDrivePermissionPlanner::uploadMultipartMetadataFlow(
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

$ignoredPermissionError = OneDrivePermissionPlanner::uploadMultipartMetadataFlow(
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

return [
    'source' => 'onedrive-multipart-upload-metadata',
    'successfulSequence' => $successful['sequence'],
    'successfulPermissionActions' => array_column($successful['metadataUpdate']['permissionWrite']['operations'], 'action'),
    'successfulFinalMetadataSet' => $successful['finalMetadataSet'],
    'permissionsOnlyError' => $permissionsOnly['error'],
    'permissionsOnlyFinalMetadataSet' => $permissionsOnly['finalMetadataSet'],
    'permissionsOnlyCancelled' => $permissionsOnly['sessionCancelled'],
    'ignoredPermissionError' => $ignoredPermissionError['ignoredUpdateError'],
    'ignoredPermissionErrorReturnedSuccess' => $ignoredPermissionError['error'] === null,
    'ignoredPermissionErrorFinalMetadataSet' => $ignoredPermissionError['finalMetadataSet'],
];
