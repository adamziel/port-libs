<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\NativeReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$reader = new NativeReader();
$blocks = [];
foreach (['reference-to-text', 'reference-to-list-item'] as $fixture) {
    $document = $reader->read(
        (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-native-odt-' . $fixture . '.native')
    );
    array_push($blocks, ...$document->children);
}

echo (new WordPressBlockWriter())->write(new AstNode('document', [], $blocks)) . "\n";
