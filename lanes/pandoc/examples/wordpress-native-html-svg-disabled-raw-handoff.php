<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$document = (new MarkdownReader(['htmlRawHtml' => false]))->read(
    (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-html-svg-disabled-raw-html.html')
);

echo (new WordPressBlockWriter())->write($document) . "\n";
