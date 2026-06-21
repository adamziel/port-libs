<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\MarkdownWriter;

$markdown = implode("\n", [
    '% Migration *Audit*',
    '% Data Liberation [Team](/wp-admin/users.php); WordPress *Review*',
    '% `May 23`, 2026',
    '',
    'Body [source](/wp-admin/post.php?post=42&action=edit) and `wp_update_post`.',
    '',
    'Notify import reviewers with the plain metadata header intact.',
]);

$document = (new MarkdownReader())->read($markdown);

echo (new MarkdownWriter([
    'variant' => 'plain',
    'template' => true,
]))->write($document) . "\n";
