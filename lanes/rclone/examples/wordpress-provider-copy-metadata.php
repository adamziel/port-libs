<?php

declare(strict_types=1);

require __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\Rclone\HashSet;
use PortLibs\Rclone\HashType;
use PortLibs\Rclone\MemoryProvider;
use PortLibs\Rclone\SyncPlan;

$remote = new MemoryProvider(
    supportedHashes: new HashSet(HashType::SHA1, HashType::MD5, HashType::QUICKXOR),
    serverSideMove: true,
    serverSideCopy: true,
);
$plan = new SyncPlan();

$remote->put('staging/site.wxr', '<rss>fresh export</rss>', [
    'modTime' => '2026-05-22T02:00:00Z',
    'mimeType' => 'application/rss+xml',
    'metadata' => [
        'description' => 'portable WordPress export',
        'permissions' => '[{"roles":["read"]}]',
    ],
    'id' => 'staging-export',
]);
$remote->put('exports/site.wxr', '<rss>previous export</rss>');

$onedrive = $plan->serverSideCopyReplace($remote, 'staging/site.wxr', 'exports/site.wxr', [
    'provider' => 'onedrive',
    'temporarySuffix' => '.wpcopy',
    'apiResult' => [
        'id' => 'onedrive-copied-export',
        'mimeType' => 'application/rss+xml',
        'hashes' => [
            'sha1Hash' => strtoupper(hash('sha1', '<rss>fresh export</rss>')),
            'quickXorHash' => 'fZ63/Cfr5wNPmPRzVwMIyoAHOLw=',
        ],
    ],
]);
$onedriveInfo = $remote->info('exports/site.wxr');
$onedriveSha1 = $remote->hashes('exports/site.wxr', new HashSet(HashType::SHA1))[HashType::SHA1];
$onedriveQuickXor = $remote->hashes('exports/site.wxr', new HashSet(HashType::QUICKXOR))[HashType::QUICKXOR];

$remote->put('exports/site.wxr', '<rss>previous export</rss>');
$onedriveFailOk = $plan->serverSideCopyReplace($remote, 'staging/site.wxr', 'exports/site.wxr', [
    'provider' => 'onedrive',
    'temporarySuffix' => '.wpcopy',
    'apiResult' => [
        'id' => 'onedrive-copied-failok',
        'permissionsWriteError' => 'failed to set permissions',
        'permissionsFailOk' => true,
    ],
]);

$remote->put('exports/site.wxr', '<rss>previous export</rss>');
$onedriveShared = $plan->serverSideCopyReplace($remote, 'staging/site.wxr', 'exports/site.wxr', [
    'provider' => 'onedrive',
    'temporarySuffix' => '.wpcopy',
    'apiResult' => [
        'remoteItem' => [
            'id' => 'shared-export-copy',
            'parentReference' => ['driveId' => 'shared-drive'],
            'file' => [
                'mimeType' => 'application/rss+xml',
                'hashes' => [
                    'sha1Hash' => strtoupper(hash('sha1', '<rss>fresh export</rss>')),
                ],
            ],
            'createdBy' => [
                'user' => [
                    'id' => 'site-owner',
                    'displayName' => 'Site Owner',
                ],
            ],
            'lastModifiedBy' => [
                'user' => [
                    'id' => 'migration-bot',
                    'displayName' => 'Migration Bot',
                ],
            ],
        ],
        'shared' => [
            'owner' => ['user' => ['id' => 'site-owner-account']],
            'sharedBy' => ['user' => ['id' => 'reviewer-account']],
            'scope' => 'users',
            'sharedDateTime' => '2026-05-23T08:15:30Z',
        ],
    ],
]);
$onedriveSharedInfo = $remote->info('exports/site.wxr');

$remote->put('staging/site-notes.one', 'OneNote migration notebook bytes', [
    'modTime' => '2026-05-22T03:00:00Z',
    'metadata' => ['wp-artifact' => 'migration-notes'],
    'id' => 'staging-notes-package',
]);
$onedrivePackage = $plan->serverSideCopyReplace($remote, 'staging/site-notes.one', 'exports/site-notes.one', [
    'provider' => 'onedrive',
    'temporarySuffix' => '.wpcopy',
    'apiResult' => [
        'remoteItem' => [
            'id' => 'shared-notes-copy',
            'parentReference' => ['driveId' => 'shared-drive'],
            'package' => ['type' => 'oneNote'],
        ],
    ],
]);
$onedrivePackageInfo = $remote->info('exports/site-notes.one');
$onedrivePackageOpenError = null;
try {
    $remote->openReader('exports/site-notes.one');
} catch (RuntimeException $throwable) {
    $onedrivePackageOpenError = $throwable->getMessage();
}
$onedrivePackageUpdateError = null;
try {
    $remote->updateObject('exports/site-notes.one', 'replacement notebook bytes');
} catch (RuntimeException $throwable) {
    $onedrivePackageUpdateError = $throwable->getMessage();
}

