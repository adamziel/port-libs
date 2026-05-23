<?php

declare(strict_types=1);

require __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\Rclone\ListDirectory;
use PortLibs\Rclone\ObjectInfo;
use PortLibs\Rclone\OneDriveListR;

/**
 * @return list<ObjectInfo>
 */
function rclone_wordpress_onedrive_permissions_collect(callable $listR): array
{
    $entries = [];
    ListDirectory::listRecursiveDirect(
        $listR,
        true,
        'site-backups',
        ListDirectory::LIST_ALL,
        static function (array $batch) use (&$entries): null {
            foreach ($batch as $entry) {
                if ($entry instanceof ObjectInfo) {
                    $entries[] = $entry;
                }
            }

            return null;
        },
    );

    return $entries;
}

$permissions = [
    [
        'id' => 'perm-reviewer',
        'grantedTo' => [
            'user' => [
                'id' => 'reviewer@example.com',
                'displayName' => 'Migration Reviewer',
            ],
        ],
        'roles' => ['read'],
    ],
    [
        'id' => 'perm-share-link',
        'link' => [
            'type' => 'view',
            'scope' => 'organization',
            'webUrl' => 'https://share.example/site-export.wxr',
        ],
        'roles' => ['read'],
        'shareId' => 'share-wordpress-export',
    ],
];

$listR = OneDriveListR::fromDelta(
    [
        [
            'id' => 'review-export',
            'name' => 'review-export.wxr',
            'size' => 18,
            'parentReference' => ['driveId' => 'site-drive', 'id' => 'backups'],
            'file' => ['mimeType' => 'application/rss+xml'],
            'metadataPermissionsMode' => 'read',
            'metadataPermissions' => $permissions,
        ],
        [
            'id' => 'private-draft',
            'name' => 'private-draft.wxr',
            'size' => 11,
            'parentReference' => ['driveId' => 'site-drive', 'id' => 'backups'],
            'file' => ['mimeType' => 'application/rss+xml'],
            'metadataPermissionsMode' => 'off',
            'metadataPermissions' => [
                ['id' => 'hidden-permission', 'roles' => ['read']],
            ],
        ],
        [
            'id' => 'broken-permissions',
            'name' => 'broken-permissions.wxr',
            'size' => 12,
            'parentReference' => ['driveId' => 'site-drive', 'id' => 'backups'],
            'file' => ['mimeType' => 'application/rss+xml'],
            'metadataPermissionsMode' => 'read',
            'metadataPermissionsMarshalError' => 'json: unsupported permission value',
        ],
    ],
    'site-drive#root',
    ['site-backups' => 'site-drive#backups'],
);

$entries = rclone_wordpress_onedrive_permissions_collect($listR);
$metadata = $entries[0]->readMetadata();
$privateMetadata = $entries[1]->readMetadata();
$marshalError = null;
try {
    $entries[2]->readMetadata();
} catch (Throwable $throwable) {
    $marshalError = $throwable->getMessage();
}

$decodedPermissions = json_decode($metadata['permissions'] ?? '[]', true);

return [
    'source' => 'onedrive-permissions-metadata-preflight',
    'manifest' => array_map(static fn (ObjectInfo $entry): string => $entry->path, $entries),
    'permissionsJson' => $metadata['permissions'] ?? null,
    'permissionIds' => array_map(static fn (array $permission): string => (string) ($permission['id'] ?? ''), $decodedPermissions),
    'reviewerDisplayName' => $decodedPermissions[0]['grantedTo']['user']['displayName'] ?? null,
    'noPermissionsWhenOff' => !array_key_exists('permissions', $privateMetadata),
    'marshalError' => $marshalError,
];
