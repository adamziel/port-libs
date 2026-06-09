<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$document = (new MarkdownReader())->read(
    (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-html-standalone-linebreak.html')
);

$blocks = (new WordPressBlockWriter())->write($document);

if (($argv[1] ?? '') === '--self-test') {
    if (
        !str_contains($blocks, '<p><br/></p>')
        || !str_contains($blocks, '<p><br/>Manual classic-editor break before reviewer note</p>')
    ) {
        throw new RuntimeException('HTML5 standalone linebreak handoff self-test failed');
    }

    echo "html5 dom handoff self-test ok\n";
    return;
}

echo $blocks . "\n";
