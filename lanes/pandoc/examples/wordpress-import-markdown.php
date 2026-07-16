<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$markdown = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-import-markdown.md');
$document = (new MarkdownReader())->read($markdown);

echo (new WordPressBlockWriter())->write($document) . "\n";
