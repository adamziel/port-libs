<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$docbook = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-docbook-table.xml');
$document = (new MarkdownReader())->read($docbook);

echo (new WordPressBlockWriter())->write($document) . "\n";
