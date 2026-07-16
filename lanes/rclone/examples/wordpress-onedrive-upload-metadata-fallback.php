<?php

declare(strict_types=1);

require __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\Rclone\OneDrivePermissionPlanner;

$plainFetch = OneDrivePermissionPlanner::fetchAndUpdateMetadataFlow(
    'exports/site.wxr',
    null,
    [
        'noVersions' => true,
        'deleteVersionsError' => 'Graph versions denied',
    ],
);

$plainUpload = OneDrivePermissionPlanner::uploadSinglepartMetadataFlow(
    'exports/site.wxr',
    null,
    [
        'noVersions' => true,
        'deleteVersionsError' => 'Graph versions denied',
    ],
);

$metadataReadFailure = OneDrivePermissionPlanner::uploadSinglepartMetadataFlow(
    'exports/site-export.wxr',
    null,
    [
        'sourceMetadataError' => 'metadata mapper denied site-export.wxr',
    ],
);

return [
    'source' => 'onedrive-upload-metadata-fallback',
    'plainFetchSequence' => $plainFetch['sequence'],
    'plainUploadSequence' => $plainUpload['sequence'],
    'plainUploadSetsFetchedMetadata' => in_array('set-upload-metadata-from-fetch', $plainUpload['sequence'], true),
    'plainSuppressedErrors' => $plainFetch['suppressedErrors'],
    'wrappedReadError' => $metadataReadFailure['error'],
];
