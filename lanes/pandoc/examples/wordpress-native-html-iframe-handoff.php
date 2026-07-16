<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$resources = [
    'https://example.test/imports/embedded-review.html' => [
        'mime' => 'text/html; charset=utf-8',
        'body' => '<!doctype html><html><body><h2>Embedded review</h2><p>Nested <strong>review</strong> content from the source frame.</p></body></html>',
    ],
    'https://example.test/imports/media/release-frame.jpg' => [
        'mime' => 'image/jpeg',
        'body' => '',
    ],
    'https://example.test/imports/legacy-packet.bin' => [
        'mime' => 'application/octet-stream',
        'body' => 'legacy packet',
    ],
];

$document = (new MarkdownReader(['htmlIframeResources' => $resources]))->read(
    (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-html-iframe-local-resource.html')
);

echo (new WordPressBlockWriter())->write($document) . "\n";
