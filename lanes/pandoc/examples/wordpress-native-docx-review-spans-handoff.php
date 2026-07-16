<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\NativeReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$reader = new NativeReader();
$comments = $reader->read((string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-native-docx-comments.native'));
$changes = $reader->read((string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-native-docx-track-changes.native'));
$document = new AstNode('document', [], array_merge($comments->children, $changes->children));

echo (new WordPressBlockWriter())->write($document) . "\n";
