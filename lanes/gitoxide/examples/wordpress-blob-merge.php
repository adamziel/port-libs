<?php

declare(strict_types=1);

use PortLibs\Gitoxide\BlobMerge;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$fixture = require dirname(__DIR__) . '/fixtures/wordpress-blob-merge.php';

$metadata = BlobMerge::mergeText($fixture['metadata']['base'], $fixture['metadata']['ours'], $fixture['metadata']['theirs']);
$theme = BlobMerge::mergeText(
    $fixture['theme']['base'],
    $fixture['theme']['ours'],
    $fixture['theme']['theirs'],
    BlobMerge::STYLE_DIFF3,
    'base/theme.json',
    'ours/theme.json',
    'theirs/theme.json',
);

echo 'metadata=' . $metadata->resolution . "\n";
echo 'metadata-content=' . str_replace("\n", '|', trim($metadata->content)) . "\n";
echo 'theme=' . $theme->resolution . "\n";
echo 'theme-conflicts=' . $theme->conflictCount . "\n";
