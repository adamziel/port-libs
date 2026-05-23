<?php

declare(strict_types=1);

use PortLibs\Gitoxide\BlobMerge;
use PortLibs\Gitoxide\BuiltinDriver;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$fixture = require dirname(__DIR__) . '/fixtures/wordpress-builtin-merge-driver.php';

$notesDriver = BuiltinDriver::fromMergeAttribute($fixture['blockNotes']['state'], $fixture['blockNotes']['value']);
$notes = BuiltinDriver::merge(
    $notesDriver,
    $fixture['blockNotes']['base'],
    $fixture['blockNotes']['ours'],
    $fixture['blockNotes']['theirs'],
);

$mediaDriver = BuiltinDriver::fromMergeAttribute($fixture['media']['state']);
$media = BuiltinDriver::merge(
    $mediaDriver,
    $fixture['media']['base'],
    $fixture['media']['ours'],
    $fixture['media']['theirs'],
);

$autoMediaDriver = BuiltinDriver::fromMergeAttribute($fixture['mediaAutoDetected']['state']);
$autoMedia = BuiltinDriver::merge(
    $autoMediaDriver,
    $fixture['mediaAutoDetected']['base'],
    $fixture['mediaAutoDetected']['ours'],
    $fixture['mediaAutoDetected']['theirs'],
);

$themeDriver = BuiltinDriver::fromMergeAttribute($fixture['themeJson']['state']);
$themeMarkerSize = BuiltinDriver::markerSizeFromAttribute($fixture['themeJson']['markerSize']);
$theme = BuiltinDriver::merge(
    $themeDriver,
    $fixture['themeJson']['base'],
    $fixture['themeJson']['ours'],
    $fixture['themeJson']['theirs'],
    BlobMerge::STYLE_MERGE,
    'base/theme.json',
    'ours/theme.json',
    'theirs/theme.json',
    $themeMarkerSize,
);

$unknownDriver = BuiltinDriver::fromMergeAttribute(
    $fixture['unknownExternal']['state'],
    $fixture['unknownExternal']['value'],
);

echo 'notes-driver=' . $notesDriver . "\n";
echo 'notes-resolution=' . $notes->resolution . "\n";
echo 'notes-content=' . str_replace("\n", '|', trim($notes->content)) . "\n";
echo 'media-driver=' . $mediaDriver . "\n";
echo 'media-resolution=' . $media->resolution . "\n";
echo 'auto-media-driver=' . $autoMediaDriver . "\n";
echo 'auto-media-resolution=' . $autoMedia->resolution . "\n";
echo 'auto-media-picked=' . str_replace("\0", '\\0', $autoMedia->content) . "\n";
echo 'theme-driver=' . $themeDriver . "\n";
echo 'theme-marker-size=' . $themeMarkerSize . "\n";
echo 'theme-content=' . str_replace("\n", '|', trim($theme->content)) . "\n";
echo 'unknown-driver=' . $unknownDriver . "\n";
