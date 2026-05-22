<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Difftastic\JsonDiffRenderer;

$encodeUtf16Le = static function (string $text): string {
    $bytes = "\xff\xfe";
    foreach (str_split($text) as $byte) {
        $bytes .= $byte . "\0";
    }

    return $bytes;
};

$before = $encodeUtf16Le("<?xml version=\"1.0\"?>\n<rss>\n  <wp:postmeta key=\"_old_builder\">legacy</wp:postmeta>\n</rss>\n");
$after = $encodeUtf16Le("<?xml version=\"1.0\"?>\n<rss>\n  <wp:postmeta key=\"_wp_page_template\">default</wp:postmeta>\n  <wp:postmeta key=\"_thumbnail_id\">42</wp:postmeta>\n</rss>\n");

echo (new JsonDiffRenderer())->renderFileBytesDiff(
    $before,
    $after,
    'wp-content/uploads/wordpress-export.xml',
    'XML',
    ['language' => 'xml'],
);
