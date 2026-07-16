<?php

declare(strict_types=1);

require __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\Rclone\OneDrivePermissionPlanner;

$zeroByteMarker = OneDrivePermissionPlanner::objectUpdateUploadFlow(
    'exports/empty-review.wxr',
    [
        'mtime' => '2026-05-23T10:00:00Z',
    ],
    [
        'size' => 0,
        'uploadCutoff' => -1,
        'metadataPermissions' => 'write',
    ],
);

$largeWxr = OneDrivePermissionPlanner::objectUpdateUploadFlow(
    'exports/site.wxr',
    [
        'mtime' => '2026-05-23T10:00:00Z',
        'permissions' => '[{"id":"reviewer","roles":["write"],"grantedToV2":{"user":{"id":"reviewer@example.com"}}}]',
    ],
    [
        'size' => 16 * 1024 * 1024,
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

$oneNote = OneDrivePermissionPlanner::objectUpdateUploadFlow(
    'exports/site-notes.one',
    null,
    [
        'size' => 1024,
        'hasMetadata' => true,
        'isOneNoteFile' => true,
    ],
);

$unknownStream = OneDrivePermissionPlanner::objectUpdateUploadFlow(
    'exports/streamed-import.wxr',
    null,
    [
        'size' => -1,
    ],
);

return [
    'source' => 'onedrive-update-upload-selection',
    'zeroByteUpload' => $zeroByteMarker['selectedUpload'],
    'zeroByteMetadataSequence' => $zeroByteMarker['sequence'],
    'largeUpload' => $largeWxr['selectedUpload'],
    'largeFinalMetadataSet' => $largeWxr['upload']['finalMetadataSet'],
    'largePermissionActions' => array_column($largeWxr['upload']['metadataUpdate']['permissionWrite']['operations'], 'action'),
    'oneNoteError' => $oneNote['error'],
    'unknownSizeError' => $unknownStream['error'],
];