$remote->put('exports/site.wxr', '<rss>previous export</rss>');
$yandex = $plan->serverSideCopyReplace($remote, 'staging/site.wxr', 'exports/site.wxr', [
    'provider' => 'yandex',
    'temporarySuffix' => '.wpcopy',
    'apiResult' => [
        'customProperties' => ['rclone_modified' => '2026-05-22T02:00:00Z'],
        'modified' => '2026-05-22T04:15:00Z',
        'md5' => strtoupper(hash('md5', '<rss>fresh export</rss>')),
        'mimeType' => 'application/rss+xml',
    ],
]);
$yandexInfo = $remote->info('exports/site.wxr');
$yandexMd5 = $remote->hashes('exports/site.wxr', new HashSet(HashType::MD5))[HashType::MD5];

$remote->put('staging/site.paper', '<paper>fresh export</paper>', [
    'modTime' => '2026-05-22T02:00:00Z',
    'metadata' => ['wp-artifact' => 'dropbox-paper-export'],
    'id' => 'staging-paper-export',
]);
$remote->put('exports/site.paper', '<paper>previous export</paper>');
$dropboxPaper = $plan->serverSideCopyReplace($remote, 'staging/site.paper', 'exports/site.paper', [
    'provider' => 'dropbox',
    'temporarySuffix' => '.wpcopy',
    'apiResult' => [
        'id' => 'dropbox-paper-copy',
        'isDownloadable' => false,
        'exportInfo' => [
            'exportAs' => 'markdown',
            'exportOptions' => ['html', 'markdown'],
        ],
        'exportFormats' => ['html', 'md'],
    ],
]);
$dropboxPaperInfo = $remote->info('exports/site.html');
$dropboxMarkdown = $plan->serverSideCopyReplace($remote, 'staging/site.paper', 'exports/markdown.paper', [
    'provider' => 'dropbox',
    'temporarySuffix' => '.wpcopy',
    'apiResult' => [
        'id' => 'dropbox-markdown-paper-copy',
        'isDownloadable' => false,
        'exportInfo' => [
            'exportAs' => 'html',
            'exportOptions' => ['html', 'markdown'],
        ],
        'exportFormats' => ['md', 'html'],
    ],
]);
$dropboxMarkdownInfo = $remote->info('exports/markdown.md');
$dropboxHidden = $plan->serverSideCopyReplace($remote, 'staging/site.paper', 'exports/hidden.paper', [
    'provider' => 'dropbox',
    'temporarySuffix' => '.wpcopy',
    'apiResult' => [
        'id' => 'dropbox-hidden-paper-copy',
        'isDownloadable' => false,
        'skipExports' => true,
        'exportInfo' => [
            'exportAs' => 'markdown',
            'exportOptions' => ['html', 'markdown'],
        ],
    ],
]);
$dropboxListOnly = $plan->serverSideCopyReplace($remote, 'staging/site.paper', 'exports/list-only.paper', [
    'provider' => 'dropbox',
    'temporarySuffix' => '.wpcopy',
    'apiResult' => [
        'id' => 'dropbox-list-only-paper-copy',
        'isDownloadable' => false,
        'showAllExports' => true,
        'exportInfo' => [
            'exportAs' => 'markdown',
            'exportOptions' => ['html', 'markdown'],
        ],
    ],
]);
$dropboxListOnlyOpenError = null;
try {
    $remote->openReader('exports/list-only.paper');
} catch (RuntimeException $throwable) {
    $dropboxListOnlyOpenError = $throwable->getMessage();
}
$remote->put('exports/invalid.paper', '<paper>previous invalid export</paper>');
$dropboxUnknownFormatError = null;
try {
    $plan->serverSideCopyReplace($remote, 'staging/site.paper', 'exports/invalid.paper', [
        'provider' => 'dropbox',
        'temporarySuffix' => '.wpcopy',
        'apiResult' => [
            'isDownloadable' => false,
            'exportInfo' => [
                'exportAs' => 'markdown',
                'exportOptions' => ['html', 'markdown'],
            ],
            'exportFormats' => ['pdf'],
        ],
    ]);
} catch (RuntimeException $throwable) {
    $dropboxUnknownFormatError = $throwable->getMessage();
}
$dropboxUnknownFormatPreserved = $remote->get('exports/invalid.paper');
$dropboxExportsListing = array_map(static fn ($info) => $info->path, $remote->list('exports'));

