<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/AstNode.php';
require_once dirname(__DIR__) . '/src/MarkdownReader.php';
require_once dirname(__DIR__) . '/src/WordPressBlockWriter.php';

use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$fixture = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-html-standalone-map-inline.html');
$document = (new MarkdownReader())->read($fixture);

echo (new WordPressBlockWriter())->write($document);
