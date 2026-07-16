<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\NativeReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$reader = new NativeReader();
$notes = $reader->read((string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-native-docx-notes.native'));
$linkInNote = $reader->read((string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-native-docx-link-in-notes.native'));

$document = new AstNode('document', [], [
    ...$notes->children,
    ...$linkInNote->children,
]);

echo (new WordPressBlockWriter())->write($document) . "\n";