$yandexSetModTime = $plan->yandexSetRcloneModified($remote, 'staging/site.wxr', '2026-05-23T12:34:56Z');
$remote->setModTimeError('staging/site.wxr', 'custom properties are locked');
$yandexSetModTimeError = null;
try {
    $plan->yandexSetRcloneModified($remote, 'staging/site.wxr', '2026-05-24T00:00:00Z');
} catch (RuntimeException $throwable) {
    $yandexSetModTimeError = $throwable->getMessage();
}
$remote->setModTimeError('staging/site.wxr', null);

$remote->put('exports/site.wxr', '<rss>previous export</rss>');
$yandexInvalidModTimeError = null;
try {
    $plan->serverSideCopyReplace($remote, 'staging/site.wxr', 'exports/site.wxr', [
        'provider' => 'yandex',
        'temporarySuffix' => '.wpcopy',
        'apiResult' => [
            'customProperties' => ['rclone_modified' => 'not-a-time'],
            'modified' => '2026-05-22T04:15:00Z',
        ],
    ]);
} catch (RuntimeException $throwable) {
    $yandexInvalidModTimeError = $throwable->getMessage();
}

$remote->put('exports/site.wxr', '<rss>previous export</rss>');
$dropboxCopyError = null;
try {
    $plan->serverSideCopyReplace($remote, 'staging/site.wxr', 'exports/site.wxr', [
        'provider' => 'dropbox',
        'temporarySuffix' => '.wpcopy',
        'providerError' => [
            'kind' => 'relocation-api',
            'message' => 'too_many_write_operations',
        ],
    ]);
} catch (RuntimeException $throwable) {
    $dropboxCopyError = $throwable->getMessage();
}

$remote->put('exports/site.wxr', '<rss>previous export</rss>');
$onedriveAccessDeniedError = null;
try {
    $plan->serverSideCopyReplace($remote, 'staging/site.wxr', 'exports/site.wxr', [
        'provider' => 'onedrive',
        'temporarySuffix' => '.wpcopy',
        'providerError' => ['kind' => 'async-access-denied'],
    ]);
} catch (RuntimeException $throwable) {
    $onedriveAccessDeniedError = $throwable->getMessage();
}

$remote->put('exports/site.wxr', '<rss>previous export</rss>');
$onedriveCrossDriveError = null;
try {
    $plan->serverSideCopyReplace($remote, 'staging/site.wxr', 'exports/site.wxr', [
        'provider' => 'onedrive',
        'temporarySuffix' => '.wpcopy',
        'apiResult' => [
            'sourceDriveType' => 'personal',
            'destinationDriveType' => 'business',
        ],
    ]);
} catch (RuntimeException $throwable) {
    $onedriveCrossDriveError = $throwable->getMessage();
}

$remote->put('exports/sharepoint-site.wxr', '<rss>previous sharepoint export</rss>');
$onedriveSharePoint = $plan->serverSideCopyReplace($remote, 'staging/site.wxr', 'exports/sharepoint-site.wxr', [
    'provider' => 'onedrive',
    'temporarySuffix' => '.wpcopy',
    'apiResult' => [
        'sourceDriveType' => 'business',
        'sourceDriveId' => 'business-drive-a',
        'destinationDriveType' => 'documentLibrary',
        'destinationDriveId' => 'sharepoint-library-b',
        'id' => 'sharepoint-wxr-copy',
    ],
]);

$remote->put('exports/sharepoint-personal.wxr', '<rss>previous personal export</rss>');
$onedriveSharePointPersonalError = null;
try {
    $plan->serverSideCopyReplace($remote, 'staging/site.wxr', 'exports/sharepoint-personal.wxr', [
        'provider' => 'onedrive',
        'temporarySuffix' => '.wpcopy',
        'apiResult' => [
            'sourceDriveType' => 'documentLibrary',
            'destinationDriveType' => 'personal',
            'sourceDriveId' => 'sharepoint-library-b',
            'destinationDriveId' => 'personal-drive',
        ],
    ]);
} catch (RuntimeException $throwable) {
    $onedriveSharePointPersonalError = $throwable->getMessage();
}

$remote->put('exports/site.wxr', '<rss>previous export</rss>');
$onedriveBadStatusError = null;
try {
    $plan->serverSideCopyReplace($remote, 'staging/site.wxr', 'exports/site.wxr', [
        'provider' => 'onedrive',
        'temporarySuffix' => '.wpcopy',
        'providerError' => [
            'kind' => 'async-status-not-json',
            'body' => 'not-json',
            'message' => 'invalid character',
        ],
    ]);
} catch (RuntimeException $throwable) {
    $onedriveBadStatusError = $throwable->getMessage();
}

