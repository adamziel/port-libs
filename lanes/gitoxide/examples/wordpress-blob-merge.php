<?php

declare(strict_types=1);

use PortLibs\Gitoxide\BlobMerge;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$fixture = require dirname(__DIR__) . '/fixtures/wordpress-blob-merge.php';

$metadata = BlobMerge::mergeText($fixture['metadata']['base'], $fixture['metadata']['ours'], $fixture['metadata']['theirs']);
$deploymentChoice = BlobMerge::mergeText(
    $fixture['themeDecision']['base'],
    $fixture['themeDecision']['ours'],
    $fixture['themeDecision']['theirs'],
    BlobMerge::STYLE_OURS,
);
$unionNotes = BlobMerge::mergeText(
    $fixture['blockNotes']['base'],
    $fixture['blockNotes']['ours'],
    $fixture['blockNotes']['theirs'],
    BlobMerge::STYLE_UNION,
);
$zealousTheme = BlobMerge::mergeText(
    $fixture['themeSharedDecision']['base'],
    $fixture['themeSharedDecision']['ours'],
    $fixture['themeSharedDecision']['theirs'],
    BlobMerge::STYLE_ZEALOUS_DIFF3,
    'base/theme.json',
    'ours/theme.json',
    'theirs/theme.json',
);
$theme = BlobMerge::mergeText(
    $fixture['theme']['base'],
    $fixture['theme']['ours'],
    $fixture['theme']['theirs'],
    BlobMerge::STYLE_DIFF3,
    'base/theme.json',
    'ours/theme.json',
    'theirs/theme.json',
);
$spacingAmbiguity = BlobMerge::mergeText(
    $fixture['spacingAmbiguity']['base'],
    $fixture['spacingAmbiguity']['ours'],
    $fixture['spacingAmbiguity']['theirs'],
);
$mixedLineEndings = BlobMerge::mergeText(
    $fixture['mixedLineEndings']['base'],
    $fixture['mixedLineEndings']['ours'],
    $fixture['mixedLineEndings']['theirs'],
    BlobMerge::STYLE_MERGE,
    'base/post.html',
    'ours/post.html',
    'theirs/post.html',
);
$sharedBlockRefactor = BlobMerge::mergeText(
    $fixture['sharedBlockRefactor']['base'],
    $fixture['sharedBlockRefactor']['ours'],
    $fixture['sharedBlockRefactor']['theirs'],
    BlobMerge::STYLE_MERGE,
    'base/post.html',
    'ours/post.html',
    'theirs/post.html',
);
$anonymousPreview = BlobMerge::mergeText(
    $fixture['anonymousPreview']['base'],
    $fixture['anonymousPreview']['ours'],
    $fixture['anonymousPreview']['theirs'],
    BlobMerge::STYLE_DIFF3,
    null,
    null,
    null,
);
$configuredZdiff3 = BlobMerge::mergeText(
    $fixture['themeSharedDecision']['base'],
    $fixture['themeSharedDecision']['ours'],
    $fixture['themeSharedDecision']['theirs'],
    $fixture['gitConfigConflictStyle'],
    'base/theme.json',
    'ours/theme.json',
    'theirs/theme.json',
);

echo 'metadata=' . $metadata->resolution . "\n";
echo 'metadata-content=' . str_replace("\n", '|', trim($metadata->content)) . "\n";
echo 'deployment-choice=' . $deploymentChoice->resolution . "\n";
echo 'deployment-layout=' . trim($deploymentChoice->content) . "\n";
echo 'union-notes=' . $unionNotes->resolution . "\n";
echo 'union-notes-content=' . str_replace("\n", '|', $unionNotes->content) . "\n";
echo 'zealous-theme=' . $zealousTheme->resolution . "\n";
echo 'zealous-theme-content=' . str_replace("\n", '|', trim($zealousTheme->content)) . "\n";
echo 'theme=' . $theme->resolution . "\n";
echo 'theme-conflicts=' . $theme->conflictCount . "\n";
echo 'spacing-ambiguity=' . $spacingAmbiguity->resolution . "\n";
echo 'spacing-ambiguity-content=' . str_replace("\n", '|', $spacingAmbiguity->content) . "\n";
echo 'mixed-line-endings=' . $mixedLineEndings->resolution . "\n";
echo 'mixed-line-endings-content=' . str_replace(["\r", "\n"], ['\\r', '|'], $mixedLineEndings->content) . "\n";
echo 'shared-block-refactor=' . $sharedBlockRefactor->resolution . "\n";
echo 'shared-block-refactor-content=' . str_replace("\n", '|', trim($sharedBlockRefactor->content)) . "\n";
echo 'anonymous-preview=' . $anonymousPreview->resolution . "\n";
echo 'anonymous-preview-content=' . str_replace("\n", '|', trim($anonymousPreview->content)) . "\n";
echo 'configured-zdiff3=' . $configuredZdiff3->resolution . "\n";
echo 'configured-zdiff3-content=' . str_replace("\n", '|', trim($configuredZdiff3->content)) . "\n";
