<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Difftastic\InlineDiffRenderer;

$before = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-git-rename-render-before.php');
$after = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-git-rename-render-after.php');

echo (new InlineDiffRenderer())->renderGitExternalTextDiff($before, $after, [
    'wp-content/plugins/acme-card/src/render-card.php',
    '/tmp/git-blob-old/render-card.php',
    'oldhash',
    '100644',
    '/tmp/git-blob-new/render-card.php',
    'newhash',
    '100755',
    'wp-content/plugins/acme-card/includes/render-card.php',
    'similarity 88%',
], [
    'language' => 'php',
    'contextLines' => 1,
]);
