<?php

declare(strict_types=1);

require __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\Rclone\OneDrivePermissionPlanner;

$zeroByteCreate = OneDrivePermissionPlanner::putCreateObjectFlow(
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

$largeCreate = OneDrivePermissionPlanner::putCreateObjectFlow(
    'exports/site.wxr',
    [
        'mtime' => '2026-05-23T10:00:00Z',
        'permissions' => '[{"id":"reviewer","roles":["write"],"grantedToV2":{"user":{"id":"reviewer@example.com"}}}]',
    ],
    [
        'size' => 12 * 1024 * 1024,
        'uploadCutoff' => 4 * 1024 * 1024,
        'parentId' => 'business-drive#exports-dir',
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

$missingParent = OneDrivePermissionPlanner::putCreateObjectFlow(
    'missing/site.wxr',
    null,
    [
        'size' => 0,
        'parentLookupError' => 'directory not found',
    ],
);

$oneNoteNameConflict = OneDrivePermissionPlanner::putCreateObjectFlow(
    'exports/site-notes.one',
    null,
    [
        'size' => 256,
        'uploadCutoff' => 1024,
        'uploadError' => 'nameAlreadyExists',
        'nameAlreadyExists' => true,
    ],
);

return [
    'source' => 'onedrive-put-create-object',
    'zeroByteParentId' => $zeroByteCreate['parentId'],
    'zeroByteTemporaryObjectHasMetadata' => $zeroByteCreate['temporaryObjectHasMetadata'],
    'zeroByteUpload' => $zeroByteCreate['selectedUpload'],
    'zeroByteSequence' => $zeroByteCreate['sequence'],
    'largeUpload' => $largeCreate['selectedUpload'],
    'largePermissionActions' => array_column($largeCreate['upload']['metadataUpdate']['permissionWrite']['operations'], 'action'),
    'missingParentError' => $missingParent['error'],
    'oneNoteNameConflictError' => $oneNoteNameConflict['error'],
];
