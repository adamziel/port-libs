<?php

declare(strict_types=1);

use PortLibs\Rclone\MemoryProvider;
use PortLibs\Rclone\SyncPlan;

$local = new MemoryProvider();
$remote = new MemoryProvider();
$plan = new SyncPlan();

$local->put('wp-content/uploads/2026/05/hero-copy.jpg', 'copy bytes');
$local->put('wp-content/uploads/2026/05/hero-archive.jpg', 'archive bytes');
$remote->put('publish/media/hero-renamed.jpg', '<rss>previous publish</rss>');
$remote->put('archive/media/hero-renamed.jpg', '<rss>previous archive</rss>');

$choicesSeen = [];
$choice = static function (array $context) use (&$choicesSeen): string {
    $choicesSeen[] = $context['action'];

    return match ($context['action']) {
        'copy to publish/media/hero-renamed.jpg' => 'n',
        'move into backup dir' => 's',
        default => 'y',
    };
};

$copyStats = null;
$copy = $plan->copytoCommand(
    $remote,
    $local,
    'wp-content/uploads/2026/05/hero-copy.jpg',
    'publish/media/hero-renamed.jpg',
    [
        'interactive' => true,
        'interactiveChoice' => $choice,
    ],
    $copyStats,
);

$moveStats = null;
$move = $plan->movetoCommand(
    $remote,
    $local,
    'wp-content/uploads/2026/05/hero-archive.jpg',
    'archive/media/hero-renamed.jpg',
    [
        'suffix' => '.bak',
        'suffixKeepExtension' => true,
        'interactive' => true,
        'interactiveChoice' => $choice,
    ],
    $moveStats,
);

return [
    'copySkippedActions' => $copy['file']['skippedActions'],
    'moveSkippedActions' => $move['file']['skippedActions'],
    'publishBytes' => $remote->get('publish/media/hero-renamed.jpg'),
    'archiveBytes' => $remote->get('archive/media/hero-renamed.jpg'),
    'archiveBackupCreated' => $remote->pathExists('archive/media/hero-renamed.bak.jpg'),
    'copySourcePreserved' => $local->pathExists('wp-content/uploads/2026/05/hero-copy.jpg'),
    'moveSourcePreserved' => $local->pathExists('wp-content/uploads/2026/05/hero-archive.jpg'),
    'copyStats' => $copyStats,
    'moveStats' => $moveStats,
    'choicesSeen' => $choicesSeen,
];
