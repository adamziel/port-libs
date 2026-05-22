<?php

declare(strict_types=1);

$commitLog = require __DIR__ . '/wp-commit-log-review.php';

return [
    'headHash' => $commitLog['headHash'],
    'commits' => $commitLog['commits'],
    'checks' => [
        ['reference' => 'main', 'ancestor' => 'import-base', 'expected' => true],
        ['reference' => 'main', 'ancestor' => 'media-import', 'expected' => true],
        ['reference' => 'media-import', 'ancestor' => 'main', 'expected' => false],
        ['reference' => 'refs/tags/import-reviewed', 'ancestor' => 'media-import', 'expected' => true],
        ['reference' => 'main^2', 'ancestor' => 'import-base', 'expected' => true],
        ['reference' => 'main~2', 'ancestor' => 'wp-init', 'expected' => true],
        ['reference' => 'media-import', 'ancestor' => 'wp-review-main', 'expected' => false],
    ],
    'expectedResolvedSpecs' => [
        'main^1' => 'wp-review-main',
        'main^2' => 'wp-media-branch',
        'main~2' => 'wp-import-base',
        'HEAD^2' => 'wp-media-branch',
    ],
];