$remote->put('exports/site.wxr', '<rss>previous export</rss>');
$sugarsyncMissingIdError = null;
try {
    $plan->serverSideCopyReplace($remote, 'staging/site.wxr', 'exports/site.wxr', [
        'provider' => 'sugarsync',
        'temporarySuffix' => '.wpcopy',
        'apiResult' => [
            'lastModified' => '2026-05-22T05:00:00Z',
        ],
    ]);
} catch (RuntimeException $throwable) {
    $sugarsyncMissingIdError = $throwable->getMessage();
}

return [
    'onedriveCopiedId' => $onedrive['copied']->id,
    'onedriveModTime' => $onedriveInfo->modTime,
    'onedrivePermissionMode' => $onedrive['copied']->metadata['onedrive_permissions_mode'],
    'onedriveSha1' => $onedriveSha1,
    'onedriveQuickXor' => $onedriveQuickXor,
    'onedriveMetadataRefresh' => $onedrive['metadataRefresh'],
    'onedriveFailOkCopiedId' => $onedriveFailOk['copied']->id,
    'onedriveFailOkMetadataRefresh' => $onedriveFailOk['metadataRefresh'],
    'onedriveSharedCopiedId' => $onedriveShared['copied']->id,
    'onedriveSharedMetadataRefresh' => $onedriveShared['metadataRefresh'],
    'onedriveSharedMetadata' => $onedriveSharedInfo->metadata,
    'onedriveSharedMimeType' => $onedriveSharedInfo->mimeType,
    'onedrivePackageCopiedId' => $onedrivePackage['copied']->id,
    'onedrivePackageMetadataRefresh' => $onedrivePackage['metadataRefresh'],
    'onedrivePackageMetadata' => $onedrivePackageInfo->metadata,
    'onedrivePackageOpenError' => $onedrivePackageOpenError,
    'onedrivePackageUpdateError' => $onedrivePackageUpdateError,
    'yandexCopiedModTime' => $yandexInfo->modTime,
    'yandexMd5' => $yandexMd5,
    'yandexMetadataRefresh' => $yandex['metadataRefresh'],
    'dropboxPaperCopiedPath' => $dropboxPaper['copied']->path,
    'dropboxPaperExportType' => $dropboxPaperInfo->metadata['dropbox_export_type'],
    'dropboxPaperSize' => $dropboxPaperInfo->size,
    'dropboxMarkdownCopiedPath' => $dropboxMarkdown['copied']->path,
    'dropboxMarkdownExportFormat' => $dropboxMarkdownInfo->metadata['dropbox_export_format'],
    'dropboxMarkdownExtension' => $dropboxMarkdownInfo->metadata['dropbox_export_extension'],
    'dropboxHiddenExportType' => $dropboxHidden['copied']->metadata['dropbox_export_type'],
    'dropboxHiddenListed' => in_array('exports/hidden.paper', $dropboxExportsListing, true),
    'dropboxListOnlyCopiedPath' => $dropboxListOnly['copied']->path,
    'dropboxListOnlyExportType' => $dropboxListOnly['copied']->metadata['dropbox_export_type'],
    'dropboxListOnlyListed' => in_array('exports/list-only.paper', $dropboxExportsListing, true),
    'dropboxListOnlyOpenError' => $dropboxListOnlyOpenError,
    'dropboxUnknownFormatError' => $dropboxUnknownFormatError,
    'dropboxUnknownFormatPreserved' => $dropboxUnknownFormatPreserved,
    'dropboxUnknownFormatTempExists' => $remote->pathExists('exports/invalid.paper.wpcopy'),
    'dropboxExportsListing' => $dropboxExportsListing,
    'yandexRcloneModified' => $yandexSetModTime->metadata['rclone_modified'],
    'yandexSetModTimeError' => $yandexSetModTimeError,
    'yandexInvalidModTimeError' => $yandexInvalidModTimeError,
    'dropboxCopyError' => $dropboxCopyError,
    'onedriveAccessDeniedError' => $onedriveAccessDeniedError,
    'onedriveCrossDriveError' => $onedriveCrossDriveError,
    'onedriveSharePointCopiedId' => $onedriveSharePoint['copied']->id,
    'onedriveSharePointBytes' => $remote->get('exports/sharepoint-site.wxr'),
    'onedriveSharePointSavedPath' => $onedriveSharePoint['savedPath'],
    'onedriveSharePointTempExists' => $remote->pathExists('exports/sharepoint-site.wxr.wpcopy'),
    'onedriveSharePointPersonalError' => $onedriveSharePointPersonalError,
    'onedriveSharePointPersonalPreserved' => $remote->get('exports/sharepoint-personal.wxr'),
    'onedriveBadStatusError' => $onedriveBadStatusError,
    'sugarsyncMissingIdError' => $sugarsyncMissingIdError,
    'restoredAfterAccessDenied' => $remote->get('exports/site.wxr'),
];
