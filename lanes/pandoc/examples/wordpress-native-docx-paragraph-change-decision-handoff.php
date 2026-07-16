<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\NativeReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$reader = new NativeReader();
$children = [];

foreach ([
    'Accepted paragraph split' => 'upstream-native-docx-paragraph-insertion-deletion-accept.native',
    'Rejected paragraph split' => 'upstream-native-docx-paragraph-insertion-deletion-reject.native',
] as $heading => $fixture) {
    $children[] = new AstNode('heading', [
        'level' => 2,
        'text' => $heading,
    ], [
        new AstNode('text', ['text' => $heading]),
    ]);

    $document = $reader->read((string) file_get_contents(dirname(__DIR__) . '/fixtures/' . $fixture));
    array_push($children, ...$document->children);
}

echo (new WordPressBlockWriter())->write(new AstNode('document', [], $children)) . "\n";
