<?php

declare(strict_types=1);

return [
    'fromCommit' => 'wp-before-block-review',
    'toCommit' => 'wp-after-block-review',
    'columns' => ['ID', 'post_content'],
    'fromRows' => [
        [
            'ID' => 701,
            'post_content' => "Draft import block\n<p>Draft import copy.</p>\n<!-- /wp:paragraph -->",
        ],
    ],
    'toRows' => [
        [
            'ID' => 701,
            'post_content' => "Draft import block\n<p>Reviewed import copy.</p>\n<p>Ready for publish.</p>\n<!-- /wp:paragraph -->",
        ],
    ],
];
