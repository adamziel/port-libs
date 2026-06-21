<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/AstNode.php';
require_once __DIR__ . '/../src/MarkdownReader.php';
require_once __DIR__ . '/../src/WordPressBlockWriter.php';

use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$fixture = (string) file_get_contents(__DIR__ . '/../fixtures/upstream-html-standalone-object-embed-inline.html');
$document = (new MarkdownReader())->read($fixture);

echo (new WordPressBlockWriter())->write($document);
