<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Pandoc\EpubPackageReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$fixture = dirname(__DIR__) . '/fixtures/epub3-package';
$document = (new EpubPackageReader())->readDirectory($fixture);
$blocks = (new WordPressBlockWriter())->write($document);

if (($argv[1] ?? '') === '--self-test') {
    foreach ([
        '<h1 id="opening-title">Opening Packet</h1>',
        '<a href="EPUB/chapter2.xhtml#details" title="Details">details</a>',
        '<img src="EPUB/images/cover.png" alt="Cover image" title="Cover"/>',
        '<blockquote class="wp-block-quote"><p>Reviewer note with <code>wp_insert_post</code>.</p></blockquote>',
    ] as $needle) {
        if (!str_contains($blocks, $needle)) {
            fwrite(STDERR, "Missing EPUB WordPress smoke output: {$needle}\n");
            exit(1);
        }
    }

    echo "EPUB3 package WordPress smoke passed\n";
    exit(0);
}

echo $blocks . "\n";
