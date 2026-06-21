<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\NativeReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$reader = new NativeReader();
$bookmarks = $reader->read((string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-native-docx-raw-bookmarks.native'));
$rawBlocks = $reader->read((string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-native-docx-raw-blocks.native'));
$document = new AstNode('document', [], array_merge($bookmarks->children, $rawBlocks->children));

echo (new WordPressBlockWriter())->write($document) . "\n";
